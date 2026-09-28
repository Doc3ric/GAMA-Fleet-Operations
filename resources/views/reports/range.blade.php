<x-app-layout>
    @section('page-title', 'Long Idling Multi-Date Explorer')
    @section('breadcrumb', 'Fleet Reports / Long Idling / Multi-Date Range')

    <div class="space-y-4">
        {{-- Header Card --}}
        <div class="rounded-2xl bg-white shadow-xs border border-slate-200/90 p-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3.5">
                    <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-indigo-600 to-blue-700 text-white flex items-center justify-center font-black shadow-xs shrink-0">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-black text-slate-900 tracking-tight">Long Idling Multi-Date Explorer</h2>
                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800 border border-blue-200">
                                Date Range Mode
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Cross-date idling monitoring & checklist PDF export across multiple report dates
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('reports.index') }}"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors shadow-2xs">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                        Daily Reports List
                    </a>
                </div>
            </div>
        </div>

        {{-- Livewire Spreadsheet Table in Range Mode --}}
        @livewire('long-idling-table', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dateMode' => 'range',
        ])
    </div>
</x-app-layout>
