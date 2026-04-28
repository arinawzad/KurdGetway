<?php

namespace App\Services\Wallet\DTOs;

/**
 * Normalized result of a provider sign-in attempt.
 *
 * Status semantics:
 *   - "token"        : user authenticated; Bearer token is in $token.
 *   - "otp_required" : provider needs an OTP step (e.g. new device).
 *                      The provider's session reference (whatever the
 *                      verify-OTP endpoint will need) is in $otpSessionId.
 *   - "unknown"      : upstream returned 200 but we couldn't classify it.
 *                      Caller should fall back to inspecting `raw`.
 */
final class SignInResult
{
    public const STATUS_TOKEN        = 'token';
    public const STATUS_OTP_REQUIRED = 'otp_required';
    public const STATUS_UNKNOWN      = 'unknown';

    /**
     * @param array<string, mixed> $raw  Untouched provider payload, so the
     *                                   frontend can show OTP delivery info,
     *                                   masked phone, expiry, etc.
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $status,
        public readonly ?string $token = null,
        public readonly ?string $otpSessionId = null,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'provider'        => $this->provider,
            'status'          => $this->status,
            'token'           => $this->token,
            'otp_session_id'  => $this->otpSessionId,
            'message'         => $this->message,
            'raw'             => $this->raw,
        ];
    }
}
