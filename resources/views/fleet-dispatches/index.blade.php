<x-app-layout>
    @section('page-title', 'Fleet Dispatch Monitoring')

    <div class="p-6 space-y-6 max-w-7xl mx-auto" x-data="{
        showCreateModal: false,
        showDepartureModal: false,
        selectedDispatch: null,
        departureTime: '{{ now()->format('Y-m-d\TH:i') }}',
        departureSource: 'authorized_dispatch',
        itineraries: {{ Js::from($approvedItineraries) }},
        selectedItineraryId: '',
        vehicleId: '',
        driverName: '',
        tripDate: '{{ now()->format('Y-m-d') }}',
        startingPoint: 'Main Yard',
        destination: '',
        plannedDistance: 0,
        plannedDeparture: '{{ now()->format('Y-m-d\T08:00') }}',
        onItinerarySelect() {
            if (!this.selectedItineraryId) return;
            const it = this.itineraries.find(i => i.id == this.selectedItineraryId);
            if (it) {
                this.vehicleId = it.vehicle_id || '';
                this.driverName = it.driver_name || (it.vehicle ? it.vehicle.operator_driver : '');
                this.tripDate = it.itinerary_date || '';
                this.destination = it.destination || '';
                this.plannedDistance = it.total_distance || 0;
            }
        },
        openDeparture(dispatch) {
            this.selectedDispatch = dispatch;
            this.departureTime = dispatch.actual_departure_time 
                ? dispatch.actual_departure_time.replace(' ', 'T').substring(0, 16) 
                : '{{ now()->format('Y-m-d\TH:i') }}';
            this.departureSource = dispatch.departure_source || 'authorized_dispatch';
            this.showDepartureModal = true;
        }
    }">

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
                        Live Punctuality
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    On-Time Dispatch Rate (OTDR), departure grace threshold controls, and trip punctuality tracking.
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
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
                    <form action="{{ route('fleet-dispatches.generate-from-itineraries') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" 
                                title="Auto-create trip dispatches from approved itineraries"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100 px-3.5 py-2 text-xs font-bold text-indigo-700 dark:text-indigo-300 shadow-2xs transition-colors">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            Auto-Generate from Itineraries
                        </button>
                    </form>
                    <button type="button" @click="showCreateModal = true"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 px-4 py-2 text-xs font-bold text-white shadow-sm transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        New Trip Dispatch
                    </button>
                @endcan
            </div>
        </div>

        {{-- KPI Cards: On-Time Dispatch Rate Dashboard --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            {{-- Rate Main Card --}}
            <div class="sm:col-span-2 rounded-2xl bg-gradient-to-br from-blue-900 via-indigo-900 to-slate-900 text-white p-5 shadow-sm border border-blue-800/50 flex flex-col justify-between relative overflow-hidden">
                <div class="absolute -right-4 -bottom-4 h-32 w-32 rounded-full bg-blue-500/10 pointer-events-none blur-xl"></div>
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-blue-200/80">On-Time Dispatch Rate</span>
                        <div class="text-[10px] text-blue-300/70 mt-0.5">Eligible On-Time &divide; Total Eligible &times; 100</div>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $statistics['on_time_rate'] >= 90 ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : ($statistics['on_time_rate'] >= 75 ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-red-500/20 text-red-300 border border-red-500/30') }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $statistics['on_time_rate'] >= 90 ? 'bg-emerald-400' : ($statistics['on_time_rate'] >= 75 ? 'bg-amber-400' : 'bg-red-400') }}"></span>
                        {{ $statistics['on_time_rate'] >= 90 ? 'Optimal' : ($statistics['on_time_rate'] >= 75 ? 'Acceptable' : 'Needs Attention') }}
                    </span>
                </div>
                <div class="my-4 flex items-baseline gap-3">
                    <span class="text-4xl sm:text-5xl font-black tracking-tight text-white">{{ number_format($statistics['on_time_rate'], 1) }}%</span>
                    <span class="text-xs text-blue-200">
                        {{ $statistics['on_time_count'] }} of {{ $statistics['eligible_count'] }} eligible departures
                    </span>
                </div>
                <div class="w-full bg-slate-800/80 rounded-full h-2 overflow-hidden border border-slate-700/60">
                    <div class="h-2 rounded-full {{ $statistics['on_time_rate'] >= 90 ? 'bg-emerald-400' : ($statistics['on_time_rate'] >= 75 ? 'bg-amber-400' : 'bg-red-400') }} transition-all duration-500"
                         style="width: {{ min(100, $statistics['on_time_rate']) }}%"></div>
                </div>
            </div>

            {{-- On-Time Departures --}}
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-200 dark:border-slate-800 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">On-Time</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-3xl font-black text-slate-900 dark:text-white">{{ $statistics['on_time_count'] }}</span>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">&le; 15m grace window</p>
                </div>
            </div>

            {{-- Delayed Departures --}}
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-200 dark:border-slate-800 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Delayed</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-red-500/10 text-red-600 dark:text-red-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-3xl font-black text-red-600 dark:text-red-400">{{ $statistics['delayed_count'] }}</span>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Exceeded departure time</p>
                </div>
            </div>

            {{-- Pending Departures --}}
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-200 dark:border-slate-800 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Pending</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-3xl font-black text-sky-600 dark:text-sky-400">{{ $statistics['pending_count'] }}</span>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Scheduled for future departure</p>
                </div>
            </div>
        </div>

        {{-- Filter Bar --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-200 dark:border-slate-800">
            <form method="GET" action="{{ route('fleet-dispatches.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 items-end">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Start Date</label>
                    <input type="date" name="start_date" value="{{ $filters['start_date'] }}"
                           class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs font-medium text-slate-800 dark:text-slate-100 outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">End Date</label>
                    <input type="date" name="end_date" value="{{ $filters['end_date'] }}"
                           class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs font-medium text-slate-800 dark:text-slate-100 outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Vehicle</label>
                    <select name="vehicle_id" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs font-medium text-slate-800 dark:text-slate-100 outline-none focus:border-blue-500">
                        <option value="">All Vehicles</option>
                        @foreach($vehicles as $veh)
                            <option value="{{ $veh->id }}" {{ $filters['vehicle_id'] == $veh->id ? 'selected' : '' }}>
                                {{ $veh->equipment_code }} {{ $veh->plate_number ? "({$veh->plate_number})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Status</label>
                    <select name="status" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs font-medium text-slate-800 dark:text-slate-100 outline-none focus:border-blue-500">
                        <option value="all">All Statuses</option>
                        <option value="ON_TIME" {{ $filters['status'] === 'ON_TIME' ? 'selected' : '' }}>On Time</option>
                        <option value="DELAYED" {{ $filters['status'] === 'DELAYED' ? 'selected' : '' }}>Delayed</option>
                        <option value="PENDING" {{ $filters['status'] === 'PENDING' ? 'selected' : '' }}>Pending</option>
                        <option value="CANCELLED" {{ $filters['status'] === 'CANCELLED' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="flex-1 rounded-xl bg-slate-800 dark:bg-slate-700 hover:bg-slate-900 px-3 py-2 text-xs font-bold text-white transition-colors">
                        Filter
                    </button>
                    <a href="{{ route('fleet-dispatches.index') }}" class="rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- Dispatches Table --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Vehicle Dispatch & Departure Punctuality</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Planned vs actual departure times with official event attribution.</p>
                </div>
                <span class="text-xs font-semibold text-slate-500">{{ $dispatches->total() }} records</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Trip Reference</th>
                            <th class="px-4 py-3 font-semibold">Vehicle & Plate</th>
                            <th class="px-4 py-3 font-semibold">Driver</th>
                            <th class="px-4 py-3 font-semibold">Route & Destination</th>
                            <th class="px-4 py-3 font-semibold">Planned Dep.</th>
                            <th class="px-4 py-3 font-semibold">Actual Dep.</th>
                            <th class="px-4 py-3 font-semibold">Delay</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold">Source</th>
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
                            @endphp
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-4 py-3.5 font-mono font-bold text-slate-900 dark:text-white">
                                    {{ $dispatch->trip_number }}
                                    <div class="text-[10px] font-sans text-slate-400">{{ $dispatch->trip_date->format('M d, Y') }}</div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-slate-800 dark:text-slate-100">{{ $veh?->equipment_code ?? '—' }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono">{{ $veh?->plate_number ?? 'No Plate' }}</div>
                                </td>
                                <td class="px-4 py-3.5 text-slate-700 dark:text-slate-300">
                                    {{ $dispatch->driver_name ?: ($veh?->operator_driver ?: '—') }}
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="font-semibold text-slate-800 dark:text-slate-200">{{ $dispatch->destination }}</div>
                                    <div class="text-[10px] text-slate-400">From: {{ $dispatch->starting_point }} &bull; {{ number_format($dispatch->planned_distance_km, 1) }} KM</div>
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
                                    @if($dispatch->delay_minutes > 0)
                                        <span class="font-bold text-red-600 dark:text-red-400">+{{ $dispatch->delay_minutes }} min</span>
                                    @else
                                        <span class="text-emerald-600 dark:text-emerald-400 font-medium">On schedule</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold border {{ $statusBadge }}">
                                        {{ str_replace('_', ' ', $dispatch->dispatch_status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-[11px] text-slate-500">
                                    {{ $dispatch->departure_source ? ucwords(str_replace('_', ' ', $dispatch->departure_source)) : '—' }}
                                </td>
                                <td class="px-4 py-3.5 text-right space-x-1.5 whitespace-nowrap">
                                    @can('recordDeparture', $dispatch)
                                        @if(!$dispatch->actual_departure_time)
                                            <form action="{{ route('fleet-dispatches.depart-now', $dispatch) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" title="Mark vehicle departed right now"
                                                        class="rounded-lg bg-emerald-600 hover:bg-emerald-700 px-2 py-1 text-[11px] font-bold text-white shadow-2xs transition-colors">
                                                    Depart Now
                                                </button>
                                            </form>
                                        @endif
                                        <button type="button" @click="openDeparture({{ Js::from($dispatch) }})"
                                                class="rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 px-2.5 py-1 text-[11px] font-bold text-slate-700 dark:text-slate-200 transition-colors">
                                            {{ $dispatch->actual_departure_time ? 'Edit Dep.' : 'Custom Dep.' }}
                                        </button>
                                    @endcan
                                    @if($dispatch->reconciliation)
                                        <a href="{{ route('mileage-reconciliations.show', $dispatch->reconciliation) }}"
                                            class="rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 hover:bg-blue-100 px-2.5 py-1 text-[11px] font-bold transition-colors">
                                            Reconciliation &rarr;
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-6 py-12 text-center text-slate-400">
                                    No dispatch records found. Click <strong>"+ New Trip Dispatch"</strong> to schedule a trip departure.
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
                            <div class="flex justify-between">
                                <span class="text-slate-400 font-semibold uppercase">Scheduled:</span>
                                <span class="font-bold text-blue-600 dark:text-blue-400" x-text="selectedDispatch.planned_departure_time"></span>
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
                            <p class="text-[10px] text-slate-500 mt-1">Per policy, GPS movement alone requires verification unless logged as official event.</p>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                            <button type="button" @click="showDepartureModal = false"
                                    class="rounded-xl bg-slate-100 dark:bg-slate-800 px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-200">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-sm">
                                Save Departure & Recalculate
                            </button>
                        </div>
                    </form>
                </template>
            </div>
        </div>

        {{-- New Dispatch Modal --}}
        <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="showCreateModal = false" class="w-full max-w-xl rounded-2xl bg-white dark:bg-slate-900 shadow-2xl border border-slate-200 dark:border-slate-800 p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Create New Trip Dispatch</h3>
                        <p class="text-xs text-slate-500">Schedule departure and initialize mileage reconciliation workflow.</p>
                    </div>
                    <button type="button" @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <form action="{{ route('fleet-dispatches.store') }}" method="POST" class="space-y-4">
                    @csrf

                    {{-- Quick Link Approved Itinerary --}}
                    @if($approvedItineraries->isNotEmpty())
                        <div class="rounded-xl bg-blue-50 dark:bg-blue-900/20 p-3 border border-blue-200 dark:border-blue-800/40">
                            <label class="block text-xs font-bold text-blue-900 dark:text-blue-300 mb-1">
                                Optional: Prefill from Finalized Itinerary
                            </label>
                            <select name="advanced_itinerary_id" x-model="selectedItineraryId" @change="onItinerarySelect()"
                                    class="w-full rounded-xl border border-blue-300 dark:border-blue-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-800 dark:text-slate-100 outline-none">
                                <option value="">-- Manual Trip Entry (No linked itinerary) --</option>
                                <template x-for="it in itineraries" :key="it.id">
                                    <option :value="it.id" x-text="`[${it.itinerary_date}] ${it.vehicle ? it.vehicle.equipment_code : 'N/A'} → ${it.destination || 'Trip'} (${it.total_distance || 0} KM)`"></option>
                                </template>
                            </select>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Vehicle *</label>
                            <select name="vehicle_id" x-model="vehicleId" required
                                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-800 dark:text-white outline-none focus:border-blue-500">
                                <option value="">-- Select Vehicle --</option>
                                @foreach($vehicles as $v)
                                    <option value="{{ $v->id }}">
                                        {{ $v->equipment_code }} &bull; {{ $v->plate_number ?: 'No Plate' }} {{ $v->hasGps() ? '(GPS)' : '(Non-GPS)' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Driver Name</label>
                            <input type="text" name="driver_name" x-model="driverName" placeholder="Driver / Operator"
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-800 dark:text-white outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Trip Date *</label>
                            <input type="date" name="trip_date" x-model="tripDate" required
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-800 dark:text-white outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Planned Departure Time *</label>
                            <input type="datetime-local" name="planned_departure_time" x-model="plannedDeparture" required
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-800 dark:text-white outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Starting Point *</label>
                            <input type="text" name="starting_point" x-model="startingPoint" required
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-800 dark:text-white outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Destination *</label>
                            <input type="text" name="destination" x-model="destination" required placeholder="Destination Facility or City"
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-800 dark:text-white outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Planned Distance (KM) *</label>
                            <input type="number" step="0.01" name="planned_distance_km" x-model="plannedDistance" required
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-800 dark:text-white outline-none focus:border-blue-500">
                            <span class="text-[10px] text-slate-400">Historical snapshot: changes to routing will not alter past reconciliations.</span>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Punctuality Grace Period (Min)</label>
                            <input type="number" name="grace_period_minutes" value="15"
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-800 dark:text-white outline-none focus:border-blue-500">
                        </div>
                    </div>

                    {{-- Mileage Reconciliation Initialization --}}
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-800/50 p-3 border border-slate-200 dark:border-slate-700 space-y-2">
                        <label class="flex items-center gap-2 text-xs font-bold text-slate-800 dark:text-slate-200 cursor-pointer">
                            <input type="checkbox" name="create_reconciliation" value="1" checked class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            <span>Initialize Mileage Reconciliation in Draft immediately</span>
                        </label>
                        <div class="grid grid-cols-2 gap-3 pt-1">
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-slate-500 mb-0.5">Initial Starting Odo</label>
                                <input type="number" step="0.1" name="starting_odometer" placeholder="e.g. 50000"
                                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1.5 text-xs text-slate-800 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-slate-500 mb-0.5">Initial Ending Odo</label>
                                <input type="number" step="0.1" name="ending_odometer" placeholder="e.g. 50125"
                                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1.5 text-xs text-slate-800 dark:text-white">
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showCreateModal = false"
                                class="rounded-xl bg-slate-100 dark:bg-slate-800 px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-sm">
                            Create Trip & Initialize Reconciliation
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
