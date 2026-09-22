<div class="space-y-4">

    {{-- Error Notifications --}}
    @if($errorMessage)
        <div class="flex items-center gap-3 rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800 shadow-xs">
            <svg class="h-5 w-5 text-rose-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            <div class="flex-1 font-medium">{{ $errorMessage }}</div>
            <button type="button" wire:click="$set('errorMessage', null)" class="text-rose-400 hover:text-rose-600 font-bold">&times;</button>
        </div>
    @endif

    @error('file')
        <div class="flex items-center gap-3 rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800 shadow-xs">
            <svg class="h-5 w-5 text-rose-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            <div class="flex-1 font-medium">{{ $message }}</div>
        </div>
    @enderror

    @if(!$fileId)
        {{-- ===================== UPLOAD CARD ===================== --}}
        <div class="rounded-2xl bg-white border border-slate-200/90 shadow-xs p-8 text-center">
            <div class="max-w-xl mx-auto">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 text-white shadow-md mb-4">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 13.5h6m-6 3h6" />
                    </svg>
                </div>

                <h3 class="text-xl font-black text-slate-900 tracking-tight">Excel Workbook Viewer</h3>
                <p class="text-xs text-slate-500 mt-1 mb-6">
                    Upload an Excel Binary Workbook (<span class="font-bold text-slate-700">.xlsb</span>) or OpenXML Workbook (<span class="font-bold text-slate-700">.xlsx</span>) to inspect its worksheets, browse records, and filter tabular data directly in your browser.
                </p>

                {{-- Upload Dropzone --}}
                <div class="relative border-2 border-dashed border-slate-300 hover:border-blue-500 rounded-2xl p-8 transition-colors bg-slate-50/60 hover:bg-blue-50/30 group">
                    <input type="file"
                           wire:model="file"
                           id="excel-file-input"
                           accept=".xlsb,.xlsx"
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">

                    <div class="flex flex-col items-center pointer-events-none">
                        <div class="p-3 bg-white rounded-full shadow-xs border border-slate-200 group-hover:border-blue-300 group-hover:scale-105 transition-all text-blue-600 mb-3">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-slate-800">
                            <span class="text-blue-600 underline">Click to choose</span> or drag and drop workbook here
                        </p>
                        <p class="text-xs text-slate-400 mt-1">
                            Supports <span class="font-semibold text-slate-600">.XLSB</span> and <span class="font-semibold text-slate-600">.XLSX</span> (up to 20 MB)
                        </p>
                    </div>

                    {{-- Upload Loading State --}}
                    <div wire:loading wire:target="file" class="absolute inset-0 bg-white/90 backdrop-blur-xs flex flex-col items-center justify-center rounded-2xl z-20">
                        <svg class="animate-spin h-8 w-8 text-blue-600 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-sm font-semibold text-slate-700">Uploading & parsing spreadsheet...</span>
                    </div>
                </div>

                {{-- Security & Isolation Info --}}
                <div class="flex items-center justify-center gap-6 mt-6 text-xs text-slate-400">
                    <span class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Read-only viewer
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        Private secure storage
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                        Zero database modifications
                    </span>
                </div>
            </div>
        </div>

    @else
        {{-- ===================== WORKBOOK LOADED ===================== --}}

        {{-- Workbook Header Card --}}
        <div class="rounded-2xl bg-white border border-slate-200/90 shadow-xs p-5">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex items-center gap-3.5 min-w-0">
                    <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-700 text-white flex items-center justify-center font-black shadow-xs shrink-0">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-lg font-bold text-slate-900 tracking-tight truncate max-w-md" title="{{ $originalName }}">
                                {{ $originalName }}
                            </h2>
                            <span class="inline-flex items-center rounded-md bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-xs font-bold text-emerald-700 uppercase">
                                {{ $extension }}
                            </span>
                            <span class="inline-flex items-center rounded-md bg-slate-100 border border-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-600">
                                {{ number_format(($fileSize ?? 0) / 1024, 1) }} KB
                            </span>
                            <span class="inline-flex items-center rounded-md bg-blue-50 border border-blue-200 px-2 py-0.5 text-xs font-semibold text-blue-700">
                                {{ count($sheets) }} {{ count($sheets) === 1 ? 'Sheet' : 'Sheets' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Spreadsheet loaded in memory &bull; Read-only mode
                        </p>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center gap-2.5 shrink-0">
                    <a href="{{ route('excel-viewer.download', $fileId) }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-slate-100 hover:bg-slate-200 px-3.5 py-2 text-xs font-bold text-slate-700 transition-colors border border-slate-200">
                        <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Download Original
                    </a>

                    <button type="button"
                            wire:click="clearFile"
                            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-700 px-3.5 py-2 text-xs font-bold text-white transition-colors shadow-xs">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Upload Another
                    </button>
                </div>
            </div>

            {{-- Worksheet Navigation Tabs --}}
            <div class="mt-5 border-t border-slate-100 pt-3">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Available Worksheets:</div>
                <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-thin">
                    @foreach($sheets as $sheet)
                        <button type="button"
                                wire:click="selectSheet('{{ $sheet['id'] }}')"
                                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all shrink-0
                                       {{ $activeSheetId === $sheet['id']
                                          ? 'bg-blue-600 text-white shadow-sm ring-2 ring-blue-600/30'
                                          : 'bg-slate-100 text-slate-700 hover:bg-slate-200 hover:text-slate-900 border border-slate-200' }}">
                            <svg class="h-3.5 w-3.5 {{ $activeSheetId === $sheet['id'] ? 'text-white' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                            </svg>
                            {{ $sheet['name'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Toolbar: Search, Header Toggle, Per Page, Stats --}}
        <div class="rounded-2xl bg-white border border-slate-200/90 shadow-xs p-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                {{-- Search Bar --}}
                <div class="relative flex-1 max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Search all cells in this sheet..."
                           class="w-full rounded-xl border border-slate-200 pl-9 pr-8 py-2 text-xs font-medium text-slate-800 placeholder-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    @if($search)
                        <button type="button"
                                wire:click="$set('search', '')"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 text-sm font-bold">
                            &times;
                        </button>
                    @endif
                </div>

                <div class="flex items-center gap-3 flex-wrap">
                    {{-- Toggle First Row as Header --}}
                    <button type="button"
                            wire:click="toggleFirstRowAsHeader"
                            class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold transition-colors border
                                   {{ $useFirstRowAsHeader
                                      ? 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100'
                                      : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' }}"
                            title="Toggle whether Row 1 is treated as the column header">
                        <span class="h-2 w-2 rounded-full {{ $useFirstRowAsHeader ? 'bg-blue-600' : 'bg-slate-400' }}"></span>
                        <span>Row 1 as Header: <span class="font-bold">{{ $useFirstRowAsHeader ? 'ON' : 'OFF' }}</span></span>
                    </button>

                    {{-- Rows Per Page --}}
                    <div class="flex items-center gap-2 text-xs text-slate-600">
                        <span class="font-medium text-slate-500">Show:</span>
                        <select wire:model.live="perPage"
                                class="rounded-xl border border-slate-200 bg-white py-1.5 pl-2.5 pr-8 text-xs font-semibold text-slate-700 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                            <option value="10">10 rows</option>
                            <option value="25">25 rows</option>
                            <option value="50">50 rows</option>
                            <option value="100">100 rows</option>
                        </select>
                    </div>

                    {{-- Row Count Badge --}}
                    <div class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-2 rounded-xl border border-slate-200">
                        Showing {{ $startRow }}&ndash;{{ $endRow }} of {{ $totalRows }} rows
                    </div>
                </div>
            </div>
        </div>

        {{-- Table Container with Horizontal Scrolling --}}
        <div class="rounded-2xl bg-white border border-slate-200/90 shadow-xs overflow-hidden">
            <div class="overflow-x-auto max-h-[600px] overflow-y-auto scrollbar-thin">
                <table class="w-full text-left border-collapse">
                    <thead class="sticky top-0 z-10 bg-[#0f172a] text-white">
                        <tr>
                            {{-- Row Index Header --}}
                            <th class="w-12 px-3 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-slate-400 bg-[#0b1120] border-r border-slate-700/60 select-none">
                                #
                            </th>
                            {{-- Column Headers --}}
                            @foreach($headers as $colIdx => $header)
                                <th class="px-4 py-3 text-xs font-bold uppercase tracking-wide text-slate-200 border-r border-slate-700/40 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span>{{ $header }}</span>
                                        @if($useFirstRowAsHeader)
                                            <span class="text-[9px] font-mono text-slate-400 font-normal">[{{ $this->getColumnLetter($colIdx) }}]</span>
                                        @endif
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 bg-white text-xs">
                        @forelse($pagedRows as $rowOffset => $row)
                            @php
                                $displayRowIndex = $startRow + $rowOffset;
                            @endphp
                            <tr class="hover:bg-blue-50/40 transition-colors {{ $loop->even ? 'bg-slate-50/40' : '' }}">
                                {{-- Row Index Cell --}}
                                <td class="px-3 py-2.5 text-center text-[11px] font-mono font-semibold text-slate-400 bg-slate-100/70 border-r border-slate-200 select-none">
                                    {{ $displayRowIndex }}
                                </td>

                                {{-- Column Data Cells --}}
                                @foreach($row as $colIdx => $cell)
                                    <td class="px-4 py-2.5 text-slate-700 border-r border-slate-100 whitespace-nowrap">
                                        @if($cell === '' || $cell === null)
                                            <span class="text-slate-300 font-light select-none">&mdash;</span>
                                        @else
                                            <span class="font-normal">{{ (string)$cell }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($headers) + 1 }}" class="py-12 text-center text-slate-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="h-8 w-8 text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                        </svg>
                                        <p class="text-sm font-semibold text-slate-600">No rows match your search</p>
                                        <p class="text-xs text-slate-400 mt-0.5">Try adjusting or clearing your search filter</p>
                                        @if($search)
                                            <button type="button" wire:click="$set('search', '')" class="mt-3 text-xs font-bold text-blue-600 hover:text-blue-800 underline">
                                                Clear Search Query
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Table Footer & Pagination Controls --}}
            @if($totalPages > 1)
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-5 py-3.5 border-t border-slate-200 bg-slate-50/70 gap-3">
                    <div class="text-xs text-slate-500 font-medium">
                        Page <span class="font-bold text-slate-800">{{ $page }}</span> of <span class="font-bold text-slate-800">{{ $totalPages }}</span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        {{-- Previous Button --}}
                        <button type="button"
                                wire:click="previousPage"
                                @if($page <= 1) disabled @endif
                                class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-bold border transition-colors
                                       {{ $page <= 1 ? 'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100 shadow-xs' }}">
                            &larr; Previous
                        </button>

                        {{-- Quick Page Buttons --}}
                        @php
                            $startP = max(1, $page - 2);
                            $endP = min($totalPages, $page + 2);
                        @endphp

                        @if($startP > 1)
                            <button type="button" wire:click="gotoPage(1)" class="h-7 w-7 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-200">1</button>
                            @if($startP > 2)
                                <span class="text-xs text-slate-400 px-1">&hellip;</span>
                            @endif
                        @endif

                        @for($p = $startP; $p <= $endP; $p++)
                            <button type="button"
                                    wire:click="gotoPage({{ $p }})"
                                    class="h-7 min-w-[28px] px-1.5 rounded-lg text-xs font-bold transition-all
                                           {{ $p === $page ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-700 hover:bg-slate-200' }}">
                                {{ $p }}
                            </button>
                        @endfor

                        @if($endP < $totalPages)
                            @if($endP < $totalPages - 1)
                                <span class="text-xs text-slate-400 px-1">&hellip;</span>
                            @endif
                            <button type="button" wire:click="gotoPage({{ $totalPages }})" class="h-7 w-7 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-200">{{ $totalPages }}</button>
                        @endif

                        {{-- Next Button --}}
                        <button type="button"
                                wire:click="nextPage({{ $totalPages }})"
                                @if($page >= $totalPages) disabled @endif
                                class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-bold border transition-colors
                                       {{ $page >= $totalPages ? 'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100 shadow-xs' }}">
                            Next &rarr;
                        </button>
                    </div>
                </div>
            @endif
        </div>
    @endif

</div>
