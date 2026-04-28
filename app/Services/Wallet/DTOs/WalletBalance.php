<?php

namespace App\Services\Wallet\DTOs;

/**
 * Normalized balance representation. Every provider maps its own balance
 * structure into this so the API consumer doesn't need to care which wallet
 * is behind the call.
 */
final class WalletBalance
{
    public function __construct(
        public readonly string $accountType,
        public readonly string $accountNumber,
        public readonly string $currency,
        public readonly string $amount,
    ) {}

    public function toArray(): array
    {
        return [
            'account_type'   => $this->accountType,
            'account_number' => $this->accountNumber,
            'currency'       => $this->currency,
            'amount'         => $this->amount,
        ];
    }
}
