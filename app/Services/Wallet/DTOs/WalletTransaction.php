<?php

namespace App\Services\Wallet\DTOs;

/**
 * Normalized single transaction across providers.
 *
 * `direction` collapses upstream wording into "debit" or "credit" so
 * consumers don't have to know FastPay's "Debit"/"Credit" casing.
 */
final class WalletTransaction
{
    public const DIRECTION_DEBIT  = 'debit';
    public const DIRECTION_CREDIT = 'credit';

    /**
     * @param array<string, mixed> $raw  Untouched provider payload, kept so
     *                                   consumers can render provider-specific
     *                                   fields (icon URL, color, service tag).
     */
    public function __construct(
        public readonly string $transactionId,
        public readonly string $title,
        public readonly string $direction,
        public readonly string $nature,
        public readonly string $transactionType,
        public readonly string $amount,
        public readonly string $currency,
        public readonly string $createdAt,
        public readonly ?WalletTransactionParty $source,
        public readonly ?WalletTransactionParty $destination,
        public readonly ?string $icon = null,
        public readonly ?string $color = null,
        public readonly ?string $serviceTag = null,
        public readonly array $raw = [],
    ) {}

    /**
     * Build from FastPay's per-transaction shape.
     *
     * @param array<string, mixed> $raw
     */
    public static function fromFastPay(array $raw): self
    {
        $direction = strtolower((string) ($raw['type_of_tx'] ?? ''));
        // Normalize: anything that isn't an explicit credit becomes a debit.
        $direction = $direction === 'credit'
            ? self::DIRECTION_CREDIT
            : ($direction === 'debit' ? self::DIRECTION_DEBIT : $direction);

        return new self(
            transactionId: (string) ($raw['transaction_id'] ?? ''),
            title: (string) ($raw['title'] ?? ''),
            direction: $direction,
            nature: (string) ($raw['nature_of_transaction'] ?? ''),
            transactionType: (string) ($raw['transaction_type'] ?? ''),
            amount: (string) ($raw['amount'] ?? '0'),
            currency: (string) ($raw['currency'] ?? ''),
            createdAt: (string) ($raw['created_at'] ?? ''),
            source: WalletTransactionParty::fromArray($raw['source'] ?? null),
            destination: WalletTransactionParty::fromArray($raw['destination'] ?? null),
            icon: $raw['icon'] ?? null,
            color: $raw['color'] ?? null,
            serviceTag: $raw['trx_service_tag'] ?? null,
            raw: $raw,
        );
    }

    public function toArray(): array
    {
        return [
            'transaction_id'   => $this->transactionId,
            'title'            => $this->title,
            'direction'        => $this->direction,
            'nature'           => $this->nature,
            'transaction_type' => $this->transactionType,
            'amount'           => $this->amount,
            'currency'         => $this->currency,
            'created_at'       => $this->createdAt,
            'source'           => $this->source?->toArray(),
            'destination'      => $this->destination?->toArray(),
            'icon'             => $this->icon,
            'color'            => $this->color,
            'service_tag'      => $this->serviceTag,
        ];
    }
}
