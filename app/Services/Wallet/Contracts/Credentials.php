<?php

namespace App\Services\Wallet\Contracts;

/**
 * Per-request credentials sent by the API caller.
 *
 * `token` is the upstream Bearer token for the wallet provider. As of the
 * latest FastPay gateway, that's all the upstream needs for authenticated
 * calls — no device-bound headers required.
 */
final class Credentials
{
    public function __construct(
        public readonly string $token,
    ) {}
}
