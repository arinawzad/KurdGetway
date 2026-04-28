<?php

namespace App\Services\Wallet;

use App\Services\Wallet\Contracts\Credentials;
use App\Services\Wallet\Contracts\WalletProvider;
use App\Services\Wallet\DTOs\SignInResult;
use App\Services\Wallet\DTOs\WalletBalance;
use App\Services\Wallet\DTOs\WalletPayment;
use App\Services\Wallet\DTOs\WalletTransaction;
use App\Services\Wallet\DTOs\WalletTransactionPage;
use App\Services\Wallet\DTOs\WalletUser;
use App\Services\Wallet\Exceptions\WalletException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Throwable;

/**
 * Adapter for FastPay's personal API gateway.
 *
 * Endpoints implemented:
 *   - POST /api/v2/auth/signin/check-same-device
 *   - GET  /api/v1/private/user/basic-information
 *   - GET  /api/v1/private/user/transaction/history?page={n}
 *   - POST /api/v1/private/{send_money_path}     (configurable, see notes)
 *
 * Notes on auth & headers:
 *   - The Authorization Bearer token is per-user and comes in via Credentials.
 *     For sign-in there is no token yet, so we send `Bearer ` (empty) to match
 *     what the upstream client emits.
 *   - User-Agent / Platform / User-Type / Accept-Language are static client
 *     identification headers; values come from config.
 *   - App-request-id is generated fresh per request — FastPay seems to expect
 *     a UUID-like prefix plus a random+timestamp suffix.
 *   - device-id / X-Signature-Id / Cookie are device-fingerprint headers.
 *     Authenticated endpoints don't strictly need them, but the sign-in
 *     endpoint validates the fingerprint and rejects requests without it as
 *     INVALID_ARGUMENT. Configure via FASTPAY_DEVICE_ID / FASTPAY_SIGNATURE_ID
 *     / FASTPAY_COOKIE in .env when you need sign-in to work.
 */
class FastPayProvider implements WalletProvider
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly array $config,
    ) {}

    public function name(): string
    {
        return 'fastpay';
    }

    /**
     * POST /api/v{login_api_version}/auth/signin/check-same-device
     *
     * Body (form-urlencoded):
     *   device_id      = client-generated UUID, persisted on the device
     *   mobile_number  = "+9647XXXXXXXXX"
     *   password       = plaintext, FastPay hashes server-side
     *
     * Response classification:
     *   - same device  => upstream embeds a fresh Bearer token in `data`,
     *                     usually under `data.token` or `data.access_token`.
     *   - new device   => upstream returns no token but signals OTP is on its
     *                     way. We surface that as STATUS_OTP_REQUIRED and
     *                     forward whatever session reference the upstream
     *                     hands back so the caller can complete OTP later.
     */
    public function signIn(
        string $mobileNumber,
        string $password,
        string $deviceId,
    ): SignInResult {
        $version = $this->config['login_api_version'] ?? 'v2';
        $path    = "/api/{$version}/auth/signin/check-same-device";

        // Sign-in is unauthenticated, but the upstream client still sends an
        // empty Bearer header. Match that exactly so we look the same on the
        // wire as the legitimate iOS app.
        $request = $this->http
            ->baseUrl($this->config['base_url'])
            ->timeout($this->config['timeout'] ?? 15)
            ->acceptJson()
            ->asForm()
            ->withToken('')
            ->withHeaders($this->buildHeaders());

        $response = $request->post($path, [
            'device_id'     => $deviceId,
            'mobile_number' => $mobileNumber,
            'password'      => $password,
        ]);

        $data = $this->unwrap($response);

        return $this->classifySignIn($data);
    }

    /**
     * POST /api/v{login_api_version}/auth/signin/verify-otp
     *
     * Body (form-urlencoded):
     *   device_id      = same UUID we used on /check-same-device
     *   mobile_number  = E.164 with leading + (e.g. "+9647509646550")
     *   otp            = 6-digit code from SMS
     *   password       = same plaintext password
     *
     * Authorization: when /check-same-device returns is_same_device:false
     * FastPay also returns a temporary JWT in `data.token`. That JWT is
     * meant to be presented as `Authorization: Bearer ...` on /verify-otp.
     * We accept it as $otpSessionToken; if absent, fall back to an empty
     * Bearer to match the pre-auth client signature.
     *
     * Successful response: data.token = the FINAL user token. We hand it
     * back wrapped in a SignInResult with STATUS_TOKEN.
     */
    public function verifyOtp(
        string $mobileNumber,
        string $password,
        string $deviceId,
        string $otp,
        ?string $otpSessionToken = null,
    ): SignInResult {
        $version = $this->config['login_api_version'] ?? 'v2';
        $path    = "/api/{$version}/auth/signin/verify-otp";

        $request = $this->http
            ->baseUrl($this->config['base_url'])
            ->timeout($this->config['timeout'] ?? 15)
            ->acceptJson()
            ->asForm()
            ->withToken($otpSessionToken ?: '')
            ->withHeaders($this->buildHeaders());

        $response = $request->post($path, [
            'device_id'     => $deviceId,
            'mobile_number' => $mobileNumber,
            'otp'           => $otp,
            'password'      => $password,
        ]);

        $data = $this->unwrap($response);

        return $this->classifyVerifyOtp($data);
    }

    public function basicInformation(Credentials $credentials): WalletUser
    {
        $version = $this->config['api_version'] ?? 'v1';
        $path    = "/api/{$version}/private/user/basic-information";

        $response = $this->request($credentials)->get($path);

        $payload = $this->unwrap($response);
        $user    = $payload['user'] ?? [];

        return new WalletUser(
            provider: $this->name(),
            identifier: (string) ($user['identifier'] ?? ''),
            firstName: $user['first_name'] ?? null,
            lastName: $user['last_name'] ?? null,
            mobileNumber: $user['mobile_number'] ?? null,
            email: $user['email'] ?? null,
            profileThumbnail: $user['profile_thumbnail'] ?? null,
            balances: array_map(
                fn (array $b) => new WalletBalance(
                    accountType: (string) ($b['account_type'] ?? ''),
                    accountNumber: (string) ($b['account_no'] ?? ''),
                    currency: (string) ($b['currency'] ?? ''),
                    amount: (string) ($b['balance'] ?? '0'),
                ),
                $user['available_balance'] ?? [],
            ),
            raw: $user,
        );
    }

    /**
     * GET /api/v{api_version}/private/user/transaction/history?page={n}
     *
     * FastPay envelope for this endpoint is split across two siblings:
     *   data            = { transactions: [...], has_next_page: bool }
     *   data_additional = { per_page: int, total: int }
     *
     * We pull data through unwrap() (which validates the envelope) and read
     * data_additional off the same Response — calling ->json() twice is fine,
     * Laravel caches the decoded body.
     */
    public function transactionHistory(Credentials $credentials, int $page = 1): WalletTransactionPage
    {
        $version = $this->config['api_version'] ?? 'v1';
        $path    = "/api/{$version}/private/user/transaction/history";
        $page    = max(1, $page);

        $response = $this->request($credentials)->get($path, ['page' => $page]);

        $payload    = $this->unwrap($response);
        $body       = (array) $response->json();
        $additional = (array) ($body['data_additional'] ?? []);

        $transactions = array_map(
            fn (array $row) => WalletTransaction::fromFastPay($row),
            $payload['transactions'] ?? [],
        );

        return new WalletTransactionPage(
            provider: $this->name(),
            transactions: $transactions,
            page: $page,
            perPage: (int) ($additional['per_page'] ?? count($transactions)),
            total: (int) ($additional['total'] ?? count($transactions)),
            hasNextPage: (bool) ($payload['has_next_page'] ?? false),
            raw: $payload + ['data_additional' => $additional],
        );
    }

    /**
     * POST /api/v{api_version}/{endpoints.send_money}
     *
     * Body shape (form-encoded; FastPay uses form for write endpoints):
     *   mobile_number  : E.164 recipient (e.g. +9647509646550)
     *   amount         : numeric string (IQD)
     *   purpose / note : optional memo
     *
     * !! HEADS-UP !!
     * The exact upstream URL and field names for FastPay's send-money flow
     * were not in the original captured request set. The defaults below are
     * a reasonable guess based on FastPay's other endpoint naming. If they
     * 404 or 422, capture the real request from the iOS app and override:
     *
     *   config/wallet.php  -> providers.fastpay.endpoints.send_money
     *   config/wallet.php  -> providers.fastpay.send_money_fields
     *
     * Without changing this file.
     */
    public function sendMoney(
        Credentials $credentials,
        string $recipientMobile,
        string $amount,
        ?string $note = null,
    ): WalletPayment
    {
        $version  = $this->config['api_version'] ?? 'v1';
        $endpoint = $this->config['endpoints']['send_money']
            ?? 'private/user/transaction/send-money';
        $path     = '/api/'.$version.'/'.ltrim($endpoint, '/');

        // Field-name overrides so the exact body shape can be tweaked from
        // config without touching this file. Defaults are sensible guesses.
        $fields = $this->config['send_money_fields'] ?? [];
        $mobileField = $fields['mobile'] ?? 'mobile_number';
        $amountField = $fields['amount'] ?? 'amount';
        $noteField   = $fields['note']   ?? 'purpose';

        $body = [
            $mobileField => $recipientMobile,
            $amountField => $amount,
        ];
        if ($note !== null && $note !== '') {
            $body[$noteField] = $note;
        }

        $response = $this->request($credentials)->asForm()->post($path, $body);

        $data = $this->unwrap($response);

        return $this->classifyPayment($data, $recipientMobile, $amount);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function classifyPayment(array $data, string $recipient, string $amount): WalletPayment
    {
        // FastPay nests the new transaction under varying keys depending on
        // the endpoint. Walk the most likely paths.
        $txId = $data['transaction_id']
            ?? $data['transaction']['transaction_id']
            ?? $data['receipt']['transaction_id']
            ?? null;

        $currency = $data['currency']
            ?? $data['transaction']['currency']
            ?? 'IQD';

        $pending = ($data['status'] ?? null) === 'pending'
            || ($data['requires_otp'] ?? null) === true
            || ($data['requires_pin'] ?? null) === true;

        $status = $pending
            ? WalletPayment::STATUS_PENDING
            : ($txId ? WalletPayment::STATUS_SUBMITTED : WalletPayment::STATUS_UNKNOWN);

        return new WalletPayment(
            provider: $this->name(),
            status: $status,
            transactionId: $txId !== null ? (string) $txId : null,
            amount: $amount,
            currency: (string) $currency,
            recipientMobile: $recipient,
            message: $data['message'] ?? null,
            raw: $data,
        );
    }

    /**
     * Walk a known set of likely keys to figure out whether FastPay handed us
     * a final auth token, a temporary OTP-session token, or asked for OTP.
     *
     * Important nuance: when FastPay returns `is_same_device: false`, it ALSO
     * returns a JWT in `data.token`. That JWT is NOT a final auth token — it's
     * a temporary one, scoped to the verify-otp call. So if we see ANY OTP
     * signal, we classify as OTP_REQUIRED and stash the JWT as the session id.
     * Only when no OTP signal is present do we treat `data.token` as the
     * final user token.
     *
     * @param array<string, mixed> $data
     */
    private function classifySignIn(array $data): SignInResult
    {
        $token = $data['token']
            ?? $data['access_token']
            ?? $data['bearer_token']
            ?? $data['user']['token']
            ?? null;
        $tokenString = is_string($token) && $token !== '' ? $token : null;

        // Common shapes signaling "we just sent you an OTP":
        //   { is_same_device: false, token: "tmp_jwt..." }
        //   { otp_required: true, session_id: "..." }
        //   { step: "otp", token: "tmp..." }
        $otpRequired = ($data['otp_required'] ?? null) === true
            || ($data['is_same_device'] ?? null) === false
            || ($data['same_device']    ?? null) === false
            || ($data['step'] ?? null)   === 'otp';

        $explicitSession = $data['otp_session_id']
            ?? $data['session_id']
            ?? $data['otp_token']
            ?? $data['otp_reference']
            ?? null;

        if ($otpRequired) {
            return new SignInResult(
                provider: $this->name(),
                status: SignInResult::STATUS_OTP_REQUIRED,
                otpSessionId: $explicitSession ? (string) $explicitSession : $tokenString,
                message: $data['message'] ?? 'OTP verification required for this device.',
                raw: $data,
            );
        }

        if ($tokenString !== null) {
            return new SignInResult(
                provider: $this->name(),
                status: SignInResult::STATUS_TOKEN,
                token: $tokenString,
                message: $data['message'] ?? 'Signed in successfully.',
                raw: $data,
            );
        }

        if ($explicitSession) {
            return new SignInResult(
                provider: $this->name(),
                status: SignInResult::STATUS_OTP_REQUIRED,
                otpSessionId: (string) $explicitSession,
                message: $data['message'] ?? 'OTP verification required for this device.',
                raw: $data,
            );
        }

        // Don't pretend to know what we got — let the caller see the payload.
        return new SignInResult(
            provider: $this->name(),
            status: SignInResult::STATUS_UNKNOWN,
            message: $data['message'] ?? null,
            raw: $data,
        );
    }

    /**
     * Classify the response from /verify-otp.
     *
     * On success FastPay returns:
     *   { code: 200, data: { token: "...", is_same_device: false } }
     *
     * Note `is_same_device: false` here is descriptive, not a request to do
     * OTP again — the verification just finished. So unlike classifySignIn,
     * we treat the presence of `data.token` as the canonical success signal.
     *
     * @param array<string, mixed> $data
     */
    private function classifyVerifyOtp(array $data): SignInResult
    {
        $token = $data['token']
            ?? $data['access_token']
            ?? $data['bearer_token']
            ?? $data['user']['token']
            ?? null;

        if (is_string($token) && $token !== '') {
            return new SignInResult(
                provider: $this->name(),
                status: SignInResult::STATUS_TOKEN,
                token: $token,
                message: $data['message'] ?? 'Signed in successfully.',
                raw: $data,
            );
        }

        return new SignInResult(
            provider: $this->name(),
            status: SignInResult::STATUS_UNKNOWN,
            message: $data['message'] ?? 'OTP verification did not return a token.',
            raw: $data,
        );
    }

    /**
     * Build a configured Http client with all FastPay-required headers.
     */
    private function request(Credentials $credentials): PendingRequest
    {
        return $this->http
            ->baseUrl($this->config['base_url'])
            ->timeout($this->config['timeout'] ?? 15)
            ->acceptJson()
            ->withToken($credentials->token)
            ->withHeaders($this->buildHeaders());
    }

    /**
     * Static request envelope. The Bearer token is added separately via
     * withToken(), so this only carries the headers FastPay still expects
     * regardless of which user is authenticated.
     *
     * device-id / X-Signature-Id / Cookie are added only when configured —
     * required for sign-in, optional for authenticated endpoints.
     *
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        $headers = [
            'User-Type'        => $this->config['user_type'] ?? 'P',
            'Platform'         => $this->config['platform']  ?? 'ios',
            'User-Agent'       => $this->config['user_agent'] ?? 'Fastpay/3.0.11',
            'Accept-Language'  => $this->config['language'] ?? 'en',
            'Accept-Encoding'  => 'gzip;q=1.0, compress;q=0.5',
            'Connection'       => 'keep-alive',
            'App-request-id'   => $this->generateAppRequestId(),
        ];

        if (! empty($this->config['device_id'])) {
            $headers['device-id'] = $this->config['device_id'];
        }
        if (! empty($this->config['signature_id'])) {
            $headers['X-Signature-Id'] = $this->config['signature_id'];
        }
        if (! empty($this->config['cookie'])) {
            $headers['Cookie'] = $this->config['cookie'];
        }

        return $headers;
    }

    /**
     * Match FastPay's observed format:
     *   {UUID}$$${random16}{epochSeconds.fraction}
     */
    private function generateAppRequestId(): string
    {
        return strtoupper((string) Str::uuid())
            .'$$$'
            .Str::random(16)
            .number_format(microtime(true), 7, '.', '');
    }

    /**
     * Validate the upstream response and return its `data` payload.
     *
     * FastPay envelope: { "code": 200, "messages": [...], "data": {...} }
     *
     * @throws WalletException
     */
    private function unwrap(Response $response): array
    {
        try {
            $body = $response->json();
        } catch (Throwable $e) {
            throw new WalletException(
                message: 'Invalid JSON response from FastPay',
                status: 502,
                previous: $e,
            );
        }

        if (! is_array($body)) {
            throw new WalletException('Unexpected response shape from FastPay', 502);
        }

        $code = (int) ($body['code'] ?? $response->status());

        if (! $response->successful() || $code !== 200) {
            throw new WalletException(
                message: $this->extractMessage($body) ?? 'FastPay request failed',
                status: $this->mapStatus($response->status()),
                upstream: $body,
            );
        }

        return $body['data'] ?? [];
    }

    private function extractMessage(array $body): ?string
    {
        if (! empty($body['messages']) && is_array($body['messages'])) {
            return is_string($body['messages'][0] ?? null)
                ? $body['messages'][0]
                : json_encode($body['messages']);
        }

        return $body['message'] ?? null;
    }

    private function mapStatus(int $upstream): int
    {
        // 401/403/404/422 surface as-is so the consumer reacts correctly.
        return match (true) {
            $upstream === 401 => 401,
            $upstream === 403 => 403,
            $upstream === 404 => 404,
            $upstream === 422 => 422,
            $upstream >= 500  => 502,
            default           => 502,
        };
    }

    /**
     * Translate a network-level failure (DNS, timeout, refused) into our
     * exception. Call from controller via try/catch around provider calls.
     */
    public static function wrapConnection(ConnectionException $e): WalletException
    {
        return new WalletException(
            message: 'Could not reach FastPay: '.$e->getMessage(),
            status: 504,
            previous: $e,
        );
    }
}
