<x-app-layout>
    @section('page-title', 'Reconciliation ' . $reconciliation->reconciliation_number)

    <div class="p-6 space-y-6 max-w-7xl mx-auto" x-data="{
        showApproveModal: false,
        showRejectModal: false,
        showReopenModal: false,
        showResolveModal: false,
        selectedException: null,
        resolveAction: 'resolve',
        resolveRemarks: '',
        openExceptionModal(exc, action) {
            this.selectedException = exc;
            this.resolveAction = action;
            this.resolveRemarks = '';
            this.showResolveModal = true;
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

        @if ($errors->any())
            <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-800 space-y-1">
                <div class="font-bold flex items-center gap-2">
                    <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    Action Required
                </div>
                <ul class="list-disc list-inside text-xs">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
                    <a href="{{ route('mileage-reconciliations.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400">&larr; Back to Reconciliations</a>
                    <span>&bull;</span>
                    <span>Trip {{ $reconciliation->dispatch?->trip_number ?? '—' }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold font-mono text-slate-900 dark:text-white">{{ $reconciliation->reconciliation_number }}</h1>
                    @php
                        $statusBadge = match($reconciliation->status) {
                            'APPROVED' => 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20 font-bold',
                            'SUBMITTED' => 'bg-blue-500/10 text-blue-600 border-blue-500/20 font-semibold',
                            'FOR_REVIEW' => 'bg-amber-500/10 text-amber-600 border-amber-500/20 font-bold',
                            'REJECTED' => 'bg-red-500/10 text-red-600 border-red-500/20 font-bold',
                            default => 'bg-slate-500/10 text-slate-600 border-slate-500/20',
                        };
                    @endphp
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs border {{ $statusBadge }}">
                        <span class="h-2 w-2 rounded-full {{ $reconciliation->isApproved() ? 'bg-emerald-500' : ($reconciliation->isRejected() ? 'bg-red-500' : ($reconciliation->isForReview() || $reconciliation->isSubmitted() ? 'bg-amber-500' : 'bg-slate-400')) }}"></span>
                        {{ $reconciliation->status }}
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold border {{ $reconciliation->is_gps_vehicle ? 'bg-indigo-500/10 text-indigo-600 border-indigo-500/20' : 'bg-slate-500/10 text-slate-600 border-slate-500/20' }}">
                        {{ $reconciliation->is_gps_vehicle ? 'GPS-Equipped Vehicle' : 'Non-GPS Vehicle' }}
                    </span>
                </div>
            </div>

            {{-- Workflow Quick Buttons --}}
            <div class="flex items-center gap-2 flex-wrap">
                @if($reconciliation->isDraft())
                    @can('submit', $reconciliation)
                        <form action="{{ route('mileage-reconciliations.submit', $reconciliation) }}" method="POST">
                            @csrf
                            <button type="submit" class="rounded-xl bg-blue-600 hover:bg-blue-700 px-4 py-2 text-xs font-bold text-white shadow-sm transition-colors">
                                Submit for Review &rarr;
                            </button>
                        </form>
                    @endcan
                @endif

                @if($reconciliation->isSubmitted() || $reconciliation->isForReview())
                    @can('approve', $reconciliation)
                        <button type="button" @click="showApproveModal = true"
                                class="rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-xs font-bold text-white shadow-sm transition-colors">
                            Approve Reconciliation
                        </button>
                    @endcan
                    @can('reject', $reconciliation)
                        <button type="button" @click="showRejectModal = true"
                                class="rounded-xl bg-red-600 hover:bg-red-700 px-3.5 py-2 text-xs font-bold text-white shadow-sm transition-colors">
                            Reject
                        </button>
                    @endcan
                @endif

                @if($reconciliation->isApproved() || $reconciliation->isRejected())
                    @can('reopen', $reconciliation)
                        <button type="button" @click="showReopenModal = true"
                                class="rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 transition-colors">
                            Reopen for Revision
                        </button>
                    @endcan
                @endif
            </div>
        </div>

        {{-- Metrics & Calculations Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Planned Distance --}}
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-200 dark:border-slate-800">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Planned Route Distance</span>
                <div class="mt-2 flex items-baseline gap-1.5">
                    <span class="text-3xl font-black text-slate-900 dark:text-white">{{ number_format($reconciliation->planned_distance_km, 2) }}</span>
                    <span class="text-xs font-bold text-slate-400">KM</span>
                </div>
                <div class="text-[11px] text-slate-500 mt-2 truncate">
                    From: {{ $reconciliation->dispatch?->starting_point ?? '—' }} &rarr; {{ $reconciliation->dispatch?->destination ?? '—' }}
                </div>
            </div>

            {{-- Actual Odometer KM --}}
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-200 dark:border-slate-800">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Actual Odometer Distance</span>
                <div class="mt-2 flex items-baseline gap-1.5">
                    <span class="text-3xl font-black text-blue-600 dark:text-blue-400">{{ number_format($reconciliation->actual_odometer_km, 2) }}</span>
                    <span class="text-xs font-bold text-blue-500">KM</span>
                </div>
                <div class="text-[11px] text-slate-500 mt-2 font-mono">
                    {{ number_format($reconciliation->starting_odometer ?? 0, 1) }} &rarr; {{ number_format($reconciliation->ending_odometer ?? 0, 1) }}
                </div>
            </div>

            {{-- GPS Reported Distance --}}
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-200 dark:border-slate-800">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">TrackSolid GPS Distance</span>
                    @if($reconciliation->is_gps_vehicle)
                        <span class="text-[10px] text-indigo-500 font-semibold bg-indigo-50 dark:bg-indigo-900/30 px-1.5 py-0.5 rounded">GPS Report</span>
                    @else
                        <span class="text-[10px] text-slate-400 font-medium">N/A (Non-GPS)</span>
                    @endif
                </div>
                <div class="mt-2 flex items-baseline gap-1.5">
                    @if($reconciliation->gps_reported_km !== null)
                        <span class="text-3xl font-black text-slate-900 dark:text-white">{{ number_format($reconciliation->gps_reported_km, 2) }}</span>
                        <span class="text-xs font-bold text-slate-400">KM</span>
                    @else
                        <span class="text-2xl font-bold text-slate-400 italic">{{ $reconciliation->is_gps_vehicle ? 'Pending GPS report' : 'Not Applicable' }}</span>
                    @endif
                </div>
                <div class="text-[11px] text-slate-500 mt-2">
                    @if($reconciliation->odometer_gps_difference_km !== null)
                        Diff vs Odo: <strong>{{ number_format($reconciliation->odometer_gps_difference_km, 2) }} KM</strong>
                    @else
                        {{ $reconciliation->is_gps_vehicle ? 'Import report or sync' : 'Odometer verified via photo' }}
                    @endif
                </div>
            </div>

            {{-- Planned vs Actual Variance --}}
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-200 dark:border-slate-800">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Trip Mileage Variance</span>
                <div class="mt-2 flex items-baseline gap-1.5">
                    <span class="text-3xl font-black {{ $reconciliation->variance_km > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                        {{ ($reconciliation->variance_km > 0 ? '+' : '') . number_format($reconciliation->variance_km, 2) }}
                    </span>
                    <span class="text-xs font-bold text-slate-400">KM</span>
                </div>
                <div class="text-[11px] font-semibold mt-2 {{ $reconciliation->variance_km > 0 ? 'text-red-500' : 'text-emerald-600' }}">
                    @if($reconciliation->variance_percentage !== null)
                        {{ ($reconciliation->variance_percentage > 0 ? '+' : '') . number_format($reconciliation->variance_percentage, 1) }}% variance from planned
                    @else
                        No planned distance benchmark
                    @endif
                </div>
            </div>
        </div>

        {{-- Exceptions Section (if any) --}}
        @if($reconciliation->exceptions->isNotEmpty())
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-200 dark:border-slate-800 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-red-500/10 text-red-600 font-bold">!</div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Mileage & Dispatch Exceptions</h2>
                    </div>
                    <span class="text-xs text-slate-400">{{ $reconciliation->openExceptions->count() }} open exception(s)</span>
                </div>

                <div class="space-y-2">
                    @foreach($reconciliation->exceptions as $exc)
                        @php
                            $sevClass = match($exc->severity) {
                                'DANGER' => 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800/40 text-red-800 dark:text-red-300',
                                'WARNING' => 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800/40 text-amber-800 dark:text-amber-300',
                                default => 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800/40 text-blue-800 dark:text-blue-300',
                            };
                        @endphp
                        <div class="rounded-xl border p-3 text-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 {{ $sevClass }}">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold uppercase tracking-wider text-[10px]">{{ str_replace('_', ' ', $exc->exception_type) }}</span>
                                    <span class="rounded px-1.5 py-0.5 text-[9px] font-bold {{ $exc->isOpen() ? 'bg-red-200 text-red-900' : 'bg-slate-200 text-slate-800' }}">
                                        {{ $exc->status }}
                                    </span>
                                </div>
                                <p class="text-slate-700 dark:text-slate-200">{{ $exc->description }}</p>
                                @if(!$exc->isOpen() && $exc->resolver)
                                    <p class="text-[10px] text-slate-500">
                                        {{ $exc->status }} by {{ $exc->resolver->name }} on {{ $exc->resolved_at?->format('M d, Y H:i') }}.
                                        @if($exc->resolution_remarks) Remarks: "{{ $exc->resolution_remarks }}" @endif
                                    </p>
                                @endif
                            </div>

                            @if($exc->isOpen())
                                @can('approve', $reconciliation)
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button type="button" @click="openExceptionModal({{ Js::from($exc) }}, 'resolve')"
                                                class="rounded-lg bg-emerald-600 hover:bg-emerald-700 px-2.5 py-1 text-[11px] font-bold text-white shadow-2xs">
                                            Resolve
                                        </button>
                                        <button type="button" @click="openExceptionModal({{ Js::from($exc) }}, 'waive')"
                                                class="rounded-lg bg-slate-600 hover:bg-slate-700 px-2.5 py-1 text-[11px] font-bold text-white shadow-2xs">
                                            Waive
                                        </button>
                                    </div>
                                @endcan
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Main Two-Column Layout: Readings & Evidence --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            {{-- Left Column: Trip & Vehicle Info + Odometer Readings Form (7 cols) --}}
            <div class="lg:col-span-7 space-y-6">

                {{-- Trip Summary Details --}}
                <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-200 dark:border-slate-800 space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-2">
                        Trip Information & Vehicle Master Link
                    </h3>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                        <div>
                            <span class="text-[10px] font-bold uppercase text-slate-400 block">Vehicle Code</span>
                            <span class="font-bold text-slate-800 dark:text-slate-100">{{ $reconciliation->vehicle?->equipment_code ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase text-slate-400 block">Plate Number</span>
                            <span class="font-bold font-mono text-slate-800 dark:text-slate-100">{{ $reconciliation->vehicle?->plate_number ?? 'No Plate' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase text-slate-400 block">Assigned Driver</span>
                            <span class="font-bold text-slate-800 dark:text-slate-100">{{ $reconciliation->dispatch?->driver_name ?: ($reconciliation->vehicle?->operator_driver ?: '—') }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase text-slate-400 block">Trip Date</span>
                            <span class="font-medium text-slate-800 dark:text-slate-100">{{ $reconciliation->trip_date->format('F d, Y') }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase text-slate-400 block">Starting Point</span>
                            <span class="font-medium text-slate-800 dark:text-slate-100">{{ $reconciliation->dispatch?->starting_point ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase text-slate-400 block">Destination</span>
                            <span class="font-medium text-slate-800 dark:text-slate-100">{{ $reconciliation->dispatch?->destination ?? '—' }}</span>
                        </div>
                    </div>

                    @if($reconciliation->dispatch?->itinerary)
                        <div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 p-3 text-xs flex items-center justify-between">
                            <div>
                                <span class="font-semibold text-slate-700 dark:text-slate-300">Linked to Itinerary:</span>
                                <span class="font-bold text-blue-600 dark:text-blue-400">#{{ $reconciliation->dispatch->itinerary->id }} &bull; {{ $reconciliation->dispatch->itinerary->destination }}</span>
                            </div>
                            <a href="{{ route('advanced-itineraries.show', $reconciliation->dispatch->itinerary) }}"
                               class="text-blue-600 dark:text-blue-400 hover:underline font-bold text-[11px]">
                                View Itinerary &rarr;
                            </a>
                        </div>
                    @endif
                </div>

                {{-- Odometer Readings Form --}}
                <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-200 dark:border-slate-800 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Odometer & Mileage Validation</h3>
                        @if($reconciliation->isApproved())
                            <span class="text-[11px] font-semibold text-slate-400 italic">Approved (Locked)</span>
                        @endif
                    </div>

                    @can('update', $reconciliation)
                        <form action="{{ route('mileage-reconciliations.update', $reconciliation) }}" method="POST" class="space-y-4">
                            @csrf
                            @method('PUT')

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        Starting Odometer Reading (KM) *
                                    </label>
                                    <input type="number" step="0.1" name="starting_odometer" value="{{ old('starting_odometer', $reconciliation->starting_odometer) }}" required
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-mono font-bold text-slate-900 dark:text-white outline-none focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        Ending Odometer Reading (KM) *
                                    </label>
                                    <input type="number" step="0.1" name="ending_odometer" value="{{ old('ending_odometer', $reconciliation->ending_odometer) }}" required
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-mono font-bold text-slate-900 dark:text-white outline-none focus:border-blue-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                        Planned Distance Benchmark (KM)
                                    </label>
                                    <input type="number" step="0.01" name="planned_distance_km" value="{{ old('planned_distance_km', $reconciliation->planned_distance_km) }}"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-mono text-slate-900 dark:text-white outline-none focus:border-blue-500">
                                </div>
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                            GPS Reported Distance (KM)
                                        </label>
                                        @if($reconciliation->vehicle?->hasGps() || $reconciliation->is_gps_vehicle)
                                            <button type="button" 
                                                    onclick="document.getElementById('sync-gps-form').submit()"
                                                    title="Query TrackSolid Pro API for live trip kilometers"
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/30 text-[11px] font-bold text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 transition-colors">
                                                <span>⚡ Pull Live GPS</span>
                                            </button>
                                        @endif
                                    </div>
                                    <input type="number" step="0.01" name="gps_reported_km" value="{{ old('gps_reported_km', $reconciliation->gps_reported_km) }}"
                                           placeholder="From TrackSolid Pro"
                                           class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-mono text-slate-900 dark:text-white outline-none focus:border-blue-500">
                                </div>
                            </div>

                            <form id="sync-gps-form" action="{{ route('mileage-reconciliations.sync-gps', $reconciliation) }}" method="POST" class="hidden">
                                @csrf
                            </form>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Operational Notes & Remarks</label>
                                <textarea name="notes" rows="2" placeholder="Route observations, traffic, or client delays..."
                                          class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs text-slate-800 dark:text-white outline-none focus:border-blue-500">{{ old('notes', $reconciliation->notes) }}</textarea>
                            </div>

                            <div class="flex items-center justify-between pt-2">
                                <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300 cursor-pointer">
                                    <input type="checkbox" name="is_gps_vehicle" value="1" {{ $reconciliation->is_gps_vehicle ? 'checked' : '' }} class="rounded border-slate-300 text-blue-600">
                                    <span>Classify as GPS-equipped vehicle</span>
                                </label>
                                <button type="submit" class="rounded-xl bg-slate-800 dark:bg-slate-700 hover:bg-slate-900 px-4 py-2 text-xs font-bold text-white transition-colors">
                                    Update Readings & Recalculate
                                </button>
                            </div>
                        </form>
                    @else
                        {{-- Read-only view for approved records --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            <div>
                                <span class="text-[10px] font-bold uppercase text-slate-400 block">Start Odo</span>
                                <span class="font-mono font-bold text-slate-800 dark:text-white">{{ number_format($reconciliation->starting_odometer ?? 0, 1) }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase text-slate-400 block">End Odo</span>
                                <span class="font-mono font-bold text-slate-800 dark:text-white">{{ number_format($reconciliation->ending_odometer ?? 0, 1) }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase text-slate-400 block">Actual Difference</span>
                                <span class="font-mono font-bold text-blue-600 dark:text-blue-400">{{ number_format($reconciliation->actual_odometer_km, 2) }} KM</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase text-slate-400 block">GPS Reading</span>
                                <span class="font-mono font-bold text-slate-800 dark:text-white">{{ $reconciliation->gps_reported_km !== null ? number_format($reconciliation->gps_reported_km, 2) . ' KM' : '—' }}</span>
                            </div>
                        </div>
                    @endcan
                </div>

            </div>

            {{-- Right Column: Evidence & Review Decision (5 cols) --}}
            <div class="lg:col-span-5 space-y-6">

                {{-- Supporting Evidence Card --}}
                <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-200 dark:border-slate-800 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                            </svg>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Supporting Evidence</h3>
                        </div>
                        <span class="text-xs text-slate-500">{{ $reconciliation->evidences->count() }} file(s)</span>
                    </div>

                    {{-- Evidence List --}}
                    <div class="space-y-2">
                        @forelse($reconciliation->evidences as $evi)
                            <div class="rounded-xl border border-slate-200 dark:border-slate-800 p-3 text-xs flex items-center justify-between gap-2 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                        @if($evi->isImage())
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                            </svg>
                                        @elseif($evi->isPdf())
                                            <svg class="h-4 w-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                        @else
                                            <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="truncate">
                                        <div class="font-semibold text-slate-800 dark:text-slate-100 truncate">{{ $evi->original_filename }}</div>
                                        <div class="text-[10px] text-slate-400 capitalize">{{ str_replace('_', ' ', $evi->evidence_type) }} &bull; {{ round($evi->file_size / 1024) }} KB</div>
                                    </div>
                                </div>

                                <a href="{{ route('mileage-reconciliations.evidence.download', $evi) }}"
                                   class="rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 px-2 py-1 text-[11px] font-bold text-slate-700 dark:text-slate-300 shrink-0">
                                    Download &darr;
                                </a>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 dark:border-slate-800 p-4 text-center text-xs text-slate-400">
                                @if(!$reconciliation->is_gps_vehicle)
                                    <span class="text-amber-600 font-semibold block mb-1">&bull; Non-GPS Requirement</span>
                                    Upload an <strong>Odometer Photo</strong> or <strong>Authorized Verification Form</strong> to complete submission.
                                @else
                                    No supporting evidence attached yet. You can attach TrackSolid reports or odometer photos.
                                @endif
                            </div>
                        @endforelse
                    </div>

                    {{-- Upload Evidence Form --}}
                    @can('uploadEvidence', $reconciliation)
                        <form action="{{ route('mileage-reconciliations.evidence.upload', $reconciliation) }}" method="POST" enctype="multipart/form-data" class="space-y-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                            @csrf
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Evidence Type *</label>
                                    <select name="evidence_type" required
                                            class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs text-slate-800 dark:text-white outline-none">
                                        <option value="odometer_photo">Odometer Photo</option>
                                        <option value="verified_reading_form">Verified Reading Form</option>
                                        <option value="gps_report">TrackSolid GPS Report</option>
                                        <option value="other">Other Document</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">File (Max 10MB) *</label>
                                    <input type="file" name="evidence_file" required
                                           class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                </div>
                            </div>
                            <button type="submit" class="w-full rounded-xl bg-blue-600 hover:bg-blue-700 py-2 text-xs font-bold text-white transition-colors shadow-xs">
                                Upload Evidence
                            </button>
                        </form>
                    @endcan
                </div>

                {{-- Review Decision Information --}}
                <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-200 dark:border-slate-800 space-y-3 text-xs">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-2">
                        Review & Approval Status
                    </h3>

                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Current Lifecycle:</span>
                            <span class="font-bold text-slate-800 dark:text-slate-100">{{ $reconciliation->status }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Submitted By:</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ $reconciliation->submitter?->name ?? 'Not yet submitted' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Submission Date:</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ $reconciliation->submitted_at?->format('M d, Y h:i A') ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Reviewer:</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ $reconciliation->reviewer?->name ?? 'Awaiting reviewer' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Reviewed At:</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ $reconciliation->reviewed_at?->format('M d, Y h:i A') ?? '—' }}</span>
                        </div>
                        @if($reconciliation->reviewer_remarks)
                            <div class="rounded-xl bg-slate-50 dark:bg-slate-800 p-3 mt-2">
                                <span class="text-[10px] font-bold uppercase text-slate-400 block mb-0.5">Reviewer Remarks:</span>
                                <p class="text-slate-700 dark:text-slate-300 italic">{{ $reconciliation->reviewer_remarks }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Audit Trail Timeline --}}
                <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-xs border border-slate-200 dark:border-slate-800 space-y-3">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-2">
                        Audit Trail Log
                    </h3>

                    <div class="space-y-3 max-h-60 overflow-y-auto text-xs pr-1">
                        @forelse($reconciliation->auditLogs as $log)
                            <div class="border-l-2 border-slate-300 dark:border-slate-700 pl-3 py-0.5 space-y-0.5">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ $log->action }}</span>
                                    <span class="text-[10px] text-slate-400">{{ $log->created_at->format('M d, H:i') }}</span>
                                </div>
                                <p class="text-slate-600 dark:text-slate-400 text-[11px]">{{ $log->remarks }}</p>
                                <span class="text-[10px] text-slate-400">By: {{ $log->user?->name ?? 'System' }}</span>
                            </div>
                        @empty
                            <p class="text-slate-400 text-xs italic">No audit log entries recorded yet.</p>
                        @endforelse
                    </div>
                </div>

            </div>
        </div>

        {{-- Approve Modal --}}
        <div x-show="showApproveModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="showApproveModal = false" class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 shadow-2xl border border-slate-200 dark:border-slate-800 p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Approve Mileage Reconciliation</h3>
                    <button type="button" @click="showApproveModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
                </div>
                <form action="{{ route('mileage-reconciliations.approve', $reconciliation) }}" method="POST" class="space-y-4">
                    @csrf
                    <p class="text-xs text-slate-600 dark:text-slate-300">
                        Confirming approval for <strong>{{ $reconciliation->reconciliation_number }}</strong>.
                        The record will be officially locked.
                    </p>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Optional Reviewer Remarks</label>
                        <textarea name="remarks" rows="2" placeholder="e.g. Mileage validated against driver log and verified evidence."
                                  class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs text-slate-800 dark:text-white outline-none"></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showApproveModal = false" class="rounded-xl bg-slate-100 dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300">Cancel</button>
                        <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 shadow-sm">Confirm Approval</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Reject Modal --}}
        <div x-show="showRejectModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="showRejectModal = false" class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 shadow-2xl border border-slate-200 dark:border-slate-800 p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white text-red-600">Reject Mileage Reconciliation</h3>
                    <button type="button" @click="showRejectModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
                </div>
                <form action="{{ route('mileage-reconciliations.reject', $reconciliation) }}" method="POST" class="space-y-4">
                    @csrf
                    <p class="text-xs text-slate-600 dark:text-slate-300">
                        Please state the reason for rejecting <strong>{{ $reconciliation->reconciliation_number }}</strong>.
                    </p>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Reason for Rejection *</label>
                        <textarea name="remarks" rows="3" required placeholder="e.g. Unclear odometer photo, or excess unexplained mileage..."
                                  class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs text-slate-800 dark:text-white outline-none"></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showRejectModal = false" class="rounded-xl bg-slate-100 dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300">Cancel</button>
                        <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white hover:bg-red-700 shadow-sm">Confirm Rejection</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Reopen Modal --}}
        <div x-show="showReopenModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="showReopenModal = false" class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 shadow-2xl border border-slate-200 dark:border-slate-800 p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Reopen for Revision</h3>
                    <button type="button" @click="showReopenModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
                </div>
                <form action="{{ route('mileage-reconciliations.reopen', $reconciliation) }}" method="POST" class="space-y-4">
                    @csrf
                    <p class="text-xs text-slate-600 dark:text-slate-300">
                        Reopening this record will revert it to <strong>DRAFT</strong> so corrections can be submitted. An audit log entry will be created.
                    </p>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Documented Revision Reason *</label>
                        <textarea name="reason" rows="3" required placeholder="Describe why this approved or rejected record is being reopened..."
                                  class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs text-slate-800 dark:text-white outline-none"></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showReopenModal = false" class="rounded-xl bg-slate-100 dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300">Cancel</button>
                        <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-sm">Confirm Reopen</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Exception Resolve / Waive Modal --}}
        <div x-show="showResolveModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="showResolveModal = false" class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 shadow-2xl border border-slate-200 dark:border-slate-800 p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white" x-text="resolveAction === 'resolve' ? 'Resolve Exception' : 'Waive Exception'"></h3>
                    <button type="button" @click="showResolveModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
                </div>
                <template x-if="selectedException">
                    <form :action="`/mileage-reconciliations/exceptions/${selectedException.id}/${resolveAction}`" method="POST" class="space-y-4">
                        @csrf
                        <div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 p-3 text-xs space-y-1">
                            <span class="font-bold text-slate-900 dark:text-white block" x-text="selectedException.exception_type"></span>
                            <p class="text-slate-600 dark:text-slate-300" x-text="selectedException.description"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Resolution / Waiver Notes *</label>
                            <textarea name="resolution_remarks" rows="3" required placeholder="Explain why this exception is resolved or waived..."
                                      class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs text-slate-800 dark:text-white outline-none"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                            <button type="button" @click="showResolveModal = false" class="rounded-xl bg-slate-100 dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300">Cancel</button>
                            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-sm" x-text="resolveAction === 'resolve' ? 'Confirm Resolve' : 'Confirm Waive'"></button>
                        </div>
                    </form>
                </template>
            </div>
        </div>

    </div>
</x-app-layout>
