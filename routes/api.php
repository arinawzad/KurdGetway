<?php

use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wallet Gateway API
|--------------------------------------------------------------------------
|
| All routes here are prefixed with /api by bootstrap/app.php.
|
| Auth model: each request must carry the upstream wallet token in the
| `Authorization: Bearer ...` header. The gateway forwards that token to
| the appropriate provider — it is not stored. Sign-in is the one
| exception — it MINTS the token for the caller.
|
| Examples:
|   POST /api/wallet/fastpay/signin
|   GET  /api/wallet/fastpay/me
|   GET  /api/wallet/fastpay/transactions?page=1
|   POST /api/wallet/fastpay/pay
|   GET  /api/wallet/fib/me           (once FIB is implemented)
|
*/

Route::prefix('wallet')->group(function () {

    Route::post('{provider}/signin', [WalletController::class, 'signIn'])
        ->whereIn('provider', ['fastpay', 'fib'])
        ->name('wallet.signin');

    Route::get('{provider}/me', [WalletController::class, 'me'])
        ->whereIn('provider', ['fastpay', 'fib'])
        ->name('wallet.me');

    Route::get('{provider}/transactions', [WalletController::class, 'transactions'])
        ->whereIn('provider', ['fastpay', 'fib'])
        ->name('wallet.transactions');

    Route::post('{provider}/pay', [WalletController::class, 'pay'])
        ->whereIn('provider', ['fastpay', 'fib'])
        ->name('wallet.pay');

});

Route::get('/', fn () => response()->json([
    'name'      => config('app.name'),
    'service'   => 'kurdgetway wallet gateway',
    'version'   => '0.1.0',
    'endpoints' => [
        'signin'       => 'POST /api/wallet/{provider}/signin',
        'me'           => 'GET  /api/wallet/{provider}/me',
        'transactions' => 'GET  /api/wallet/{provider}/transactions?page=1',
        'pay'          => 'POST /api/wallet/{provider}/pay',
    ],
]));
