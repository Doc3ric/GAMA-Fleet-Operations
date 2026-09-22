<x-app-layout>
    @section('page-title', 'Dashboard')
    @section('page-subtitle', 'Operational Overview')

    <div class="space-y-5">

        {{-- KPI Cards Grid --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

            {{-- Today Report --}}
            <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Today's Report</p>
                    <div class="rounded-xl bg-red-50 p-2">
                        <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>
                </div>
                @if($todayReport)
                    <p class="text-2xl font-bold text-slate-900">{{ $todayReport->long_idling_records_count ?? $todayReport->longIdlingRecords->count() }} Records</p>
                    <div>
                        @if($todayReport->isCompleted())
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Completed
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>Draft
                            </span>
                        @endif
                    </div>
                @else
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-500 font-bold text-lg border border-amber-100 select-none">?</div>
                        <div>
                            <p class="text-sm font-semibold text-slate-700">No report yet</p>
                            <p class="text-xs text-slate-400">Pending daily generation</p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Total Reports --}}
            <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Total Reports</p>
                    <div class="rounded-xl bg-blue-50 p-2">
                        <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-slate-900">{{ number_format($totalReports) }}</p>
                <p class="text-xs text-slate-400 font-medium">Long Idling Reports</p>
            </div>

            {{-- Total Records --}}
            <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Total Records</p>
                    <div class="rounded-xl bg-emerald-50 p-2">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-slate-900">{{ number_format($totalRecords) }}</p>
                <p class="text-xs text-slate-400 font-medium">Idling Events Logged</p>
            </div>

            {{-- Quick Action --}}
            <div class="rounded-2xl bg-[#1e3a5f] shadow-sm p-5 flex flex-col justify-between relative overflow-hidden">
                <div class="absolute -top-4 -right-4 h-24 w-24 rounded-full bg-blue-500/10 pointer-events-none"></div>
                <div class="absolute -bottom-6 -left-2 h-20 w-20 rounded-full bg-blue-600/10 pointer-events-none"></div>
                <div class="relative flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-widest text-blue-300">Quick Action</p>
                    @if(!$todayReport)
                        <span class="rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-bold text-white tracking-wide">REQUIRED</span>
                    @endif
                </div>
                @if($todayReport)
                    <div class="relative mt-2">
                        <p class="text-sm font-semibold text-white">Today's report is ready</p>
                        <a href="{{ route('reports.show', $todayReport) }}"
                           class="mt-3 inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#1e3a5f] hover:bg-blue-50 transition-colors shadow-sm">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                            Open Report
                        </a>
                    </div>
                @else
                    <div class="relative mt-2">
                        <p class="text-sm font-semibold text-slate-200">No report for today yet</p>
                        <a href="{{ route('reports.create') }}"
                           class="mt-3 inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#1e3a5f] hover:bg-blue-50 transition-colors shadow-sm w-full justify-center">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            + Create Today's Report
                        </a>
                    </div>
                @endif
            </div>

        </div>

        {{-- Fuel Consumption Benchmark Banner --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="h-11 w-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-slate-800">Average Fuel Consumption</h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            Full-Tank Benchmark
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        @if($fuelTestCount > 0)
                            Fleet Average: <strong class="text-emerald-700 font-mono">{{ number_format($fleetAvgKmL, 2) }} km/L</strong> across {{ $fuelTestCount }} test(s).
                            @if($latestFuelTest)
                                Latest: {{ $latestFuelTest->vehicle?->equipment_code ?? 'Vehicle' }} at <strong class="text-slate-800 font-mono">{{ number_format($latestFuelTest->average_fuel_consumption, 2) }} km/L</strong>.
                            @endif
                        @else
                            No fuel consumption tests recorded yet. Run a test to establish baseline vehicle fuel economy.
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('fuel-consumption.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors shadow-2xs">
                    View Fuel Tests &rarr;
                </a>
                <a href="{{ route('fuel-consumption.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 text-xs font-bold transition-colors shadow-2xs">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    + Record Fuel Test
                </a>
            </div>
        </div>

        {{-- Recent Reports Table --}}
        <div class="rounded-2xl bg-white shadow-sm border border-slate-200 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <h2 class="text-sm font-bold text-slate-800">Recent Reports</h2>
                    <span class="inline-flex items-center rounded-full bg-slate-100 border border-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-500">
                        {{ $recentReports->count() }} items logged
                    </span>
                </div>
                <a href="{{ route('reports.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700 transition-colors">View all &rarr;</a>
            </div>

            @if($recentReports->count() > 0)
            <div class="grid grid-cols-4 gap-4 px-6 py-2.5 bg-slate-50 border-b border-slate-100">
                <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Report Date</div>
                <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Total Records</div>
                <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Status</div>
                <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 text-right">Action</div>
            </div>
            @endif

            <div class="divide-y divide-slate-100">
                @forelse($recentReports as $report)
                    <div class="grid grid-cols-4 gap-4 items-center px-6 py-4 hover:bg-slate-50/70 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-500">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-slate-800">{{ $report->report_date->format('M j, Y') }}</div>
                                <div class="text-xs text-slate-400">Processed at 18:30 PM</div>
                            </div>
                        </div>
                        <div>
                            <span class="inline-flex items-center rounded-lg bg-slate-100 border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600">
                                {{ number_format($report->long_idling_records_count) }} records
                            </span>
                        </div>
                        <div>
                            @if($report->isCompleted())
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Completed
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>Draft
                                </span>
                            @endif
                        </div>
                        <div class="flex justify-end">
                            <a href="{{ route('reports.show', $report) }}"
                               class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors">
                                Open
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-12 text-center">
                        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                            <svg class="h-6 w-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-slate-500">No reports yet</p>
                        <p class="text-xs text-slate-400 mt-1">Create your first Long Idling Report to get started.</p>
                    </div>
                @endforelse
            </div>

            @if($recentReports->count() > 0)
            <div class="flex items-center justify-between px-6 py-3 border-t border-slate-100 bg-slate-50/50">
                <p class="text-xs text-slate-400">Showing latest {{ $recentReports->count() }} report cycles</p>
                <div class="flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    Sync status: Synchronized
                </div>
            </div>
            @endif
        </div>

    </div>

</x-app-layout>