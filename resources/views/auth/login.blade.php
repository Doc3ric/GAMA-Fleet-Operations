<x-guest-layout>

<div class="min-h-screen w-full flex">

    {{-- ===== LEFT BRAND PANEL ===== --}}
    <div class="hidden lg:flex lg:w-1/2 xl:w-7/12 relative flex-col items-center justify-center bg-[#0f172a] overflow-hidden min-h-screen p-8">

        {{-- Decorative circles --}}
        <div class="absolute -top-24 -left-24 h-96 w-96 rounded-full bg-blue-600/10 pointer-events-none"></div>
        <div class="absolute top-1/2 -right-32 h-80 w-80 rounded-full bg-blue-500/10 pointer-events-none"></div>
        <div class="absolute -bottom-20 left-1/4 h-64 w-64 rounded-full bg-red-500/5 pointer-events-none"></div>

        {{-- Dot grid pattern --}}
        <div class="absolute inset-0 opacity-5 pointer-events-none"
             style="background-image: radial-gradient(circle, #94a3b8 1px, transparent 1px); background-size: 32px 32px;"></div>

        {{-- Brand content --}}
        <div class="relative z-10 flex flex-col items-center text-center max-w-md">

            {{-- Logo --}}
            <div class="mb-6 flex h-36 w-36 items-center justify-center rounded-2xl bg-white shadow-2xl p-3 overflow-hidden">
                <img src="{{ asset('images/gamalogo.png') }}" alt="GAMA Logo" class="h-full w-full object-contain">
            </div>

            {{-- Title --}}
            <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center gap-2">
                GAMA <span class="h-2 w-2 rounded-full bg-red-500 inline-block"></span>
            </h1>
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-400 mt-1">Fleet Operations</p>

            {{-- Decorative divider --}}
            <div class="mt-5 mb-5 flex items-center gap-3">
                <div class="h-px w-12 bg-slate-700"></div>
                <div class="h-1.5 w-1.5 rounded-full bg-red-500"></div>
                <div class="h-px w-12 bg-slate-700"></div>
            </div>

            {{-- Tagline --}}
            <p class="text-slate-400 text-sm leading-relaxed">
                GPS-powered fleet monitoring and management system for real-time operational oversight.
            </p>

            {{-- Feature list --}}
            <div class="mt-8 flex flex-col gap-2.5 w-full">
                @foreach([
                    ['GPS Live Tracking', 'M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12'],
                    ['Long Idling Reports', 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z'],
                    ['Vehicle Master List', 'M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z'],
                ] as [$label, $path])
                <div class="flex items-center gap-3 rounded-xl bg-slate-800/60 border border-slate-700/50 px-4 py-2.5">
                    <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg bg-blue-600/20 text-blue-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-slate-300">{{ $label }}</span>
                </div>
                @endforeach
            </div>

        </div>

        {{-- Bottom copyright --}}
        <div class="absolute bottom-6 text-xs text-slate-500 text-center">
            &copy; {{ date('Y') }} GAMA Foods Corporation. All rights reserved.
        </div>

    </div>

    {{-- ===== RIGHT FORM PANEL ===== --}}
    <div class="flex w-full lg:w-1/2 xl:w-5/12 flex-col items-center justify-center px-6 py-12 bg-white min-h-screen">

        {{-- Mobile logo --}}
        <div class="lg:hidden mb-6 flex flex-col items-center">
            <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-white shadow-lg border border-slate-200 p-2 overflow-hidden">
                <img src="{{ asset('images/gamalogo.png') }}" alt="GAMA Logo" class="h-full w-full object-contain">
            </div>
            <p class="mt-3 text-lg font-bold text-slate-800 flex items-center gap-1.5">
                GAMA <span class="h-1.5 w-1.5 rounded-full bg-red-500 inline-block"></span>
            </p>
            <p class="text-xs text-slate-400 uppercase tracking-wider">Fleet Operations</p>
        </div>

        <div class="w-full max-w-sm">

            {{-- Heading --}}
            <div class="mb-6">
                <h2 class="text-2xl font-extrabold text-slate-900">Welcome back</h2>
                <p class="mt-1 text-sm text-slate-500">Sign in to your account to continue</p>
            </div>

            {{-- Session status --}}
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Email Address
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="you@example.com"
                        class="block w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400
                               focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-colors
                               @error('email') border-red-400 bg-red-50 @enderror"
                    >
                    <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
                </div>

                {{-- Password --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-sm font-semibold text-slate-700">
                            Password
                        </label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}"
                               class="text-xs font-medium text-blue-600 hover:text-blue-700 transition-colors">
                                Forgot password?
                            </a>
                        @endif
                    </div>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="block w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400
                               focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-colors
                               @error('password') border-red-400 bg-red-50 @enderror"
                    >
                    <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                </div>

                {{-- Remember me --}}
                <div class="flex items-center gap-2 pt-1">
                    <input
                        id="remember_me"
                        type="checkbox"
                        name="remember"
                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                    >
                    <label for="remember_me" class="text-sm text-slate-600 select-none cursor-pointer">
                        Keep me signed in
                    </label>
                </div>

                {{-- Submit button --}}
                <button
                    type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white
                           hover:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2
                           transition-colors shadow-sm cursor-pointer mt-2"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                    </svg>
                    Sign In
                </button>

            </form>

            {{-- Footer note --}}
            <p class="mt-8 text-center text-xs text-slate-400">
                GAMA Fleet Operations Management System &bull; {{ date('Y') }}
            </p>

        </div>
    </div>

</div>

</x-guest-layout>