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
            <div class="w-full max-w-3xl rounded-2xl bg-white dark:bg-slate-900 shadow-2xl border border-slate-200 dark:border-slate-800 p-6 space-y-5 max-h-[92vh] overflow-y-auto">
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
                            <span class="text-[10px] text-slate-400">Manual address input & map pin</span>
                        </div>

                        {{-- ORIGIN LOCATION (MANUAL INPUT AND POINT THE MAP) --}}
                        <div class="rounded-xl bg-white dark:bg-slate-900 p-3 border border-slate-200 dark:border-slate-700 space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                    <span>Origin Location (Starting Point) *</span>
                                </label>
                                <button type="button" @click="openMapPicker('origin')"
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 hover:text-blue-700 dark:text-blue-400 cursor-pointer">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                    </svg>
                                    Point on Map
                                </button>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                                <div class="sm:col-span-7">
                                    <input type="text" name="starting_point" x-model="origin.address" required placeholder="e.g. Cagayan de Oro Central Yard"
                                           class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-800 dark:text-white outline-none">
                                </div>
                                <div class="sm:col-span-5 flex items-center gap-1">
                                    <input type="number" step="0.000001" name="origin_latitude" x-model="origin.lat" placeholder="Lat"
                                           class="w-1/2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-2 py-1.5 text-[11px] font-mono">
                                    <input type="number" step="0.000001" name="origin_longitude" x-model="origin.lng" placeholder="Lng"
                                           class="w-1/2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-2 py-1.5 text-[11px] font-mono">
                                </div>
                            </div>
                        </div>

                        {{-- INTERMEDIATE DESTINATIONS (ADD MULTIPLE TIMES) --}}
                        {{-- Matches the diagram's "Destination (MANUAL INPUT AND POINT THE MAP) THIS FUNCTION CAN BE ADD MULTIPLE TIMES" --}}
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                                    Intermediate Destinations / Stops (<span x-text="intermediateStops.length"></span>)
                                </span>
                                <button type="button" @click="addIntermediateStop()"
                                        class="inline-flex items-center gap-1 rounded-lg bg-blue-50 dark:bg-blue-900/40 border border-blue-200 px-2.5 py-1 text-[11px] font-bold text-blue-700 dark:text-blue-300 hover:bg-blue-100 cursor-pointer">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    Add Stop Multiple Times
                                </button>
                            </div>

                            <template x-for="(stop, index) in intermediateStops" :key="index">
                                <div class="rounded-xl bg-white dark:bg-slate-900 p-3 border border-slate-200 dark:border-slate-700 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                            <span class="flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold text-white" x-text="index + 1"></span>
                                            <span>Intermediate Stop #<span x-text="index + 1"></span></span>
                                        </span>
                                        <div class="flex items-center gap-3">
                                            <button type="button" @click="openMapPicker('intermediate', index)"
                                                    class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 hover:text-blue-700 cursor-pointer">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                                </svg>
                                                Point on Map
                                            </button>
                                            <button type="button" @click="removeIntermediateStop(index)" class="text-red-500 hover:text-red-700 text-xs font-bold cursor-pointer">
                                                Remove &times;
                                            </button>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                                        <div class="sm:col-span-7">
                                            <input type="text" :name="`waypoints[${index}][name]`" x-model="stop.address" required placeholder="Stop address or landmark"
                                                   class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-800 dark:text-white outline-none">
                                        </div>
                                        <div class="sm:col-span-5 flex items-center gap-1">
                                            <input type="number" step="0.000001" :name="`waypoints[${index}][lat]`" x-model="stop.lat" placeholder="Lat"
                                                   class="w-1/2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-2 py-1.5 text-[11px] font-mono">
                                            <input type="number" step="0.000001" :name="`waypoints[${index}][lng]`" x-model="stop.lng" placeholder="Lng"
                                                   class="w-1/2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-2 py-1.5 text-[11px] font-mono">
                                        </div>
                                    </div>
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
                                <button type="button" @click="openMapPicker('destination')"
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 hover:text-blue-700 dark:text-blue-400 cursor-pointer">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                    </svg>
                                    Point on Map
                                </button>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                                <div class="sm:col-span-7">
                                    <input type="text" name="destination" x-model="destination.address" required placeholder="e.g. Iligan City Distribution Depot"
                                           class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-800 dark:text-white outline-none">
                                </div>
                                <div class="sm:col-span-5 flex items-center gap-1">
                                    <input type="number" step="0.000001" name="destination_latitude" x-model="destination.lat" placeholder="Lat"
                                           class="w-1/2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-2 py-1.5 text-[11px] font-mono">
                                    <input type="number" step="0.000001" name="destination_longitude" x-model="destination.lng" placeholder="Lng"
                                           class="w-1/2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-2 py-1.5 text-[11px] font-mono">
                                </div>
                            </div>
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
                                <input type="number" step="0.01" name="planned_distance_km" x-model="plannedDistance" required
                                       class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm font-bold text-blue-600 dark:text-blue-400 outline-none">
                            </div>
                            <div class="rounded-xl bg-white dark:bg-slate-900 p-3 border border-slate-200 dark:border-slate-700">
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Estimated Driving Time
                                </label>
                                <div class="px-3 py-2 text-sm font-bold text-slate-800 dark:text-slate-100" x-text="estimatedDuration || '—'"></div>
                            </div>
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

        {{-- POINT ON MAP MODAL (Interactive Leaflet Map Coordinate Picker) --}}
        <div x-show="showMapPickerModal" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xs"
             @click.self="closeMapPicker()">
            <div class="w-full max-w-2xl rounded-2xl bg-white dark:bg-slate-900 shadow-2xl border border-slate-200 dark:border-slate-800 p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white">Point on Map: Select Location</h4>
                        <p class="text-[11px] text-slate-500">Click anywhere on the map or drag the marker to pin exact coordinates.</p>
                    </div>
                    <button type="button" @click="closeMapPicker()" class="text-slate-400 hover:text-slate-600 text-lg font-bold cursor-pointer">&times;</button>
                </div>

                {{-- Quick Directory Dropdown --}}
                <div class="flex items-center gap-2">
                    <select x-model="selectedDirectoryLocation" @change="onDirectoryLocationPick()"
                            class="flex-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-800 dark:text-slate-200">
                        <option value="">-- Choose from Registered Company Locations --</option>
                        <template x-for="loc in locations" :key="loc.id">
                            <option :value="loc.id" x-text="loc.official_name"></option>
                        </template>
                    </select>
                </div>

                {{-- Map Div --}}
                <div id="route-picker-map" class="map-container border border-slate-200 dark:border-slate-700"></div>

                <div class="flex items-center justify-between text-xs pt-1">
                    <div class="font-mono text-slate-500">
                        Selected: <span class="font-bold text-blue-600" x-text="pickerCoords.lat"></span>, <span class="font-bold text-blue-600" x-text="pickerCoords.lng"></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="closeMapPicker()"
                                class="rounded-xl border border-slate-300 dark:border-slate-700 px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
                            Cancel
                        </button>
                        <button type="button" @click="confirmMapPicker()"
                                class="rounded-xl bg-blue-600 hover:bg-blue-700 px-4 py-2 text-xs font-bold text-white cursor-pointer shadow-xs">
                            Confirm Coordinates
                        </button>
                    </div>
                </div>
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
                showMapPickerModal: false,
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
                origin: { address: 'Cagayan de Oro Main Yard', lat: 8.4822, lng: 124.6472 },
                intermediateStops: [],
                destination: { address: '', lat: null, lng: null },
                activePickerTarget: null, // 'origin', 'destination', or { type: 'intermediate', index: 0 }
                pickerCoords: { lat: 8.4822, lng: 124.6472 },
                pickerAddress: '',
                selectedDirectoryLocation: '',
                pickerMap: null,
                pickerMarker: null,
                liveMap: null,

                openCreateModal() {
                    this.showCreateModal = true;
                },

                onVehicleChange() {
                    this.selectedVehicle = this.vehicles.find(v => v.id == this.vehicleId) || null;
                    if (this.selectedVehicle && this.selectedVehicle.operator_driver && !this.driverName) {
                        this.driverName = this.selectedVehicle.operator_driver;
                    }
                },

                addIntermediateStop() {
                    this.intermediateStops.push({
                        address: '',
                        lat: null,
                        lng: null
                    });
                },

                removeIntermediateStop(index) {
                    this.intermediateStops.splice(index, 1);
                },

                openMapPicker(type, index = null) {
                    this.activePickerTarget = index !== null ? { type: 'intermediate', index: index } : type;
                    this.pickerAddress = '';
                    this.selectedDirectoryLocation = '';
                    let targetLat = 8.4822, targetLng = 124.6472;

                    if (type === 'origin' && this.origin.lat) {
                        targetLat = parseFloat(this.origin.lat);
                        targetLng = parseFloat(this.origin.lng);
                    } else if (type === 'destination' && this.destination.lat) {
                        targetLat = parseFloat(this.destination.lat);
                        targetLng = parseFloat(this.destination.lng);
                    } else if (index !== null && this.intermediateStops[index] && this.intermediateStops[index].lat) {
                        targetLat = parseFloat(this.intermediateStops[index].lat);
                        targetLng = parseFloat(this.intermediateStops[index].lng);
                    }

                    this.pickerCoords = { lat: targetLat.toFixed(6), lng: targetLng.toFixed(6) };
                    this.showMapPickerModal = true;

                    this.$nextTick(() => {
                        this.initPickerMap(targetLat, targetLng);
                    });
                },

                closeMapPicker() {
                    this.showMapPickerModal = false;
                    this.showCreateModal = true;
                },

                initPickerMap(lat, lng) {
                    if (this.pickerMap) {
                        this.pickerMap.remove();
                        this.pickerMap = null;
                    }

                    const mapDiv = document.getElementById('route-picker-map');
                    if (!mapDiv) return;

                    this.pickerMap = L.map('route-picker-map').setView([lat, lng], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap'
                    }).addTo(this.pickerMap);

                    this.pickerMarker = L.marker([lat, lng], { draggable: true }).addTo(this.pickerMap);

                    this.pickerMarker.on('dragend', (e) => {
                        const pos = e.target.getLatLng();
                        this.pickerCoords.lat = pos.lat.toFixed(6);
                        this.pickerCoords.lng = pos.lng.toFixed(6);
                    });

                    this.pickerMap.on('click', (e) => {
                        this.pickerMarker.setLatLng(e.latlng);
                        this.pickerCoords.lat = e.latlng.lat.toFixed(6);
                        this.pickerCoords.lng = e.latlng.lng.toFixed(6);
                    });

                    setTimeout(() => {
                        if (this.pickerMap) {
                            this.pickerMap.invalidateSize();
                        }
                    }, 250);
                },

                onDirectoryLocationPick() {
                    if (!this.selectedDirectoryLocation) return;
                    const loc = this.locations.find(l => l.id == this.selectedDirectoryLocation);
                    if (loc && loc.latitude && loc.longitude) {
                        const lat = parseFloat(loc.latitude);
                        const lng = parseFloat(loc.longitude);
                        this.pickerCoords.lat = lat.toFixed(6);
                        this.pickerCoords.lng = lng.toFixed(6);
                        this.pickerAddress = loc.official_name || loc.name || '';
                        if (this.pickerMarker && this.pickerMap) {
                            this.pickerMarker.setLatLng([lat, lng]);
                            this.pickerMap.setView([lat, lng], 14);
                        }
                    }
                },

                confirmMapPicker() {
                    const lat = parseFloat(this.pickerCoords.lat);
                    const lng = parseFloat(this.pickerCoords.lng);

                    if (this.activePickerTarget === 'origin') {
                        this.origin.lat = lat;
                        this.origin.lng = lng;
                        if (this.pickerAddress && (!this.origin.address || this.origin.address === 'Cagayan de Oro Main Yard')) {
                            this.origin.address = this.pickerAddress;
                        }
                    } else if (this.activePickerTarget === 'destination') {
                        this.destination.lat = lat;
                        this.destination.lng = lng;
                        if (this.pickerAddress && !this.destination.address) {
                            this.destination.address = this.pickerAddress;
                        }
                    } else if (typeof this.activePickerTarget === 'object' && this.activePickerTarget.type === 'intermediate') {
                        const idx = this.activePickerTarget.index;
                        if (this.intermediateStops[idx]) {
                            this.intermediateStops[idx].lat = lat;
                            this.intermediateStops[idx].lng = lng;
                            if (this.pickerAddress && !this.intermediateStops[idx].address) {
                                this.intermediateStops[idx].address = this.pickerAddress;
                            }
                        }
                    }

                    this.showMapPickerModal = false;
                    this.showCreateModal = true;
                },

                async calculateRouteDistance() {
                    const points = [];
                    if (this.origin.lat && this.origin.lng) {
                        points.push({ name: this.origin.address || 'Origin', lat: parseFloat(this.origin.lat), lng: parseFloat(this.origin.lng) });
                    }
                    this.intermediateStops.forEach((stop, i) => {
                        if (stop.lat && stop.lng) {
                            points.push({ name: stop.address || `Stop #${i+1}`, lat: parseFloat(stop.lat), lng: parseFloat(stop.lng) });
                        }
                    });
                    if (this.destination.lat && this.destination.lng) {
                        points.push({ name: this.destination.address || 'Destination', lat: parseFloat(this.destination.lat), lng: parseFloat(this.destination.lng) });
                    }

                    if (points.length < 2) {
                        alert('Please enter and pin coordinates for at least the Origin and Final Destination before calculating route distance.');
                        return;
                    }

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
                        if (data.success) {
                            this.plannedDistance = data.total_distance_km;
                            this.estimatedDuration = data.formatted_total_duration;
                            this.calculatedGeometry = data.geometry;
                        } else {
                            alert('Distance calculation was unable to find road network for the selected points. You can enter distance manually.');
                        }
                    } catch (e) {
                        console.error('Route calculation error:', e);
                    } finally {
                        this.calculatingDistance = false;
                    }
                },

                onFormSubmit(e) {
                    // Ensures that distance is present
                    if (!this.plannedDistance || this.plannedDistance <= 0) {
                        if (!confirm('Planned distance is currently 0 km. Do you want to continue anyway?')) {
                            e.preventDefault();
                        }
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
