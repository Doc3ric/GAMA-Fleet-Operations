<x-app-layout>
    @section('page-title', 'Preview TrackSolid Import')

    <div class="p-6 space-y-6 max-w-7xl mx-auto">

        {{-- Header & Breadcrumbs --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <a href="{{ route('tracksolid-reports.index') }}" class="text-xs text-slate-500 hover:text-blue-600 mb-1 inline-block">
                    &larr; Cancel and return to Uploads
                </a>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Import Preview: {{ $preview['filename'] }}</h1>
                    <span class="inline-flex items-center rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-semibold text-amber-600 border border-amber-500/20">
                        Dry Run / Pending Confirmation
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Please inspect matched vehicle identifiers and GPS distance readings before committing records into the database.
                </p>
            </div>

            <form action="{{ route('tracksolid-reports.confirm') }}" method="POST" class="flex items-center gap-3">
                @csrf
                <a href="{{ route('tracksolid-reports.index') }}"
                   class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 transition-colors">
                    Discard Preview
                </a>
                <button type="submit"
                        class="rounded-xl bg-blue-600 hover:bg-blue-700 px-5 py-2 text-xs font-bold text-white shadow-sm transition-colors">
                    Confirm & Commit {{ $preview['total_rows'] }} Records &rarr;
                </button>
            </form>
        </div>

        {{-- Statistics Badges --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Total Detected</span>
                <span class="text-2xl font-black text-slate-900 dark:text-white mt-1 block">{{ $preview['total_rows'] }}</span>
            </div>
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 block">Matched to Vehicle</span>
                <span class="text-2xl font-black text-emerald-600 mt-1 block">{{ $preview['matched_count'] }}</span>
            </div>
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600 block">Ambiguous Matches</span>
                <span class="text-2xl font-black text-amber-600 mt-1 block">{{ $preview['ambiguous_count'] }}</span>
            </div>
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Unmatched Units</span>
                <span class="text-2xl font-black text-slate-500 mt-1 block">{{ $preview['unmatched_count'] }}</span>
            </div>
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-red-500 block">Duplicates Filtered</span>
                <span class="text-2xl font-black text-red-500 mt-1 block">{{ $preview['duplicate_count'] }}</span>
            </div>
        </div>

        {{-- Options Banner --}}
        <div class="rounded-2xl bg-blue-50/70 dark:bg-blue-900/20 p-4 border border-blue-200 dark:border-blue-800/40 text-xs text-blue-900 dark:text-blue-300 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                </svg>
                <span>
                    <strong>Automatic Reconciliation Link:</strong> Matched GPS distances will be linked to active reconciliations for the same vehicle and trip date. Approved reconciliations are strictly protected from modification.
                </span>
            </div>
        </div>

        {{-- Preview Table --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Detailed Row Preview</h2>
                <span class="text-xs text-slate-500">Showing all {{ count($preview['rows']) }} parsed row(s)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3 font-semibold text-center">Row #</th>
                            <th class="px-4 py-3 font-semibold">TrackSolid Device / IMEI</th>
                            <th class="px-4 py-3 font-semibold">Matched System Vehicle</th>
                            <th class="px-4 py-3 font-semibold">Recorded Time Window</th>
                            <th class="px-4 py-3 font-semibold text-right">GPS Distance</th>
                            <th class="px-4 py-3 font-semibold text-right">Start Odo</th>
                            <th class="px-4 py-3 font-semibold text-right">End Odo</th>
                            <th class="px-4 py-3 font-semibold text-center">Match Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach($preview['rows'] as $r)
                            @php
                                $badgeClass = match($r['match_status']) {
                                    'MATCHED' => 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20 font-bold',
                                    'AMBIGUOUS' => 'bg-amber-500/10 text-amber-600 border-amber-500/20 font-semibold',
                                    'DUPLICATE' => 'bg-red-500/10 text-red-600 border-red-500/20',
                                    default => 'bg-slate-500/10 text-slate-500 border-slate-500/20',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 text-center text-slate-400 font-mono">
                                    {{ $r['row_index'] }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-800 dark:text-slate-100">{{ $r['device_name'] ?: 'Unnamed Device' }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">{{ $r['imei'] ?: 'No IMEI' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($r['vehicle_code'])
                                        <div class="font-bold text-emerald-600 dark:text-emerald-400">{{ $r['vehicle_code'] }}</div>
                                        <div class="text-[10px] text-slate-500 font-mono">{{ $r['vehicle_plate'] ?: 'No Plate' }}</div>
                                    @else
                                        <span class="text-slate-400 italic">No vehicle match</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                    <div>{{ $r['start_time'] ?: '—' }}</div>
                                    <div class="text-[10px] text-slate-400">To: {{ $r['end_time'] ?: '—' }}</div>
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-slate-900 dark:text-white">
                                    {{ $r['distance_km'] !== null ? number_format($r['distance_km'], 2) . ' KM' : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-slate-600 dark:text-slate-400">
                                    {{ $r['start_odometer'] !== null ? number_format($r['start_odometer'], 1) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-slate-600 dark:text-slate-400">
                                    {{ $r['end_odometer'] !== null ? number_format($r['end_odometer'], 1) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] border {{ $badgeClass }}">
                                        {{ $r['match_status'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-3 bg-slate-50/50 dark:bg-slate-800/30">
                <a href="{{ route('tracksolid-reports.index') }}"
                   class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50">
                    Discard
                </a>
                <form action="{{ route('tracksolid-reports.confirm') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="rounded-xl bg-blue-600 hover:bg-blue-700 px-5 py-2 text-xs font-bold text-white shadow-sm">
                        Confirm & Commit {{ $preview['total_rows'] }} Records
                    </button>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
