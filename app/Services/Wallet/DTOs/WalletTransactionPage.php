<?php

namespace App\Services\Wallet\DTOs;

/**
 * Paginated transaction list. FastPay returns:
 *   data            = { transactions: [...], has_next_page: bool }
 *   data_additional = { per_page: int, total: int }
 *
 * We flatten that into a single, predictable page object.
 */
final class WalletTransactionPage
{
    /**
     * @param WalletTransaction[]  $transactions
     * @param array<string, mixed> $raw  Original (data + data_additional) payload.
     */
    public function __construct(
        public readonly string $provider,
        public readonly array $transactions,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total,
        public readonly bool $hasNextPage,
        public readonly array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'provider'      => $this->provider,
            'page'          => $this->page,
            'per_page'      => $this->perPage,
            'total'         => $this->total,
            'has_next_page' => $this->hasNextPage,
            'transactions'  => array_map(fn ($t) => $t->toArray(), $this->transactions),
        ];
    }
}
