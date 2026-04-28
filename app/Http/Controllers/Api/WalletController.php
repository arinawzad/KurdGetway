<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Wallet\Contracts\Credentials;
use App\Services\Wallet\Exceptions\WalletException;
use App\Services\Wallet\FastPayProvider;
use App\Services\Wallet\WalletManager;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HTTP entry point for the wallet gateway.
 *
 * Conventions
 * -----------
 *  - The caller's wallet token is passed in `Authorization: Bearer <token>`.
 *  - Provider-specific overrides (FastPay device-id, signature-id) come in
 *    via X-Wallet-* headers and are forwarded as Credentials extras.
 *  - The provider is selected via a route segment, e.g. /api/wallet/fastpay/me.
 */
class WalletController extends Controller
{
    public function __construct(
        private readonly WalletManager $wallets,
    ) {}

    /**
     * POST /api/wallet/{provider}/signin
     *
     * Body (JSON or form):
     *   mobile_number  required, e.g. "+9647509646550"
     *   password       required
     *   device_id      required, client-generated UUID kept on the device.
     *                  Same UUID across logins → same-device path → token.
     *                  New UUID → upstream may send an OTP challenge.
     */
    public function signIn(Request $request, string $provider): JsonResponse
    {
        $validated = $request->validate([
            'mobile_number' => ['required', 'string'],
            'password'      => ['required', 'string'],
            'device_id'     => ['required', 'string', 'min:8'],
        ]);

        try {
            $result = $this->wallets->provider($provider)->signIn(
                mobileNumber: $validated['mobile_number'],
                password:     $validated['password'],
                deviceId:     $validated['device_id'],
            );

            return response()->json([
                'ok'   => true,
                'data' => $result->toArray(),
            ]);
        } catch (ConnectionException $e) {
            return $this->error(FastPayProvider::wrapConnection($e));
        } catch (WalletException $e) {
            return $this->error($e);
        }
    }

    public function me(Request $request, string $provider): JsonResponse
    {
        try {
            $credentials = $this->credentialsFrom($request, $provider);
            $user        = $this->wallets->provider($provider)->basicInformation($credentials);

            return response()->json([
                'ok'   => true,
                'data' => $user->toArray(),
            ]);
        } catch (ConnectionException $e) {
            return $this->error(FastPayProvider::wrapConnection($e));
        } catch (WalletException $e) {
            return $this->error($e);
        }
    }

    /**
     * GET /api/wallet/{provider}/transactions?page=1
     *
     * Returns one page of normalized transactions. The caller drives
     * pagination by reading `has_next_page` and bumping the `page` query
     * param until it's false.
     */
    public function transactions(Request $request, string $provider): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));

        try {
            $credentials = $this->credentialsFrom($request, $provider);
            $result      = $this->wallets->provider($provider)->transactionHistory($credentials, $page);

            return response()->json([
                'ok'   => true,
                'data' => $result->toArray(),
            ]);
        } catch (ConnectionException $e) {
            return $this->error(FastPayProvider::wrapConnection($e));
        } catch (WalletException $e) {
            return $this->error($e);
        }
    }

    /**
     * POST /api/wallet/{provider}/pay
     *
     * Body (JSON or form):
     *   recipient_mobile  required, E.164 with leading + (e.g. "+9647509646550")
     *   amount            required, numeric (string or number; serialized as string)
     *   note              optional, free-form memo
     *
     * Self-transfer is intentionally not blocked here — the upstream will
     * reject it if it doesn't allow it. The frontend uses self-transfer for
     * a round-trip "Pay Test" sanity check.
     */
    public function pay(Request $request, string $provider): JsonResponse
    {
        $validated = $request->validate([
            'recipient_mobile' => ['required', 'string', 'min:6'],
            'amount'           => ['required'],
            'note'             => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        try {
            $credentials = $this->credentialsFrom($request, $provider);
            $payment     = $this->wallets->provider($provider)->sendMoney(
                credentials:     $credentials,
                recipientMobile: $validated['recipient_mobile'],
                amount:          (string) $validated['amount'],
                note:            $validated['note'] ?? null,
            );

            return response()->json([
                'ok'   => true,
                'data' => $payment->toArray(),
            ]);
        } catch (ConnectionException $e) {
            return $this->error(FastPayProvider::wrapConnection($e));
        } catch (WalletException $e) {
            return $this->error($e);
        }
    }

    /**
     * Pull the upstream token off the request, falling back to config
     * defaults when not supplied. The API caller's `Authorization: Bearer ...`
     * header always wins over the config token, so a single deployment can
     * serve both single-account (config) and multi-tenant (per-request) modes.
     */
    private function credentialsFrom(Request $request, string $provider): Credentials
    {
        $token = $request->bearerToken()
            ?: config("wallet.providers.{$provider}.token");

        if (! $token) {
            throw new WalletException(
                message: 'No wallet token available. Send `Authorization: Bearer ...` or set the provider token in .env.',
                status: 401,
            );
        }

        return new Credentials(token: $token);
    }

    private function error(WalletException $e): JsonResponse
    {
        return response()->json([
            'ok'    => false,
            'error' => [
                'message'  => $e->getMessage(),
                'upstream' => $e->upstream,
            ],
        ], $e->status);
    }
}
