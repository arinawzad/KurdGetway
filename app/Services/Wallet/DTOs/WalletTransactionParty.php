<?php

namespace App\Services\Wallet\DTOs;

/**
 * The other side of a transaction (sender or receiver).
 *
 * Both fields are nullable because the upstream is loose: system-originated
 * entries (e.g. cashback, bill payments) may omit either name or mobile.
 */
final class WalletTransactionParty
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $mobileNumber,
    ) {}

    /**
     * @param array<string, mixed>|null $raw  FastPay shape: { name, mobile_no }
     */
    public static function fromArray(?array $raw): ?self
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        return new self(
            name: isset($raw['name']) ? (string) $raw['name'] : null,
            mobileNumber: isset($raw['mobile_no']) ? (string) $raw['mobile_no'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'name'          => $this->name,
            'mobile_number' => $this->mobileNumber,
        ];
    }
}
