<x-app-layout>
    @section('page-title', 'Edit Report')
    @section('breadcrumb', 'Fleet Reports / Long Idling / Edit')

    <div class="max-w-2xl">
        <div class="rounded-xl bg-white shadow-sm border border-slate-200 p-6">
            <h2 class="text-base font-semibold text-slate-800 mb-5">Edit Report</h2>

            <form method="POST" action="{{ route('reports.update', $report) }}">
                @csrf @method('PUT')

                <div class="mb-5">
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="report_date">
                        Report Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="report_date" name="report_date"
                           value="{{ old('report_date', $report->report_date->format('Y-m-d')) }}"
                           class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none @error('report_date') border-red-400 @enderror">
                    @error('report_date')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-5">
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="3"
                        class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none resize-none">{{ old('remarks', $report->remarks) }}</textarea>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition-colors">
                        Save Changes
                    </button>
                    <a href="{{ route('reports.show', $report) }}" class="text-sm text-slate-500 hover:text-slate-700">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
