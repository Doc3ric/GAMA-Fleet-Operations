<x-app-layout>
    @section('page-title', 'Mileage Reconciliation')

    <div class="p-6 space-y-6 max-w-7xl mx-auto">

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
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Mileage Reconciliation</h1>
                    <span class="inline-flex items-center rounded-full bg-blue-500/10 px-2.5 py-0.5 text-xs font-semibold text-blue-600 dark:text-blue-400 border border-blue-500/20">
                        Planned vs Actual Controls
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Audited comparison of odometer, GPS run records, and approved itinerary planned mileage with exception flags.
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('mileage-reconciliations.export-excel', request()->query()) }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 shadow-xs transition-colors">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    Export Excel
                </a>
                <a href="{{ route('mileage-reconciliations.export-pdf', request()->query()) }}" target="_blank"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 shadow-xs transition-colors">
                    <svg class="h-4 w-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6" />
                    </svg>
                    Export PDF
                </a>
                <a href="{{ route('tracksolid-reports.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl bg-slate-800 dark:bg-slate-700 hover:bg-slate-900 px-3 py-2 text-xs font-bold text-white transition-colors">
                    <svg class="h-4 w-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    Import TrackSolid Report
                </a>
            </div>
        </div>

        {{-- Summary Cards (Section 6 Requirements) --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
            {{-- Total Trips Requiring Reconciliation --}}
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block truncate">Requiring Rec.</span>
                <span class="text-2xl font-black text-slate-900 dark:text-white mt-1 block">{{ $statistics['total_requiring_reconciliation'] }}</span>
                <span class="text-[10px] text-slate-500 mt-0.5 block">Total Eligible Trips</span>
            </div>

            {{-- Reconciliations Completed --}}
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block truncate">Completed</span>
                <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1 block">{{ $statistics['reconciliations_completed'] }}</span>
                <span class="text-[10px] text-emerald-600/80 mt-0.5 block">Officially Approved</span>
            </div>

            {{-- Pending Reviews --}}
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block truncate">Pending Reviews</span>
                <span class="text-2xl font-black text-amber-500 mt-1 block">{{ $statistics['pending_reviews'] }}</span>
                <span class="text-[10px] text-amber-600/80 mt-0.5 block">Awaiting Decision</span>
            </div>

            {{-- Trips with Exceptions --}}
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block truncate">Exceptions</span>
                <span class="text-2xl font-black text-red-600 dark:text-red-400 mt-1 block">{{ $statistics['trips_with_exceptions'] }}</span>
                <span class="text-[10px] text-red-500 mt-0.5 block">Exceeds Thresholds</span>
            </div>

            {{-- Total Planned KM --}}
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block truncate">Planned Distance</span>
                <span class="text-2xl font-black text-slate-900 dark:text-white mt-1 block">{{ number_format($statistics['total_planned_km'], 1) }}</span>
                <span class="text-[10px] text-slate-500 mt-0.5 block">Total Planned KM</span>
            </div>

            {{-- Total Actual KM --}}
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block truncate">Actual Odometer</span>
                <span class="text-2xl font-black text-blue-600 dark:text-blue-400 mt-1 block">{{ number_format($statistics['total_actual_km'], 1) }}</span>
                <span class="text-[10px] text-blue-500/80 mt-0.5 block">Total Actual KM</span>
            </div>

            {{-- Total Mileage Variance --}}
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block truncate">Net Variance</span>
                <span class="text-2xl font-black {{ $statistics['total_mileage_variance'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }} mt-1 block">
                    {{ ($statistics['total_mileage_variance'] > 0 ? '+' : '') . number_format($statistics['total_mileage_variance'], 1) }}
                </span>
                <span class="text-[10px] text-slate-500 mt-0.5 block">Actual vs Planned KM</span>
            </div>
        </div>

        {{-- Filter Bar --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 shadow-xs border border-slate-200 dark:border-slate-800">
            <form method="GET" action="{{ route('mileage-reconciliations.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3 items-end">
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
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">GPS Availability</label>
                    <select name="gps" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs font-medium text-slate-800 dark:text-slate-100 outline-none focus:border-blue-500">
                        <option value="all">All Vehicles</option>
                        <option value="gps" {{ $filters['gps'] === 'gps' ? 'selected' : '' }}>GPS Equipped Only</option>
                        <option value="non_gps" {{ $filters['gps'] === 'non_gps' ? 'selected' : '' }}>Non-GPS Only</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Status</label>
                    <select name="status" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs font-medium text-slate-800 dark:text-slate-100 outline-none focus:border-blue-500">
                        <option value="all">All Statuses</option>
                        <option value="DRAFT" {{ $filters['status'] === 'DRAFT' ? 'selected' : '' }}>Draft</option>
                        <option value="SUBMITTED" {{ $filters['status'] === 'SUBMITTED' ? 'selected' : '' }}>Submitted</option>
                        <option value="FOR_REVIEW" {{ $filters['status'] === 'FOR_REVIEW' ? 'selected' : '' }}>For Review</option>
                        <option value="APPROVED" {{ $filters['status'] === 'APPROVED' ? 'selected' : '' }}>Approved</option>
                        <option value="REJECTED" {{ $filters['status'] === 'REJECTED' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="flex-1 rounded-xl bg-slate-800 dark:bg-slate-700 hover:bg-slate-900 px-3 py-2 text-xs font-bold text-white transition-colors">
                        Filter
                    </button>
                    <a href="{{ route('mileage-reconciliations.index') }}" class="rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- Reconciliation Table --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Trip Mileage Reconciliations</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Planned route distance vs actual odometer difference vs TrackSolid GPS mileage.</p>
                </div>
                <span class="text-xs font-semibold text-slate-500">{{ $reconciliations->total() }} records</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Rec Reference</th>
                            <th class="px-4 py-3 font-semibold">Vehicle & Plate</th>
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 font-semibold text-center">Category</th>
                            <th class="px-4 py-3 font-semibold text-right">Planned KM</th>
                            <th class="px-4 py-3 font-semibold text-right">Start Odo</th>
                            <th class="px-4 py-3 font-semibold text-right">End Odo</th>
                            <th class="px-4 py-3 font-semibold text-right">Actual KM</th>
                            <th class="px-4 py-3 font-semibold text-right">GPS KM</th>
                            <th class="px-4 py-3 font-semibold text-right">Variance</th>
                            <th class="px-4 py-3 font-semibold text-center">Evidence</th>
                            <th class="px-4 py-3 font-semibold text-center">Status</th>
                            <th class="px-4 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse($reconciliations as $rec)
                            @php
                                $veh = $rec->vehicle;
                                $statusBadge = match($rec->status) {
                                    'APPROVED' => 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
                                    'SUBMITTED' => 'bg-blue-500/10 text-blue-600 border-blue-500/20',
                                    'FOR_REVIEW' => 'bg-amber-500/10 text-amber-600 border-amber-500/20 font-semibold',
                                    'REJECTED' => 'bg-red-500/10 text-red-600 border-red-500/20 font-bold',
                                    default => 'bg-slate-500/10 text-slate-500 border-slate-500/20',
                                };
                                $evidenceCount = $rec->evidences->count();
                            @endphp
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-4 py-3.5 font-mono font-bold text-slate-900 dark:text-white">
                                    {{ $rec->reconciliation_number }}
                                    <div class="text-[10px] font-sans text-slate-400">{{ $rec->dispatch?->trip_number ?? '—' }}</div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-slate-800 dark:text-slate-100">{{ $veh?->equipment_code ?? '—' }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono">{{ $veh?->plate_number ?? 'No Plate' }}</div>
                                </td>
                                <td class="px-4 py-3.5 text-slate-700 dark:text-slate-300">
                                    {{ $rec->trip_date->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold border {{ $rec->is_gps_vehicle ? 'bg-indigo-500/10 text-indigo-600 border-indigo-500/20' : 'bg-slate-500/10 text-slate-600 border-slate-500/20' }}">
                                        {{ $rec->is_gps_vehicle ? 'GPS' : 'Non-GPS' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-right font-medium text-slate-600 dark:text-slate-400">
                                    {{ number_format($rec->planned_distance_km, 1) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-slate-700 dark:text-slate-300">
                                    {{ $rec->starting_odometer !== null ? number_format($rec->starting_odometer, 1) : '—' }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-slate-700 dark:text-slate-300">
                                    {{ $rec->ending_odometer !== null ? number_format($rec->ending_odometer, 1) : '—' }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-bold text-slate-900 dark:text-white">
                                    {{ number_format($rec->actual_odometer_km, 1) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-slate-700 dark:text-slate-300">
                                    {{ $rec->gps_reported_km !== null ? number_format($rec->gps_reported_km, 1) : '—' }}
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <div class="{{ $rec->variance_km > 0 ? 'text-red-600 dark:text-red-400 font-bold' : 'text-emerald-600 dark:text-emerald-400 font-medium' }}">
                                        {{ ($rec->variance_km > 0 ? '+' : '') . number_format($rec->variance_km, 1) }} KM
                                    </div>
                                    @if($rec->variance_percentage !== null)
                                        <div class="text-[10px] text-slate-400 font-mono">
                                            {{ ($rec->variance_percentage > 0 ? '+' : '') . number_format($rec->variance_percentage, 1) }}%
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    @if($evidenceCount > 0)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 text-emerald-600 px-2 py-0.5 text-[10px] font-bold border border-emerald-500/20">
                                            {{ $evidenceCount }} Attached
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 text-slate-400 px-2 py-0.5 text-[10px] font-medium border border-slate-200">
                                            None
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold border {{ $statusBadge }}">
                                        {{ $rec->status }}
                                    </span>
                                    @if($rec->has_exceptions)
                                        <div class="mt-1">
                                            <span class="inline-flex items-center rounded-full bg-red-500/10 text-red-600 px-1.5 py-0.5 text-[9px] font-bold border border-red-500/20 animate-pulse">
                                                EXCEPTION
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    <a href="{{ route('mileage-reconciliations.show', $rec) }}"
                                       class="rounded-lg bg-blue-600 hover:bg-blue-700 px-3 py-1.5 text-xs font-bold text-white shadow-xs transition-colors inline-flex items-center gap-1">
                                        Review Detail &rarr;
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="px-6 py-12 text-center text-slate-400">
                                    No mileage reconciliation records found matching criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($reconciliations->hasPages())
                <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                    {{ $reconciliations->links() }}
                </div>
            @endif
        </div>

    </div>
</x-app-layout>
