<?php

namespace App\Services\Wallet;

use App\Services\Wallet\Contracts\WalletProvider;
use App\Services\Wallet\Exceptions\WalletException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use InvalidArgumentException;

/**
 * Resolves a WalletProvider by name. Add new providers (FIB, etc.) by
 * registering a driver creator inside createDriver().
 */
class WalletManager
{
    /** @var array<string, WalletProvider> */
    private array $resolved = [];

    public function __construct(
        private readonly Container $app,
        private readonly array $config,
    ) {}

    public function provider(?string $name = null): WalletProvider
    {
        $name = $name ?: ($this->config['default'] ?? null);

        if (! $name) {
            throw new InvalidArgumentException('No wallet provider specified and no default configured.');
        }

        return $this->resolved[$name] ??= $this->resolve($name);
    }

    private function resolve(string $name): WalletProvider
    {
        $config = $this->config['providers'][$name] ?? null;

        if (! $config) {
            throw new WalletException("Unknown wallet provider [{$name}].", 404);
        }

        $driver = $config['driver'] ?? $name;

        return match ($driver) {
            'fastpay' => new FastPayProvider($this->app->make(HttpFactory::class), $config),
            // 'fib' => new FibProvider($this->app->make(HttpFactory::class), $config),
            default   => throw new WalletException("Driver [{$driver}] is not implemented yet.", 501),
        };
    }
}
