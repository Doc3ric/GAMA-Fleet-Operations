<x-app-layout>
    @section('page-title', 'New Long Idling Report')
    @section('breadcrumb', 'Fleet Reports / Long Idling / New')

    <div class="max-w-2xl">
        <div class="rounded-xl bg-white shadow-sm border border-slate-200 p-6">
            <h2 class="text-base font-semibold text-slate-800 mb-5">Create Daily Report</h2>

            <form method="POST" action="{{ route('reports.store') }}" x-data="{ duplicate: false }">
                @csrf

                {{-- Date --}}
                <div class="mb-5">
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="report_date">
                        Report Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="report_date" name="report_date"
                           value="{{ old('report_date', now()->format('Y-m-d')) }}"
                           class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none @error('report_date') border-red-400 @enderror">
                    @error('report_date')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Remarks --}}
                <div class="mb-5">
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="remarks">
                        Remarks <span class="text-slate-400 font-normal">(optional)</span>
                    </label>
                    <textarea id="remarks" name="remarks" rows="3"
                        class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none resize-none"
                        placeholder="e.g. Heavy traffic day, Metro Manila routes">{{ old('remarks') }}</textarea>
                </div>

                {{-- Duplicate --}}
                @if($previousReport)
                <div class="mb-5 rounded-lg border border-blue-100 bg-blue-50 p-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" x-model="duplicate" name="duplicate_toggle"
                               class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <div>
                            <span class="text-sm font-medium text-slate-800">
                                Duplicate from previous report
                            </span>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Pre-fill device list from
                                <strong>{{ $previousReport->report_date->format('F j, Y') }}</strong>
                                ({{ $previousReport->longIdlingRecords->count() }} vehicles).
                                Times, addresses, and screenshots will be cleared.
                            </p>
                        </div>
                    </label>
                    <input type="hidden" name="duplicate_from"
                           :value="duplicate ? {{ $previousReport->id }} : '"
                           x-bind:disabled="!duplicate">
                </div>
                @endif

                {{-- Actions --}}
                <div class="flex items-center gap-3 pt-2">
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Create Report
                    </button>
                    <a href="{{ route('reports.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
