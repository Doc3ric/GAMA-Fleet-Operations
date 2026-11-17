<x-app-layout>
    @section('page-title', 'Fleet Dispatch Monitoring')

    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            .map-container {
                height: 420px;
                width: 100%;
                border-radius: 1rem;
                z-index: 10;
            }
            .dispatch-route-map {
                height: 390px;
            }
            .dispatch-route-map-expanded {
                height: 70vh;
            }
            @media (min-width: 1024px) {
                .dispatch-route-map {
                    height: 590px;
                }
            }
            .leaflet-popup-content-wrapper {
                border-radius: 0.75rem;
                padding: 4px;
                font-family: inherit;
            }
        </style>
    @endpush

    <div class="p-6 space-y-6 max-w-7xl mx-auto" x-data="fleetDispatchManager({
        itineraries: {{ Js::from($approvedItineraries) }},
        vehicles: {{ Js::from($vehicles) }},
        locations: {{ Js::from($locations) }},
        calculateRouteUrl: '{{ route('fleet-dispatches.calculate-route') }}',
        searchPlacesUrl: '{{ route('locations.searchPlaces') }}',
        reverseGeocodeUrl: '{{ route('locations.reverseGeocode') }}',
        csrfToken: '{{ csrf_token() }}'
    })">

        {{-- Top Notification Banner --}}
        @if (session('status'))
            <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800">&times;</button>
            </div>
        @endif

        {{-- Header & Actions --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Fleet Dispatch Monitoring</h1>
                    <span class="inline-flex items-center rounded-full bg-blue-500/10 px-2.5 py-0.5 text-xs font-semibold text-blue-600 dark:text-blue-400 border border-blue-500/20">
                        Live GPS Monitoring
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Multi-stop route management, TrackSolid GPS cross-track compliance, departure grace controls, and automated alert detection.
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                {{-- Run Monitoring Sweep Button --}}
                <form action="{{ route('fleet-dispatches.run-monitoring') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" 
                            title="Run instant TrackSolid GPS compliance check for active dispatches"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/30 hover:bg-blue-100 px-3.5 py-2 text-xs font-bold text-blue-700 dark:text-blue-300 shadow-2xs transition-colors">
                        <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Check GPS Compliance
                    </button>
                </form>

                <a href="{{ route('fleet-dispatches.export-excel', request()->query()) }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 shadow-xs transition-colors">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    Export Excel
                </a>
                <a href="{{ route('fleet-dispatches.export-pdf', request()->query()) }}" target="_blank"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 shadow-xs transition-colors">
                    <svg class="h-4 w-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6" />
                    </svg>
                    Export PDF
                </a>
                @can('create', App\Models\FleetDispatch::class)
                    <button type="button" @click="openCreateModal()"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 px-4 py-2 text-xs font-bold text-white shadow-sm transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Create New Fleet Dispatch
                    </button>
                @endcan
            </div>
        </div>

        {{-- Top KPI & Alert Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            {{-- KPI Summary Stats (7 cols) --}}
            <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                {{-- Rate Card --}}
                <div class="sm:col-span-2 rounded-2xl bg-gradient-to-br from-blue-900 via-indigo-900 to-slate-900 text-white p-5 shadow-sm border border-blue-800/50 flex flex-col justify-between relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-blue-200/80">On-Time Dispatch Rate</span>
                            <div class="text-[10px] text-blue-300/70 mt-0.5">Eligible On-Time &divide; Total &times; 100</div>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $statistics['on_time_rate'] >= 90 ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : ($statistics['on_time_rate'] >= 75 ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-red-500/20 text-red-300 border border-red-500/30') }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $statistics['on_time_rate'] >= 90 ? 'bg-emerald-400' : ($statistics['on_time_rate'] >= 75 ? 'bg-amber-400' : 'bg-red-400') }}"></span>
                            {{ $statistics['on_time_rate'] >= 90 ? 'Optimal' : ($statistics['on_time_rate'] >= 75 ? 'Acceptable' : 'Needs Attention') }}
                        </span>
                    </div>
                    <div class="my-3 flex items-baseline gap-3">
                        <span class="text-4xl font-black text-white">{{ number_format($statistics['on_time_rate'], 1) }}%</span>
                        <span class="text-xs text-blue-200">
                            {{ $statistics['on_time_count'] }} of {{ $statistics['eligible_count'] }} departures
                        </span>
                    </div>
                    <div class="w-full bg-slate-800/80 rounded-full h-2 overflow-hidden border border-slate-700/60">
                        <div class="h-2 rounded-full {{ $statistics['on_time_rate'] >= 90 ? 'bg-emerald-400' : ($statistics['on_time_rate'] >= 75 ? 'bg-amber-400' : 'bg-red-400') }} transition-all duration-500"
                             style="width: {{ min(100, $statistics['on_time_rate']) }}%"></div>
                    </div>
                </div>

                {{-- On-Time --}}
                <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-200 dark:border-slate-800 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">On-Time</span>
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2">
                        <span class="text-2xl font-black text-slate-900 dark:text-white">{{ $statistics['on_time_count'] }}</span>
                        <p class="text-[10px] text-slate-400 mt-0.5">&le; 15m grace window</p>
                    </div>
                </div>

                {{-- Delayed --}}
                <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-200 dark:border-slate-800 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Delayed</span>
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-red-500/10 text-red-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2">
                        <span class="text-2xl font-black text-red-600">{{ $statistics['delayed_count'] }}</span>
                        <p class="text-[10px] text-slate-400 mt-0.5">Exceeded grace period</p>
                    </div>
                </div>
            </div>

            {{-- NOTIFICATION LIST THAT THIS VEHICLE IS NOT FOLLOW THE ASSIGNED VEHICLES (5 cols) --}}
            {{-- Matches the upper-right notification box from your workflow diagram --}}
            <div class="lg:col-span-5 rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-200 dark:border-slate-800 flex flex-col">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="relative flex h-2.5 w-2.5">
                            @if($activeAlerts->where('status', 'CONFIRMED')->isNotEmpty())
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                            @else
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            @endif
                        </span>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white">
                            Active Dispatch Compliance Alerts
                        </h2>
                    </div>
                    <span class="rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:text-slate-300">
                        {{ $activeAlerts->count() }} Active
                    </span>
                </div>

                <div class="mt-3 flex-1 overflow-y-auto max-h-56 divide-y divide-slate-100 dark:divide-slate-800 pr-1 space-y-2">
                    @forelse($activeAlerts as $alert)
                        @php
                            $isConfirmed = $alert->isConfirmed();
                            $badgeClass = $isConfirmed 
                                ? 'bg-red-500/10 text-red-600 border-red-500/20' 
                                : 'bg-amber-500/10 text-amber-600 border-amber-500/20';
                            $veh = $alert->vehicle;
                            $disp = $alert->dispatch;
                        @endphp
                        <div class="pt-2 text-xs">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-slate-900 dark:text-white">{{ $veh?->equipment_code ?? 'Vehicle' }}</span>
                                    <span class="text-[10px] font-mono text-slate-400">({{ $veh?->plate_number ?? 'No Plate' }})</span>
                                    <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-bold border {{ $badgeClass }}">
                                        {{ $alert->status }}
                                    </span>
                                </div>
                                <span class="text-[10px] text-slate-400">{{ $alert->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-[11px] text-slate-600 dark:text-slate-300 mt-0.5">
                                {{ $alert->description }}
                            </p>
                            <div class="mt-2 flex items-center justify-between text-[10px]">
                                <span class="font-mono text-slate-400">Trip: {{ $disp?->trip_number ?? 'N/A' }}</span>
                                <div class="flex items-center gap-2">
                                    @if($disp)
                                        <button type="button" @click="viewLiveTrack({{ $disp->id }})"
                                                class="font-bold text-blue-600 hover:text-blue-700 dark:text-blue-400 cursor-pointer">
                                            Live Track &rarr;
                                        </button>
                                    @endif
                                    <form action="{{ route('fleet-dispatches.events.resolve', $alert) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-slate-400 hover:text-slate-600 font-semibold cursor-pointer">
                                            Resolve
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-slate-400 text-xs">
                            <svg class="h-6 w-6 text-emerald-500 mx-auto mb-1.5 opacity-80" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            All active vehicles are following their assigned routes and departure schedules.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Filter Bar --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-200 dark:border-slate-800">
            <form method="GET" action="{{ route('fleet-dispatches.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3 items-end">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Start Date</label>
                    <input type="date" name="start_date" value="{{ $filters['start_date'] }}"
                           class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs font-medium text-slate-800 dark:text-slate-100 outline-none">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">End Date</label>
                    <input type="date" name="end_date" value="{{ $filters['end_date'] }}"
                           class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs font-medium text-slate-800 dark:text-slate-100 outline-none">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Vehicle</label>
                    <select name="vehicle_id" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs font-medium text-slate-800 dark:text-slate-100 outline-none">
                        <option value="">All Vehicles</option>
                        @foreach($vehicles as $veh)
                            <option value="{{ $veh->id }}" {{ $filters['vehicle_id'] == $veh->id ? 'selected' : '' }}>
                                {{ $veh->equipment_code }} {{ $veh->plate_number ? "({$veh->plate_number})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Punctuality</label>
                    <select name="status" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs font-medium text-slate-800 dark:text-slate-100 outline-none">
                        <option value="all">All Punctuality</option>
                        <option value="ON_TIME" {{ $filters['status'] === 'ON_TIME' ? 'selected' : '' }}>On Time</option>
                        <option value="DELAYED" {{ $filters['status'] === 'DELAYED' ? 'selected' : '' }}>Delayed</option>
                        <option value="PENDING" {{ $filters['status'] === 'PENDING' ? 'selected' : '' }}>Pending</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Monitoring Status</label>
                    <select name="monitoring_status" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs font-medium text-slate-800 dark:text-slate-100 outline-none">
                        <option value="all">All Compliance</option>
                        <option value="NORMAL" {{ ($filters['monitoring_status'] ?? '') === 'NORMAL' ? 'selected' : '' }}>Normal / In Corridor</option>
                        <option value="POSSIBLE_EXCEPTION" {{ ($filters['monitoring_status'] ?? '') === 'POSSIBLE_EXCEPTION' ? 'selected' : '' }}>Possible Exception</option>
                        <option value="CONFIRMED_EXCEPTION" {{ ($filters['monitoring_status'] ?? '') === 'CONFIRMED_EXCEPTION' ? 'selected' : '' }}>Confirmed Exception</option>
                        <option value="GPS_UNAVAILABLE" {{ ($filters['monitoring_status'] ?? '') === 'GPS_UNAVAILABLE' ? 'selected' : '' }}>GPS Unavailable</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="flex-1 rounded-xl bg-slate-800 dark:bg-slate-700 hover:bg-slate-900 px-3 py-2 text-xs font-bold text-white transition-colors cursor-pointer">
                        Filter
                    </button>
                    <a href="{{ route('fleet-dispatches.index') }}" class="rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- LIST OF THE DEVICE THAT ALREADY SAVED WITH DATA TABLE AND ACTIONS FUNCTIONS --}}
        {{-- Matches the saved records table from your workflow diagram --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Saved Fleet Dispatch Records</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Assigned vehicles, multi-stop itineraries, real-time GPS compliance, and operational departures.</p>
                </div>
                <span class="text-xs font-semibold text-slate-500">{{ $dispatches->total() }} records</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Trip Reference</th>
                            <th class="px-4 py-3 font-semibold">Assigned Vehicle</th>
                            <th class="px-4 py-3 font-semibold">Driver</th>
                            <th class="px-4 py-3 font-semibold">Route & Destinations</th>
                            <th class="px-4 py-3 font-semibold">Planned Dep.</th>
                            <th class="px-4 py-3 font-semibold">Actual Dep.</th>
                            <th class="px-4 py-3 font-semibold">GPS Compliance</th>
                            <th class="px-4 py-3 font-semibold">Punctuality</th>
                            <th class="px-4 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse($dispatches as $dispatch)
                            @php
                                $veh = $dispatch->vehicle;
                                $statusBadge = match($dispatch->dispatch_status) {
                                    'ON_TIME' => 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
                                    'DELAYED' => 'bg-red-500/10 text-red-600 border-red-500/20 font-bold',
                                    'PENDING' => 'bg-sky-500/10 text-sky-600 border-sky-500/20',
                                    default => 'bg-slate-500/10 text-slate-500 border-slate-500/20',
                                };
                                $monitorBadge = match($dispatch->monitoring_status) {
                                    'CONFIRMED_EXCEPTION' => 'bg-red-500/10 text-red-600 border-red-500/30 font-bold',
                                    'POSSIBLE_EXCEPTION' => 'bg-amber-500/10 text-amber-600 border-amber-500/30',
                                    'GPS_UNAVAILABLE' => 'bg-slate-500/10 text-slate-500 border-slate-500/20',
                                    default => 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-4 py-3.5 font-mono font-bold text-slate-900 dark:text-white">
                                    {{ $dispatch->trip_number }}
                                    <div class="text-[10px] font-sans text-slate-400">{{ $dispatch->trip_date->format('M d, Y') }}</div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                                        <span>{{ $veh?->equipment_code ?? '—' }}</span>
                                        @if($veh?->hasGps())
                                            <span class="inline-flex items-center rounded-sm bg-blue-100 dark:bg-blue-900/40 px-1 py-0.2 text-[9px] font-bold text-blue-700 dark:text-blue-300">GPS</span>
                                        @else
                                            <span class="inline-flex items-center rounded-sm bg-slate-100 dark:bg-slate-800 px-1 py-0.2 text-[9px] text-slate-400">NO GPS</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-500 font-mono">{{ $veh?->plate_number ?? 'No Plate' }}</div>
                                </td>
                                <td class="px-4 py-3.5 text-slate-700 dark:text-slate-300">
                                    {{ $dispatch->driver_name ?: ($veh?->operator_driver ?: '—') }}
                                </td>
                                <td class="px-4 py-3.5 max-w-xs">
                                    <div class="font-semibold text-slate-800 dark:text-slate-200 truncate" title="{{ $dispatch->destination }}">
                                        {{ $dispatch->destination }}
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        From: {{ $dispatch->starting_point }} &bull; {{ number_format($dispatch->planned_distance_km, 1) }} KM
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-slate-700 dark:text-slate-300">
                                    <span class="font-semibold">{{ $dispatch->planned_departure_time->format('h:i A') }}</span>
                                </td>
                                <td class="px-4 py-3.5">
                                    @if($dispatch->actual_departure_time)
                                        <span class="font-bold text-slate-900 dark:text-white">{{ $dispatch->actual_departure_time->format('h:i A') }}</span>
                                    @else
                                        <span class="text-slate-400 italic">Not departed</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold border {{ $monitorBadge }}">
                                        {{ str_replace('_', ' ', $dispatch->monitoring_status ?? 'NORMAL') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold border {{ $statusBadge }}">
                                        {{ str_replace('_', ' ', $dispatch->dispatch_status) }}
                                    </span>
                                    @if($dispatch->delay_minutes > 0)
                                        <div class="text-[10px] text-red-500 font-bold mt-0.5">+{{ $dispatch->delay_minutes }} min</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right space-x-1 whitespace-nowrap">
                                    {{-- Live Track Button --}}
                                    <button type="button" @click="viewLiveTrack({{ $dispatch->id }})"
                                            title="View planned route vs TrackSolid GPS telemetry"
                                            class="rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 px-2 py-1 text-[11px] font-bold transition-colors cursor-pointer">
                                        Live Track
                                    </button>

                                    @can('recordDeparture', $dispatch)
                                        @if(!$dispatch->actual_departure_time)
                                            <form action="{{ route('fleet-dispatches.depart-now', $dispatch) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" title="Mark vehicle departed right now"
                                                        class="rounded-lg bg-emerald-600 hover:bg-emerald-700 px-2 py-1 text-[11px] font-bold text-white shadow-2xs transition-colors cursor-pointer">
                                                    Depart
                                                </button>
                                            </form>
                                        @endif
                                        <button type="button" @click="openDeparture({{ Js::from($dispatch) }})"
                                                class="rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 px-2 py-1 text-[11px] font-bold text-slate-700 dark:text-slate-200 transition-colors cursor-pointer">
                                            {{ $dispatch->actual_departure_time ? 'Edit Dep.' : 'Custom Dep.' }}
                                        </button>
                                    @endcan

                                    @if($dispatch->reconciliation)
                                        <a href="{{ route('mileage-reconciliations.show', $dispatch->reconciliation) }}"
                                           class="rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 hover:bg-blue-100 px-2 py-1 text-[11px] font-bold transition-colors">
                                            Reconcile &rarr;
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                                    No dispatch records found. Click <strong>"Create New Fleet Dispatch"</strong> to build a multi-stop itinerary and schedule a departure.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($dispatches->hasPages())
                <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                    {{ $dispatches->links() }}
                </div>
            @endif
        </div>

        {{-- WORKFLOW MODAL: CREATE NEW FLEET DISPATCH ROUTE BUILDER --}}
        {{-- Exactly follows your attached Fleet Dispatch workflow diagram: --}}
        {{-- 1. Create -> 2. Choose Date & Vehicle -> 3. Origin (Manual + Map) -> 4. Intermediate Destinations (Add multiple times) -> 5. Final Dest (Manual + Map) -> 6. Calculate All Distance -> 7. Save --}}
        <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-xs">
            <div class="w-full max-w-7xl rounded-2xl bg-white dark:bg-slate-900 shadow-2xl border border-slate-200 dark:border-slate-800 p-6 space-y-5 max-h-[92vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>Create Fleet Dispatch</span>
                            <span class="rounded-full bg-blue-50 dark:bg-blue-950 px-2.5 py-0.5 text-[10px] font-bold text-blue-600 border border-blue-200">
                                Multi-Stop Route Workflow
                            </span>
                        </h3>
                        <p class="text-xs text-slate-500">Assign vehicle, configure multi-stop itinerary with interactive map pinning, and calculate route distance.</p>
                    </div>
                    <button type="button" @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <form action="{{ route('fleet-dispatches.store') }}" method="POST" class="space-y-5" @submit="onFormSubmit($event)">
                    @csrf
                    <input type="hidden" name="route_result" x-model="routeResultJson">

                    {{-- DIAGRAM STEP 1: CHOOSE DATE & CHOOSE VEHICLE --}}
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/60 space-y-3">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white">1</span>
                            <span>Choose Schedule & Vehicle Assignment</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Itinerary Date *
                                </label>
                                <input type="date" name="trip_date" x-model="tripDate" required
                                       class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-800 dark:text-white outline-none focus:border-blue-500">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Assigned Company Vehicle *
                                </label>
                                <select name="vehicle_id" x-model="vehicleId" @change="onVehicleChange()" required
                                        class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-800 dark:text-white outline-none focus:border-blue-500">
                                    <option value="">-- Choose Vehicle (e.g. BT7) --</option>
                                    @foreach($vehicles as $v)
                                        <option value="{{ $v->id }}">
                                            {{ $v->equipment_code }} &bull; {{ $v->plate_number ?: 'No Plate' }} {{ $v->hasGps() ? '(GPS TrackSolid)' : '(Non-GPS)' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Assigned Vehicle Confirmation Pill (Matches diagram's "Assigned Vehicle: BT7") --}}
                        <template x-if="selectedVehicle">
                            <div class="flex items-center justify-between rounded-lg bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 px-3 py-2 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-blue-900 dark:text-blue-300">Assigned Vehicle:</span>
                                    <span class="font-mono font-bold text-blue-700 dark:text-blue-400" x-text="selectedVehicle.equipment_code"></span>
                                    <span class="text-slate-500" x-text="selectedVehicle.plate_number ? `(${selectedVehicle.plate_number})` : ''"></span>
                                </div>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                      :class="selectedVehicle.has_gps ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                                      x-text="selectedVehicle.has_gps ? 'TrackSolid GPS Active' : 'No GPS Unit'"></span>
                            </div>
                        </template>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Assigned Driver</label>
                                <input type="text" name="driver_name" x-model="driverName" placeholder="Driver / Operator Name"
                                       class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-800 dark:text-white outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Planned Departure Time *</label>
                                <input type="datetime-local" name="planned_departure_time" x-model="plannedDeparture" required
                                       class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-800 dark:text-white outline-none focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    {{-- DIAGRAM STEP 2: ROUTE ITINERARY BUILDER (ORIGIN -> INTERMEDIATE STOPS -> FINAL DESTINATION) --}}
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/60 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white">2</span>
                                <span>Multi-Stop Route Builder & Waypoints</span>
                            </div>
                                <span class="text-[10px] text-slate-400">Search, add stops, and pin locations on the map</span>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] gap-4 items-start">
                        <div class="space-y-4">

                        {{-- ORIGIN LOCATION (MANUAL INPUT AND POINT THE MAP) --}}
                        <div class="rounded-xl bg-white dark:bg-slate-900 p-3 border border-slate-200 dark:border-slate-700 space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                    <span>Origin Location (Starting Point) *</span>
                                </label>
                                <button type="button" @click.stop="openMapPicker('origin')"
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 hover:text-blue-700 dark:text-blue-400 cursor-pointer">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                    </svg>
                                    Pin origin
                                </button>
                            </div>
                            <div class="relative">
                                <input type="text" name="starting_point" x-model="origin.address" @input="onAddressInput('origin', null, $event.target.value)" @keydown.enter.prevent="selectFirstSuggestion('origin')" required autocomplete="off" placeholder="Search a yard, address, or landmark"
                                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs text-slate-800 dark:text-white outline-none focus:border-blue-500">
                                <div x-show="origin.suggestions.length > 0" x-cloak class="absolute left-0 right-0 top-full z-20 mt-1 max-h-56 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xl">
                                    <template x-for="(suggestion, suggestionIndex) in origin.suggestions" :key="`${suggestion.source}-${suggestion.latitude}-${suggestionIndex}`">
                                        <button type="button" @click="selectLocation('origin', suggestion)" class="block w-full border-b border-slate-100 dark:border-slate-800 px-3 py-2 text-left last:border-0 hover:bg-blue-50 dark:hover:bg-slate-800">
                                            <span class="block text-xs font-semibold text-slate-800 dark:text-white" x-text="suggestion.title"></span>
                                            <span class="block text-[10px] text-slate-500" x-text="suggestion.subtitle || suggestion.source_label"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <input type="hidden" name="origin_latitude" x-model="origin.lat">
                            <input type="hidden" name="origin_longitude" x-model="origin.lng">
                            <div class="text-[10px] text-slate-500" x-text="origin.lat && origin.lng ? `Pinned ${Number(origin.lat).toFixed(5)}, ${Number(origin.lng).toFixed(5)}` : 'Choose a search result or pin this location on the map.'"></div>
                        </div>

                        {{-- INTERMEDIATE DESTINATIONS (ADD MULTIPLE TIMES) --}}
                        {{-- Matches the diagram's "Destination (MANUAL INPUT AND POINT THE MAP) THIS FUNCTION CAN BE ADD MULTIPLE TIMES" --}}
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                        Intermediate Destinations / Stops (<span x-text="intermediateStops.length"></span>)
                                    </span>
                                    <span class="text-[10px] text-slate-400">Drag a stop or use arrows to change its order.</span>
                                </div>
                                <button type="button" @click="addIntermediateStop()"
                                        class="inline-flex items-center gap-1 rounded-lg bg-blue-50 dark:bg-blue-900/40 border border-blue-200 px-2.5 py-1 text-[11px] font-bold text-blue-700 dark:text-blue-300 hover:bg-blue-100 cursor-pointer">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    Add stop
                                </button>
                            </div>

                            <template x-for="(stop, index) in intermediateStops" :key="index">
                                <div draggable="true" @dragstart.stop="draggingStopIndex = index" @dragover.prevent @drop.stop.prevent="moveIntermediateStop(draggingStopIndex, index)" @dragend="draggingStopIndex = null" class="rounded-xl bg-white dark:bg-slate-900 p-3 border border-slate-200 dark:border-slate-700 space-y-2 cursor-grab active:cursor-grabbing">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                            <span class="flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold text-white" x-text="index + 1"></span>
                                            <span>Intermediate Stop #<span x-text="index + 1"></span></span>
                                        </span>
                                        <div class="flex items-center gap-3">
                                            <button type="button" @click="moveIntermediateStop(index, index - 1)" :disabled="index === 0" class="text-[10px] font-bold text-slate-400 hover:text-blue-600 disabled:opacity-30" aria-label="Move stop up">↑</button>
                                            <button type="button" @click="moveIntermediateStop(index, index + 1)" :disabled="index === intermediateStops.length - 1" class="text-[10px] font-bold text-slate-400 hover:text-blue-600 disabled:opacity-30" aria-label="Move stop down">↓</button>
                                            <button type="button" @click.stop="openMapPicker('intermediate', index)"
                                                    class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 hover:text-blue-700 cursor-pointer">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                                </svg>
                                                Pin stop
                                            </button>
                                            <button type="button" @click="removeIntermediateStop(index)" class="text-red-500 hover:text-red-700 text-xs font-bold cursor-pointer">
                                                Remove &times;
                                            </button>
                                        </div>
                                    </div>
                                    <div class="relative">
                                        <input type="text" :name="`waypoints[${index}][name]`" x-model="stop.address" @input="onAddressInput('intermediate', index, $event.target.value)" @keydown.enter.prevent="selectFirstSuggestion('intermediate', index)" required autocomplete="off" placeholder="Search for a stop or landmark"
                                               class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs text-slate-800 dark:text-white outline-none focus:border-blue-500">
                                        <div x-show="stop.suggestions.length > 0" x-cloak class="absolute left-0 right-0 top-full z-20 mt-1 max-h-56 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xl">
                                            <template x-for="(suggestion, suggestionIndex) in stop.suggestions" :key="`${suggestion.source}-${suggestion.latitude}-${suggestionIndex}`">
                                                <button type="button" @click="selectLocation('intermediate', suggestion, index)" class="block w-full border-b border-slate-100 dark:border-slate-800 px-3 py-2 text-left last:border-0 hover:bg-blue-50 dark:hover:bg-slate-800">
                                                    <span class="block text-xs font-semibold text-slate-800 dark:text-white" x-text="suggestion.title"></span>
                                                    <span class="block text-[10px] text-slate-500" x-text="suggestion.subtitle || suggestion.source_label"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                    <input type="hidden" :name="`waypoints[${index}][address]`" x-model="stop.address">
                                    <input type="hidden" :name="`waypoints[${index}][lat]`" x-model="stop.lat">
                                    <input type="hidden" :name="`waypoints[${index}][lng]`" x-model="stop.lng">
                                    <div class="text-[10px] text-slate-500" x-text="stop.lat && stop.lng ? `Pinned ${Number(stop.lat).toFixed(5)}, ${Number(stop.lng).toFixed(5)}` : 'Select a result or pin the stop on the map.'"></div>
                                </div>
                            </template>
                        </div>

                        {{-- FINAL DESTINATION (MANUAL INPUT AND POINT THE MAP) --}}
                        <div class="rounded-xl bg-white dark:bg-slate-900 p-3 border border-slate-200 dark:border-slate-700 space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full bg-red-500"></span>
                                    <span>Final Destination *</span>
                                </label>
                                <button type="button" @click.stop="openMapPicker('destination')"
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 hover:text-blue-700 dark:text-blue-400 cursor-pointer">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                    </svg>
                                    Pin destination
                                </button>
                            </div>
                            <div class="relative">
                                <input type="text" name="destination" x-model="destination.address" @input="onAddressInput('destination', null, $event.target.value)" @keydown.enter.prevent="selectFirstSuggestion('destination')" required autocomplete="off" placeholder="Search for a customer, depot, or address"
                                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs text-slate-800 dark:text-white outline-none focus:border-blue-500">
                                <div x-show="destination.suggestions.length > 0" x-cloak class="absolute left-0 right-0 top-full z-20 mt-1 max-h-56 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xl">
                                    <template x-for="(suggestion, suggestionIndex) in destination.suggestions" :key="`${suggestion.source}-${suggestion.latitude}-${suggestionIndex}`">
                                        <button type="button" @click="selectLocation('destination', suggestion)" class="block w-full border-b border-slate-100 dark:border-slate-800 px-3 py-2 text-left last:border-0 hover:bg-blue-50 dark:hover:bg-slate-800">
                                            <span class="block text-xs font-semibold text-slate-800 dark:text-white" x-text="suggestion.title"></span>
                                            <span class="block text-[10px] text-slate-500" x-text="suggestion.subtitle || suggestion.source_label"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <input type="hidden" name="destination_latitude" x-model="destination.lat">
                            <input type="hidden" name="destination_longitude" x-model="destination.lng">
                            <div class="text-[10px] text-slate-500" x-text="destination.lat && destination.lng ? `Pinned ${Number(destination.lat).toFixed(5)}, ${Number(destination.lng).toFixed(5)}` : 'Choose a search result or pin this location on the map.'"></div>
                        </div>

                        {{-- DIAGRAM STEP 3: CALCULATE ALL THE DISTANCE --}}
                        <div class="rounded-xl bg-slate-100 dark:bg-slate-800/80 p-3.5 border border-slate-300 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <span class="text-xs font-bold text-slate-900 dark:text-white block">Calculate Total Route Distance</span>
                                <span class="text-[10px] text-slate-500">Computes sequential driving distance across origin, all intermediate stops, and final destination.</span>
                            </div>
                            <button type="button" @click="calculateRouteDistance()" :disabled="calculatingDistance"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 px-4 py-2 text-xs font-bold text-white shadow-xs transition-colors cursor-pointer disabled:opacity-50">
                                <template x-if="calculatingDistance">
                                    <span>Calculating...</span>
                                </template>
                                <template x-if="!calculatingDistance">
                                    <span>Calculate All Distance &rarr;</span>
                                </template>
                            </button>
                        </div>

                        {{-- Calculated Distance & Leg Summary --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="rounded-xl bg-white dark:bg-slate-900 p-3 border border-slate-200 dark:border-slate-700">
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Total Planned Distance (KM) *
                                </label>
                                <input type="number" step="0.01" name="planned_distance_km" x-model="plannedDistance" required readonly
                                       class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm font-bold text-blue-600 dark:text-blue-400 outline-none">
                            </div>
                            <div class="rounded-xl bg-white dark:bg-slate-900 p-3 border border-slate-200 dark:border-slate-700">
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Estimated Driving Time
                                </label>
                                <div class="px-3 py-2 text-sm font-bold text-slate-800 dark:text-slate-100" x-text="estimatedDuration || '—'"></div>
                            </div>
                        </div>

                        <div x-show="routeAlternatives.length > 0" x-cloak class="space-y-2">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-bold text-slate-800 dark:text-slate-100">Route options</h4>
                                <span class="text-[10px] text-slate-500" x-text="routeSourceLabel"></span>
                            </div>
                            <template x-for="(route, index) in routeAlternatives" :key="route.id || index">
                                <button type="button" @click="selectRoute(index)"
                                        class="w-full rounded-xl border p-3 text-left transition-colors"
                                        :class="selectedRouteIndex === index ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/40' : 'border-slate-200 bg-white dark:bg-slate-900 dark:border-slate-700 hover:border-blue-300'">
                                    <div class="flex items-center justify-between gap-3 text-xs">
                                        <span class="font-bold text-slate-800 dark:text-white" x-text="`Route ${index + 1}`"></span>
                                        <span class="font-semibold text-blue-700 dark:text-blue-300" x-text="`${route.distance_km} km · ${route.formatted_duration || formatDuration(route.duration_minutes)}`"></span>
                                    </div>
                                </button>
                            </template>
                        </div>

                        <div x-show="selectedRoute && selectedRoute.legs?.length" x-cloak class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 divide-y divide-slate-100 dark:divide-slate-800">
                            <template x-for="(leg, index) in selectedRoute?.legs || []" :key="`${leg.from_name}-${index}`">
                                <div class="flex items-center justify-between gap-3 px-3 py-2 text-[11px]">
                                    <span class="min-w-0 truncate text-slate-600 dark:text-slate-300" x-text="`${leg.from_name} → ${leg.to_name}`"></span>
                                    <span class="shrink-0 font-semibold text-slate-800 dark:text-white" x-text="`${leg.distance_km} km · ${leg.formatted_duration}`"></span>
                                </div>
                            </template>
                        </div>
                        </div>

                        <aside class="space-y-3 lg:sticky lg:top-0">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <h3 class="text-xs font-bold text-slate-800 dark:text-white">Route map</h3>
                                    <p class="text-[10px] text-slate-500" x-text="activeLocationLabel ? `Click the map to set ${activeLocationLabel}` : 'Choose a stop, then click the map to pin it.'"></p>
                                </div>
                                <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-0.5" role="group" aria-label="Map style">
                                    <button type="button" @click="setRouteMapLayer('street')" :aria-pressed="mapBaseLayer === 'street'" :class="mapBaseLayer === 'street' ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-300'" class="rounded-md px-2.5 py-1.5 text-[10px] font-semibold">Street</button>
                                    <button type="button" @click="setRouteMapLayer('satellite')" :aria-pressed="mapBaseLayer === 'satellite'" :class="mapBaseLayer === 'satellite' ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-300'" class="rounded-md px-2.5 py-1.5 text-[10px] font-semibold">Satellite</button>
                                </div>
                                <button type="button" @click="swapRouteEndpoints()" class="rounded-lg border border-slate-200 dark:border-slate-700 px-2.5 py-1.5 text-[10px] font-semibold text-slate-600 dark:text-slate-300 hover:border-blue-300">
                                    Swap start and finish
                                </button>
                                <button type="button" @click="toggleMapExpanded()" class="rounded-lg border border-slate-200 dark:border-slate-700 px-2.5 py-1.5 text-[10px] font-semibold text-slate-600 dark:text-slate-300 hover:border-blue-300" x-text="mapExpanded ? 'Reduce map' : 'Expand map'"></button>
                            </div>
                            <div id="dispatch-route-map" :class="mapExpanded ? 'dispatch-route-map-expanded' : ''" class="dispatch-route-map map-container border border-slate-200 dark:border-slate-700"></div>
                            <div class="flex flex-wrap gap-2 text-[10px] text-slate-500">
                                <span class="inline-flex items-center gap-1"><i class="h-2 w-2 rounded-full bg-emerald-500"></i>Origin</span>
                                <span class="inline-flex items-center gap-1"><i class="h-2 w-2 rounded-full bg-amber-500"></i>Stop</span>
                                <span class="inline-flex items-center gap-1"><i class="h-2 w-2 rounded-full bg-rose-500"></i>Destination</span>
                                <span x-show="selectedRoute?.source === 'haversine_estimate'" class="font-semibold text-amber-600">Estimated distance · Haversine fallback, not road routed</span>
                            </div>
                        </aside>
                        </div>
                    </div>

                    {{-- Mileage Reconciliation Initialization Options --}}
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-800/40 p-3 border border-slate-200 dark:border-slate-700 space-y-2">
                        <label class="flex items-center gap-2 text-xs font-bold text-slate-800 dark:text-slate-200 cursor-pointer">
                            <input type="checkbox" name="create_reconciliation" value="1" checked class="rounded border-slate-300 text-blue-600">
                            <span>Initialize Mileage Reconciliation in Draft immediately upon dispatch save</span>
                        </label>
                        <div class="grid grid-cols-2 gap-3 pt-1">
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-slate-500 mb-0.5">Initial Starting Odo</label>
                                <input type="number" step="0.1" name="starting_odometer" placeholder="e.g. 50000"
                                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1.5 text-xs text-slate-800 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-slate-500 mb-0.5">Punctuality Grace (Min)</label>
                                <input type="number" name="grace_period_minutes" value="15"
                                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1.5 text-xs text-slate-800 dark:text-white">
                            </div>
                        </div>
                    </div>

                    {{-- DIAGRAM STEP 4: SAVE BUTTON --}}
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showCreateModal = false"
                                class="rounded-xl bg-slate-100 dark:bg-slate-800 px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="rounded-xl bg-blue-600 px-5 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition-colors cursor-pointer">
                            Save Dispatch & Activate GPS Monitoring
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- LIVE TRACK & ROUTE INSPECTION MODAL --}}
        <div x-show="showLiveTrackModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-xs">
            <div @click.away="showLiveTrackModal = false" class="w-full max-w-4xl rounded-2xl bg-white dark:bg-slate-900 shadow-2xl border border-slate-200 dark:border-slate-800 p-6 space-y-4 max-h-[92vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>Live Route & GPS Compliance</span>
                            <template x-if="liveTrackData && liveTrackData.dispatch">
                                <span class="rounded-full bg-blue-100 dark:bg-blue-900 px-2.5 py-0.5 text-xs font-mono font-bold text-blue-700 dark:text-blue-300" x-text="liveTrackData.dispatch.trip_number"></span>
                            </template>
                        </h3>
                        <p class="text-xs text-slate-500">Comparing assigned multi-stop itinerary corridor against live TrackSolid GPS telemetry.</p>
                    </div>
                    <button type="button" @click="showLiveTrackModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
                </div>

                {{-- Map Container --}}
                <div id="live-tracking-map" class="map-container border border-slate-200 dark:border-slate-700" style="height: 460px;"></div>

                {{-- Live Telemetry Details --}}
                <template x-if="liveTrackData">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs bg-slate-50 dark:bg-slate-800/60 p-3 rounded-xl">
                        <div>
                            <span class="text-slate-400 font-bold block uppercase text-[10px]">Vehicle & Driver</span>
                            <span class="font-bold text-slate-800 dark:text-white" x-text="liveTrackData.dispatch.vehicle_code"></span>
                            <span class="text-slate-500" x-text="liveTrackData.dispatch.driver_name ? `• ${liveTrackData.dispatch.driver_name}` : ''"></span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold block uppercase text-[10px]">Current GPS Telemetry</span>
                            <template x-if="liveTrackData.live_point">
                                <span class="font-semibold text-slate-800 dark:text-white" x-text="`${liveTrackData.live_point.speed} km/h • ${liveTrackData.live_point.gps_time}`"></span>
                            </template>
                            <template x-if="!liveTrackData.live_point">
                                <span class="text-amber-500 italic">No live telemetry available</span>
                            </template>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold block uppercase text-[10px]">Compliance Status</span>
                            <span class="font-bold uppercase" :class="liveTrackData.dispatch.monitoring_status === 'CONFIRMED_EXCEPTION' ? 'text-red-500' : (liveTrackData.dispatch.monitoring_status === 'POSSIBLE_EXCEPTION' ? 'text-amber-500' : 'text-emerald-500')" x-text="liveTrackData.dispatch.monitoring_status"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Record Departure Modal --}}
        <div x-show="showDepartureModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="showDepartureModal = false" class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 shadow-2xl border border-slate-200 dark:border-slate-800 p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Record Actual Departure</h3>
                    <button type="button" @click="showDepartureModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <template x-if="selectedDispatch">
                    <form :action="`/fleet-dispatches/${selectedDispatch.id}/record-departure`" method="POST" class="space-y-4">
                        @csrf
                        <div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 p-3 text-xs space-y-1">
                            <div class="flex justify-between">
                                <span class="text-slate-400 font-semibold uppercase">Trip:</span>
                                <span class="font-mono font-bold text-slate-900 dark:text-white" x-text="selectedDispatch.trip_number"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400 font-semibold uppercase">Destination:</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200" x-text="selectedDispatch.destination"></span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Actual Departure Date & Time
                            </label>
                            <input type="datetime-local" name="actual_departure_time" x-model="departureTime" required
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-800 dark:text-white outline-none focus:border-blue-500">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Official Departure Source Event
                            </label>
                            <select name="departure_source" x-model="departureSource" required
                                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-800 dark:text-white outline-none focus:border-blue-500">
                                <option value="authorized_dispatch">Authorized Dispatch Log (Yard Gate / Manager)</option>
                                <option value="driver_submitted">Driver Submitted Departure Time</option>
                                <option value="gps_movement">TrackSolid GPS Initial Movement Event</option>
                            </select>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                            <button type="button" @click="showDepartureModal = false"
                                    class="rounded-xl bg-slate-100 dark:bg-slate-800 px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-200">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-sm cursor-pointer">
                                Save Departure & Recalculate
                            </button>
                        </div>
                    </form>
                </template>
            </div>
        </div>

    </div>

    @push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        function fleetDispatchManager(config) {
            return {
                showCreateModal: false,
                showDepartureModal: false,
                showLiveTrackModal: false,
                calculatingDistance: false,
                selectedDispatch: null,
                liveTrackData: null,
                departureTime: '{{ now()->format('Y-m-d\TH:i') }}',
                departureSource: 'authorized_dispatch',
                itineraries: config.itineraries || [],
                vehicles: config.vehicles || [],
                locations: config.locations || [],
                vehicleId: '',
                selectedVehicle: null,
                driverName: '',
                tripDate: '{{ now()->format('Y-m-d') }}',
                plannedDeparture: '{{ now()->format('Y-m-d\T08:00') }}',
                plannedDistance: 0,
                estimatedDuration: '',
                calculatedGeometry: [],
                routeAlternatives: [],
                selectedRoute: null,
                selectedRouteIndex: -1,
                routeResultJson: '',
                routeSourceLabel: '',
                routeMap: null,
                routeStreetLayer: null,
                routeSatelliteLayer: null,
                routeBaseLayer: null,
                mapBaseLayer: 'street',
                mapExpanded: false,
                routeMarkerLayer: null,
                routeLine: null,
                routeRequestToken: 0,
                autoRouteTimer: null,
                draggingStopIndex: null,
                origin: { address: 'Cagayan de Oro Main Yard', lat: 8.4822, lng: 124.6472, suggestions: [], searchToken: 0, reverseToken: 0, manualAddress: false },
                intermediateStops: [],
                destination: { address: '', lat: null, lng: null, suggestions: [], searchToken: 0, reverseToken: 0, manualAddress: false },
                activePickerTarget: 'origin',
                liveMap: null,

                get activeLocationLabel() {
                    const target = this.getLocationTarget();
                    if (this.activePickerTarget === 'origin') return 'the origin';
                    if (this.activePickerTarget === 'destination') return 'the destination';
                    return `stop ${this.activePickerTarget.index + 1}`;
                },

                openCreateModal() {
                    this.showCreateModal = true;
                    this.$nextTick(() => this.initRouteMap());
                },

                onVehicleChange() {
                    this.selectedVehicle = this.vehicles.find(v => v.id == this.vehicleId) || null;
                    if (this.selectedVehicle && this.selectedVehicle.operator_driver && !this.driverName) {
                        this.driverName = this.selectedVehicle.operator_driver;
                    }
                },

                addIntermediateStop() {
                    if (this.intermediateStops.length >= 20) {
                        alert('A dispatch can include up to 20 intermediate stops.');
                        return;
                    }

                    this.intermediateStops.push({
                        address: '',
                        lat: null,
                        lng: null,
                        suggestions: [],
                        searchToken: 0,
                        reverseToken: 0,
                        manualAddress: false,
                    });
                    this.clearCalculatedRoute();
                },

                removeIntermediateStop(index) {
                    this.intermediateStops.splice(index, 1);
                    this.clearCalculatedRoute();
                    if (this.activePickerTarget?.type === 'intermediate' && this.activePickerTarget.index === index) {
                        this.activePickerTarget = 'origin';
                    } else if (this.activePickerTarget?.type === 'intermediate' && this.activePickerTarget.index > index) {
                        this.activePickerTarget.index--;
                    }
                    this.syncRouteMap();
                    this.scheduleAutoRouteCalculation();
                },

                openMapPicker(type, index = null) {
                    this.activePickerTarget = index !== null ? { type: 'intermediate', index: index } : type;
                    const target = this.getLocationTarget();
                    this.$nextTick(() => {
                        this.initRouteMap();
                        if (target?.lat && target?.lng && this.routeMap) {
                            this.routeMap.setView([Number(target.lat), Number(target.lng)], 15);
                        }
                        document.getElementById('dispatch-route-map')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    });
                },

                toggleMapExpanded() {
                    this.mapExpanded = !this.mapExpanded;
                    this.$nextTick(() => this.routeMap?.invalidateSize());
                },

                getLocationTarget(type = null, index = null) {
                    const targetType = type ?? this.activePickerTarget;
                    if (targetType === 'origin') return this.origin;
                    if (targetType === 'destination') return this.destination;
                    const stopIndex = index ?? targetType?.index;
                    return (targetType === 'intermediate' || targetType?.type === 'intermediate') && this.intermediateStops[stopIndex]
                        ? this.intermediateStops[stopIndex]
                        : null;
                },

                onAddressInput(type, index = null, value = null) {
                    const target = this.getLocationTarget(type, index);
                    if (!target) return;

                    if (value !== null) target.address = value;
                    target.lat = null;
                    target.lng = null;
                    target.manualAddress = true;
                    target.reverseToken++;
                    this.activePickerTarget = index !== null ? { type: 'intermediate', index } : type;
                    this.clearCalculatedRoute();
                    this.searchLocationSuggestions(type, index);
                    this.syncRouteMap();
                },

                selectFirstSuggestion(type, index = null) {
                    const target = this.getLocationTarget(type, index);
                    if (target?.suggestions?.[0]) {
                        this.selectLocation(type, target.suggestions[0], index);
                    }
                },

                searchLocationSuggestions(type, index = null) {
                    const target = this.getLocationTarget(type, index);
                    if (!target) return;
                    const query = target.address.trim();
                    const token = ++target.searchToken;
                    clearTimeout(target.searchTimer);
                    target.suggestions = [];

                    if (query.length < 3) return;

                    target.searchTimer = setTimeout(async () => {
                        try {
                            const response = await fetch(`${config.searchPlacesUrl}?q=${encodeURIComponent(query)}`, {
                                headers: { 'Accept': 'application/json' }
                            });
                            const data = await response.json();
                            if (token === target.searchToken) {
                                target.suggestions = response.ok && data.success ? (data.results || []) : [];
                            }
                        } catch (error) {
                            if (token === target.searchToken) target.suggestions = [];
                            console.error('Location search failed:', error);
                        }
                    }, 450);
                },

                selectLocation(type, suggestion, index = null) {
                    const target = this.getLocationTarget(type, index);
                    if (!target) return;
                    target.address = suggestion.address || suggestion.title || suggestion.subtitle || '';
                    if (!target.address.trim()) target.address = suggestion.title || '';
                    target.lat = Number(suggestion.latitude);
                    target.lng = Number(suggestion.longitude);
                    target.manualAddress = false;
                    target.suggestions = [];
                    target.searchToken++;
                    target.reverseToken++;
                    this.activePickerTarget = index !== null ? { type: 'intermediate', index } : type;
                    this.clearCalculatedRoute();
                    this.syncRouteMap();
                    if (this.routeMap) this.routeMap.setView([target.lat, target.lng], 14);
                    this.scheduleAutoRouteCalculation();
                },

                initRouteMap() {
                    const mapDiv = document.getElementById('dispatch-route-map');
                    if (!mapDiv || typeof L === 'undefined') return;

                    if (!this.routeMap) {
                        this.routeMap = L.map(mapDiv).setView([8.4822, 124.6472], 12);
                        this.routeStreetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; OpenStreetMap'
                        });
                        this.routeSatelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                            maxZoom: 19,
                            attribution: 'Tiles &copy; Esri — Sources: Esri, Maxar, Earthstar Geographics, and the GIS User Community'
                        });
                        this.routeBaseLayer = this.routeStreetLayer.addTo(this.routeMap);
                        this.routeMarkerLayer = L.layerGroup().addTo(this.routeMap);
                        this.routeMap.on('click', (event) => this.applyMapPoint(event.latlng.lat, event.latlng.lng));
                    }

                    setTimeout(() => {
                        if (this.routeMap) this.routeMap.invalidateSize();
                        this.syncRouteMap();
                    }, 150);
                },

                setRouteMapLayer(layerName) {
                    if (!this.routeMap || !['street', 'satellite'].includes(layerName)) return;
                    const nextLayer = layerName === 'satellite' ? this.routeSatelliteLayer : this.routeStreetLayer;
                    if (!nextLayer || nextLayer === this.routeBaseLayer) {
                        this.mapBaseLayer = layerName;
                        return;
                    }
                    if (this.routeBaseLayer && this.routeMap.hasLayer(this.routeBaseLayer)) {
                        this.routeMap.removeLayer(this.routeBaseLayer);
                    }
                    this.routeBaseLayer = nextLayer.addTo(this.routeMap);
                    this.mapBaseLayer = layerName;
                },

                applyMapPoint(latitude, longitude) {
                    const target = this.getLocationTarget();
                    if (!target) return;
                    target.lat = Number(latitude.toFixed(6));
                    target.lng = Number(longitude.toFixed(6));
                    target.suggestions = [];
                    target.reverseToken++;
                    this.clearCalculatedRoute();
                    this.syncRouteMap();
                    if (!target.manualAddress) {
                        target.address = '';
                        this.reverseGeocodeLocation(target, target.reverseToken);
                    }
                    this.scheduleAutoRouteCalculation();
                },

                async reverseGeocodeLocation(target, token) {
                    try {
                        const response = await fetch(config.reverseGeocodeUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ latitude: target.lat, longitude: target.lng })
                        });
                        const data = await response.json();
                        if (response.ok && token === target.reverseToken && data.result?.address) {
                            target.address = data.result.address;
                            target.suggestions = [];
                            this.scheduleAutoRouteCalculation();
                        }
                    } catch (error) {
                        console.error('Reverse geocoding failed:', error);
                    }
                },

                syncRouteMap() {
                    if (!this.routeMap || !this.routeMarkerLayer || typeof L === 'undefined') return;
                    this.routeMarkerLayer.clearLayers();
                    if (this.routeLine) {
                        this.routeMap.removeLayer(this.routeLine);
                        this.routeLine = null;
                    }

                    const markers = [
                        { target: this.origin, type: 'origin', label: 'A', color: '#059669', name: 'Origin' },
                        ...this.intermediateStops.map((target, index) => ({ target, type: 'intermediate', index, label: String(index + 1), color: '#d97706', name: `Stop ${index + 1}` })),
                        { target: this.destination, type: 'destination', label: 'B', color: '#e11d48', name: 'Destination' },
                    ];
                    const bounds = [];

                    markers.forEach((entry) => {
                        if (entry.target.lat === null || entry.target.lat === '' || entry.target.lng === null || entry.target.lng === '') return;
                        if (!Number.isFinite(Number(entry.target.lat)) || !Number.isFinite(Number(entry.target.lng))) return;
                        const position = [Number(entry.target.lat), Number(entry.target.lng)];
                        const icon = L.divIcon({
                            className: '',
                            html: `<span style="display:flex;width:28px;height:28px;align-items:center;justify-content:center;border:2px solid #fff;border-radius:50%;background:${entry.color};box-shadow:0 2px 8px #0004;color:#fff;font-size:11px;font-weight:700">${entry.label}</span>`,
                            iconSize: [28, 28],
                            iconAnchor: [14, 14],
                        });
                        const marker = L.marker(position, { icon, draggable: true }).addTo(this.routeMarkerLayer);
                        const popup = document.createElement('div');
                        popup.textContent = `${entry.name}: ${entry.target.address || 'Pinned location'}`;
                        marker.bindPopup(popup);
                        marker.on('dragend', (event) => {
                            const location = event.target.getLatLng();
                            this.activePickerTarget = entry.type === 'intermediate' ? { type: entry.type, index: entry.index } : entry.type;
                            this.applyMapPoint(location.lat, location.lng);
                        });
                        marker.on('click', () => {
                            this.activePickerTarget = entry.type === 'intermediate' ? { type: entry.type, index: entry.index } : entry.type;
                        });
                        bounds.push(position);
                    });

                    if (this.selectedRoute?.geometry?.length > 1) {
                        const fallback = this.selectedRoute.source === 'haversine_estimate';
                        this.routeLine = L.polyline(this.selectedRoute.geometry, {
                            color: fallback ? '#d97706' : '#2563eb',
                            weight: 5,
                            opacity: 0.85,
                            dashArray: fallback ? '8 8' : null,
                        }).addTo(this.routeMap);
                        bounds.push(...this.selectedRoute.geometry);
                    }

                    if (bounds.length > 1) this.routeMap.fitBounds(bounds, { padding: [28, 28], maxZoom: 15 });
                },

                clearCalculatedRoute() {
                    this.routeRequestToken++;
                    clearTimeout(this.autoRouteTimer);
                    this.calculatingDistance = false;
                    this.routeAlternatives = [];
                    this.selectedRoute = null;
                    this.selectedRouteIndex = -1;
                    this.routeResultJson = '';
                    this.routeSourceLabel = '';
                    this.plannedDistance = 0;
                    this.estimatedDuration = '';
                    this.calculatedGeometry = [];
                    if (this.routeMap) this.syncRouteMap();
                },

                moveIntermediateStop(fromIndex, toIndex) {
                    if (!Number.isInteger(fromIndex) || !Number.isInteger(toIndex)
                        || fromIndex < 0 || toIndex < 0
                        || fromIndex >= this.intermediateStops.length || toIndex >= this.intermediateStops.length
                        || fromIndex === toIndex) return;
                    const activeTarget = this.activePickerTarget?.type === 'intermediate'
                        ? this.intermediateStops[this.activePickerTarget.index]
                        : null;
                    const [stop] = this.intermediateStops.splice(fromIndex, 1);
                    this.intermediateStops.splice(toIndex, 0, stop);
                    this.draggingStopIndex = null;
                    if (activeTarget) {
                        this.activePickerTarget.index = this.intermediateStops.indexOf(activeTarget);
                    }
                    this.clearCalculatedRoute();
                    this.syncRouteMap();
                    this.scheduleAutoRouteCalculation();
                },

                swapRouteEndpoints() {
                    [this.origin, this.destination] = [this.destination, this.origin];
                    this.intermediateStops.reverse();
                    this.activePickerTarget = 'origin';
                    this.clearCalculatedRoute();
                    this.syncRouteMap();
                    this.scheduleAutoRouteCalculation();
                },

                getRoutePoints() {
                    const locations = [this.origin, ...this.intermediateStops, this.destination];
                    if (locations.some((location) => !location.address.trim()
                        || location.lat === null || location.lat === ''
                        || location.lng === null || location.lng === ''
                        || !Number.isFinite(Number(location.lat))
                        || !Number.isFinite(Number(location.lng))
                        || Number(location.lat) < -90 || Number(location.lat) > 90
                        || Number(location.lng) < -180 || Number(location.lng) > 180)) return [];
                    return locations.map((location) => ({
                        name: location.address,
                        lat: Number(location.lat),
                        lng: Number(location.lng),
                    }));
                },

                scheduleAutoRouteCalculation() {
                    clearTimeout(this.autoRouteTimer);
                    if (this.getRoutePoints().length < 2) return;
                    this.autoRouteTimer = setTimeout(() => this.calculateRouteDistance(), 500);
                },

                selectRoute(index) {
                    const route = this.routeAlternatives[index];
                    if (!route) return;
                    this.selectedRouteIndex = index;
                    this.selectedRoute = route;
                    this.plannedDistance = route.distance_km;
                    this.estimatedDuration = route.formatted_duration || this.formatDuration(route.duration_minutes);
                    this.calculatedGeometry = route.geometry || [];
                    this.routeSourceLabel = route.source === 'haversine_estimate'
                        ? 'Estimated distance · Haversine fallback (not road routed)'
                        : 'Road route · OSRM';
                    this.routeResultJson = JSON.stringify(route);
                    this.syncRouteMap();
                },

                formatDuration(minutes) {
                    const value = Number(minutes || 0);
                    const hours = Math.floor(value / 60);
                    const remainingMinutes = value % 60;
                    return hours ? `${hours}h${remainingMinutes ? ` ${remainingMinutes}m` : ''}` : `${remainingMinutes}m`;
                },

                async calculateRouteDistance() {
                    clearTimeout(this.autoRouteTimer);
                    const points = this.getRoutePoints();
                    if (points.length !== this.intermediateStops.length + 2) {
                        alert('Choose an address suggestion or pin every stop on the map before calculating the route.');
                        return;
                    }

                    const requestToken = ++this.routeRequestToken;
                    this.calculatingDistance = true;
                    try {
                        const res = await fetch(config.calculateRouteUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ points: points })
                        });
                        const data = await res.json();
                        if (requestToken !== this.routeRequestToken) return;
                        if (data.success && (data.alternatives || []).length > 0) {
                            this.routeAlternatives = data.alternatives;
                            this.selectRoute(0);
                        } else {
                            alert('Unable to calculate a usable route. Check the locations and try again.');
                        }
                    } catch (e) {
                        console.error('Route calculation error:', e);
                        alert('Route calculation is temporarily unavailable. Please try again.');
                    } finally {
                        if (requestToken === this.routeRequestToken) this.calculatingDistance = false;
                    }
                },

                onFormSubmit(e) {
                    if (this.getRoutePoints().length !== this.intermediateStops.length + 2) {
                        e.preventDefault();
                        alert('Each stop needs a selected address or valid map pin before saving.');
                        return;
                    }
                    if (!this.selectedRoute || !this.routeResultJson) {
                        e.preventDefault();
                        alert('Calculate the route and choose a route option before saving the dispatch.');
                        return;
                    }
                    if (!this.plannedDistance || this.plannedDistance <= 0) {
                        e.preventDefault();
                        alert('The planned route must have a distance greater than zero.');
                    }
                },

                openDeparture(dispatch) {
                    this.selectedDispatch = dispatch;
                    this.departureTime = dispatch.actual_departure_time 
                        ? dispatch.actual_departure_time.replace(' ', 'T').substring(0, 16) 
                        : '{{ now()->format('Y-m-d\TH:i') }}';
                    this.departureSource = dispatch.departure_source || 'authorized_dispatch';
                    this.showDepartureModal = true;
                },

                async viewLiveTrack(dispatchId) {
                    this.showLiveTrackModal = true;
                    try {
                        const res = await fetch(`/fleet-dispatches/${dispatchId}/live-track`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        this.liveTrackData = await res.json();
                        this.$nextTick(() => {
                            this.initLiveMap(this.liveTrackData);
                        });
                    } catch (e) {
                        console.error(e);
                    }
                },

                initLiveMap(data) {
                    if (this.liveMap) {
                        this.liveMap.remove();
                        this.liveMap = null;
                    }
                    const div = document.getElementById('live-tracking-map');
                    if (!div) return;

                    const waypoints = data.waypoints || [];
                    const defaultCenter = waypoints.length > 0 ? [waypoints[0].lat, waypoints[0].lng] : [8.4822, 124.6472];

                    this.liveMap = L.map('live-tracking-map').setView(defaultCenter, 12);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap'
                    }).addTo(this.liveMap);

                    const bounds = [];

                    // Plot waypoints
                    waypoints.forEach((wp, idx) => {
                        const marker = L.marker([wp.lat, wp.lng]).addTo(this.liveMap);
                        marker.bindPopup(`<b>${wp.name || 'Waypoint'}</b><br>Stop #${idx + 1}`);
                        bounds.push([wp.lat, wp.lng]);
                    });

                    // Draw route polyline
                    if (waypoints.length >= 2) {
                        const polyPoints = waypoints.map(w => [w.lat, w.lng]);
                        L.polyline(polyPoints, { color: '#2563eb', weight: 4, opacity: 0.8 }).addTo(this.liveMap);
                    }

                    // Plot live vehicle position if available
                    if (data.live_point) {
                        const live = data.live_point;
                        const carIcon = L.divIcon({
                            html: '<div style="background-color: #ef4444; color: white; border-radius: 9999px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; border: 2px solid white; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.3);">🚗</div>',
                            className: '',
                            iconSize: [28, 28],
                            iconAnchor: [14, 14]
                        });

                        const carMarker = L.marker([live.lat, live.lng], { icon: carIcon }).addTo(this.liveMap);
                        carMarker.bindPopup(`<b>${data.dispatch.vehicle_code}</b><br>Speed: ${live.speed} km/h<br>GPS Time: ${live.gps_time}`).openPopup();
                        bounds.push([live.lat, live.lng]);
                    }

                    if (bounds.length > 0) {
                        this.liveMap.fitBounds(bounds, { padding: [40, 40] });
                    }

                    setTimeout(() => {
                        this.liveMap.invalidateSize();
                    }, 200);
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
