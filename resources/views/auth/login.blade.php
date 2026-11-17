<x-guest-layout>

<div class="min-h-screen w-full flex flex-col lg:flex-row bg-slate-50 dark:bg-[#070b14] selection:bg-blue-500 selection:text-white transition-colors duration-150">

    {{-- ===== LEFT BRAND & TELEMETRY PANEL ===== --}}
    <div class="hidden lg:flex lg:w-1/2 xl:w-7/12 relative flex-col justify-between bg-[#090d16] overflow-hidden min-h-screen p-10 xl:p-14 select-none">

        {{-- Atmospheric Lighting & Grid Pattern --}}
        <div class="absolute -top-32 -left-32 h-[32rem] w-[32rem] rounded-full bg-blue-600/15 blur-3xl pointer-events-none"></div>
        <div class="absolute top-1/2 -right-24 h-[28rem] w-[28rem] rounded-full bg-indigo-600/10 blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 left-1/3 h-[24rem] w-[24rem] rounded-full bg-cyan-500/10 blur-3xl pointer-events-none"></div>

        {{-- Telemetry Grid Texture --}}
        <div class="absolute inset-0 opacity-[0.06] pointer-events-none"
             style="background-image: linear-gradient(to right, #94a3b8 1px, transparent 1px), linear-gradient(to bottom, #94a3b8 1px, transparent 1px); background-size: 40px 40px; mask-image: radial-gradient(ellipse at center, black 30%, transparent 80%); -webkit-mask-image: radial-gradient(ellipse at center, black 30%, transparent 80%);"></div>

        {{-- Top Brand Navigation Bar --}}
        <div class="relative z-10 flex items-center">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl shadow-lg ring-1 ring-white/20 p-1.5"
                     style="background-color: #ffffff !important;">
                    <img src="{{ asset('images/gamalogo.png') }}" alt="GAMA Logo" class="h-full w-full object-contain">
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-base font-extrabold tracking-tight text-white">GAMA</span>
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                    </div>
                    <p class="text-[10px] font-semibold tracking-[0.2em] uppercase text-blue-400">Fleet Operations</p>
                </div>
            </div>
        </div>

        {{-- Main Center Showcase --}}
        <div class="relative z-10 my-auto py-8 flex flex-col items-start max-w-xl">

            {{-- Category Pill --}}
            <div class="inline-flex items-center gap-2 rounded-full border border-blue-500/20 bg-blue-500/10 px-3.5 py-1 text-xs font-semibold text-blue-300 backdrop-blur-sm mb-4">
                <svg class="h-3.5 w-3.5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                </svg>
                <span>Enterprise Fleet Intelligence</span>
            </div>

            {{-- Headline --}}
            <h1 class="text-3xl xl:text-4xl font-extrabold tracking-tight text-white leading-tight">
                Unified real-time telematics &amp; fleet management.
            </h1>
            <p class="mt-3 text-sm xl:text-base text-slate-400 leading-relaxed max-w-lg">
                Precision GPS vehicle tracking, automated idle fuel diagnostics, and driver dispatch operations engineered for GAMA Foods Corporation.
            </p>

            {{-- Glassmorphism HUD Telemetry Card --}}
            <div class="mt-8 w-full rounded-2xl border border-slate-700/60 bg-slate-900/60 p-5 shadow-2xl backdrop-blur-xl">

                {{-- HUD Card Header --}}
                <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.121a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M5.106 18.894c-3.808-3.807-3.808-9.98 0-13.788m13.788 0c3.808 3.807 3.808 9.98 0 13.788M12 12h.008v.008H12V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-200">Live Telematics Overview</span>
                    </div>
                    <span class="font-mono text-[11px] text-slate-400">Sync: 1.2s &bull; High Precision</span>
                </div>

                {{-- Operational Highlights Grid --}}
                <div class="space-y-2.5">
                    {{-- Item 1 --}}
                    <div class="flex items-center gap-3.5 rounded-xl border border-slate-800/80 bg-slate-950/40 px-3.5 py-2.5 transition-colors hover:border-slate-700/80 hover:bg-slate-800/40">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-blue-500/15 text-blue-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-semibold text-slate-200">GPS Live Vehicle Positioning</div>
                            <div class="text-[11px] text-slate-400 truncate">Instant route monitoring, ignition sensors &amp; geo-boundaries</div>
                        </div>
                    </div>

                    {{-- Item 2 --}}
                    <div class="flex items-center gap-3.5 rounded-xl border border-slate-800/80 bg-slate-950/40 px-3.5 py-2.5 transition-colors hover:border-slate-700/80 hover:bg-slate-800/40">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-amber-500/15 text-amber-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-semibold text-slate-200">Idling &amp; Fuel Analytics</div>
                            <div class="text-[11px] text-slate-400 truncate">Excessive idling triggers, trip logs &amp; fuel consumption audits</div>
                        </div>
                    </div>

                    {{-- Item 3 --}}
                    <div class="flex items-center gap-3.5 rounded-xl border border-slate-800/80 bg-slate-950/40 px-3.5 py-2.5 transition-colors hover:border-slate-700/80 hover:bg-slate-800/40">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-semibold text-slate-200">Vehicle Master List &amp; Dispatch</div>
                            <div class="text-[11px] text-slate-400 truncate">Preventive fleet maintenance, schedules &amp; driver assignment</div>
                        </div>
                    </div>
                </div>

                {{-- Micro Status Badges --}}
                <div class="mt-4 grid grid-cols-3 gap-2 border-t border-slate-800 pt-3 text-center">
                    <div class="rounded-lg bg-slate-950/40 py-1.5 px-2">
                        <div class="text-[10px] uppercase tracking-wider text-slate-400">Fleet Status</div>
                        <div class="font-mono text-xs font-bold text-emerald-400">99.98% Active</div>
                    </div>
                    <div class="rounded-lg bg-slate-950/40 py-1.5 px-2">
                        <div class="text-[10px] uppercase tracking-wider text-slate-400">Telemetry</div>
                        <div class="font-mono text-xs font-bold text-blue-400">Real-time</div>
                    </div>
                    <div class="rounded-lg bg-slate-950/40 py-1.5 px-2">
                        <div class="text-[10px] uppercase tracking-wider text-slate-400">Encryption</div>
                        <div class="font-mono text-xs font-bold text-slate-200">256-Bit SSL</div>
                    </div>
                </div>

            </div>

        </div>

        {{-- Bottom Footer Line --}}
        <div class="relative z-10 flex items-center justify-between border-t border-slate-800/80 pt-5 text-xs text-slate-500">
            <div>&copy; {{ date('Y') }} GAMA Foods Corporation. All rights reserved.</div>
            <div class="flex items-center gap-1.5 font-medium text-slate-400">
                <svg class="h-3.5 w-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
                <span>Enterprise Portal</span>
            </div>
        </div>

    </div>

    {{-- ===== RIGHT AUTHENTICATION PANEL ===== --}}
    <div class="flex flex-1 flex-col justify-between min-h-screen px-6 py-8 sm:px-10 lg:px-12 xl:px-16">

        {{-- Top Utility Bar --}}
        <div class="flex items-center justify-between w-full max-w-md mx-auto">
            {{-- Mobile-only Brand (left) --}}
            <div class="lg:hidden flex items-center gap-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl shadow-md ring-1 ring-slate-200 dark:ring-slate-700 p-1"
                     style="background-color: #ffffff !important;">
                    <img src="{{ asset('images/gamalogo.png') }}" alt="GAMA Logo" class="h-full w-full object-contain">
                </div>
                <div>
                    <div class="flex items-center gap-1">
                        <span class="text-sm font-extrabold tracking-tight text-slate-900 dark:text-white">GAMA</span>
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                    </div>
                    <p class="text-[9px] font-semibold tracking-wider uppercase text-blue-600 dark:text-blue-400">Fleet Operations</p>
                </div>
            </div>

            {{-- Theme Switcher Button --}}
            <button type="button"
                    onclick="toggleThemeMode()"
                    class="ml-auto inline-flex items-center justify-center h-9 w-9 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-800 text-slate-500 hover:text-slate-800 dark:text-amber-400 dark:hover:text-amber-300 hover:bg-slate-100 dark:hover:bg-slate-700/70 transition-all shadow-sm cursor-pointer"
                    title="Toggle dark/light theme"
                    aria-label="Toggle dark/light theme">
                {{-- Moon (shown in light mode) --}}
                <svg class="h-4 w-4 dark:hidden" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                </svg>
                {{-- Sun (shown in dark mode) --}}
                <svg class="h-4 w-4 hidden dark:inline text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                </svg>
            </button>
        </div>

        {{-- Form Container Card --}}
        <div class="w-full max-w-md mx-auto my-auto py-8">

            {{-- Card Surface --}}
            <div class="rounded-3xl border border-slate-200/80 dark:border-slate-800 bg-white/90 dark:bg-slate-900/80 p-7 sm:p-9 shadow-xl shadow-slate-200/50 dark:shadow-none backdrop-blur-xl transition-all">

                {{-- Header --}}
                <div class="mb-7">
                    <div class="inline-flex items-center gap-1.5 rounded-md bg-blue-50 dark:bg-blue-950/60 border border-blue-100 dark:border-blue-900/60 px-2.5 py-1 text-[11px] font-semibold text-blue-700 dark:text-blue-300 mb-3">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        <span>Secure Console Sign In</span>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                        Welcome back
                    </h2>
                    <p class="mt-1.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                        Sign in to access your fleet tracking, telemetry logs, and dispatch tools.
                    </p>
                </div>

                {{-- Session Status Banner --}}
                @if (session('status'))
                    <div class="mb-5 flex items-center gap-2.5 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/50 px-3.5 py-2.5 text-xs font-medium text-emerald-800 dark:text-emerald-300">
                        <svg class="h-4 w-4 flex-shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    {{-- Email Address --}}
                    <div>
                        <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                            Email Address
                        </label>
                        <div class="relative">
                            {{-- Envelope Icon --}}
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 dark:text-slate-500">
                                <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>
                            </div>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="name@company.com"
                                class="block w-full h-11 sm:h-12 rounded-xl pl-10 pr-4 text-sm border bg-slate-50/70 dark:bg-slate-800/60 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500
                                       border-slate-200 dark:border-slate-700/80 focus:border-blue-600 dark:focus:border-blue-500 focus:bg-white dark:focus:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-blue-500/10 transition-all
                                       @error('email') border-rose-400 dark:border-rose-500 bg-rose-50/40 dark:bg-rose-950/20 focus:border-rose-500 focus:ring-rose-500/10 @enderror"
                            >
                        </div>
                        @if ($errors->has('email'))
                            <div class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-rose-600 dark:text-rose-400">
                                <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                </svg>
                                <span>{{ $errors->first('email') }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Password --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                Password
                            </label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}"
                                   class="text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
                                    Forgot password?
                                </a>
                            @endif
                        </div>
                        <div class="relative">
                            {{-- Lock Icon --}}
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 dark:text-slate-500">
                                <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </div>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••••••"
                                class="block w-full h-11 sm:h-12 rounded-xl pl-10 pr-11 text-sm border bg-slate-50/70 dark:bg-slate-800/60 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500
                                       border-slate-200 dark:border-slate-700/80 focus:border-blue-600 dark:focus:border-blue-500 focus:bg-white dark:focus:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-blue-500/10 transition-all
                                       @error('password') border-rose-400 dark:border-rose-500 bg-rose-50/40 dark:bg-rose-950/20 focus:border-rose-500 focus:ring-rose-500/10 @enderror"
                            >
                            {{-- Toggle Password Visibility Button --}}
                            <button
                                type="button"
                                onclick="togglePasswordVisibility()"
                                class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors cursor-pointer focus:outline-none"
                                aria-label="Toggle password visibility"
                                title="Show or hide password"
                            >
                                {{-- Eye Open (shown when hidden) --}}
                                <svg id="password-eye-open" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                {{-- Eye Closed (shown when revealed) --}}
                                <svg id="password-eye-closed" class="h-4.5 w-4.5 hidden" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                        @if ($errors->has('password'))
                            <div class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-rose-600 dark:text-rose-400">
                                <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                </svg>
                                <span>{{ $errors->first('password') }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Remember me checkbox --}}
                    <div class="flex items-center justify-between pt-0.5">
                        <label for="remember_me" class="inline-flex items-center gap-2.5 cursor-pointer select-none">
                            <input
                                id="remember_me"
                                type="checkbox"
                                name="remember"
                                class="h-4 w-4 rounded border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-blue-600 focus:ring-blue-500 focus:ring-offset-0 cursor-pointer transition-colors"
                            >
                            <span class="text-xs sm:text-sm font-medium text-slate-600 dark:text-slate-400">
                                Keep me signed in
                            </span>
                        </label>
                    </div>

                    {{-- Submit Button --}}
                    <button
                        type="submit"
                        class="group relative flex w-full h-11 sm:h-12 items-center justify-center gap-2.5 rounded-xl bg-gradient-to-r from-blue-600 via-blue-600 to-indigo-600 px-5 text-sm font-bold text-white shadow-lg shadow-blue-500/25 hover:from-blue-500 hover:via-blue-500 hover:to-indigo-500 hover:shadow-blue-500/35 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 transition-all duration-150 cursor-pointer"
                    >
                        <span>Sign In to Fleet Console</span>
                        <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </button>

                </form>

            </div>

        </div>

        {{-- Bottom Trust Bar --}}
        <div class="w-full max-w-md mx-auto pt-6 border-t border-slate-200/80 dark:border-slate-800/80 flex flex-col sm:flex-row items-center justify-between gap-2.5 text-center sm:text-left text-xs text-slate-400 dark:text-slate-500">
            <div class="flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5 text-blue-500 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
                <span>256-Bit SSL Encrypted Access</span>
            </div>
            <div>
                GAMA FOMS &bull; {{ date('Y') }}
            </div>
        </div>

    </div>

</div>

{{-- Inline Vanilla JS Helpers for Password Visibility & Theme Toggle --}}
<script>
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('password');
        const eyeOpen = document.getElementById('password-eye-open');
        const eyeClosed = document.getElementById('password-eye-closed');
        if (!passwordInput) return;

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            if (eyeOpen) eyeOpen.classList.add('hidden');
            if (eyeClosed) eyeClosed.classList.remove('hidden');
        } else {
            passwordInput.type = 'password';
            if (eyeOpen) eyeOpen.classList.remove('hidden');
            if (eyeClosed) eyeClosed.classList.add('hidden');
        }
    }

    function toggleThemeMode() {
        const isDark = document.documentElement.classList.contains('dark');
        if (isDark) {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        } else {
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        }
    }
</script>

</x-guest-layout>
