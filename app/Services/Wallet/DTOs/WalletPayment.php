<?php

namespace App\Services\Wallet\DTOs;

/**
 * Result of a send-money / payment attempt.
 *
 * Status semantics:
 *   - "submitted" : upstream accepted the request. The transaction may still
 *                   be in-flight (some providers settle async). The caller
 *                   should verify by polling transaction history.
 *   - "pending"   : upstream returned 200 but explicitly told us it's pending
 *                   (e.g. waiting on PIN/OTP step).
 *   - "unknown"   : we couldn't classify — caller should inspect `raw`.
 */
final class WalletPayment
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PENDING   = 'pending';
    public const STATUS_UNKNOWN   = 'unknown';

    /**
     * @param array<string, mixed> $raw  Untouched provider payload.
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $status,
        public readonly ?string $transactionId,
        public readonly string $amount,
        public readonly string $currency,
        public readonly string $recipientMobile,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'provider'         => $this->provider,
            'status'           => $this->status,
            'transaction_id'   => $this->transactionId,
            'amount'           => $this->amount,
            'currency'         => $this->currency,
            'recipient_mobile' => $this->recipientMobile,
            'message'          => $this->message,
        ];
    }
}
