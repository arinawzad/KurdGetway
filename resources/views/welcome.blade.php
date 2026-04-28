<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Kurdgetway — Wallet Gateway Tester</title>

    <!-- Tailwind via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Inter font for a clean fintech look -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />

    <style>
        html, body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, monospace; }

        /* Animated gradient background */
        .bg-aurora {
            background: radial-gradient(1200px 600px at 10% -10%, rgba(56,189,248,0.18), transparent 60%),
                        radial-gradient(900px 500px at 110% 10%, rgba(168,85,247,0.18), transparent 55%),
                        radial-gradient(700px 400px at 50% 110%, rgba(34,197,94,0.12), transparent 60%),
                        #0b1020;
        }

        .glass {
            background: rgba(17, 24, 39, 0.55);
            border: 1px solid rgba(255,255,255,0.08);
            /* NOTE: backdrop-filter intentionally removed.
               Stacking it on ~7 elements + the modal backdrop made every
               modal open 500–1500ms. The slightly opaque background below
               gives the same depth without the GPU cost. */
        }

        /* Spinner */
        .spinner {
            border: 2px solid rgba(255,255,255,0.25);
            border-top-color: #fff;
            border-radius: 9999px;
            width: 16px; height: 16px;
            animation: spin 0.7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Modal animation */
        .modal-enter { animation: modalIn 180ms ease-out forwards; }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(8px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Pretty scrollbars in the JSON viewer */
        pre::-webkit-scrollbar { height: 8px; width: 8px; }
        pre::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 4px; }
    </style>
</head>
<body class="bg-aurora text-slate-100 min-h-full antialiased">

    {{-- ========================== HEADER ========================== --}}
    <header class="max-w-5xl mx-auto px-6 pt-10 pb-6 flex items-center">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-400 to-violet-500 flex items-center justify-center shadow-lg shadow-violet-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2zm12 8a2 2 0 110-4 2 2 0 010 4z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight">Kurdgetway</h1>
                <p class="text-xs text-slate-400">Wallet gateway · tester UI</p>
            </div>
        </div>
    </header>

    {{-- ========================== HERO ========================== --}}
    <section class="max-w-5xl mx-auto px-6 pt-4 pb-10">
        <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight">
            Test the wallet gateway
            <span class="bg-gradient-to-r from-sky-400 to-violet-400 bg-clip-text text-transparent">in one click.</span>
        </h2>
        <p class="mt-3 text-slate-400 max-w-2xl">
            Sign in with your FastPay credentials, or paste an existing Bearer token.
            Results open in a modal — exactly what you'd see from the upstream API.
        </p>
    </section>

    {{-- ========================== DEVICE BADGE ========================== --}}
    <section class="max-w-5xl mx-auto px-6 mb-4">
        <div class="glass rounded-xl px-4 py-3 flex items-center gap-3 flex-wrap">
            <div class="w-8 h-8 rounded-lg bg-violet-500/20 border border-violet-400/30 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-violet-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17a4 4 0 100-8 4 4 0 000 8zm0 0v2m0-12V5m6 6h2M3 11h2"/>
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-xs uppercase tracking-widest text-slate-400">Your device ID</div>
                <div class="text-sm font-mono text-slate-200 truncate" id="device-id-display">—</div>
                <div class="text-[11px] text-slate-500 mt-0.5">
                    Saved in this browser. Reuse it on next login to skip OTP.
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" id="btn-copy-device" title="Copy device ID"
                        class="px-2.5 py-1.5 text-xs rounded-lg bg-white/5 hover:bg-white/10 border border-white/10">
                    Copy
                </button>
                <button type="button" id="btn-regen-device" title="Generate a new device ID (will trigger OTP on next login)"
                        class="px-2.5 py-1.5 text-xs rounded-lg bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-200">
                    Regenerate
                </button>
            </div>
        </div>
    </section>

    {{-- ========================== CONFIG CARD ========================== --}}
    <section class="max-w-5xl mx-auto px-6">
        <div class="glass rounded-2xl p-6 sm:p-8 shadow-xl shadow-black/20">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                {{-- Provider --}}
                <div class="sm:col-span-1">
                    <label class="block text-xs font-medium text-slate-300 mb-2">Provider</label>
                    <select id="provider"
                            class="w-full bg-slate-900/60 border border-white/10 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sky-400/40">
                        <option value="fastpay">FastPay</option>
                        <option value="fib" disabled>FIB (coming soon)</option>
                    </select>
                </div>

                {{-- Token --}}
                <div class="sm:col-span-2">
                    <div class="flex items-center justify-between mb-2 gap-2">
                        <label class="block text-xs font-medium text-slate-300">
                            Bearer Token <span class="text-slate-500">(forwarded as <span class="font-mono">Authorization</span>)</span>
                        </label>
                        {{-- Dev convenience: one-click copy of the FASTPAY_TOKEN env line.
                             After paste-into-.env, the welcome tester is no longer needed in prod. --}}
                        <button type="button" id="btn-copy-token"
                                title="Copy as FASTPAY_TOKEN=... for your .env"
                                class="text-[11px] text-sky-300 hover:text-sky-200 inline-flex items-center gap-1 px-2 py-0.5 rounded hover:bg-sky-500/10 border border-sky-500/20">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            Copy to .env
                        </button>
                    </div>
                    <input id="token" type="password" autocomplete="off" spellcheck="false"
                           placeholder="Sign in below or paste an existing token…"
                           class="w-full bg-slate-900/60 border border-white/10 rounded-lg px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-sky-400/40" />
                </div>
            </div>

            <div class="mt-4 flex items-center gap-2">
                <input id="remember" type="checkbox" class="w-4 h-4 rounded border-white/20 bg-slate-900/60 text-sky-500 focus:ring-sky-400/40" />
                <label for="remember" class="text-xs text-slate-400 select-none">
                    Remember token in this browser (localStorage)
                </label>
            </div>
        </div>
    </section>

    {{-- ========================== ACTION BUTTONS ========================== --}}
    <section class="max-w-5xl mx-auto px-6 mt-8">
        <h3 class="text-sm uppercase tracking-widest text-slate-400 mb-3">Endpoints</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">

            {{-- Button: Sign In (NEW) --}}
            <button type="button"
                    id="btn-sign-in"
                    class="group relative text-left rounded-2xl p-5 bg-gradient-to-br from-fuchsia-500/20 to-pink-500/20 border border-white/10 hover:border-fuchsia-400/40 transition shadow-lg hover:shadow-fuchsia-500/10">
                <div class="flex items-center justify-between">
                    <div class="w-10 h-10 rounded-xl bg-fuchsia-500/20 border border-fuchsia-400/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-fuchsia-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="font-semibold text-white">Sign In</div>
                    <div class="text-xs text-slate-400 mt-0.5">Mint a fresh Bearer token</div>
                    <div class="mt-3 text-[11px] text-slate-500 font-mono truncate">POST /signin/check-same-device</div>
                </div>
            </button>

            {{-- Button 1: Basic Info --}}
            <button type="button"
                    id="btn-basic-info"
                    class="group relative text-left rounded-2xl p-5 bg-gradient-to-br from-sky-500/20 to-violet-500/20 border border-white/10 hover:border-sky-400/40 transition shadow-lg hover:shadow-sky-500/10">
                <div class="flex items-center justify-between">
                    <div class="w-10 h-10 rounded-xl bg-sky-500/20 border border-sky-400/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-sky-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <span class="btn-spinner hidden"><span class="spinner"></span></span>
                </div>
                <div class="mt-4">
                    <div class="font-semibold text-white">Basic Info</div>
                    <div class="text-xs text-slate-400 mt-0.5">Authenticated user &amp; balances</div>
                    <div class="mt-3 text-[11px] text-slate-500 font-mono truncate">GET /api/wallet/{provider}/me</div>
                </div>
            </button>

            {{-- Button: Transactions (NEW) --}}
            <button type="button"
                    id="btn-transactions"
                    class="group relative text-left rounded-2xl p-5 bg-gradient-to-br from-cyan-500/20 to-blue-500/20 border border-white/10 hover:border-cyan-400/40 transition shadow-lg hover:shadow-cyan-500/10">
                <div class="flex items-center justify-between">
                    <div class="w-10 h-10 rounded-xl bg-cyan-500/20 border border-cyan-400/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-cyan-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h7M17 12l4 4m0-4l-4 4"/>
                        </svg>
                    </div>
                    <span class="btn-spinner hidden"><span class="spinner"></span></span>
                </div>
                <div class="mt-4">
                    <div class="font-semibold text-white">Transactions</div>
                    <div class="text-xs text-slate-400 mt-0.5">Recent activity &amp; history</div>
                    <div class="mt-3 text-[11px] text-slate-500 font-mono truncate">GET /api/wallet/{provider}/transactions</div>
                </div>
            </button>

            {{-- Button: Watch Inbox (NEW) --}}
            {{-- No sending. Pure receive-watcher: polls /transactions for any
                 new incoming credit to the authenticated user's account. --}}
            <button type="button"
                    id="btn-pay-test"
                    class="group relative text-left rounded-2xl p-5 bg-gradient-to-br from-lime-500/20 to-emerald-500/20 border border-white/10 hover:border-lime-400/40 transition shadow-lg hover:shadow-lime-500/10">
                <div class="flex items-center justify-between">
                    <div class="w-10 h-10 rounded-xl bg-lime-500/20 border border-lime-400/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-lime-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </div>
                    <span class="btn-spinner hidden"><span class="spinner"></span></span>
                </div>
                <div class="mt-4">
                    <div class="font-semibold text-white">Watch Inbox</div>
                    <div class="text-xs text-slate-400 mt-0.5">Detect incoming money to your number</div>
                    <div class="mt-3 text-[11px] text-slate-500 font-mono truncate">GET /api/wallet/{provider}/transactions</div>
                </div>
            </button>

            {{-- Button 2: API Info --}}
            <button type="button"
                    id="btn-api-info"
                    class="group relative text-left rounded-2xl p-5 bg-gradient-to-br from-emerald-500/20 to-teal-500/20 border border-white/10 hover:border-emerald-400/40 transition shadow-lg hover:shadow-emerald-500/10">
                <div class="flex items-center justify-between">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/>
                        </svg>
                    </div>
                    <span class="btn-spinner hidden"><span class="spinner"></span></span>
                </div>
                <div class="mt-4">
                    <div class="font-semibold text-white">API Info</div>
                    <div class="text-xs text-slate-400 mt-0.5">Service name &amp; version</div>
                    <div class="mt-3 text-[11px] text-slate-500 font-mono truncate">GET /api/</div>
                </div>
            </button>
        </div>
    </section>

    <footer class="max-w-5xl mx-auto px-6 py-10 mt-10 text-xs text-slate-500 flex flex-col sm:flex-row items-start sm:items-center gap-2 justify-between">
        <div>© {{ date('Y') }} Kurdgetway · Internal tester</div>
        <div class="font-mono">Laravel {{ app()->version() }} · PHP {{ PHP_VERSION }}</div>
    </footer>

    {{-- ========================== SIGN-IN MODAL ========================== --}}
    <div id="signin-modal" class="fixed inset-0 z-[60] hidden">
        <div id="signin-backdrop" class="absolute inset-0 bg-black/75"></div>

        <div class="absolute inset-0 flex items-center justify-center p-4 sm:p-6">
            <div class="modal-enter w-full max-w-md glass rounded-2xl shadow-2xl shadow-black/40 overflow-hidden">

                <div class="flex items-start justify-between px-6 py-4 border-b border-white/5">
                    <div class="min-w-0">
                        <h4 class="text-base font-semibold">Sign in to FastPay</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Same device → token. New device → OTP.</p>
                    </div>
                    <button type="button" id="signin-close"
                            class="text-slate-400 hover:text-white p-1 rounded hover:bg-white/5">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5 space-y-4">

                    {{-- Device pill inside the modal so users can see what they're authenticating as --}}
                    <div class="rounded-lg border border-violet-400/20 bg-violet-500/10 px-3 py-2 text-[11px] text-violet-200">
                        <div class="uppercase tracking-widest text-violet-300/80 mb-0.5">Device ID being used</div>
                        <div class="font-mono break-all" id="signin-device-display">—</div>
                    </div>

                    {{-- Error banner --}}
                    <div id="signin-error" class="hidden px-3 py-2 rounded-lg bg-rose-500/10 border border-rose-500/30 text-sm text-rose-200">
                        <span id="signin-error-message"></span>
                    </div>

                    {{-- OTP-required banner --}}
                    <div id="signin-otp" class="hidden px-3 py-2 rounded-lg bg-amber-500/10 border border-amber-500/30 text-sm text-amber-200">
                        <div class="font-medium">OTP required</div>
                        <div class="text-amber-200/80 mt-0.5" id="signin-otp-message">
                            FastPay didn't recognize this device. An OTP has likely been sent to your phone.
                        </div>
                        <div class="text-amber-200/60 mt-1 text-[11px] font-mono" id="signin-otp-session"></div>
                    </div>

                    {{-- OTP code input (visible only during the OTP step). 6 individual
                         boxes give a nice mobile-keyboard UX; values are concatenated
                         on submit. Paste support is wired up in JS. --}}
                    <div id="signin-otp-input-wrap" class="hidden space-y-3">
                        <div class="text-xs text-slate-300">
                            Enter the 6-digit code sent to
                            <span id="signin-otp-target" class="font-mono text-slate-100">your phone</span>.
                        </div>
                        <div class="flex justify-between gap-2" id="signin-otp-boxes">
                            <input data-otp-index="0" inputmode="numeric" autocomplete="one-time-code" maxlength="1" pattern="\d"
                                   class="otp-box w-full text-center text-lg font-mono bg-slate-900/60 border border-white/10 rounded-lg py-2.5 focus:outline-none focus:ring-2 focus:ring-fuchsia-400/40" />
                            <input data-otp-index="1" inputmode="numeric" autocomplete="one-time-code" maxlength="1" pattern="\d"
                                   class="otp-box w-full text-center text-lg font-mono bg-slate-900/60 border border-white/10 rounded-lg py-2.5 focus:outline-none focus:ring-2 focus:ring-fuchsia-400/40" />
                            <input data-otp-index="2" inputmode="numeric" autocomplete="one-time-code" maxlength="1" pattern="\d"
                                   class="otp-box w-full text-center text-lg font-mono bg-slate-900/60 border border-white/10 rounded-lg py-2.5 focus:outline-none focus:ring-2 focus:ring-fuchsia-400/40" />
                            <input data-otp-index="3" inputmode="numeric" autocomplete="one-time-code" maxlength="1" pattern="\d"
                                   class="otp-box w-full text-center text-lg font-mono bg-slate-900/60 border border-white/10 rounded-lg py-2.5 focus:outline-none focus:ring-2 focus:ring-fuchsia-400/40" />
                            <input data-otp-index="4" inputmode="numeric" autocomplete="one-time-code" maxlength="1" pattern="\d"
                                   class="otp-box w-full text-center text-lg font-mono bg-slate-900/60 border border-white/10 rounded-lg py-2.5 focus:outline-none focus:ring-2 focus:ring-fuchsia-400/40" />
                            <input data-otp-index="5" inputmode="numeric" autocomplete="one-time-code" maxlength="1" pattern="\d"
                                   class="otp-box w-full text-center text-lg font-mono bg-slate-900/60 border border-white/10 rounded-lg py-2.5 focus:outline-none focus:ring-2 focus:ring-fuchsia-400/40" />
                        </div>
                        <div class="flex items-center justify-between text-[11px]">
                            <button type="button" id="signin-otp-back"
                                    class="text-slate-400 hover:text-slate-200 inline-flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                                </svg>
                                Use different account
                            </button>
                            <button type="button" id="signin-otp-resend"
                                    class="text-fuchsia-300 hover:text-fuchsia-200">
                                Resend code
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Mobile number</label>
                        <input id="signin-mobile" type="tel" autocomplete="tel" spellcheck="false"
                               placeholder="+964750xxxxxxx"
                               class="signin-credentials-field w-full bg-slate-900/60 border border-white/10 rounded-lg px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-fuchsia-400/40" />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">Password</label>
                        <input id="signin-password" type="password" autocomplete="current-password" spellcheck="false"
                               class="signin-credentials-field w-full bg-slate-900/60 border border-white/10 rounded-lg px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-fuchsia-400/40" />
                    </div>

                    <div class="flex items-center gap-2 pt-1 signin-credentials-field">
                        <input id="signin-remember" type="checkbox" checked
                               class="w-4 h-4 rounded border-white/20 bg-slate-900/60 text-fuchsia-500 focus:ring-fuchsia-400/40" />
                        <label for="signin-remember" class="text-xs text-slate-400 select-none">
                            Remember mobile number on this browser
                        </label>
                    </div>

                    {{-- Escape hatch for the FastPay 5-minute rate limit:
                         if you already received an OTP from a prior attempt,
                         skip /signin entirely and go straight to entering the
                         code. The credentials still need to be filled in so
                         /verify-otp gets the same envelope FastPay expects. --}}
                    <div class="signin-credentials-field text-center pt-1">
                        <button type="button" id="signin-have-code"
                                class="text-[11px] text-fuchsia-300 hover:text-fuchsia-200">
                            Already received a code? Enter it instead →
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 px-6 py-3 border-t border-white/5 bg-slate-950/30">
                    <button type="button" id="signin-cancel"
                            class="px-3 py-1.5 text-sm rounded-lg bg-white/5 hover:bg-white/10 border border-white/10">
                        Cancel
                    </button>
                    <button type="button" id="signin-submit"
                            class="inline-flex items-center gap-2 px-4 py-1.5 text-sm rounded-lg bg-gradient-to-br from-fuchsia-500 to-pink-500 hover:from-fuchsia-400 hover:to-pink-400 text-white font-medium shadow-lg shadow-fuchsia-500/20">
                        <span class="signin-spinner hidden"><span class="spinner"></span></span>
                        <span id="signin-submit-label">Sign In</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================== WATCH INBOX MODAL ========================== --}}
    {{-- Pure receive-watcher. No sending. Auto-fetches the user's mobile from
         /me, then polls /transactions every 5s for 60s, looking for any new
         credit. First match → success. --}}
    <div id="pay-modal" class="fixed inset-0 z-[60] hidden">
        <div id="pay-backdrop" class="absolute inset-0 bg-black/75"></div>

        <div class="absolute inset-0 flex items-center justify-center p-4 sm:p-6">
            <div class="modal-enter w-full max-w-md glass rounded-2xl shadow-2xl shadow-black/40 overflow-hidden">

                <div class="flex items-start justify-between px-6 py-4 border-b border-white/5">
                    <div class="min-w-0">
                        <h4 class="text-base font-semibold">Watch Inbox</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Detect incoming money to your number.</p>
                    </div>
                    <button type="button" id="pay-close"
                            class="text-slate-400 hover:text-white p-1 rounded hover:bg-white/5">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5 space-y-4">

                    {{-- How it works --}}
                    <div class="rounded-lg border border-amber-400/20 bg-amber-500/10 px-3 py-2 text-[11px] text-amber-200">
                        <div class="font-medium text-amber-100">How this works</div>
                        Watches your transaction history for <strong>1 minute</strong>, polling every <strong>5 seconds</strong> for any new incoming credit. Trigger a payment to your number from another phone or wallet during the watch window.
                    </div>

                    {{-- Auto-fetched user mobile --}}
                    <div class="rounded-lg border border-violet-400/20 bg-violet-500/10 px-3 py-2 text-[11px] text-violet-200">
                        <div class="uppercase tracking-widest text-violet-300/80 mb-0.5">Your number (receiver)</div>
                        <div class="font-mono break-all" id="pay-my-number">—</div>
                    </div>

                    {{-- Optional sender filter --}}
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1.5">
                            Filter by sender <span class="text-slate-500">(optional)</span>
                        </label>
                        <input id="pay-target" type="tel" autocomplete="off" spellcheck="false"
                               placeholder="+9647XXXXXXXXX  — leave empty for any sender"
                               class="w-full bg-slate-900/60 border border-white/10 rounded-lg px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-lime-400/40" />
                        <div class="text-[11px] text-slate-500 mt-1">
                            If set, only incoming credits whose sender mobile matches this number will be counted. Matching is digit-only on the last 9 digits, so country-code formatting is forgiving.
                        </div>
                    </div>

                    {{-- Error / status banner --}}
                    <div id="pay-error" class="hidden px-3 py-2 rounded-lg bg-rose-500/10 border border-rose-500/30 text-sm text-rose-200">
                        <span id="pay-error-message"></span>
                    </div>

                    {{-- Watching banner --}}
                    <div id="pay-verify" class="hidden px-3 py-2 rounded-lg bg-cyan-500/10 border border-cyan-500/30 text-sm text-cyan-100">
                        <div class="flex items-center gap-2">
                            <span class="spinner"></span>
                            <span id="pay-verify-message">Watching for incoming money…</span>
                        </div>
                        <div class="text-[11px] text-cyan-200/70 mt-1" id="pay-verify-detail"></div>
                    </div>

                    {{-- Success banner --}}
                    <div id="pay-success" class="hidden px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-sm text-emerald-100">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span id="pay-success-message">Money received.</span>
                        </div>
                        <div class="text-[11px] text-emerald-200/70 mt-1 font-mono" id="pay-success-detail"></div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 px-6 py-3 border-t border-white/5 bg-slate-950/30">
                    <button type="button" id="pay-cancel"
                            class="px-3 py-1.5 text-sm rounded-lg bg-white/5 hover:bg-white/10 border border-white/10">
                        Close
                    </button>
                    <button type="button" id="pay-submit"
                            class="inline-flex items-center gap-2 px-4 py-1.5 text-sm rounded-lg bg-gradient-to-br from-lime-500 to-emerald-500 hover:from-lime-400 hover:to-emerald-400 text-white font-medium shadow-lg shadow-lime-500/20 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="pay-spinner hidden"><span class="spinner"></span></span>
                        <span id="pay-submit-label">Restart Watch</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================== RESULT MODAL ========================== --}}
    <div id="modal" class="fixed inset-0 z-50 hidden">
        <div id="modal-backdrop" class="absolute inset-0 bg-black/75"></div>

        <div class="absolute inset-0 flex items-center justify-center p-4 sm:p-6">
            <div class="modal-enter w-full max-w-2xl glass rounded-2xl shadow-2xl shadow-black/40 overflow-hidden">

                <div class="flex items-start justify-between px-6 py-4 border-b border-white/5">
                    <div class="flex items-center gap-3 min-w-0">
                        <span id="modal-status-dot" class="w-2.5 h-2.5 rounded-full bg-slate-500 shrink-0"></span>
                        <div class="min-w-0">
                            <h4 id="modal-title" class="text-base font-semibold truncate">Result</h4>
                            <p id="modal-subtitle" class="text-xs text-slate-400 font-mono truncate">—</p>
                        </div>
                    </div>
                    <button type="button" id="modal-close"
                            class="text-slate-400 hover:text-white p-1 rounded hover:bg-white/5">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5 max-h-[65vh] overflow-y-auto">

                    <div id="modal-error" class="hidden mb-4 px-4 py-3 rounded-lg bg-rose-500/10 border border-rose-500/30 text-sm text-rose-200">
                        <div class="font-medium" id="modal-error-title">Something went wrong</div>
                        <div class="text-rose-300/80 mt-0.5" id="modal-error-message"></div>
                    </div>

                    <div id="modal-loading" class="hidden flex items-center gap-3 text-slate-300">
                        <span class="spinner"></span> Loading…
                    </div>

                    <div id="modal-user" class="hidden">
                        <div class="flex items-center gap-4">
                            <img id="modal-user-avatar" src="" alt=""
                                 class="w-14 h-14 rounded-full bg-slate-700 object-cover border border-white/10" />
                            <div class="min-w-0">
                                <div id="modal-user-name" class="text-lg font-semibold truncate">—</div>
                                <div id="modal-user-meta" class="text-xs text-slate-400 truncate font-mono">—</div>
                            </div>
                        </div>

                        <div class="mt-5">
                            <div class="text-xs uppercase tracking-widest text-slate-400 mb-2">Balances</div>
                            <div id="modal-user-balances" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
                        </div>
                    </div>

                    {{-- ===== Transactions panel ===== --}}
                    <div id="modal-transactions" class="hidden">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div class="text-xs uppercase tracking-widest text-slate-400" id="modal-tx-page-info">—</div>
                            <div class="flex items-center gap-1">
                                <button type="button" id="modal-tx-prev"
                                        class="px-2.5 py-1 text-xs rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                                    </svg>
                                    Prev
                                </button>
                                <button type="button" id="modal-tx-next"
                                        class="px-2.5 py-1 text-xs rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 inline-flex items-center gap-1">
                                    Next
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div id="modal-tx-list" class="space-y-2"></div>
                    </div>

                    <div id="modal-raw-wrap" class="hidden mt-5">
                        <button type="button" id="toggle-raw"
                                class="text-xs text-slate-400 hover:text-white inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                            <span id="toggle-raw-label">Show raw JSON</span>
                        </button>
                        <pre id="modal-raw"
                             class="hidden mt-2 text-[12px] bg-slate-950/70 border border-white/5 rounded-lg p-4 overflow-auto font-mono text-slate-200"></pre>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 px-6 py-3 border-t border-white/5 bg-slate-950/30">
                    <button type="button" id="modal-close-2"
                            class="px-3 py-1.5 text-sm rounded-lg bg-white/5 hover:bg-white/10 border border-white/10">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================== TOAST ========================== --}}
    <div id="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[70] hidden">
        <div class="glass rounded-full px-4 py-2 text-sm shadow-xl shadow-black/40 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            <span id="toast-message">—</span>
        </div>
    </div>

    {{-- ========================== JS ========================== --}}
    <script>
    (function () {
        // ----------------- storage keys -----------------
        const STORAGE_KEY  = 'kurdgetway:tester';   // token + advanced headers
        const DEVICE_KEY   = 'kurdgetway:device_id'; // per-browser UUID
        const MOBILE_KEY   = 'kurdgetway:mobile';

        // ----------------- element refs -----------------
        const els = {
            // config card
            provider:     document.getElementById('provider'),
            token:        document.getElementById('token'),
            btnCopyToken: document.getElementById('btn-copy-token'),
            remember:     document.getElementById('remember'),

            // device badge
            deviceDisplay: document.getElementById('device-id-display'),
            btnCopyDevice: document.getElementById('btn-copy-device'),
            btnRegenDevice:document.getElementById('btn-regen-device'),

            // action buttons
            btnSignIn:    document.getElementById('btn-sign-in'),
            btnBasicInfo: document.getElementById('btn-basic-info'),
            btnTxs:       document.getElementById('btn-transactions'),
            btnPayTest:   document.getElementById('btn-pay-test'),
            btnApiInfo:   document.getElementById('btn-api-info'),

            // pay-test modal
            payModal:        document.getElementById('pay-modal'),
            payBackdrop:     document.getElementById('pay-backdrop'),
            payClose:        document.getElementById('pay-close'),
            payCancel:       document.getElementById('pay-cancel'),
            paySubmit:       document.getElementById('pay-submit'),
            paySubmitLabel:  document.getElementById('pay-submit-label'),
            payMyNumber:     document.getElementById('pay-my-number'),
            payTarget:       document.getElementById('pay-target'),
            payError:        document.getElementById('pay-error'),
            payErrorMsg:     document.getElementById('pay-error-message'),
            payVerify:       document.getElementById('pay-verify'),
            payVerifyMsg:    document.getElementById('pay-verify-message'),
            payVerifyDetail: document.getElementById('pay-verify-detail'),
            paySuccess:      document.getElementById('pay-success'),
            paySuccessMsg:   document.getElementById('pay-success-message'),
            paySuccessDetail:document.getElementById('pay-success-detail'),

            // sign-in modal
            signinModal:    document.getElementById('signin-modal'),
            signinBackdrop: document.getElementById('signin-backdrop'),
            signinClose:    document.getElementById('signin-close'),
            signinCancel:   document.getElementById('signin-cancel'),
            signinSubmit:   document.getElementById('signin-submit'),
            signinSubmitLabel: document.getElementById('signin-submit-label'),
            signinDevice:   document.getElementById('signin-device-display'),
            signinError:    document.getElementById('signin-error'),
            signinErrorMsg: document.getElementById('signin-error-message'),
            signinOtp:      document.getElementById('signin-otp'),
            signinOtpMsg:   document.getElementById('signin-otp-message'),
            signinOtpSess:  document.getElementById('signin-otp-session'),
            signinMobile:   document.getElementById('signin-mobile'),
            signinPassword: document.getElementById('signin-password'),
            signinRemember: document.getElementById('signin-remember'),
            signinCredFields:    document.querySelectorAll('.signin-credentials-field'),
            signinOtpInputWrap:  document.getElementById('signin-otp-input-wrap'),
            signinOtpTarget:     document.getElementById('signin-otp-target'),
            signinOtpBoxes:      document.querySelectorAll('#signin-otp-boxes input'),
            signinOtpBack:       document.getElementById('signin-otp-back'),
            signinOtpResend:     document.getElementById('signin-otp-resend'),
            signinHaveCode:      document.getElementById('signin-have-code'),

            // result modal
            modal:        document.getElementById('modal'),
            backdrop:     document.getElementById('modal-backdrop'),
            close:        document.getElementById('modal-close'),
            close2:       document.getElementById('modal-close-2'),
            statusDot:    document.getElementById('modal-status-dot'),
            title:        document.getElementById('modal-title'),
            subtitle:     document.getElementById('modal-subtitle'),
            loading:      document.getElementById('modal-loading'),
            error:        document.getElementById('modal-error'),
            errorTitle:   document.getElementById('modal-error-title'),
            errorMsg:     document.getElementById('modal-error-message'),
            userBlock:    document.getElementById('modal-user'),
            userAvatar:   document.getElementById('modal-user-avatar'),
            userName:     document.getElementById('modal-user-name'),
            userMeta:     document.getElementById('modal-user-meta'),
            userBalances: document.getElementById('modal-user-balances'),
            txsBlock:     document.getElementById('modal-transactions'),
            txsList:      document.getElementById('modal-tx-list'),
            txsPageInfo:  document.getElementById('modal-tx-page-info'),
            txsPrev:      document.getElementById('modal-tx-prev'),
            txsNext:      document.getElementById('modal-tx-next'),
            rawWrap:      document.getElementById('modal-raw-wrap'),
            raw:          document.getElementById('modal-raw'),
            toggleRaw:    document.getElementById('toggle-raw'),
            toggleLabel:  document.getElementById('toggle-raw-label'),

            // toast
            toast:    document.getElementById('toast'),
            toastMsg: document.getElementById('toast-message'),
        };

        // ----------------- UUID v4 -----------------
        function uuidv4() {
            if (window.crypto && typeof crypto.randomUUID === 'function') {
                return crypto.randomUUID();
            }
            // RFC4122 v4 fallback
            const b = new Uint8Array(16);
            (window.crypto || window.msCrypto).getRandomValues(b);
            b[6] = (b[6] & 0x0f) | 0x40;
            b[8] = (b[8] & 0x3f) | 0x80;
            const h = [...b].map(x => x.toString(16).padStart(2, '0'));
            return `${h.slice(0,4).join('')}-${h.slice(4,6).join('')}-${h.slice(6,8).join('')}-${h.slice(8,10).join('')}-${h.slice(10,16).join('')}`.toUpperCase();
        }

        // ----------------- device ID lifecycle -----------------
        // Same UUID across logins → upstream "same-device" path → token returned.
        // Regenerate → upstream sends OTP for the new device.
        function getOrCreateDeviceId() {
            let id = localStorage.getItem(DEVICE_KEY);
            if (!id) {
                id = uuidv4();
                localStorage.setItem(DEVICE_KEY, id);
            }
            return id;
        }
        function regenerateDeviceId() {
            const id = uuidv4();
            localStorage.setItem(DEVICE_KEY, id);
            return id;
        }
        function paintDeviceId() {
            const id = getOrCreateDeviceId();
            els.deviceDisplay.textContent = id;
            els.signinDevice.textContent  = id;
        }

        els.btnRegenDevice.addEventListener('click', () => {
            if (!confirm('Regenerate device ID? Your next sign-in will require an OTP from FastPay.')) return;
            regenerateDeviceId();
            paintDeviceId();
            toast('New device ID generated. Next sign-in will need OTP.');
        });
        els.btnCopyDevice.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(getOrCreateDeviceId());
                toast('Device ID copied');
            } catch {
                toast('Could not copy');
            }
        });

        // ----------------- copy Bearer token to .env -----------------
        // Dev workflow: sign in via the UI → copy token → paste into .env as
        // FASTPAY_TOKEN=... → the welcome tester UI is no longer needed in
        // production. The post-copy alert nudges the dev to do exactly that.
        els.btnCopyToken.addEventListener('click', async () => {
            const token = els.token.value.trim();
            if (!token) {
                alert('No token to copy yet. Sign in below, or paste an existing Bearer token first.');
                return;
            }
            const envLine = 'FASTPAY_TOKEN=' + token;
            try {
                await navigator.clipboard.writeText(envLine);
                toast('Copied FASTPAY_TOKEN=…');
                alert(
                    'Token copied to clipboard as:\n\n' +
                    '  FASTPAY_TOKEN=...\n\n' +
                    'Paste it into your .env file, then run:\n' +
                    '  php artisan config:clear\n\n' +
                    'IMPORTANT: After configuring, remove or protect this welcome tester page in production. ' +
                    'The recommended way is to delete the `/` route in routes/web.php, or wrap it in an ' +
                    'environment check (`if (app()->environment("local")) { ... }`).'
                );
            } catch (e) {
                alert('Could not copy to clipboard: ' + (e.message || e) + '\n\nManual copy:\n' + envLine);
            }
        });

        // ----------------- localStorage persistence (config card) -----------------
        try {
            const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
            if (saved) {
                els.provider.value     = saved.provider     ?? 'fastpay';
                els.token.value        = saved.token        ?? '';
                els.remember.checked   = true;
            }
        } catch (_) {}

        // mobile number
        els.signinMobile.value = localStorage.getItem(MOBILE_KEY) || '';

        function persistConfigMaybe() {
            if (!els.remember.checked) {
                localStorage.removeItem(STORAGE_KEY);
                return;
            }
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                provider: els.provider.value,
                token:    els.token.value,
            }));
        }
        els.remember.addEventListener('change', persistConfigMaybe);
        ['token','provider'].forEach(k =>
            els[k].addEventListener('change', persistConfigMaybe));

        // ----------------- toast -----------------
        let toastTimer = null;
        function toast(msg) {
            els.toastMsg.textContent = msg;
            els.toast.classList.remove('hidden');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => els.toast.classList.add('hidden'), 2200);
        }

        // ----------------- result-modal helpers -----------------
        function openModal({ title, subtitle, status }) {
            els.title.textContent    = title    || 'Result';
            els.subtitle.textContent = subtitle || '—';
            setStatus(status);
            els.modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function setStatus(status) {
            els.statusDot.className = 'w-2.5 h-2.5 rounded-full shrink-0 ' + ({
                loading: 'bg-amber-400 animate-pulse',
                ok:      'bg-emerald-400',
                err:     'bg-rose-500',
            }[status] || 'bg-slate-500');
        }
        function closeModal() {
            els.modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
        function resetModalBody() {
            els.error.classList.add('hidden');
            els.loading.classList.add('hidden');
            els.userBlock.classList.add('hidden');
            els.txsBlock.classList.add('hidden');
            els.rawWrap.classList.add('hidden');
            els.raw.classList.add('hidden');
            els.toggleLabel.textContent = 'Show raw JSON';
            els.userBalances.innerHTML = '';
            els.txsList.innerHTML = '';
        }
        function showError(title, message) {
            els.error.classList.remove('hidden');
            els.errorTitle.textContent = title || 'Error';
            els.errorMsg.textContent   = message || '';
        }
        function showRaw(obj) {
            els.rawWrap.classList.remove('hidden');
            els.raw.textContent = JSON.stringify(obj, null, 2);
        }
        els.toggleRaw.addEventListener('click', () => {
            const isHidden = els.raw.classList.contains('hidden');
            els.raw.classList.toggle('hidden');
            els.toggleLabel.textContent = isHidden ? 'Hide raw JSON' : 'Show raw JSON';
        });
        els.close.addEventListener('click', closeModal);
        els.close2.addEventListener('click', closeModal);
        els.backdrop.addEventListener('click', closeModal);

        // ----------------- per-button spinner -----------------
        function setBtnLoading(btn, loading) {
            btn.disabled = loading;
            btn.classList.toggle('opacity-60', loading);
            btn.classList.toggle('cursor-not-allowed', loading);
            const sp = btn.querySelector('.btn-spinner');
            if (sp) sp.classList.toggle('hidden', !loading);
        }

        // ----------------- pretty user renderer -----------------
        function renderUser(user) {
            els.userBlock.classList.remove('hidden');
            // Cache the user's mobile so Pay Test can prefill it without an extra /me round-trip.
            if (user?.mobile_number) cachedUserMobile = user.mobile_number;
            const fullName = [user.first_name, user.last_name].filter(Boolean).join(' ') || '(no name)';
            els.userName.textContent = fullName;

            const metaParts = [];
            if (user.identifier)    metaParts.push('ID: ' + user.identifier);
            if (user.mobile_number) metaParts.push(user.mobile_number);
            if (user.email)         metaParts.push(user.email);
            els.userMeta.textContent = metaParts.join(' · ') || '—';

            if (user.profile_thumbnail) {
                els.userAvatar.src = user.profile_thumbnail;
                els.userAvatar.classList.remove('hidden');
            } else {
                els.userAvatar.removeAttribute('src');
                els.userAvatar.classList.add('hidden');
            }

            const balances = Array.isArray(user.balances) ? user.balances : [];
            els.userBalances.innerHTML = balances.length
                ? balances.map(b => `
                    <div class="rounded-xl border border-white/10 bg-slate-900/40 p-3">
                        <div class="text-[11px] uppercase tracking-wider text-slate-500">${escapeHtml(b.account_type || 'Account')}</div>
                        <div class="mt-1 flex items-baseline gap-1">
                            <span class="text-xl font-semibold">${escapeHtml(b.amount ?? '0')}</span>
                            <span class="text-xs text-slate-400 font-mono">${escapeHtml(b.currency || '')}</span>
                        </div>
                        <div class="mt-1 text-[11px] text-slate-500 font-mono truncate">${escapeHtml(b.account_number || '')}</div>
                    </div>
                `).join('')
                : `<div class="text-xs text-slate-400 italic">No balances returned.</div>`;
        }

        function escapeHtml(s) {
            return String(s ?? '').replace(/[&<>"']/g, c => ({
                '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
            }[c]));
        }

        // ----------------- pretty transactions renderer -----------------
        function renderTransactions(data) {
            els.txsBlock.classList.remove('hidden');

            const txs        = Array.isArray(data?.transactions) ? data.transactions : [];
            const page       = Number(data?.page ?? 1);
            const perPage    = Number(data?.per_page ?? txs.length) || txs.length;
            const total      = Number(data?.total ?? txs.length) || txs.length;
            const totalPages = perPage > 0 ? Math.max(1, Math.ceil(total / perPage)) : 1;
            const hasNext    = !!data?.has_next_page;

            els.txsPageInfo.textContent =
                `Page ${page.toLocaleString()} of ${totalPages.toLocaleString()} · ${total.toLocaleString()} total`;

            const setDisabled = (btn, disabled) => {
                btn.disabled = disabled;
                btn.classList.toggle('opacity-40', disabled);
                btn.classList.toggle('cursor-not-allowed', disabled);
            };
            setDisabled(els.txsPrev, page <= 1);
            setDisabled(els.txsNext, !hasNext);

            if (txs.length === 0) {
                els.txsList.innerHTML =
                    '<div class="text-xs text-slate-400 italic px-1">No transactions on this page.</div>';
                return;
            }

            els.txsList.innerHTML = txs.map(t => {
                const isCredit     = t.direction === 'credit';
                const sign         = isCredit ? '+' : '−';
                const amountClass  = isCredit ? 'text-emerald-400' : 'text-rose-400';
                const counterparty = isCredit ? t.source : t.destination;
                const cpName       = counterparty?.name || null;
                const cpMobile     = counterparty?.mobile_number || null;
                const accent       = t.color || '#64748b';

                return `
                    <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-900/40 border border-white/5 hover:border-white/10 transition">
                        <div class="w-9 h-9 rounded-full shrink-0 flex items-center justify-center"
                             style="background-color:${escapeHtml(accent)}1f;border:1px solid ${escapeHtml(accent)}55">
                            ${t.icon
                                ? `<img src="${escapeHtml(t.icon)}" alt="" class="w-5 h-5 object-contain" onerror="this.style.display='none'">`
                                : `<span class="text-xs font-semibold" style="color:${escapeHtml(accent)}">${isCredit ? '+' : '−'}</span>`}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline justify-between gap-3">
                                <div class="font-medium text-sm truncate">${escapeHtml(t.title || t.transaction_type || '—')}</div>
                                <div class="text-sm font-semibold whitespace-nowrap ${amountClass}">
                                    ${sign}${escapeHtml(t.amount)}
                                    <span class="text-[10px] font-mono text-slate-400 ml-0.5">${escapeHtml(t.currency || '')}</span>
                                </div>
                            </div>
                            <div class="text-[11px] text-slate-400 truncate mt-0.5">
                                ${escapeHtml(t.transaction_type || '')}${cpName ? ' · ' + escapeHtml(cpName) : ''}
                            </div>
                            <div class="flex items-center justify-between gap-3 mt-1">
                                <div class="text-[10px] text-slate-500 font-mono truncate">${escapeHtml(t.transaction_id || '')}${cpMobile ? ' · ' + escapeHtml(cpMobile) : ''}</div>
                                <div class="text-[10px] text-slate-500 whitespace-nowrap">${escapeHtml(t.created_at || '')}</div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // ----------------- generic JSON fetcher (drives the result modal) -----------------
        async function callEndpoint({ btn, title, subtitle, url, method = 'GET', headers = {}, body = null, render }) {
            persistConfigMaybe();
            resetModalBody();
            openModal({ title, subtitle, status: 'loading' });
            els.loading.classList.remove('hidden');
            if (btn) setBtnLoading(btn, true);

            let res, parsed;
            try {
                const init = { method, headers: { 'Accept': 'application/json', ...headers } };
                if (body !== null) init.body = body;
                res = await fetch(url, init);
                const text = await res.text();
                try { parsed = text ? JSON.parse(text) : null; } catch { parsed = { raw: text }; }
            } catch (e) {
                els.loading.classList.add('hidden');
                if (btn) setBtnLoading(btn, false);
                setStatus('err');
                showError('Network error', e.message || String(e));
                return null;
            }

            els.loading.classList.add('hidden');
            if (btn) setBtnLoading(btn, false);
            els.subtitle.textContent = `${url} · ${res.status}`;

            const ok = res.ok && (parsed?.ok !== false);
            setStatus(ok ? 'ok' : 'err');

            if (!ok) {
                const msg = parsed?.error?.message
                         || parsed?.message
                         || (typeof parsed === 'string' ? parsed : `HTTP ${res.status}`);
                showError(`Request failed (${res.status})`, msg);
            } else if (render) {
                try { render(parsed); } catch (e) { showError('Render error', e.message); }
            }

            showRaw(parsed);
            return { ok, status: res.status, parsed };
        }

        // ============================================================
        // SIGN-IN flow
        // ============================================================
        // Two-step UX:
        //   step = 'credentials' → user types mobile + password → POST /signin
        //     ⤷ if same device  → token returned, we're done.
        //     ⤷ if new device   → backend returns otp_required (+ session id)
        //                          → we switch to step 'otp'.
        //   step = 'otp'         → user types 6 digits → POST /verify-otp
        //                          → final token returned.
        let signinStep              = 'credentials';   // 'credentials' | 'otp'
        let signinPendingOtpSession = null;            // temp token from /signin
        let signinPendingMobile     = null;            // remembered for /verify-otp
        let signinPendingPassword   = null;            // remembered for /verify-otp

        function setSignInStep(step) {
            signinStep = step;
            const isOtp = step === 'otp';

            // Toggle credentials fields vs OTP box wrap.
            els.signinCredFields.forEach(el => el.classList.toggle('hidden', isOtp));
            els.signinOtpInputWrap.classList.toggle('hidden', !isOtp);

            // Submit button label tracks the step.
            els.signinSubmitLabel.textContent = isOtp ? 'Verify' : 'Sign In';

            if (isOtp) {
                // Mask mobile for the "sent to" line: keep last 4 digits.
                const mob = signinPendingMobile || '';
                const masked = mob.length > 4
                    ? mob.slice(0, -4).replace(/\d/g, '•') + mob.slice(-4)
                    : mob;
                els.signinOtpTarget.textContent = masked || 'your phone';

                // Reset boxes and focus first.
                els.signinOtpBoxes.forEach(b => { b.value = ''; });
                setTimeout(() => els.signinOtpBoxes[0]?.focus(), 50);
            } else {
                // Returning to credentials: clear pending state.
                signinPendingOtpSession = null;
            }
        }

        function readOtpCode() {
            return Array.from(els.signinOtpBoxes).map(b => b.value).join('');
        }

        // ----- 6-box OTP input UX -----
        els.signinOtpBoxes.forEach((box, idx) => {
            box.addEventListener('input', (e) => {
                // Strip non-digits and keep only the last char (handles
                // mobile keyboards that auto-suggest the full code).
                const digits = e.target.value.replace(/\D/g, '');
                e.target.value = digits.slice(-1);

                if (e.target.value && idx < els.signinOtpBoxes.length - 1) {
                    els.signinOtpBoxes[idx + 1].focus();
                }

                // Auto-submit when all 6 digits are filled.
                if (readOtpCode().length === els.signinOtpBoxes.length) {
                    doVerifyOtp();
                }
            });

            box.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && idx > 0) {
                    els.signinOtpBoxes[idx - 1].focus();
                    els.signinOtpBoxes[idx - 1].value = '';
                    e.preventDefault();
                } else if (e.key === 'ArrowLeft' && idx > 0) {
                    els.signinOtpBoxes[idx - 1].focus();
                    e.preventDefault();
                } else if (e.key === 'ArrowRight' && idx < els.signinOtpBoxes.length - 1) {
                    els.signinOtpBoxes[idx + 1].focus();
                    e.preventDefault();
                } else if (e.key === 'Enter') {
                    doVerifyOtp();
                }
            });

            box.addEventListener('paste', (e) => {
                const text = (e.clipboardData || window.clipboardData).getData('text') || '';
                const digits = text.replace(/\D/g, '').slice(0, els.signinOtpBoxes.length);
                if (!digits) return;
                e.preventDefault();
                els.signinOtpBoxes.forEach((b, i) => { b.value = digits[i] || ''; });
                const focusIdx = Math.min(digits.length, els.signinOtpBoxes.length - 1);
                els.signinOtpBoxes[focusIdx].focus();
                if (digits.length === els.signinOtpBoxes.length) doVerifyOtp();
            });
        });

        function openSignInModal() {
            paintDeviceId();
            els.signinError.classList.add('hidden');
            els.signinOtp.classList.add('hidden');
            els.signinPassword.value = '';
            signinPendingOtpSession = null;
            signinPendingMobile = null;
            signinPendingPassword = null;
            setSignInStep('credentials');
            els.signinModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            setTimeout(() => {
                (els.signinMobile.value ? els.signinPassword : els.signinMobile).focus();
            }, 50);
        }
        function closeSignInModal() {
            els.signinModal.classList.add('hidden');
            document.body.style.overflow = '';
        }
        els.btnSignIn.addEventListener('click', openSignInModal);
        els.signinClose.addEventListener('click', closeSignInModal);
        els.signinCancel.addEventListener('click', closeSignInModal);
        els.signinBackdrop.addEventListener('click', closeSignInModal);

        // "Use different account" → back to credentials step.
        els.signinOtpBack.addEventListener('click', () => {
            els.signinError.classList.add('hidden');
            els.signinOtp.classList.add('hidden');
            setSignInStep('credentials');
        });

        // "Resend code" → re-run /signin with the saved credentials.
        els.signinOtpResend.addEventListener('click', async () => {
            if (!signinPendingMobile || !signinPendingPassword) {
                setSignInStep('credentials');
                return;
            }
            // Stay on the OTP step but show a transient "resending…" hint.
            const prevLabel = els.signinOtpResend.textContent;
            els.signinOtpResend.textContent = 'Resending…';
            els.signinOtpResend.disabled = true;
            try {
                await postSignIn({
                    mobile:   signinPendingMobile,
                    password: signinPendingPassword,
                    silent:   true,   // don't switch UI on success/failure
                });
                toast('Code resent');
            } finally {
                els.signinOtpResend.textContent = prevLabel;
                els.signinOtpResend.disabled = false;
            }
        });

        // "Already received a code?" — manual jump to OTP step. Useful when
        // FastPay's 5-minute rate-limit blocks a fresh /signin but the OTP
        // from an earlier attempt is still in the user's SMS inbox.
        els.signinHaveCode.addEventListener('click', () => {
            const mobile   = els.signinMobile.value.trim();
            const password = els.signinPassword.value;
            if (!mobile || !password) {
                els.signinError.classList.remove('hidden');
                els.signinErrorMsg.textContent = 'Please type your mobile number and password first.';
                return;
            }
            els.signinError.classList.add('hidden');
            signinPendingMobile     = mobile;
            signinPendingPassword   = password;
            // We don't have a session token — verify-otp will go through with
            // an empty Bearer header (matching the pre-auth client signature).
            signinPendingOtpSession = null;
            setSignInStep('otp');
        });

        // Heuristic: does this upstream error mean "an OTP is still pending
        // and you should just enter it"? FastPay's exact wording varies.
        function looksLikeOtpRateLimit(msg) {
            if (!msg) return false;
            const m = String(msg).toLowerCase();
            return /\b(wait|minute|too many|already|generate another|resend)\b/.test(m)
                && m.includes('otp');
        }

        function setSignInLoading(loading) {
            els.signinSubmit.disabled = loading;
            els.signinSubmit.classList.toggle('opacity-60', loading);
            els.signinSubmit.querySelector('.signin-spinner').classList.toggle('hidden', !loading);
        }

        /**
         * Single submit handler routed by step. Click "Sign In" while in
         * 'credentials' step → doSignIn. Click while in 'otp' step → doVerifyOtp.
         */
        function onSignInSubmit() {
            if (signinStep === 'otp') doVerifyOtp();
            else                       doSignIn();
        }

        /**
         * Low-level: POST /signin and return the parsed JSON envelope.
         * Pulled out so the Resend button can reuse it without redoing the
         * UI state-machine.
         */
        async function postSignIn({ mobile, password, silent = false }) {
            const provider = els.provider.value;
            const deviceId = getOrCreateDeviceId();

            if (!silent) setSignInLoading(true);
            let res, parsed;
            try {
                res = await fetch(`/api/wallet/${provider}/signin`, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        mobile_number: mobile,
                        password:      password,
                        device_id:     deviceId,
                    }),
                });
                const text = await res.text();
                try { parsed = text ? JSON.parse(text) : null; } catch { parsed = { raw: text }; }
            } finally {
                if (!silent) setSignInLoading(false);
            }
            return { res, parsed };
        }

        async function doSignIn() {
            const mobile   = els.signinMobile.value.trim();
            const password = els.signinPassword.value;
            const provider = els.provider.value;

            els.signinError.classList.add('hidden');
            els.signinOtp.classList.add('hidden');

            if (!mobile || !password) {
                els.signinError.classList.remove('hidden');
                els.signinErrorMsg.textContent = 'Please enter both mobile number and password.';
                return;
            }

            if (els.signinRemember.checked) {
                localStorage.setItem(MOBILE_KEY, mobile);
            } else {
                localStorage.removeItem(MOBILE_KEY);
            }

            // Stash for verify-otp / resend.
            signinPendingMobile   = mobile;
            signinPendingPassword = password;

            let res, parsed;
            try {
                ({ res, parsed } = await postSignIn({ mobile, password }));
            } catch (e) {
                els.signinError.classList.remove('hidden');
                els.signinErrorMsg.textContent = 'Network error: ' + (e.message || e);
                return;
            }

            if (!res.ok || parsed?.ok === false) {
                const msg = parsed?.error?.message || parsed?.message || `HTTP ${res.status}`;
                els.signinError.classList.remove('hidden');
                els.signinErrorMsg.textContent = msg;

                // FastPay rate-limited the OTP generation — but a previously
                // sent OTP is probably still valid. Switch to the OTP step
                // automatically so the user can just type that earlier code.
                if (looksLikeOtpRateLimit(msg)) {
                    signinPendingOtpSession = null;
                    setSignInStep('otp');
                }
                return;
            }

            const result = parsed?.data || {};

            if (result.status === 'token' && result.token) {
                // Same-device path — done.
                els.token.value = result.token;
                persistConfigMaybe();
                closeSignInModal();
                toast('Signed in — token saved');
                resetModalBody();
                openModal({
                    title: 'Sign-in successful',
                    subtitle: `POST /api/wallet/${provider}/signin · ${res.status}`,
                    status: 'ok',
                });
                showRaw(parsed);
                return;
            }

            if (result.status === 'otp_required') {
                // New-device path — switch to OTP step.
                signinPendingOtpSession = result.otp_session_id || null;
                els.signinOtp.classList.remove('hidden');
                els.signinOtpMsg.textContent = result.message
                    || "FastPay didn't recognize this device. An OTP has likely been sent to your phone.";
                els.signinOtpSess.textContent = signinPendingOtpSession
                    ? `Session token captured (…${signinPendingOtpSession.slice(-12)})`
                    : '';
                setSignInStep('otp');
                return;
            }

            // Unknown shape — surface raw JSON so the user can inspect.
            closeSignInModal();
            resetModalBody();
            openModal({
                title: 'Sign-in returned an unrecognized payload',
                subtitle: `POST /api/wallet/${provider}/signin · ${res.status}`,
                status: 'err',
            });
            showError("Couldn't classify response", 'See raw payload below.');
            showRaw(parsed);
        }

        async function doVerifyOtp() {
            const otp      = readOtpCode();
            const provider = els.provider.value;
            const deviceId = getOrCreateDeviceId();

            els.signinError.classList.add('hidden');

            if (otp.length !== els.signinOtpBoxes.length) {
                els.signinError.classList.remove('hidden');
                els.signinErrorMsg.textContent = 'Please enter all 6 digits.';
                return;
            }
            if (!signinPendingMobile || !signinPendingPassword) {
                // Edge case: refreshed/reopened modal. Send them back to step 1.
                setSignInStep('credentials');
                els.signinError.classList.remove('hidden');
                els.signinErrorMsg.textContent = 'Session expired. Please sign in again.';
                return;
            }

            setSignInLoading(true);
            let res, parsed;
            try {
                res = await fetch(`/api/wallet/${provider}/verify-otp`, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        mobile_number:  signinPendingMobile,
                        password:       signinPendingPassword,
                        device_id:      deviceId,
                        otp:            otp,
                        otp_session_id: signinPendingOtpSession,
                    }),
                });
                const text = await res.text();
                try { parsed = text ? JSON.parse(text) : null; } catch { parsed = { raw: text }; }
            } catch (e) {
                setSignInLoading(false);
                els.signinError.classList.remove('hidden');
                els.signinErrorMsg.textContent = 'Network error: ' + (e.message || e);
                return;
            }
            setSignInLoading(false);

            if (!res.ok || parsed?.ok === false) {
                const msg = parsed?.error?.message || parsed?.message || `HTTP ${res.status}`;
                els.signinError.classList.remove('hidden');
                els.signinErrorMsg.textContent = msg;
                // Clear the boxes so the user can retry without backspacing.
                els.signinOtpBoxes.forEach(b => { b.value = ''; });
                els.signinOtpBoxes[0]?.focus();
                return;
            }

            const result = parsed?.data || {};

            if (result.status === 'token' && result.token) {
                els.token.value = result.token;
                persistConfigMaybe();
                closeSignInModal();
                toast('Verified — token saved');
                resetModalBody();
                openModal({
                    title: 'OTP verified — signed in',
                    subtitle: `POST /api/wallet/${provider}/verify-otp · ${res.status}`,
                    status: 'ok',
                });
                showRaw(parsed);
                return;
            }

            // Should not happen on a 200, but stay defensive.
            closeSignInModal();
            resetModalBody();
            openModal({
                title: 'Verify-OTP returned an unrecognized payload',
                subtitle: `POST /api/wallet/${provider}/verify-otp · ${res.status}`,
                status: 'err',
            });
            showError("Couldn't extract token", result.message || 'See raw payload below.');
            showRaw(parsed);
        }

        els.signinSubmit.addEventListener('click', onSignInSubmit);
        els.signinPassword.addEventListener('keydown', e => { if (e.key === 'Enter') doSignIn(); });
        els.signinMobile.addEventListener('keydown',   e => { if (e.key === 'Enter') doSignIn(); });

        // ----------------- BUTTON: Basic Info -----------------
        els.btnBasicInfo.addEventListener('click', () => {
            const token = els.token.value.trim();
            if (!token) {
                resetModalBody();
                openModal({ title: 'Basic Info', subtitle: 'GET /api/wallet/' + els.provider.value + '/me', status: 'err' });
                showError('Missing token', 'Sign in first, or paste an existing Bearer token.');
                return;
            }

            const provider = els.provider.value;
            const headers = { 'Authorization': 'Bearer ' + token };

            callEndpoint({
                btn:      els.btnBasicInfo,
                title:    'Basic Info — ' + provider,
                subtitle: 'GET /api/wallet/' + provider + '/me',
                url:      '/api/wallet/' + provider + '/me',
                headers,
                render: (body) => { if (body?.data) renderUser(body.data); },
            });
        });

        // ----------------- BUTTON: Transactions -----------------
        // Prev/Next inside the modal re-call this with a different page.
        let currentTxPage = 1;
        function loadTransactions(page) {
            currentTxPage = Math.max(1, page | 0);

            const token = els.token.value.trim();
            const provider = els.provider.value;
            const path = '/api/wallet/' + provider + '/transactions?page=' + currentTxPage;

            if (!token) {
                resetModalBody();
                openModal({ title: 'Transactions', subtitle: 'GET ' + path, status: 'err' });
                showError('Missing token', 'Sign in first, or paste an existing Bearer token.');
                return;
            }

            const headers = { 'Authorization': 'Bearer ' + token };

            callEndpoint({
                btn:      els.btnTxs,
                title:    'Transactions — ' + provider,
                subtitle: 'GET ' + path,
                url:      path,
                headers,
                render: (body) => { if (body?.data) renderTransactions(body.data); },
            });
        }
        els.btnTxs.addEventListener('click', () => loadTransactions(1));
        els.txsPrev.addEventListener('click', () => {
            if (els.txsPrev.disabled) return;
            loadTransactions(currentTxPage - 1);
        });
        els.txsNext.addEventListener('click', () => {
            if (els.txsNext.disabled) return;
            loadTransactions(currentTxPage + 1);
        });

        // ============================================================
        // WATCH INBOX flow
        // ============================================================
        // Pure receive-watcher. No /pay call.
        // 1. Click "Watch Inbox" → modal opens.
        // 2. We auto-fetch the user's mobile from /me (cached after first time).
        // 3. Snapshot existing tx IDs on page 1, then poll every 5s for 60s,
        //    looking for any NEW credit not in the snapshot. First match wins.
        // 4. The Restart button re-runs the same flow.

        let cachedUserMobile = null;       // populated by /me or sign-in response
        let payVerifyTimerId = null;       // setTimeout handle, so we can cancel
        let payVerifyAbort   = false;      // if user closes modal mid-poll
        let payIsWatching    = false;      // toggles Restart button enabled state

        function setPayWatching(watching) {
            payIsWatching = watching;
            els.paySubmit.disabled = watching;
            els.paySubmit.classList.toggle('opacity-60', watching);
            els.paySubmit.querySelector('.pay-spinner').classList.toggle('hidden', !watching);
            els.paySubmitLabel.textContent = watching ? 'Watching…' : 'Restart Watch';
        }
        function payHideAllBanners() {
            els.payError.classList.add('hidden');
            els.payVerify.classList.add('hidden');
            els.paySuccess.classList.add('hidden');
        }
        function payShowError(msg) {
            els.payError.classList.remove('hidden');
            els.payErrorMsg.textContent = msg;
        }
        function closePayModal() {
            els.payModal.classList.add('hidden');
            document.body.style.overflow = '';
            payVerifyAbort = true;
            if (payVerifyTimerId) { clearTimeout(payVerifyTimerId); payVerifyTimerId = null; }
            setPayWatching(false);
        }

        // Build the FastPay-flavored headers off the config card.
        // FastPay only needs the Bearer token now — no extra device-bound headers.
        function payHeaders() {
            return {
                Accept: 'application/json',
                'Authorization': 'Bearer ' + els.token.value.trim(),
            };
        }

        // Fetch /me silently (no result modal) just to get the user's mobile.
        async function fetchMyMobile() {
            const token = els.token.value.trim();
            if (!token) return null;
            const provider = els.provider.value;
            try {
                const res = await fetch('/api/wallet/' + provider + '/me', { headers: payHeaders() });
                if (!res.ok) return null;
                const body = await res.json();
                return body?.data?.mobile_number || null;
            } catch { return null; }
        }

        async function snapshotKnownTxIds() {
            const provider = els.provider.value;
            try {
                const res = await fetch('/api/wallet/' + provider + '/transactions?page=1', { headers: payHeaders() });
                const body = await res.json();
                return new Set((body?.data?.transactions || []).map(t => t.transaction_id));
            } catch { return new Set(); }
        }

        /**
         * Poll /transactions?page=1 looking for a NEW credit not in the
         * pre-watch snapshot. If `targetDigits` is set, the credit's source
         * mobile must end with those digits (last 9 compared, country-code
         * agnostic). Returns the matching transaction, or null on timeout.
         */
        async function watchForAnyIncoming({ knownTxIds, targetDigits = null, timeoutMs = 60_000, intervalMs = 5_000 }) {
            const provider = els.provider.value;
            const start    = Date.now();
            const totalSec = Math.round(timeoutMs / 1000);
            const wantTail = targetDigits ? targetDigits.slice(-9) : null;
            let attempts   = 0;

            while (!payVerifyAbort && Date.now() - start < timeoutMs) {
                attempts += 1;
                const elapsed = Math.round((Date.now() - start) / 1000);
                els.payVerifyDetail.textContent =
                    `Check #${attempts} · ${elapsed}s / ${totalSec}s`;

                let body;
                try {
                    const res = await fetch('/api/wallet/' + provider + '/transactions?page=1', { headers: payHeaders() });
                    body = await res.json();
                } catch { body = null; }

                const txs = body?.data?.transactions || [];
                const match = txs.find(t => {
                    if (t.direction !== 'credit') return false;
                    if (knownTxIds.has(t.transaction_id)) return false;
                    if (wantTail) {
                        const srcDigits = String(t.source?.mobile_number || '').replace(/\D/g, '');
                        if (!srcDigits.endsWith(wantTail)) return false;
                    }
                    return true;
                });
                if (match) return match;

                if (payVerifyAbort) return null;
                await new Promise(r => { payVerifyTimerId = setTimeout(r, intervalMs); });
            }
            return null;
        }

        async function startWatching() {
            payHideAllBanners();

            if (!els.token.value.trim()) {
                payShowError('Sign in first, or paste an existing Bearer token.');
                return;
            }

            // Resolve user mobile (for display). Not blocking — if /me fails
            // we still watch, just without showing the number.
            if (!cachedUserMobile) cachedUserMobile = await fetchMyMobile();
            els.payMyNumber.textContent = cachedUserMobile || '— (could not resolve)';

            // Read the optional sender filter from the input. Strip non-digits
            // so the polling matcher gets a clean comparison key.
            const targetRaw    = els.payTarget.value.trim();
            const targetDigits = targetRaw.replace(/\D/g, '') || null;
            const targetLabel  = targetRaw || null;

            payVerifyAbort = false;
            setPayWatching(true);

            // Snapshot existing tx IDs so the watcher only picks up brand-new credits.
            const knownTxIds = await snapshotKnownTxIds();

            els.payVerify.classList.remove('hidden');
            els.payVerifyMsg.textContent = targetLabel
                ? `Watching for money from ${targetLabel}…`
                : 'Watching for incoming money…';
            els.payVerifyDetail.textContent = 'Polling every 5s for 60s…';

            const match = await watchForAnyIncoming({
                knownTxIds,
                targetDigits,
                timeoutMs:  60_000,
                intervalMs: 5_000,
            });

            setPayWatching(false);
            els.payVerify.classList.add('hidden');

            if (payVerifyAbort) return;     // user closed the modal

            if (match) {
                els.paySuccess.classList.remove('hidden');
                const fromName = match.source?.name || 'sender';
                const fromMob  = match.source?.mobile_number ? ` (${match.source.mobile_number})` : '';
                els.paySuccessMsg.textContent =
                    `✓ Money received: +${match.amount} ${match.currency} from ${fromName}${fromMob}.`;
                els.paySuccessDetail.textContent =
                    `${match.transaction_id} · ${match.created_at}`;
                toast('Incoming payment detected');
            } else {
                payShowError(targetLabel
                    ? `No incoming credit from ${targetLabel} in the last 60 seconds.`
                    : 'No incoming credit appeared in the last 60 seconds.');
            }
        }

        async function openPayModal() {
            payHideAllBanners();
            els.payMyNumber.textContent = cachedUserMobile || 'Resolving…';
            els.payModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            // Auto-fire on open. The user doesn't need to click anything.
            startWatching();
        }

        els.btnPayTest.addEventListener('click', openPayModal);
        els.payClose.addEventListener('click', closePayModal);
        els.payCancel.addEventListener('click', closePayModal);
        els.payBackdrop.addEventListener('click', closePayModal);
        els.paySubmit.addEventListener('click', () => {
            if (!payIsWatching) startWatching();
        });

        // ----------------- BUTTON: API Info -----------------
        els.btnApiInfo.addEventListener('click', () => {
            callEndpoint({
                btn:      els.btnApiInfo,
                title:    'API Info',
                subtitle: 'GET /api/',
                url:      '/api/',
            });
        });

        // ----------------- global key handler -----------------
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            if (!els.payModal.classList.contains('hidden'))         closePayModal();
            else if (!els.signinModal.classList.contains('hidden')) closeSignInModal();
            else if (!els.modal.classList.contains('hidden'))       closeModal();
        });

        // ----------------- init -----------------
        paintDeviceId();
    })();
    </script>
</body>
</html>
