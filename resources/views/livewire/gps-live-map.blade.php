<div class="space-y-4"
     x-data="gpsLiveMap({
        vehicles: @js($vehicles),
        selectedVehicleId: @entangle('selectedVehicleId'),
        selectedImei: @entangle('selectedImei')
     })"
     x-init="initMap()"
     @if($autoRefresh) wire:poll.{{ $refreshInterval }}s="refreshLocations" @endif
>
    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #fleet-live-map {
                height: 650px !important;
                min-height: 650px !important;
                width: 100% !important;
                z-index: 10;
            }
            @media (min-width: 1024px) {
                .gps-layout-map {
                    grid-column: span 8 / span 8 !important;
                }
                .gps-layout-sidebar {
                    grid-column: span 4 / span 4 !important;
                }
            }
            .leaflet-popup-content-wrapper {
                border-radius: 1rem;
                padding: 0;
                overflow: hidden;
                box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.15), 0 8px 10px -6px rgb(0 0 0 / 0.1);
                border: 1px solid #e2e8f0;
            }
            .leaflet-popup-content {
                margin: 0;
                line-height: 1.4;
            }
            .leaflet-popup-tip {
                background: #ffffff;
            }
            .custom-gps-pin {
                background: transparent;
                border: none;
            }
        </style>
    @endpush

    {{-- Top Action & Header Bar --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-1">
        <div>
            <div class="flex items-center gap-2.5">
                <h2 class="text-xl font-bold text-slate-800 dark:text-white tracking-tight">Live GPS Fleet Map</h2>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold {{ $apiStatus === 'connected' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800' : 'bg-red-50 text-red-700 border border-red-200' }}">
                    <span class="h-2 w-2 rounded-full {{ $apiStatus === 'connected' ? 'bg-emerald-500 animate-pulse' : 'bg-red-500' }}"></span>
                    {{ $apiStatus === 'connected' ? 'TrackSolid API Connected' : 'API Connection Issue' }}
                </span>
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    &bull; {{ $totalFleetCount }} Trackers Monitored
                </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Real-time telematics, vehicle movement tracking, ignition status, and geolocations</p>
        </div>

        {{-- Refresh Controls & Timers --}}
        <div class="flex flex-wrap items-center gap-2">
            {{-- Auto-refresh Pill --}}
            <button type="button"
                    wire:click="$toggle('autoRefresh')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-colors shadow-xs {{ $autoRefresh ? 'bg-emerald-50 border-emerald-200 text-emerald-800 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:border-emerald-700 dark:text-emerald-300' : 'bg-slate-100 border-slate-300 text-slate-600 hover:bg-slate-200' }}"
                    title="Toggle automatic background refreshing every 30 seconds">
                <span class="h-2 w-2 rounded-full {{ $autoRefresh ? 'bg-emerald-500 animate-ping' : 'bg-slate-400' }}"></span>
                <span>Auto-refresh: {{ $autoRefresh ? '30s' : 'OFF' }}</span>
            </button>

            {{-- Manual Refresh Button --}}
            <button type="button"
                    wire:click="refreshLocations(true)"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors disabled:opacity-50 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                <svg wire:loading.class="animate-spin text-blue-600" class="h-4 w-4 text-slate-600 dark:text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                <span wire:loading.remove>Refresh Now</span>
                <span wire:loading>Syncing...</span>
            </button>

            {{-- Center Map on Fleet --}}
            <button type="button"
                    @click="fitBounds()"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                    title="Fit view to all active fleet pins">
                <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                </svg>
                <span>Fit Fleet</span>
            </button>

            {{-- Link to GPS Devices Table --}}
            <a href="{{ route('devices.index') }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
                <span>Device List</span>
            </a>
        </div>
    </div>

    {{-- Error Banner if any --}}
    @if ($errorMessage)
        <div class="rounded-xl bg-amber-50 border border-amber-200 p-3.5 text-xs text-amber-800 flex items-center justify-between dark:bg-amber-950/60 dark:border-amber-800 dark:text-amber-200">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 text-amber-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <span>{{ $errorMessage }}</span>
            </div>
            <span class="text-[11px] text-amber-700 dark:text-amber-300">Using last cached positions</span>
        </div>
    @endif

    {{-- Quick Status Filter Metric Badges --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2">
        {{-- All Fleet --}}
        <button type="button"
                wire:click="setStatusFilter('all')"
                class="flex flex-col p-2.5 rounded-xl border text-left transition-all duration-150 {{ $statusFilter === 'all' ? 'bg-slate-900 border-slate-900 text-white shadow-sm dark:bg-blue-600 dark:border-blue-600' : 'bg-white border-slate-200 text-slate-700 hover:border-slate-300 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-300' }}">
            <span class="text-[10px] font-semibold uppercase tracking-wider {{ $statusFilter === 'all' ? 'text-slate-300 dark:text-blue-100' : 'text-slate-400' }}">All Units</span>
            <div class="flex items-baseline justify-between mt-0.5">
                <span class="text-lg font-bold">{{ $summary['total'] }}</span>
                <span class="text-[10px] opacity-80">{{ $summary['has_coords'] }} pinned</span>
            </div>
        </button>

        {{-- Moving --}}
        <button type="button"
                wire:click="setStatusFilter('moving')"
                class="flex flex-col p-2.5 rounded-xl border text-left transition-all duration-150 {{ $statusFilter === 'moving' ? 'bg-emerald-600 border-emerald-600 text-white shadow-sm' : 'bg-white border-slate-200 text-slate-700 hover:border-emerald-300 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-300' }}">
            <div class="flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full bg-emerald-500 {{ $statusFilter === 'moving' ? 'bg-white animate-pulse' : '' }}"></span>
                <span class="text-[10px] font-semibold uppercase tracking-wider {{ $statusFilter === 'moving' ? 'text-emerald-100' : 'text-emerald-700 dark:text-emerald-400' }}">Moving</span>
            </div>
            <div class="flex items-baseline justify-between mt-0.5">
                <span class="text-lg font-bold text-emerald-600 {{ $statusFilter === 'moving' ? '!text-white' : '' }}">{{ $summary['moving'] }}</span>
                <span class="text-[10px] {{ $statusFilter === 'moving' ? 'text-emerald-100' : 'text-slate-400' }}">&ge; 3 km/h</span>
            </div>
        </button>

        {{-- Idling --}}
        <button type="button"
                wire:click="setStatusFilter('idling')"
                class="flex flex-col p-2.5 rounded-xl border text-left transition-all duration-150 {{ $statusFilter === 'idling' ? 'bg-amber-500 border-amber-500 text-white shadow-sm' : 'bg-white border-slate-200 text-slate-700 hover:border-amber-300 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-300' }}">
            <div class="flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full bg-amber-500 {{ $statusFilter === 'idling' ? 'bg-white' : '' }}"></span>
                <span class="text-[10px] font-semibold uppercase tracking-wider {{ $statusFilter === 'idling' ? 'text-amber-100' : 'text-amber-700 dark:text-amber-400' }}">Idling</span>
            </div>
            <div class="flex items-baseline justify-between mt-0.5">
                <span class="text-lg font-bold text-amber-600 {{ $statusFilter === 'idling' ? '!text-white' : '' }}">{{ $summary['idling'] }}</span>
                <span class="text-[10px] {{ $statusFilter === 'idling' ? 'text-amber-100' : 'text-slate-400' }}">ACC ON</span>
            </div>
        </button>

        {{-- Parked --}}
        <button type="button"
                wire:click="setStatusFilter('parked')"
                class="flex flex-col p-2.5 rounded-xl border text-left transition-all duration-150 {{ $statusFilter === 'parked' ? 'bg-blue-600 border-blue-600 text-white shadow-sm' : 'bg-white border-slate-200 text-slate-700 hover:border-blue-300 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-300' }}">
            <div class="flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full bg-blue-500 {{ $statusFilter === 'parked' ? 'bg-white' : '' }}"></span>
                <span class="text-[10px] font-semibold uppercase tracking-wider {{ $statusFilter === 'parked' ? 'text-blue-100' : 'text-blue-700 dark:text-blue-400' }}">Parked</span>
            </div>
            <div class="flex items-baseline justify-between mt-0.5">
                <span class="text-lg font-bold text-blue-600 {{ $statusFilter === 'parked' ? '!text-white' : '' }}">{{ $summary['parked'] }}</span>
                <span class="text-[10px] {{ $statusFilter === 'parked' ? 'text-blue-100' : 'text-slate-400' }}">Engine OFF</span>
            </div>
        </button>

        {{-- Offline --}}
        <button type="button"
                wire:click="setStatusFilter('offline')"
                class="flex flex-col p-2.5 rounded-xl border text-left transition-all duration-150 {{ $statusFilter === 'offline' ? 'bg-slate-700 border-slate-700 text-white shadow-sm' : 'bg-white border-slate-200 text-slate-700 hover:border-slate-300 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-300' }}">
            <div class="flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full bg-slate-400 {{ $statusFilter === 'offline' ? 'bg-white' : '' }}"></span>
                <span class="text-[10px] font-semibold uppercase tracking-wider {{ $statusFilter === 'offline' ? 'text-slate-200' : 'text-slate-500 dark:text-slate-400' }}">Offline</span>
            </div>
            <div class="flex items-baseline justify-between mt-0.5">
                <span class="text-lg font-bold text-slate-600 {{ $statusFilter === 'offline' ? '!text-white' : 'dark:text-slate-400' }}">{{ $summary['offline'] }}</span>
                <span class="text-[10px] {{ $statusFilter === 'offline' ? 'text-slate-300' : 'text-slate-400' }}">No Heartbeat</span>
            </div>
        </button>

        {{-- Stale Data --}}
        <button type="button"
                wire:click="setStatusFilter('stale')"
                class="flex flex-col p-2.5 rounded-xl border text-left transition-all duration-150 {{ $statusFilter === 'stale' ? 'bg-orange-600 border-orange-600 text-white shadow-sm' : 'bg-white border-slate-200 text-slate-700 hover:border-orange-300 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-300' }}">
            <div class="flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full bg-orange-400 {{ $statusFilter === 'stale' ? 'bg-white' : '' }}"></span>
                <span class="text-[10px] font-semibold uppercase tracking-wider {{ $statusFilter === 'stale' ? 'text-orange-100' : 'text-orange-700 dark:text-orange-400' }}">Stale Data</span>
            </div>
            <div class="flex items-baseline justify-between mt-0.5">
                <span class="text-lg font-bold text-orange-600 {{ $statusFilter === 'stale' ? '!text-white' : '' }}">{{ $summary['stale'] }}</span>
                <span class="text-[10px] {{ $statusFilter === 'stale' ? 'text-orange-100' : 'text-slate-400' }}">&gt; 2h old</span>
            </div>
        </button>

        {{-- Issues / Alerts --}}
        <button type="button"
                wire:click="setStatusFilter('issues')"
                class="flex flex-col p-2.5 rounded-xl border text-left transition-all duration-150 {{ $statusFilter === 'issues' ? 'bg-red-600 border-red-600 text-white shadow-sm' : 'bg-white border-slate-200 text-slate-700 hover:border-red-300 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-300' }}">
            <div class="flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full bg-red-500 {{ $statusFilter === 'issues' ? 'bg-white animate-pulse' : '' }}"></span>
                <span class="text-[10px] font-semibold uppercase tracking-wider {{ $statusFilter === 'issues' ? 'text-red-100' : 'text-red-700 dark:text-red-400' }}">Alerts</span>
            </div>
            <div class="flex items-baseline justify-between mt-0.5">
                <span class="text-lg font-bold text-red-600 {{ $statusFilter === 'issues' ? '!text-white' : '' }}">{{ $summary['issues'] }}</span>
                <span class="text-[10px] {{ $statusFilter === 'issues' ? 'text-red-100' : 'text-slate-400' }}">Expired/No Fix</span>
            </div>
        </button>

        {{-- Unmapped Trackers --}}
        <button type="button"
                wire:click="setStatusFilter('unmapped')"
                class="flex flex-col p-2.5 rounded-xl border text-left transition-all duration-150 {{ $statusFilter === 'unmapped' ? 'bg-purple-600 border-purple-600 text-white shadow-sm' : 'bg-white border-slate-200 text-slate-700 hover:border-purple-300 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-300' }}">
            <div class="flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full bg-purple-500 {{ $statusFilter === 'unmapped' ? 'bg-white' : '' }}"></span>
                <span class="text-[10px] font-semibold uppercase tracking-wider {{ $statusFilter === 'unmapped' ? 'text-purple-100' : 'text-purple-700 dark:text-purple-400' }}">Unmapped</span>
            </div>
            <div class="flex items-baseline justify-between mt-0.5">
                <span class="text-lg font-bold text-purple-600 {{ $statusFilter === 'unmapped' ? '!text-white' : '' }}">{{ $summary['unmapped'] }}</span>
                <span class="text-[10px] {{ $statusFilter === 'unmapped' ? 'text-purple-100' : 'text-slate-400' }}">Stand-alone</span>
            </div>
        </button>
    </div>

    {{-- Main 2-Column Interface: Leaflet Map (Left) & Vehicle Explorer (Right) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">

        {{-- Left / Map Column (8 cols) --}}
        <div class="lg:col-span-8 gps-layout-map rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col relative">

            {{-- Map Toolbar Overlay --}}
            <div class="absolute top-3 left-3 z-[1000] flex items-center gap-2 bg-white/95 dark:bg-slate-900/95 backdrop-blur-xs px-3 py-1.5 rounded-xl shadow-md border border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-300">
                <span class="h-2 w-2 rounded-full bg-blue-600 animate-ping"></span>
                <span>Active Map Pins: <strong x-text="visiblePinsCount">0</strong></span>
                <span class="text-slate-300 dark:text-slate-700">|</span>
                <span class="text-[11px] text-slate-500">Updated: {{ $lastRefreshedAt ?: 'Connecting...' }}</span>
            </div>

            {{-- Leaflet Map Canvas (wire:ignore ensures DOM stays intact across Livewire refreshes) --}}
            <div id="fleet-live-map" wire:ignore class="w-full z-10" style="min-height: 650px; height: 650px;"></div>

            {{-- Map Legend Bar at Bottom --}}
            <div class="p-3 bg-slate-50 dark:bg-slate-950/60 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between text-xs text-slate-600 dark:text-slate-400 gap-2">
                <div class="flex items-center gap-4">
                    <span class="font-bold text-slate-700 dark:text-slate-200">Legend:</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Moving (&ge;3 km/h)</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Idling (Engine ON)</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span> Parked (Engine OFF)</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-400"></span> Offline / Sleeping</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-purple-500"></span> Unmapped Tracker</span>
                </div>
                <div class="text-[11px] text-slate-400">
                    Click pin to inspect vehicle telematics
                </div>
            </div>
        </div>

        {{-- Right / Vehicle Explorer & Telematics Panel (4 cols) --}}
        <div class="lg:col-span-4 gps-layout-sidebar space-y-3 flex flex-col">

            {{-- Search & Controls Card --}}
            <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-3.5 shadow-sm space-y-2.5">
                {{-- Search Box --}}
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                    <input type="text"
                           wire:model.live.debounce.250ms="search"
                           placeholder="Filter equipment, plate, driver, IMEI..."
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2 pl-9 pr-3 text-xs text-slate-800 placeholder:text-slate-400 focus:bg-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100" />
                    @if($search)
                        <button type="button" wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                            &times;
                        </button>
                    @endif
                </div>

                {{-- Filter & Unmapped Toggle Row --}}
                <div class="flex items-center justify-between text-xs text-slate-500">
                    <div class="flex items-center gap-1.5">
                        <span class="font-bold text-slate-700 dark:text-slate-200">{{ count($vehicles) }}</span>
                        <span>{{ Str::plural('unit', count($vehicles)) }} shown</span>
                    </div>

                    <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                        <input type="checkbox" wire:model.live="showUnmapped" class="rounded border-slate-300 text-blue-600 shadow-xs focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-800" />
                        <span class="text-[11px] font-medium text-slate-600 dark:text-slate-400">Show Unmapped</span>
                    </label>
                </div>
            </div>

            {{-- Selected Vehicle Detail Inspection Card (if selected) --}}
            @if ($selectedItem)
                <div class="rounded-2xl bg-blue-50/60 dark:bg-blue-950/40 border-2 border-blue-300 dark:border-blue-800 p-4 shadow-sm space-y-3 relative">
                    <button type="button"
                            wire:click="clearSelection"
                            class="absolute top-3 right-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-sm font-bold">
                        &times;
                    </button>

                    <div class="flex items-start justify-between pr-6">
                        <div>
                            <span class="text-[10px] font-bold text-blue-700 dark:text-blue-300 uppercase tracking-wider">{{ $selectedItem['vehicle_type'] }}</span>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $selectedItem['equipment_code'] }}</h3>
                            <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $selectedItem['plate_number'] }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold
                            {{ $selectedItem['state'] === 'moving' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300' : '' }}
                            {{ $selectedItem['state'] === 'idling' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300' : '' }}
                            {{ $selectedItem['state'] === 'parked' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300' : '' }}
                            {{ $selectedItem['state'] === 'offline' ? 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300' : '' }}
                        ">
                            <span class="h-1.5 w-1.5 rounded-full {{ $selectedItem['state'] === 'moving' ? 'bg-emerald-600 animate-ping' : ($selectedItem['state'] === 'idling' ? 'bg-amber-600' : ($selectedItem['state'] === 'parked' ? 'bg-blue-600' : 'bg-slate-500')) }}"></span>
                            {{ $selectedItem['state_label'] }}
                        </span>
                    </div>

                    {{-- Metrics Grid --}}
                    <div class="grid grid-cols-2 gap-2 text-xs bg-white dark:bg-slate-900 p-3 rounded-xl border border-blue-100 dark:border-blue-900">
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase tracking-wider block">Speed</span>
                            <span class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $selectedItem['speed'] }} km/h</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase tracking-wider block">Ignition (ACC)</span>
                            <span class="text-xs font-bold {{ $selectedItem['is_ignition_on'] ? 'text-amber-600' : 'text-slate-500' }}">
                                {{ $selectedItem['is_ignition_on'] ? '⚡ Engine ON' : '⭕ Engine OFF' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase tracking-wider block">Driver</span>
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-300 truncate block">{{ $selectedItem['operator_driver'] }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase tracking-wider block">Last GPS Fix</span>
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-300 truncate block">{{ $selectedItem['last_contact'] }}</span>
                        </div>
                    </div>

                    {{-- Coordinates & Details --}}
                    <div class="space-y-1 text-xs text-slate-600 dark:text-slate-400">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Coordinates:</span>
                            @if($selectedItem['has_coords'])
                                <span class="font-mono text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                    {{ round($selectedItem['lat'], 5) }}, {{ round($selectedItem['lng'], 5) }}
                                </span>
                            @else
                                <span class="text-amber-600 font-semibold">No fix available</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">GPS Tracker:</span>
                            <span class="font-medium text-slate-700 dark:text-slate-300">{{ $selectedItem['device_name'] ?: 'N/A' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">IMEI:</span>
                            <span class="font-mono text-[11px] text-slate-500">{{ $selectedItem['imei'] ?: 'Unassigned' }}</span>
                        </div>
                    </div>

                    {{-- Issues if any --}}
                    @if(!empty($selectedItem['issues']))
                        <div class="space-y-1">
                            @foreach($selectedItem['issues'] as $issue)
                                <div class="flex items-center gap-1.5 text-[11px] text-amber-700 dark:text-amber-300 font-medium">
                                    <svg class="h-3.5 w-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                    </svg>
                                    <span>{{ $issue }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Actions --}}
                    <div class="pt-1 flex items-center justify-between border-t border-blue-200/60 dark:border-blue-800/60">
                        @if($selectedItem['has_coords'])
                            <button type="button"
                                    @click="focusCoords({{ $selectedItem['lat'] }}, {{ $selectedItem['lng'] }})"
                                    class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                </svg>
                                Zoom to Unit
                            </button>
                        @endif

                        @if($selectedItem['id'])
                            <a href="{{ route('vehicles.show', $selectedItem['id']) }}"
                               target="_blank"
                               class="inline-flex items-center gap-1 text-xs font-bold text-slate-700 hover:text-slate-900 dark:text-slate-300">
                                <span>Vehicle Profile &rarr;</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Scrollable Vehicle List --}}
            <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex-1 flex flex-col overflow-hidden max-h-[500px]">
                <div class="p-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Fleet Units List</span>
                    <span class="text-[10px] text-slate-400">Click unit to focus</span>
                </div>

                <div class="divide-y divide-slate-100 dark:divide-slate-800 overflow-y-auto flex-1">
                    @forelse ($vehicles as $item)
                        <div wire:key="vehicle-item-{{ $item['id'] ?? $item['imei'] }}"
                             wire:click="{{ $item['is_unmapped'] ? "selectUnmappedDevice('{$item['imei']}')" : "selectVehicle({$item['id']})" }}"
                             @click="onVehicleItemClicked({{ json_encode($item) }})"
                             class="p-3 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors cursor-pointer flex items-center justify-between gap-2.5 {{ ($selectedVehicleId === $item['id'] || $selectedImei === $item['imei']) ? 'bg-blue-50/80 dark:bg-blue-950/60 border-l-4 border-blue-600' : '' }}">
                            
                            {{-- State Color Pip & Unit Info --}}
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="h-3 w-3 rounded-full flex-shrink-0
                                    {{ $item['state'] === 'moving' ? 'bg-emerald-500 animate-pulse' : '' }}
                                    {{ $item['state'] === 'idling' ? 'bg-amber-500' : '' }}
                                    {{ $item['state'] === 'parked' ? 'bg-blue-500' : '' }}
                                    {{ $item['state'] === 'offline' ? 'bg-slate-300 dark:bg-slate-700' : '' }}
                                    {{ $item['is_unmapped'] ? 'bg-purple-500' : '' }}
                                " title="{{ $item['state_label'] }}"></span>

                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <p class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate">{{ $item['equipment_code'] }}</p>
                                        @if($item['is_unmapped'])
                                            <span class="text-[9px] bg-purple-100 text-purple-700 dark:bg-purple-900/60 dark:text-purple-300 px-1.5 py-0.2 rounded-full font-semibold">Unmapped</span>
                                        @endif
                                    </div>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ $item['plate_number'] }} &bull; {{ $item['operator_driver'] }}</p>
                                </div>
                            </div>

                            {{-- Speed / Status Badge & GPS indicator --}}
                            <div class="text-right flex-shrink-0">
                                @if($item['state'] === 'moving')
                                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 block">{{ $item['speed'] }} km/h</span>
                                @else
                                    <span class="text-[11px] font-semibold text-slate-600 dark:text-slate-400 block">{{ $item['state_label'] }}</span>
                                @endif

                                <div class="flex items-center justify-end gap-1 mt-0.5">
                                    @if($item['has_coords'])
                                        <span class="text-[9px] text-slate-400 font-mono">{{ $item['last_contact'] }}</span>
                                    @else
                                        <span class="text-[9px] text-amber-600 dark:text-amber-400 font-bold">No Fix</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-xs text-slate-500 dark:text-slate-400">
                            No vehicles or GPS units match current filters.
                        </div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            function gpsLiveMap(config) {
                return {
                    vehicles: config.vehicles || [],
                    map: null,
                    markers: {},
                    visiblePinsCount: 0,

                    initMap() {
                        const mapEl = document.getElementById('fleet-live-map');
                        if (!mapEl || this.map) return;

                        // Tile layers
                        const googleHybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
                            maxZoom: 20,
                            attribution: '&copy; Google Maps Satellite'
                        });

                        const googleStreets = L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
                            maxZoom: 20,
                            attribution: '&copy; Google Maps Streets'
                        });

                        const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; OpenStreetMap contributors'
                        });

                        const esriSatellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                            maxZoom: 19,
                            attribution: '&copy; Esri World Imagery'
                        });

                        // Default center: Northern Mindanao (Cagayan de Oro) [8.48, 124.65]
                        this.map = L.map('fleet-live-map', {
                            center: [8.48, 124.65],
                            zoom: 11,
                            layers: [googleHybrid]
                        });

                        const baseMaps = {
                            "🛰️ Google Satellite": googleHybrid,
                            "🗺️ Google Streets": googleStreets,
                            "🗺️ OpenStreetMap": osmLayer,
                            "🛰️ Esri Satellite": esriSatellite
                        };
                        L.control.layers(baseMaps, null, { position: 'topright' }).addTo(this.map);

                        // Render markers
                        this.renderMarkers(this.vehicles);

                        // Fit bounds on initial load
                        this.fitBounds();

                        // Invalidate size after render to ensure full dimensions are applied
                        setTimeout(() => {
                            if (this.map) {
                                this.map.invalidateSize();
                                this.fitBounds();
                            }
                        }, 250);

                        window.addEventListener('resize', () => {
                            if (this.map) {
                                this.map.invalidateSize();
                            }
                        });

                        // Listen to Livewire refresh events
                        window.addEventListener('gps-locations-refreshed', (event) => {
                            if (event.detail && event.detail.vehicles) {
                                this.vehicles = event.detail.vehicles;
                                this.renderMarkers(this.vehicles);
                            }
                        });

                        window.addEventListener('focus-vehicle-on-map', (event) => {
                            const vId = event.detail.vehicleId;
                            this.focusItemByKey('v-' + vId);
                        });

                        window.addEventListener('focus-device-on-map', (event) => {
                            const imei = event.detail.imei;
                            this.focusItemByKey('d-' + imei);
                        });
                    },

                    renderMarkers(items) {
                        if (!this.map) return;

                        const currentKeys = new Set();
                        let count = 0;

                        items.forEach(item => {
                            if (!item.has_coords || item.lat === null || item.lng === null) {
                                return;
                            }

                            const key = item.id ? ('v-' + item.id) : ('d-' + item.imei);
                            currentKeys.add(key);
                            count++;

                            const icon = this.createMarkerIcon(item);

                            if (this.markers[key]) {
                                // Update position and icon
                                this.markers[key].setLatLng([item.lat, item.lng]);
                                this.markers[key].setIcon(icon);
                                this.markers[key].setPopupContent(this.createPopupContent(item));
                            } else {
                                // Create new marker
                                const marker = L.marker([item.lat, item.lng], { icon: icon }).addTo(this.map);
                                marker.bindPopup(this.createPopupContent(item));
                                marker.on('click', () => {
                                    if (item.id) {
                                        @this.selectVehicle(item.id);
                                    } else {
                                        @this.selectUnmappedDevice(item.imei);
                                    }
                                });
                                this.markers[key] = marker;
                            }
                        });

                        // Remove markers no longer in filtered set
                        Object.keys(this.markers).forEach(k => {
                            if (!currentKeys.has(k)) {
                                this.map.removeLayer(this.markers[k]);
                                delete this.markers[k];
                            }
                        });

                        this.visiblePinsCount = count;
                    },

                    createMarkerIcon(item) {
                        let bgColor = '#64748b'; // slate
                        let pulseHtml = '';
                        let glyph = '';

                        if (item.is_unmapped) {
                            bgColor = '#9333ea'; // purple
                            glyph = `<svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>`;
                        } else if (item.state === 'moving') {
                            bgColor = '#10b981'; // emerald
                            pulseHtml = `<div class="absolute -inset-1 rounded-full bg-emerald-400 opacity-60 animate-ping"></div>`;
                            // Rotating heading arrow
                            glyph = `<svg class="w-4 h-4 text-white" style="transform: rotate(${item.direction || 0}deg);" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z"/></svg>`;
                        } else if (item.state === 'idling') {
                            bgColor = '#f59e0b'; // amber
                            glyph = `<svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>`;
                        } else if (item.state === 'parked') {
                            bgColor = '#2563eb'; // blue
                            glyph = `<span class="text-white text-[10px] font-black">P</span>`;
                        } else {
                            bgColor = '#64748b'; // slate
                            glyph = `<svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636a9 9 0 0 1 0 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 0 1 0 7.072m0 0l-2.829-2.829m-4.243 4.243a9 9 0 0 1-2.828-6.364 9 9 0 0 1 2.828-6.364m2.829 2.829a5 5 0 0 0 0 7.072" /></svg>`;
                        }

                        const html = `
                            <div class="relative flex items-center justify-center">
                                ${pulseHtml}
                                <div style="background-color: ${bgColor};" class="relative flex items-center justify-center h-8 w-8 rounded-full shadow-lg border-2 border-white ring-1 ring-black/10">
                                    ${glyph}
                                </div>
                                <div class="absolute -bottom-4 whitespace-nowrap bg-slate-900/90 text-white font-bold text-[9px] px-1.5 py-0.2 rounded-md shadow-sm pointer-events-none">
                                    ${item.equipment_code}
                                </div>
                            </div>
                        `;

                        return L.divIcon({
                            className: 'custom-gps-pin',
                            html: html,
                            iconSize: [32, 32],
                            iconAnchor: [16, 16],
                            popupAnchor: [0, -20]
                        });
                    },

                    createPopupContent(item) {
                        return `
                            <div style="font-family: inherit; width: 240px; padding: 12px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; margin-bottom: 8px;">
                                    <div>
                                        <div style="font-size: 9px; font-weight: 800; color: #2563eb; text-transform: uppercase;">${item.vehicle_type}</div>
                                        <div style="font-size: 14px; font-weight: 800; color: #0f172a; line-height: 1.2;">${item.equipment_code}</div>
                                        <div style="font-size: 11px; font-weight: 600; color: #64748b;">${item.plate_number}</div>
                                    </div>
                                    <span style="font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 9999px; background: ${item.state === 'moving' ? '#d1fae5; color: #065f46;' : (item.state === 'idling' ? '#fef3c7; color: #92400e;' : (item.state === 'parked' ? '#dbeafe; color: #1e40af;' : '#f1f5f9; color: #475569;'))}">
                                        ${item.state_label}
                                    </span>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-size: 11px; margin-bottom: 8px;">
                                    <div>
                                        <span style="font-size: 9px; color: #94a3b8; text-transform: uppercase; display: block;">Speed</span>
                                        <strong style="color: #0f172a; font-size: 13px;">${item.speed} km/h</strong>
                                    </div>
                                    <div>
                                        <span style="font-size: 9px; color: #94a3b8; text-transform: uppercase; display: block;">Ignition</span>
                                        <strong style="color: ${item.is_ignition_on ? '#d97706' : '#64748b'};">${item.is_ignition_on ? '⚡ Engine ON' : '⭕ Engine OFF'}</strong>
                                    </div>
                                    <div style="grid-column: span 2;">
                                        <span style="font-size: 9px; color: #94a3b8; text-transform: uppercase; display: block;">Driver</span>
                                        <span style="color: #334155; font-weight: 600;">${item.operator_driver}</span>
                                    </div>
                                    <div style="grid-column: span 2;">
                                        <span style="font-size: 9px; color: #94a3b8; text-transform: uppercase; display: block;">Last Fix</span>
                                        <span style="color: #475569;">${item.last_contact}</span>
                                    </div>
                                </div>

                                <div style="border-top: 1px solid #f1f5f9; padding-top: 8px; font-size: 10px; color: #64748b; display: flex; align-items: center; justify-content: space-between;">
                                    <span>${item.lat.toFixed(5)}, ${item.lng.toFixed(5)}</span>
                                    ${item.id ? `<a href="/vehicles/${item.id}" target="_blank" style="color: #2563eb; font-weight: 700; text-decoration: none;">View &rarr;</a>` : ''}
                                </div>
                            </div>
                        `;
                    },

                    fitBounds() {
                        if (!this.map) return;
                        const bounds = [];
                        Object.values(this.markers).forEach(m => {
                            bounds.push(m.getLatLng());
                        });

                        if (bounds.length > 0) {
                            this.map.fitBounds(bounds, { padding: [50, 50], maxZoom: 15 });
                        }
                    },

                    focusCoords(lat, lng) {
                        if (!this.map || !lat || !lng) return;
                        this.map.flyTo([lat, lng], 16, { animate: true, duration: 1.0 });
                    },

                    focusItemByKey(key) {
                        const marker = this.markers[key];
                        if (marker && this.map) {
                            this.map.flyTo(marker.getLatLng(), 16, { animate: true, duration: 1.0 });
                            marker.openPopup();
                        }
                    },

                    onVehicleItemClicked(item) {
                        if (item.has_coords && item.lat && item.lng) {
                            this.focusCoords(item.lat, item.lng);
                            const key = item.id ? ('v-' + item.id) : ('d-' + item.imei);
                            const marker = this.markers[key];
                            if (marker) {
                                marker.openPopup();
                            }
                        }
                    }
                };
            }
        </script>
    @endpush
</div>
