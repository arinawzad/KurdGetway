<?php

namespace App\Services\Wallet\Contracts;

use App\Services\Wallet\DTOs\SignInResult;
use App\Services\Wallet\DTOs\WalletPayment;
use App\Services\Wallet\DTOs\WalletTransactionPage;
use App\Services\Wallet\DTOs\WalletUser;

/**
 * Contract every wallet provider (FastPay, FIB, ...) must implement.
 *
 * Each provider is a thin adapter around an upstream HTTP API. Providers are
 * stateless and per-request: the caller's auth token is passed in via the
 * Credentials object so a single provider instance can serve many users.
 */
interface WalletProvider
{
    /**
     * Identifier returned by name() must match the provider key in
     * config/wallet.php (e.g. "fastpay", "fib").
     */
    public function name(): string;

    /**
     * Authenticate a user against the upstream wallet.
     *
     * The provider returns a {@see SignInResult} which can either carry a
     * fresh Bearer token (same-device login) or signal that an OTP step is
     * required (e.g. the device_id changed).
     *
     * @throws \App\Services\Wallet\Exceptions\WalletException
     */
    public function signIn(
        string $mobileNumber,
        string $password,
        string $deviceId,
    ): SignInResult;

    /**
     * Complete two-factor sign-in by submitting the 6-digit OTP that the
     * upstream wallet just sent to the user's phone.
     *
     * Mobile, password and device_id are re-sent because FastPay's
     * /verify-otp endpoint expects the same envelope as /check-same-device,
     * plus the OTP. The optional $otpSessionToken is the temporary JWT that
     * was returned alongside `is_same_device: false` on the previous step;
     * FastPay validates it as `Authorization: Bearer ...` on verify-otp.
     *
     * @throws \App\Services\Wallet\Exceptions\WalletException
     */
    public function verifyOtp(
        string $mobileNumber,
        string $password,
        string $deviceId,
        string $otp,
        ?string $otpSessionToken = null,
    ): SignInResult;

    /**
     * Fetch the authenticated user's basic information & primary balance.
     *
     * @throws \App\Services\Wallet\Exceptions\WalletException on upstream
     *         failure (network, auth, validation, etc.)
     */
    public function basicInformation(Credentials $credentials): WalletUser;

    /**
     * Fetch the authenticated user's transaction history, one page at a time.
     *
     * Pagination is provider-driven: callers walk pages by incrementing the
     * 1-based page number until {@see WalletTransactionPage::$hasNextPage}
     * is false.
     *
     * @throws \App\Services\Wallet\Exceptions\WalletException
     */
    public function transactionHistory(Credentials $credentials, int $page = 1): WalletTransactionPage;

    /**
     * Send money from the authenticated user to another wallet user.
     *
     * Self-transfer is allowed at this layer — the upstream may or may not
     * accept it (FastPay typically rejects sending to your own number), but
     * we don't second-guess the caller.
     *
     * @param string $recipientMobile  E.164 with leading + (e.g. "+964750xxxxxxx").
     * @param string $amount           Stringified to avoid float precision loss.
     *                                 Provider decides minor units / formatting.
     * @param string|null $note        Free-form memo, optional.
     *
     * @throws \App\Services\Wallet\Exceptions\WalletException
     */
    public function sendMoney(
        Credentials $credentials,
        string $recipientMobile,
        string $amount,
        ?string $note = null,
    ): WalletPayment;
}
