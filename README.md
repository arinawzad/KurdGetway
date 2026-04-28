<div align="center">

# Kurdgetway

**A unified Laravel API gateway for Iraqi mobile wallets.**

One stable API across providers — FastPay today, FIB coming soon.

[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Status: alpha](https://img.shields.io/badge/Status-alpha-orange)]()

</div>

---

It also ships with a **browser-based tester UI** at `/` — sign in, view balances, page through transactions, and watch your inbox for incoming credits, all without writing a line of frontend code.

> ⚠️ **Unofficial.** Kurdgetway is not affiliated with or endorsed by FastPay, FIB, or any other wallet provider. It speaks to public-facing APIs by reverse-engineering the providers' mobile clients. Use it on accounts you own and at your own risk. Nothing here circumvents authentication — you still need valid credentials.

## What is this?

Each Iraqi mobile wallet ships its own SDK, headers, envelope format, error vocabulary, and pagination quirks. If you want to build *one* app that works against multiple wallets, you spend most of your time just normalizing inputs and outputs.

Kurdgetway is a thin Laravel-based gateway that does that normalization for you. You point it at a wallet provider (currently **FastPay**), and it gives you a single, predictable HTTP+JSON API for sign-in, profile, transaction history, and payments. New providers are added by implementing one PHP interface — the public surface stays the same.

## Features

- **Unified `/api/wallet/{provider}/*` API** — same response shape regardless of upstream
- **FastPay adapter** covering the whole core flow:
  - `POST /signin` — same-device token mint *or* OTP-required classification
  - `GET /me` — profile & balances
  - `GET /transactions?page=N` — paginated, normalized history with `direction` (debit/credit), source/destination parties, icons, and timestamps
  - `POST /pay` — send-money with configurable upstream URL & body fields
- **Built-in tester UI** at `/`, including:
  - Sign-in modal with same-device-vs-OTP detection
  - Pretty profile + balance card
  - Paginated transaction list
  - **Watch Inbox** — polls your history every 5 seconds for 1 minute, optionally filtered by sender mobile, and surfaces any incoming credit in real time
  - One-click "Copy as `FASTPAY_TOKEN=…`" button for `.env` configuration
- **Multi-tenant ready** — each HTTP request can carry its own Bearer token in `Authorization: Bearer …`, or fall back to a single-account token from `.env`
- **No persistence of upstream secrets** — Bearer tokens are forwarded, never stored
- **Upstream errors faithfully surfaced** — including the original `code` / `messages` / `data` envelope under `error.upstream`, so callers can react to specific upstream conditions

## Requirements

- PHP **8.3+**
- Composer 2.x
- A database (SQLite, MySQL, or PostgreSQL — used by Laravel for sessions, cache, and queue tables)
- Node.js 18+ (optional — only if you intend to rebuild the bundled frontend assets)

## Quick start

```bash
git clone https://github.com/<your-username>/kurdgetway.git
cd kurdgetway

composer install
cp .env.example .env
php artisan key:generate
php artisan migrate

php artisan serve
```

Open <http://localhost:8000> — you'll see the tester UI. Sign in with your FastPay credentials, or paste an existing Bearer token, and start exploring.

## Configuration

All wallet settings live in [`config/wallet.php`](config/wallet.php) and are populated from `.env`.

### FastPay

```env
FASTPAY_BASE_URL=https://apigw-personal.fast-pay.iq
FASTPAY_API_VERSION=v1

# Optional — single-account fallback token. Per-request callers send their own
# in the Authorization header, which always wins.
FASTPAY_TOKEN=

# Required for sign-in (unauthenticated endpoint validates a device fingerprint).
# Capture these once from the mobile app's network traffic.
FASTPAY_DEVICE_ID=
FASTPAY_SIGNATURE_ID=
FASTPAY_COOKIE=

# Static client identification — defaults are sensible.
FASTPAY_USER_AGENT="Fastpay/3.0.11 (com.NGC.SSLWirless.FastPay; build:224; iOS 26.4.1) Alamofire/3.0.11"
FASTPAY_PLATFORM=ios
FASTPAY_USER_TYPE=P
FASTPAY_LANGUAGE=en
FASTPAY_TIMEOUT=15
```

> **Why the device-fingerprint vars?** FastPay accepts the Bearer token alone for authenticated endpoints (`/me`, `/transactions`, `/pay`). The unauthenticated **sign-in** endpoint additionally validates a device fingerprint — leaving these blank returns `INVALID_ARGUMENT`. Capture them once from the mobile app's network traffic. Once you have a working token, you can run the gateway in `/me`-only mode without them.

## API reference

All routes are prefixed with `/api`. Auth is a forwarded `Authorization: Bearer …` header — the gateway never stores user tokens.

### `POST /api/wallet/{provider}/signin`

Mint a fresh token, or detect that an OTP step is required.

**Body** (JSON or form):

| Field           | Required | Notes                                                       |
|-----------------|----------|-------------------------------------------------------------|
| `mobile_number` | yes      | E.164 with leading `+`, e.g. `+9647509646550`               |
| `password`      | yes      | Plaintext — the upstream hashes server-side                 |
| `device_id`     | yes      | Client-generated UUID, persist on the device                |

**Response shape**

```json
{
  "ok": true,
  "data": {
    "provider": "fastpay",
    "status": "token",
    "token": "eyJ0eXAiOiJKV1Qi...",
    "otp_session_id": null,
    "message": "Signed in successfully.",
    "raw": { ... }
  }
}
```

`status` is one of:
- `"token"` — you got a fresh Bearer token in `data.token`
- `"otp_required"` — new device; FastPay sent an OTP. Take the next step in your client.
- `"unknown"` — non-200 path Kurdgetway couldn't classify; inspect `raw`.

### `GET /api/wallet/{provider}/me`

Returns the authenticated user's profile and balances.

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     http://localhost:8000/api/wallet/fastpay/me
```

```json
{
  "ok": true,
  "data": {
    "provider": "fastpay",
    "identifier": "123456",
    "first_name": "Jane",
    "last_name": "Doe",
    "mobile_number": "+964750xxxxxxx",
    "email": "jane@example.com",
    "profile_thumbnail": "https://...",
    "balances": [
      {
        "account_type": "Fastpay Savings Account",
        "account_number": "XXXXXXXXXXXX",
        "currency": "IQD",
        "amount": "2,059"
      }
    ]
  }
}
```

### `GET /api/wallet/{provider}/transactions?page=N`

Paginated, normalized transaction history.

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     "http://localhost:8000/api/wallet/fastpay/transactions?page=1"
```

```json
{
  "ok": true,
  "data": {
    "provider": "fastpay",
    "page": 1,
    "per_page": 10,
    "total": 3762,
    "has_next_page": true,
    "transactions": [
      {
        "transaction_id": "XXXXXXXXXX",
        "title": "You Received Money",
        "direction": "credit",
        "nature": "Transfer",
        "transaction_type": "Add Money",
        "amount": "18,180",
        "currency": "IQD",
        "created_at": "26 April 2026 09:27 AM",
        "source": { "name": "FIB MR", "mobile_number": "+964880xxxxxxx" },
        "destination": null,
        "icon": "https://.../receive.png",
        "color": "#03EBA3",
        "service_tag": null
      }
    ]
  }
}
```

Pagination is consumer-driven: walk pages by incrementing `page` until `has_next_page` is `false`.

### `POST /api/wallet/{provider}/pay`

Send money to another wallet user.

**Body**:

| Field              | Required | Notes                                                 |
|--------------------|----------|-------------------------------------------------------|
| `recipient_mobile` | yes      | E.164 with leading `+`                                |
| `amount`           | yes      | Numeric (string or number; serialized as string)      |
| `note`             | no       | Free-form memo, max 255 chars                         |

> **Heads-up.** The upstream send-money URL and body field names weren't in our reference capture set — the defaults are educated guesses. If FastPay returns 404 / 422, override without code changes via:
>
> ```env
> FASTPAY_SEND_MONEY_PATH=private/your/correct/path
> FASTPAY_SEND_MONEY_FIELD_MOBILE=mobile_number
> FASTPAY_SEND_MONEY_FIELD_AMOUNT=amount
> FASTPAY_SEND_MONEY_FIELD_NOTE=purpose
> ```

### Errors

All errors share the same envelope:

```json
{
  "ok": false,
  "error": {
    "message": "Authentication failed",
    "upstream": { "code": 401, "messages": ["Authentication failed"], "data": null }
  }
}
```

`error.upstream` carries the verbatim upstream envelope when one was returned, so callers can react to specific upstream conditions without parsing strings.

## Architecture

```
HTTP request
   ↓
routes/api.php  ──►  WalletController
                          │
                          ▼
                    WalletManager  (resolves driver by name)
                          │
                          ▼
                    WalletProvider  (interface)
                          ▲
              ┌───────────┴───────────┐
              │                       │
       FastPayProvider          FibProvider (stub)
```

- **`Contracts\WalletProvider`** — the one interface every adapter implements (`signIn`, `basicInformation`, `transactionHistory`, `sendMoney`).
- **`Contracts\Credentials`** — wraps the per-request Bearer token.
- **`DTOs\*`** — normalized response shapes (`SignInResult`, `WalletUser`, `WalletBalance`, `WalletTransactionPage`, `WalletTransaction`, `WalletTransactionParty`, `WalletPayment`).
- **`WalletManager`** — resolves a provider instance by name (config-driven).
- **`Exceptions\WalletException`** — carries an HTTP status and the upstream envelope through to the controller, which renders the standard error response.

### Adding a new provider

1. Create `app/Services/Wallet/MyProvider.php` implementing `WalletProvider`.
2. Map the upstream's response shape into the existing DTOs (`WalletUser`, `WalletTransactionPage`, etc.) — your callers don't have to know what the upstream looks like.
3. Add a config block in `config/wallet.php`:

   ```php
   'mywallet' => [
       'driver'   => 'mywallet',
       'base_url' => env('MYWALLET_BASE_URL'),
       // ... whatever your adapter needs
   ],
   ```

4. Register it in `WalletManager::resolve()`:

   ```php
   return match ($driver) {
       'fastpay'  => new FastPayProvider($this->app->make(HttpFactory::class), $config),
       'mywallet' => new MyProvider($this->app->make(HttpFactory::class), $config),
       default    => throw new WalletException("Driver [{$driver}] is not implemented yet.", 501),
   };
   ```

5. Whitelist the route segment in `routes/api.php`:

   ```php
   ->whereIn('provider', ['fastpay', 'fib', 'mywallet'])
   ```

That's it. The controller, DTOs, and tester UI work for free.

## Tester UI

The welcome page (`/`) is a self-contained Tailwind dashboard. It's intentionally a single Blade file — no build step required.

- **Sign In** — opens a modal that handles same-device-vs-OTP classification.
- **Basic Info** — fetches `/me` and renders profile + balances.
- **Transactions** — paginated list with sender/receiver, amount, date, color & icon from upstream.
- **Watch Inbox** — opens a modal that snapshots existing transaction IDs, then polls `/transactions?page=1` every 5s for 60s. Any new credit (optionally filtered by a target sender's number) shows up live with a green ✓ banner.
- **Copy to .env** — next to the Bearer Token field. Copies the token as a ready-to-paste env line, then alerts the developer to remove or guard the welcome page in production.

> **Production tip.** The welcome route is a *developer tester*, not a public-facing UI. In production, either delete the `/` route in `routes/web.php` or guard it:
>
> ```php
> Route::get('/', function () {
>     abort_unless(app()->environment('local'), 404);
>     return view('welcome');
> });
> ```

## Testing

```bash
composer test
```

This clears config and runs PHPUnit. (Test coverage is currently minimal — contributions welcome.)

## Roadmap

- [ ] FIB provider implementation
- [ ] OTP-completion endpoint (`POST /signin/verify-otp`)
- [ ] Webhook-based incoming-payment notifications (push, not poll)
- [ ] Bill-payment & top-up endpoints
- [ ] Full PHPUnit coverage with HTTP-mocked upstreams
- [ ] Docker Compose for one-command setup

## Contributing

PRs and issues welcome. Particularly useful contributions:

- **Captured request templates** for endpoints not yet implemented (sanitize tokens before sharing!)
- **New provider adapters** — FIB, Zain Cash, etc.
- **Bug reports** with the upstream `error.upstream` envelope attached

Please run `./vendor/bin/pint` before submitting code; the project follows Laravel-flavored PSR-12 via Pint defaults.

## Security

If you find a security issue, please **don't open a public issue**. Email the maintainer or use GitHub's [private vulnerability reporting](https://docs.github.com/en/code-security/security-advisories/guidance-on-reporting-and-writing/privately-reporting-a-security-vulnerability) so we can coordinate a fix before disclosure.

In particular, **do not commit real tokens, device IDs, signatures, or cookies**. The `.env` file is gitignored — keep it that way. Never paste real secrets into `.env.example`.

## License

[MIT](LICENSE) — do whatever you want, just don't sue us.

## Acknowledgements

- Built on the [Laravel](https://laravel.com) framework.
- Tested against real FastPay client network traffic. Thanks to the open-source community of curious developers documenting wallet APIs in the region.
