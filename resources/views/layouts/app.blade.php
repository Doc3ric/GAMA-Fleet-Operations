<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'GAMA FOMS') }} &mdash; @yield('page-title', 'Dashboard')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('styles')
</head>
<body class="h-full bg-slate-100 font-sans antialiased">

<div class="flex h-full">

    {{-- ===================== SIDEBAR ===================== --}}
    <aside class="flex w-60 flex-col bg-[#0f172a] text-slate-100 min-h-screen fixed inset-y-0 left-0 z-30">

        {{-- Logo / Brand --}}
        <div class="flex items-center gap-3 px-5 py-5 border-b border-slate-700/60">
            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white shadow-md p-0.5 overflow-hidden">
                <img src="{{ asset('images/gamalogo.png') }}" alt="GAMA Logo" class="h-full w-full object-contain">
            </div>
            <div>
                <div class="flex items-center gap-1.5">
                    <span class="text-sm font-bold leading-tight tracking-wide text-white">GAMA</span>
                    <span class="h-1.5 w-1.5 rounded-full bg-red-500 inline-block"></span>
                </div>
                <div class="text-[10px] text-slate-400 leading-tight uppercase tracking-widest">Fleet Operations</div>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto px-3 py-5 space-y-0.5">

            {{-- Dashboard --}}
            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150
                      {{ request()->routeIs('dashboard') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">
                <svg class="h-4.5 w-4.5 flex-shrink-0 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                </svg>
                Dashboard
            </a>

            {{-- Section Label --}}
            <div class="pt-5 pb-2 px-3 text-[10px] uppercase tracking-widest font-semibold text-slate-500">Fleet Management</div>

            {{-- Vehicle Master List --}}
            <a href="{{ route('vehicles.index') }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150
                      {{ request()->routeIs('vehicles.*') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">
                <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                </svg>
                Vehicle Master List
                <span class="ml-auto inline-flex items-center gap-1 rounded-full bg-emerald-500/20 px-2 py-0.5 text-[10px] font-semibold text-emerald-400 border border-emerald-500/30">Live</span>
            </a>

            {{-- GPS Devices --}}
            @php
                $sidebarExpiringCount = \App\Models\Device::where('created_by', auth()->id())->expiringSoon(30)->count();
                $sidebarExpiredCount = \App\Models\Device::where('created_by', auth()->id())->expired()->count();
                $sidebarAlertCount = $sidebarExpiringCount + $sidebarExpiredCount;
            @endphp
            <a href="{{ route('devices.index') }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150
                      {{ request()->routeIs('devices.*') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">
                <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.122a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M5.106 18.894c-3.808-3.808-3.808-9.98 0-13.789m13.788 0c3.808 3.808 3.808 9.98 0 13.788M12 12h.008v.008H12V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                </svg>
                GPS Devices
                @if($sidebarAlertCount > 0)
                    <span class="ml-auto inline-flex items-center gap-1 rounded-full bg-red-500/20 px-2 py-0.5 text-[10px] font-bold text-red-400 border border-red-500/30 animate-pulse" title="{{ $sidebarAlertCount }} devices need budgeting/renewal">
                        {{ $sidebarAlertCount }}
                    </span>
                @endif
            </a>

            {{-- Location Directory --}}
            <a href="{{ route('locations.index') }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150
                      {{ request()->routeIs('locations.*') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">
                <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                </svg>
                Location Directory
                @if(request()->routeIs('locations.*'))
                    <span class="ml-auto h-2 w-2 rounded-full bg-red-500"></span>
                @endif
            </a>

            {{-- Section Label --}}
            <div class="pt-5 pb-2 px-3 text-[10px] uppercase tracking-widest font-semibold text-slate-500">Fleet Reports</div>

            {{-- Long Idling --}}
            <a href="{{ route('reports.index') }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150
                      {{ request()->routeIs('reports.*') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">
                <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                </svg>
                Long Idling
                @if(request()->routeIs('reports.*'))
                    <span class="ml-auto h-2 w-2 rounded-full bg-red-500"></span>
                @endif
            </a>

            {{-- Driver Itinerary --}}
            <a href="{{ route('itineraries.index') }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150
                      {{ request()->routeIs('itineraries.*') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">
                <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                </svg>
                Driver Itinerary
                @if(request()->routeIs('itineraries.*'))
                    <span class="ml-auto h-2 w-2 rounded-full bg-red-500"></span>
                @endif
            </a>

            {{-- Advanced Itinerary --}}
            <a href="{{ route('advanced-itineraries.index') }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150
                      {{ request()->routeIs('advanced-itineraries.*') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">
                <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                </svg>
                Advanced Itinerary
                @if(request()->routeIs('advanced-itineraries.*'))
                    <span class="ml-auto h-2 w-2 rounded-full bg-red-500"></span>
                @endif
            </a>

            {{-- Average Fuel Consumption --}}
            <a href="{{ route('fuel-consumption.index') }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150
                      {{ request()->routeIs('fuel-consumption.*') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">
                <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                </svg>
                Avg Fuel Consumption
                @if(request()->routeIs('fuel-consumption.*'))
                    <span class="ml-auto h-2 w-2 rounded-full bg-red-500"></span>
                @endif
            </a>

            {{-- Section Label --}}
            <div class="pt-5 pb-2 px-3 text-[10px] uppercase tracking-widest font-semibold text-slate-500">Tools</div>

            {{-- Excel Viewer --}}
            <a href="{{ route('excel-viewer.index') }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150
                      {{ request()->routeIs('excel-viewer.*') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">
                <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 13.5h6m-6 3h6" />
                </svg>
                Excel Viewer
                @if(request()->routeIs('excel-viewer.*'))
                    <span class="ml-auto h-2 w-2 rounded-full bg-red-500"></span>
                @else
                    <span class="ml-auto inline-flex items-center rounded-full bg-emerald-500/20 px-1.5 py-0.5 text-[9px] font-semibold text-emerald-400 border border-emerald-500/30">XLSB</span>
                @endif
            </a>

            {{-- Coming Soon items --}}
            @foreach([
                ['Overspeed', 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z'],
                ['Geofence', 'M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z'],
            ] as [$label, $path])
            <div class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-600 cursor-not-allowed select-none">
                <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
                </svg>
                <span>{{ $label }}</span>
                <span class="ml-auto text-[9px] bg-slate-800 text-slate-500 px-1.5 py-0.5 rounded-full border border-slate-700 font-medium">Soon</span>
            </div>
            @endforeach

        </nav>

        {{-- User / Bottom --}}
        <div class="border-t border-slate-700/60 px-3 py-4 space-y-1">
            {{-- User Avatar Block --}}
            <div class="flex items-center gap-3 rounded-xl bg-slate-800/50 px-3 py-2.5 mb-1">
                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-blue-600 text-white text-xs font-bold select-none">
                    {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold text-white truncate">{{ Auth::user()->name }}</p>
                    <p class="text-[10px] text-slate-400 truncate">Monitoring Center</p>
                </div>
                <span class="h-2 w-2 rounded-full bg-emerald-400 flex-shrink-0"></span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                    </svg>
                    Sign Out
                </button>
            </form>
        </div>

    </aside>

    {{-- ===================== MAIN CONTENT ===================== --}}
    <div class="flex flex-col flex-1 ml-60 min-h-screen">

        {{-- Top Bar --}}
        <header class="sticky top-0 z-20 bg-white border-b border-slate-200 shadow-sm">
            <div class="flex items-center justify-between px-6 py-3.5">
                <div class="flex items-center gap-3">
                    <h1 class="text-lg font-bold text-slate-900">@yield('page-title', 'Dashboard')</h1>
                    @hasSection('page-subtitle')
                        <span class="hidden sm:inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500">@yield('page-subtitle')</span>
                    @endif
                    @hasSection('breadcrumb')
                        <div class="text-xs text-slate-400 mt-0.5">@yield('breadcrumb')</div>
                    @endif
                </div>
                <div class="flex items-center gap-4">
                    {{-- Date --}}
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        <span class="font-medium">{{ now()->format('l, F j, Y') }}</span>
                    </div>
                    {{-- Bell --}}
                    <div class="relative">
                        <button class="relative flex items-center justify-center h-8 w-8 rounded-full hover:bg-slate-100 text-slate-500 transition-colors">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                            </svg>
                            <span class="absolute top-1 right-1 h-1.5 w-1.5 rounded-full bg-red-500"></span>
                        </button>
                    </div>
                    {{-- Live Feed --}}
                    <div class="flex items-center gap-2 rounded-full bg-slate-50 border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700">
                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        LIVE FEED
                    </div>
                </div>
            </div>
        </header>

        {{-- Flash Messages --}}
        <div class="px-6 pt-4">
            @if(session('success'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                     x-transition:leave="transition ease-in duration-300"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="flex items-center gap-3 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 mb-2">
                    <svg class="h-5 w-5 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    {{ session('success') }}
                    <button @click="show = false" class="ml-auto text-emerald-500 hover:text-emerald-700">&times;</button>
                </div>
            @endif
            @if(session('error'))
                <div class="flex items-center gap-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800 mb-2">
                    <svg class="h-5 w-5 text-red-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    {{ session('error') }}
                </div>
            @endif
        </div>

        {{-- Page Content --}}
        <main class="flex-1 px-6 py-5">
            {{ $slot }}
        </main>

        {{-- Footer --}}
        <footer class="border-t border-slate-200 px-6 py-4 text-xs text-slate-400 text-center">
            &copy; {{ date('Y') }} GAMA Fleet Operations Management System. All rights reserved.
        </footer>

    </div>
</div>

@livewireScripts
@stack('scripts')
</body>
</html>
