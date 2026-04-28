<?php

namespace App\Services\Wallet\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Single exception type for any failure raised by a wallet provider.
 *
 * `$status` is the HTTP status we want to return to the API caller (NOT
 * necessarily the upstream status — e.g. an upstream 502 should surface as
 * a 502, but a network timeout maps to 504 from our side).
 */
class WalletException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 502,
        public readonly ?array $upstream = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
