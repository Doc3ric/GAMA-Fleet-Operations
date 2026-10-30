<div class="space-y-4">

    {{-- Feedback & Error Alerts --}}
    @if ($feedbackMessage)
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-xs font-semibold text-emerald-800 flex items-center justify-between dark:bg-emerald-950/50 dark:border-emerald-800 dark:text-emerald-300">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 text-emerald-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span>{{ $feedbackMessage }}</span>
            </div>
            <button type="button" wire:click="$set('feedbackMessage', null)" class="text-emerald-500 hover:text-emerald-700">&times;</button>
        </div>
    @endif

    @if ($errorMessage)
        <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-xs font-semibold text-red-800 flex items-center justify-between dark:bg-red-950/50 dark:border-red-800 dark:text-red-300">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 text-red-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <span>{{ $errorMessage }}</span>
            </div>
            <button type="button" wire:click="$set('errorMessage', null)" class="text-red-500 hover:text-red-700">&times;</button>
        </div>
    @endif

    {{-- Top Action Header --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-1">
        <div>
            <div class="flex items-center gap-2.5">
                <h2 class="text-xl font-bold text-slate-800 dark:text-white tracking-tight">Idling & Parking Detection</h2>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    Continuous Telematics Analysis
                </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Automated stationary event tracking, ignition state verification, and duration audit logs</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- Live Fleet Scan --}}
            <button type="button"
                    wire:click="scanLiveFleet"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors disabled:opacity-50 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200">
                <svg wire:loading.class="animate-spin text-blue-600" class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                <span wire:loading.remove wire:target="scanLiveFleet">Live Telematics Scan</span>
                <span wire:loading wire:target="scanLiveFleet">Evaluating Fleet...</span>
            </button>

            {{-- Reconstruct History Button --}}
            <button type="button"
                    wire:click="reconstructHistory"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-3.5 py-2 text-xs font-bold text-white shadow-xs hover:bg-blue-700 transition-colors disabled:opacity-50"
                    title="Analyze raw TrackSolid Pro track points to extract confirmed idling and parking intervals">
                <svg wire:loading.class="animate-spin" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span wire:loading.remove wire:target="reconstructHistory">Reconstruct GPS History</span>
                <span wire:loading wire:target="reconstructHistory">Analyzing GPS Points...</span>
            </button>

            {{-- Live Map Link --}}
            <a href="{{ route('gps-map.index') }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200">
                <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                </svg>
                <span>Live Map</span>
            </a>
        </div>
    </div>

    {{-- Metrics KPI Bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-3">
        {{-- Ongoing Idling --}}
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Active Idling</span>
                <span class="h-2 w-2 rounded-full bg-amber-500 animate-ping"></span>
            </div>
            <div class="mt-2 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-amber-600">{{ $stats['ongoing_idling'] }}</span>
                <span class="text-xs text-slate-400">units right now</span>
            </div>
            <span class="text-[11px] text-slate-500 mt-1 block">Engine ON &bull; Stationary</span>
        </div>

        {{-- Ongoing Parked --}}
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-700 dark:text-blue-400">Active Parked</span>
                <span class="h-2 w-2 rounded-full bg-blue-500"></span>
            </div>
            <div class="mt-2 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-blue-600">{{ $stats['ongoing_parking'] }}</span>
                <span class="text-xs text-slate-400">units</span>
            </div>
            <span class="text-[11px] text-slate-500 mt-1 block">Engine OFF &bull; Connected</span>
        </div>

        {{-- Total Idling Hours --}}
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total Idling Time</span>
            <div class="mt-2 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900 dark:text-white">{{ $stats['total_idling_hours'] }}</span>
                <span class="text-xs text-slate-400">Hours</span>
            </div>
            <span class="text-[11px] text-slate-500 mt-1 block">{{ $stats['total_idling_events'] }} confirmed events</span>
        </div>

        {{-- Total Parking Hours --}}
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total Parked Time</span>
            <div class="mt-2 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-slate-900 dark:text-white">{{ $stats['total_parking_hours'] }}</span>
                <span class="text-xs text-slate-400">Hours</span>
            </div>
            <span class="text-[11px] text-slate-500 mt-1 block">{{ $stats['total_parking_events'] }} parking stays</span>
        </div>

        {{-- Currently Moving --}}
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Active Moving</span>
                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
            </div>
            <div class="mt-2 flex items-baseline gap-1.5">
                <span class="text-2xl font-black text-emerald-600">{{ $stats['ongoing_moving'] }}</span>
                <span class="text-xs text-slate-400">in transit</span>
            </div>
            <span class="text-[11px] text-slate-500 mt-1 block">&ge; 3.0 km/h</span>
        </div>
    </div>

    {{-- Filters & Controls Panel --}}
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-3.5">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
            {{-- Date Presets --}}
            <div class="lg:col-span-3">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Time Horizon</label>
                <div class="grid grid-cols-4 gap-1">
                    <button type="button" wire:click="setDateRange('today')" class="px-2 py-1.5 text-xs font-semibold rounded-lg border text-center transition-colors {{ $datePreset === 'today' ? 'bg-slate-900 border-slate-900 text-white dark:bg-blue-600 dark:border-blue-600' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300' }}">Today</button>
                    <button type="button" wire:click="setDateRange('yesterday')" class="px-2 py-1.5 text-xs font-semibold rounded-lg border text-center transition-colors {{ $datePreset === 'yesterday' ? 'bg-slate-900 border-slate-900 text-white dark:bg-blue-600 dark:border-blue-600' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300' }}">Yest.</button>
                    <button type="button" wire:click="setDateRange('7days')" class="px-2 py-1.5 text-xs font-semibold rounded-lg border text-center transition-colors {{ $datePreset === '7days' ? 'bg-slate-900 border-slate-900 text-white dark:bg-blue-600 dark:border-blue-600' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300' }}">7 Days</button>
                    <button type="button" wire:click="setDateRange('30days')" class="px-2 py-1.5 text-xs font-semibold rounded-lg border text-center transition-colors {{ $datePreset === '30days' ? 'bg-slate-900 border-slate-900 text-white dark:bg-blue-600 dark:border-blue-600' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300' }}">30 Days</button>
                </div>
            </div>

            {{-- Custom Date Inputs --}}
            <div class="lg:col-span-3 grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Start Date</label>
                    <input type="date" wire:model.live="startDate" class="w-full text-xs font-medium rounded-xl border-slate-200 bg-slate-50/50 focus:bg-white focus:border-blue-500 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100" />
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">End Date</label>
                    <input type="date" wire:model.live="endDate" class="w-full text-xs font-medium rounded-xl border-slate-200 bg-slate-50/50 focus:bg-white focus:border-blue-500 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100" />
                </div>
            </div>

            {{-- Vehicle Selector --}}
            <div class="lg:col-span-3">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Fleet Vehicle</label>
                <select wire:model.live="selectedVehicleId" class="w-full text-xs font-medium rounded-xl border-slate-200 bg-slate-50/50 focus:bg-white focus:border-blue-500 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100">
                    <option value="">-- All Fleet Vehicles --</option>
                    @foreach($vehicles as $v)
                        <option value="{{ $v->id }}">{{ $v->equipment_code }} &bull; {{ $v->plate_number }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Search Bar --}}
            <div class="lg:col-span-3">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Search Keyword</label>
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Filter equipment, plate, driver, IMEI..." class="w-full text-xs font-medium rounded-xl border-slate-200 bg-slate-50/50 pr-8 focus:bg-white focus:border-blue-500 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100" />
                    @if($search)
                        <button type="button" wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600">&times;</button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Second Filter Row: Event Type, Status Mode, Data Quality --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-slate-400 text-[11px] font-semibold mr-1">Event Type:</span>
                @foreach(['all' => 'All Events', 'idling' => 'Idling', 'parking' => 'Parked', 'moving' => 'Moving', 'offline' => 'Offline'] as $typeKey => $typeLabel)
                    <button type="button"
                            wire:click="$set('eventType', '{{ $typeKey }}')"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors {{ $eventType === $typeKey ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' }}">
                        {{ $typeLabel }}
                    </button>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-1">
                    <span class="text-slate-400 text-[11px] font-semibold">State:</span>
                    <select wire:model.live="statusMode" class="text-xs py-1 px-2.5 rounded-lg border-slate-200 bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
                        <option value="all">All States</option>
                        <option value="ongoing">Ongoing Only</option>
                        <option value="completed">Completed Only</option>
                    </select>
                </div>

                <div class="flex items-center gap-1">
                    <span class="text-slate-400 text-[11px] font-semibold">Quality:</span>
                    <select wire:model.live="dataQuality" class="text-xs py-1 px-2.5 rounded-lg border-slate-200 bg-slate-50 dark:bg-slate-800 dark:border-slate-700">
                        <option value="all">All Quality</option>
                        <option value="good">Verified Good</option>
                        <option value="stale">Stale Telemetry</option>
                        <option value="intermittent">Intermittent</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Events Table Card --}}
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white">Detected Stationary & Movement Intervals</h3>
                <span class="text-xs text-slate-400">({{ $events->total() }} records)</span>
            </div>

            <span class="text-xs text-slate-400 font-mono">
                Showing {{ $events->firstItem() ?? 0 }}-{{ $events->lastItem() ?? 0 }} of {{ $events->total() }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/80 dark:bg-slate-800/80 border-b border-slate-100 dark:border-slate-800 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="py-3 px-4">Event Type</th>
                        <th class="py-3 px-4">Vehicle Unit</th>
                        <th class="py-3 px-4">Driver / Operator</th>
                        <th class="py-3 px-4">Start Time</th>
                        <th class="py-3 px-4">End Time</th>
                        <th class="py-3 px-4">Duration</th>
                        <th class="py-3 px-4">Location Coordinates</th>
                        <th class="py-3 px-4">Source & Quality</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($events as $event)
                        @php
                            $badge = $event->badge_color;
                        @endphp
                        <tr wire:key="event-row-{{ $event->id }}" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                            {{-- Event Type Badge --}}
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border {{ $badge['bg'] }} {{ $badge['text'] }} {{ $badge['border'] }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $badge['dot'] }} {{ $event->is_ongoing ? 'animate-ping' : '' }}"></span>
                                    <span>{{ $event->event_type_label }}</span>
                                </span>
                                @if($event->is_ongoing)
                                    <span class="ml-1 text-[9px] font-bold uppercase tracking-wider text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded-md border border-amber-200">Ongoing</span>
                                @endif
                            </td>

                            {{-- Vehicle Unit --}}
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($event->vehicle)
                                    <div class="font-bold text-slate-800 dark:text-slate-100">{{ $event->vehicle->equipment_code }}</div>
                                    <div class="text-[11px] text-slate-400">{{ $event->vehicle->plate_number }}</div>
                                @else
                                    <span class="text-purple-600 font-semibold font-mono">{{ $event->imei }}</span>
                                    <div class="text-[10px] text-slate-400">Unmapped Device</div>
                                @endif
                            </td>

                            {{-- Driver --}}
                            <td class="py-3 px-4 whitespace-nowrap text-slate-600 dark:text-slate-300">
                                {{ $event->vehicle?->operator_driver ?: 'Unassigned' }}
                            </td>

                            {{-- Start Time --}}
                            <td class="py-3 px-4 whitespace-nowrap font-mono text-slate-700 dark:text-slate-300">
                                {{ $event->start_time ? $event->start_time->format('M d, Y h:i A') : 'N/A' }}
                            </td>

                            {{-- End Time --}}
                            <td class="py-3 px-4 whitespace-nowrap font-mono text-slate-700 dark:text-slate-300">
                                @if($event->is_ongoing)
                                    <span class="text-emerald-600 font-bold flex items-center gap-1">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Still Active
                                    </span>
                                @else
                                    {{ $event->end_time ? $event->end_time->format('M d, Y h:i A') : 'N/A' }}
                                @endif
                            </td>

                            {{-- Duration --}}
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="font-mono text-xs font-bold text-slate-900 dark:text-white {{ $event->is_ongoing ? 'text-amber-600' : '' }}">
                                    {{ $event->formatted_duration }}
                                </span>
                            </td>

                            {{-- Location --}}
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($event->latitude && $event->longitude)
                                    <a href="https://www.google.com/maps?q={{ $event->latitude }},{{ $event->longitude }}"
                                       target="_blank"
                                       class="inline-flex items-center gap-1 font-mono text-[11px] text-blue-600 hover:underline">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                        </svg>
                                        {{ round($event->latitude, 4) }}, {{ round($event->longitude, 4) }}
                                    </a>
                                @else
                                    <span class="text-slate-400">No fix</span>
                                @endif
                            </td>

                            {{-- Source & Quality --}}
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="text-[10px] text-slate-500 uppercase tracking-wider block font-semibold">
                                    {{ str_replace('_', ' ', $event->source) }}
                                </span>
                                <span class="text-[10px] text-emerald-600 font-medium">Quality: {{ ucfirst($event->data_quality) }}</span>
                            </td>

                            {{-- Actions --}}
                            <td class="py-3 px-4 whitespace-nowrap text-right">
                                <button type="button"
                                        wire:click="viewEvent({{ $event->id }})"
                                        class="px-2.5 py-1 text-xs font-bold text-blue-600 hover:text-blue-800 bg-blue-50 dark:bg-blue-950/60 rounded-lg transition-colors">
                                    Inspect &rarr;
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="p-8 text-center text-xs text-slate-400">
                                No telemetry events found matching current criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination Footer --}}
        @if ($events->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                {{ $events->links() }}
            </div>
        @endif
    </div>

    {{-- Event Detail Slide-over / Modal --}}
    @if ($viewingEvent)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 dark:border-slate-800 space-y-4 relative">
                <button type="button"
                        wire:click="closeEventModal"
                        class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 text-lg font-bold">
                    &times;
                </button>

                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600">Telemetry Event Detail</span>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white mt-0.5">
                        {{ $viewingEvent->vehicle?->equipment_code ?: "Device {$viewingEvent->imei}" }}
                    </h3>
                    <p class="text-xs text-slate-500">{{ $viewingEvent->event_type_label }} &bull; {{ $viewingEvent->formatted_duration }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs bg-slate-50 dark:bg-slate-800/60 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700">
                    <div>
                        <span class="text-[10px] text-slate-400 uppercase tracking-wider block">Start Time</span>
                        <strong class="text-slate-800 dark:text-slate-100">{{ $viewingEvent->start_time?->format('Y-m-d H:i:s') }}</strong>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 uppercase tracking-wider block">End Time</span>
                        <strong class="text-slate-800 dark:text-slate-100">{{ $viewingEvent->end_time ? $viewingEvent->end_time->format('Y-m-d H:i:s') : 'Ongoing (Active)' }}</strong>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 uppercase tracking-wider block">Duration</span>
                        <strong class="text-slate-800 dark:text-slate-100">{{ $viewingEvent->formatted_duration }} ({{ $viewingEvent->duration_seconds }}s)</strong>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 uppercase tracking-wider block">IMEI</span>
                        <strong class="font-mono text-slate-800 dark:text-slate-100">{{ $viewingEvent->imei }}</strong>
                    </div>
                </div>

                <div class="text-xs space-y-1.5 text-slate-600 dark:text-slate-300">
                    <p><strong>Coordinates:</strong> {{ $viewingEvent->latitude && $viewingEvent->longitude ? "{$viewingEvent->latitude}, {$viewingEvent->longitude}" : 'No GPS coordinates available' }}</p>
                    <p><strong>Source:</strong> {{ ucfirst(str_replace('_', ' ', $viewingEvent->source)) }}</p>
                    <p><strong>Quality Assurance:</strong> {{ ucfirst($viewingEvent->data_quality) }}</p>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    @if($viewingEvent->latitude && $viewingEvent->longitude)
                        <a href="https://www.google.com/maps?q={{ $viewingEvent->latitude }},{{ $viewingEvent->longitude }}"
                           target="_blank"
                           class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-800">
                            <span>Open in Google Maps &rarr;</span>
                        </a>
                    @else
                        <span></span>
                    @endif

                    <button type="button"
                            wire:click="closeEventModal"
                            class="px-4 py-2 bg-slate-800 text-white rounded-xl text-xs font-bold hover:bg-slate-900 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
