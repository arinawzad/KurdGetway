<?php

namespace App\Services\Wallet\DTOs;

/**
 * Normalized user profile across providers. Provider-specific extras live
 * in the `raw` array for callers that need access to fields we haven't
 * abstracted yet.
 */
final class WalletUser
{
    /**
     * @param WalletBalance[]      $balances
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $identifier,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $mobileNumber,
        public readonly ?string $email,
        public readonly ?string $profileThumbnail,
        public readonly array $balances,
        public readonly array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'provider'          => $this->provider,
            'identifier'        => $this->identifier,
            'first_name'        => $this->firstName,
            'last_name'         => $this->lastName,
            'mobile_number'     => $this->mobileNumber,
            'email'             => $this->email,
            'profile_thumbnail' => $this->profileThumbnail,
            'balances'          => array_map(fn ($b) => $b->toArray(), $this->balances),
        ];
    }
}
