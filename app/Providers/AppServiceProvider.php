<?php

namespace App\Providers;

use App\Services\Wallet\WalletManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WalletManager::class, function ($app) {
            return new WalletManager($app, $app['config']->get('wallet', []));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
