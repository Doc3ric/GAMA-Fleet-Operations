<x-app-layout>
    @section('page-title', 'Long Idling Report - ' . $report->report_date->format('F j, Y'))
    @section('breadcrumb', 'Fleet Reports / Long Idling / ' . $report->report_date->format('M j, Y'))

    <div class="space-y-4" x-data="{ showImportModal: false }" @open-import-modal.window="showImportModal = true">

        {{-- Flash notifications --}}
        @if(session('success'))
            <div x-data="{ show: true }" x-show="show" class="flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 shadow-sm">
                <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span class="font-semibold">{{ session('success') }}</span>
                <button type="button" @click="show = false" class="ml-auto text-emerald-600 hover:text-emerald-800 font-bold">&times;</button>
            </div>
        @endif

        @if(session('error'))
            <div x-data="{ show: true }" x-show="show" class="flex items-center gap-2 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800 shadow-sm">
                <svg class="h-5 w-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
                <span class="font-semibold">{{ session('error') }}</span>
                <button type="button" @click="show = false" class="ml-auto text-rose-600 hover:text-rose-800 font-bold">&times;</button>
            </div>
        @endif

        {{-- Report Header Card --}}
        <div class="rounded-2xl bg-white shadow-xs border border-slate-200/90 p-5">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white flex items-center justify-center font-black shadow-xs shrink-0">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-xl font-black text-slate-900 tracking-tight">{{ $report->report_date->format('F j, Y') }}</h2>
                            @if($report->isCompleted())
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-xs font-bold text-emerald-700">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                    Completed
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-2.5 py-0.5 text-xs font-bold text-amber-700">
                                    Draft
                                </span>
                            @endif
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 border border-slate-200">
                                {{ $report->longIdlingRecords->count() }} records
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Daily Long Idling Monitoring Report
                            @if($report->remarks)
                                &bull; <span class="italic text-slate-600">{{ $report->remarks }}</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    {{-- Edit --}}
                    <a href="{{ route('reports.edit', $report) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors shadow-2xs">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" /></svg>
                        Edit
                    </a>

                    {{-- Mark Complete / Draft --}}
                    @if($report->isDraft())
                        <form method="POST" action="{{ route('reports.markComplete', $report) }}">
                            @csrf @method('PATCH')
                            <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition-colors shadow-2xs cursor-pointer">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                Mark Complete
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('reports.markDraft', $report) }}">
                            @csrf @method('PATCH')
                            <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-amber-300 bg-amber-50 px-3.5 py-2 text-xs font-bold text-amber-800 hover:bg-amber-100 transition-colors cursor-pointer">
                                Revert to Draft
                            </button>
                        </form>
                    @endif

                    {{-- PDF --}}
                    <div x-data="{ generating: false }">
                        <a href="{{ route('reports.generatePdf', $report) }}" target="_blank"
                           @click="generating = true; setTimeout(() => generating = false, 4000)"
                           class="inline-flex items-center gap-1.5 rounded-xl bg-red-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-red-700 transition-colors shadow-2xs">
                            <svg x-show="!generating" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                            <svg x-show="generating" style="display: none;" class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span x-text="generating ? 'Generating...' : 'PDF'">PDF</span>
                        </a>
                    </div>

                    {{-- Excel --}}
                    <a href="{{ route('reports.exportExcel', $report) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition-colors shadow-2xs">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                        Excel
                    </a>

                    {{-- Import Data --}}
                    <button type="button" @click="showImportModal = true"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer shadow-2xs">
                        <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                        Import Data
                    </button>
                </div>
            </div>
        </div>

        {{-- Livewire Spreadsheet Table --}}
        @livewire('long-idling-table', ['reportId' => $report->id])

        {{-- Import Modal --}}
        <div x-show="showImportModal"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
             style="display: none;"
             @keydown.escape.window="showImportModal = false"
             @click.self="showImportModal = false">

            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl transition-all" @click.stop>
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-800">Import Long Idling Data</h3>
                            <p class="text-xs text-slate-500">Upload an Excel or CSV file from your tracking software</p>
                        </div>
                    </div>
                    <button type="button" @click="showImportModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form action="{{ route('reports.importData', $report) }}" method="POST" enctype="multipart/form-data" class="mt-5 space-y-4" x-data="{ isSubmitting: false }" @submit="isSubmitting = true">
                    @csrf

                    {{-- Template column info --}}
                    <div class="rounded-xl bg-slate-50 border border-slate-200/80 p-3 text-xs text-slate-600">
                        <div class="font-semibold text-slate-700 mb-1 flex items-center justify-between">
                            <span>Supported Template Headers:</span>
                            <a href="{{ route('reports.downloadTemplate') }}" class="text-blue-600 hover:text-blue-700 font-semibold inline-flex items-center gap-1 hover:underline">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                Download Template (.xlsx)
                            </a>
                        </div>
                        <div class="flex flex-wrap gap-1 mt-1 text-[11px] text-slate-500">
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5 font-mono">Device Name</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5 font-mono">IMEI</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5 font-mono">Model</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5 font-mono">State</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5 font-mono">Start time</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5 font-mono">End Time</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5 font-mono">Coordinates</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5 font-mono">Address</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5 font-mono">Stay time</span>
                        </div>
                    </div>

                    {{-- File Input --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Select Spreadsheet File (.xlsx, .xls, .csv)</label>
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                               class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-slate-300 rounded-xl cursor-pointer">
                    </div>

                    {{-- Mode Radio --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Import Mode</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                            <label class="flex items-start gap-2.5 rounded-xl border border-slate-200 p-3 cursor-pointer hover:bg-slate-50 has-checked:border-blue-600 has-checked:bg-blue-50/40">
                                <input type="radio" name="mode" value="append" checked class="mt-0.5 text-blue-600 focus:ring-blue-500">
                                <div>
                                    <span class="font-semibold text-slate-800">Append Records</span>
                                    <p class="text-[11px] text-slate-500 mt-0.5">Add imported rows to existing data</p>
                                </div>
                            </label>
                            <label class="flex items-start gap-2.5 rounded-xl border border-slate-200 p-3 cursor-pointer hover:bg-slate-50 has-checked:border-red-600 has-checked:bg-red-50/40">
                                <input type="radio" name="mode" value="replace" class="mt-0.5 text-red-600 focus:ring-red-500">
                                <div>
                                    <span class="font-semibold text-slate-800">Replace Records</span>
                                    <p class="text-[11px] text-slate-500 mt-0.5">Clear current rows and start fresh</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="showImportModal = false"
                                class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" :disabled="isSubmitting"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50 cursor-pointer shadow-sm">
                            <span x-show="!isSubmitting" class="inline-flex items-center gap-1.5">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                                Import Records
                            </span>
                            <span x-show="isSubmitting" style="display: none;" class="inline-flex items-center gap-1.5">
                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Importing...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>