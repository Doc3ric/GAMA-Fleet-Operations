<x-app-layout>
    @section('page-title', 'TrackSolid Pro Report Uploads')

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

        @if ($errors->any())
            <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-800 space-y-1">
                <div class="font-bold flex items-center gap-2">
                    <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    Upload Validation Failed
                </div>
                <ul class="list-disc list-inside text-xs">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Header & Live API Sync --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">TrackSolid Pro GPS Integration</h1>
                    <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                        Live API Connected
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Upload TrackSolid Excel/CSV files, or synchronize live devices directly from the TrackSolid Pro Open API.
                </p>
            </div>
            <div>
                <form action="{{ route('tracksolid-reports.sync-devices-api') }}" method="POST">
                    @csrf
                    <button type="submit" 
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span>⚡ Sync 124 Fleet Devices Live</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Upload Card & Column Format Guide --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            {{-- Upload Box (7 cols) --}}
            <div class="lg:col-span-7 rounded-2xl bg-white dark:bg-slate-900 p-6 shadow-xs border border-slate-200 dark:border-slate-800 space-y-4">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Upload GPS Report for Preview</h2>

                <form action="{{ route('tracksolid-reports.preview') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div class="border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-2xl p-6 text-center hover:border-blue-500 transition-colors">
                        <svg class="h-10 w-10 text-slate-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>
                        <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Choose a TrackSolid Pro Excel or CSV file</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Supports .xlsx, .csv, and .xls (Max 15MB)</p>
                        <input type="file" name="report_file" required accept=".xlsx,.csv,.xls"
                               class="mt-4 text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer">
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <span class="text-xs text-slate-400">Records will be validated & matched before saving.</span>
                        <button type="submit" class="rounded-xl bg-blue-600 hover:bg-blue-700 px-5 py-2 text-xs font-bold text-white shadow-sm transition-colors">
                            Upload & Preview Records &rarr;
                        </button>
                    </div>
                </form>
            </div>

            {{-- Format Guide (5 cols) --}}
            <div class="lg:col-span-5 rounded-2xl bg-slate-50 dark:bg-slate-900/60 p-6 border border-slate-200 dark:border-slate-800 space-y-3 text-xs">
                <h3 class="font-bold text-slate-900 dark:text-white text-sm">Supported Columns in TrackSolid Reports</h3>
                <p class="text-slate-500 leading-relaxed">
                    The import service automatically recognizes diverse TrackSolid header variations across all worksheets.
                </p>

                <div class="space-y-2 pt-2">
                    <div class="rounded-xl bg-white dark:bg-slate-800 p-2.5 border border-slate-200/80 dark:border-slate-700">
                        <span class="font-bold text-slate-800 dark:text-slate-100 block">Device Name / Vehicle</span>
                        <span class="text-[11px] text-slate-500">e.g. "BT-01", "BT01 - ABC-1234", "SV 18"</span>
                    </div>
                    <div class="rounded-xl bg-white dark:bg-slate-800 p-2.5 border border-slate-200/80 dark:border-slate-700">
                        <span class="font-bold text-slate-800 dark:text-slate-100 block">IMEI Number (Recommended)</span>
                        <span class="text-[11px] text-slate-500">15-digit unique hardware device identifier</span>
                    </div>
                    <div class="rounded-xl bg-white dark:bg-slate-800 p-2.5 border border-slate-200/80 dark:border-slate-700">
                        <span class="font-bold text-slate-800 dark:text-slate-100 block">Start Time & End Time</span>
                        <span class="text-[11px] text-slate-500">Timestamp of trip start and arrival</span>
                    </div>
                    <div class="rounded-xl bg-white dark:bg-slate-800 p-2.5 border border-slate-200/80 dark:border-slate-700">
                        <span class="font-bold text-slate-800 dark:text-slate-100 block">Run Distance / Mileage</span>
                        <span class="text-[11px] text-slate-500">Recorded GPS distance in kilometers</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Past Imports History --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-xs border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Recent TrackSolid Imports</h2>
                <span class="text-xs text-slate-500">{{ $recentImports->total() }} import batch(es)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Report File</th>
                            <th class="px-4 py-3 font-semibold">Total Rows</th>
                            <th class="px-4 py-3 font-semibold text-center">Matched</th>
                            <th class="px-4 py-3 font-semibold text-center">Unmatched</th>
                            <th class="px-4 py-3 font-semibold text-center">Duplicates</th>
                            <th class="px-4 py-3 font-semibold">Uploaded By</th>
                            <th class="px-4 py-3 font-semibold">Date & Time</th>
                            <th class="px-4 py-3 font-semibold text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse($recentImports as $batch)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3.5 font-bold text-slate-800 dark:text-white">
                                    {{ $batch->filename }}
                                </td>
                                <td class="px-4 py-3.5 font-semibold text-slate-700 dark:text-slate-300">
                                    {{ $batch->total_rows }}
                                </td>
                                <td class="px-4 py-3.5 text-center font-bold text-emerald-600">
                                    {{ $batch->matched_rows }}
                                </td>
                                <td class="px-4 py-3.5 text-center font-bold text-slate-500">
                                    {{ $batch->unmatched_rows }}
                                </td>
                                <td class="px-4 py-3.5 text-center font-bold text-amber-600">
                                    {{ $batch->duplicate_rows }}
                                </td>
                                <td class="px-4 py-3.5 text-slate-700 dark:text-slate-300">
                                    {{ $batch->creator?->name ?? 'System' }}
                                </td>
                                <td class="px-4 py-3.5 text-slate-500 font-mono text-[11px]">
                                    {{ $batch->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <span class="inline-flex items-center rounded-full bg-emerald-500/10 text-emerald-600 px-2 py-0.5 text-[10px] font-bold border border-emerald-500/20">
                                        {{ $batch->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-10 text-center text-slate-400">
                                    No GPS report imports found. Upload your first report using the box above.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($recentImports->hasPages())
                <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                    {{ $recentImports->links() }}
                </div>
            @endif
        </div>

    </div>
</x-app-layout>
