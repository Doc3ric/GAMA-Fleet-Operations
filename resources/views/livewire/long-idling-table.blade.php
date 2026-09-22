<div class="space-y-3" id="long-idling-table-container">

    {{-- Flash & Save Feedback Notification --}}
    @if(session('success') || $savedMessage)
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 shadow-sm transition-all">
            <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <span class="font-semibold">{{ session('success') ?: $savedMessage }}</span>
            <button type="button" @click="show = false" class="ml-auto text-emerald-600 hover:text-emerald-800 font-bold">&times;</button>
        </div>
    @endif

    {{-- Toolbar --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 rounded-xl bg-white border border-slate-200 px-4 py-3 shadow-sm">
        <div class="flex flex-wrap items-center gap-2.5">
            {{-- Search Input --}}
            <div class="relative">
                <svg class="absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search device, IMEI, address..."
                       class="pl-9 pr-4 py-2 text-sm border border-slate-300 rounded-lg w-56 sm:w-64 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
            </div>

            {{-- Sort Dropdown --}}
            <div class="flex items-center gap-1.5">
                <label for="sort-select" class="text-xs font-semibold text-slate-500 whitespace-nowrap">Sort:</label>
                <select id="sort-select" wire:model.live="sortBy"
                        class="py-2 pl-2.5 pr-8 text-xs font-semibold rounded-lg border border-slate-300 bg-white text-slate-700 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none cursor-pointer">
                    <option value="default">Default Order</option>
                    <option value="stay_time_desc">⏱ Most Stay Time (Highest)</option>
                    <option value="stay_time_asc">⏱ Less Stay Time (Lowest)</option>
                    <option value="device_asc">🔤 Device Name (A &rarr; Z)</option>
                    <option value="device_desc">🔤 Device Name (Z &rarr; A)</option>
                </select>
            </div>

            {{-- Duration Filter Dropdown --}}
            <div class="flex items-center gap-1.5">
                <label for="duration-select" class="text-xs font-semibold text-slate-500 whitespace-nowrap">Duration:</label>
                <select id="duration-select" wire:model.live="filterDuration"
                        class="py-2 pl-2.5 pr-8 text-xs font-semibold rounded-lg border border-slate-300 bg-white text-slate-700 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none cursor-pointer">
                    <option value="all">All Durations</option>
                    <option value="1h">&ge; 1 Hour</option>
                    <option value="2h">&ge; 2 Hours</option>
                    <option value="3h">&ge; 3 Hours</option>
                </select>
            </div>

            {{-- Quick Filter Pills --}}
            <div class="hidden xl:flex items-center gap-1 bg-slate-100 p-1 rounded-lg">
                <button type="button" wire:click="setSort('default')"
                        class="px-2.5 py-1 text-xs font-bold rounded-md transition-colors {{ $sortBy === 'default' && $filterDuration === 'all' ? 'bg-white text-slate-800 shadow-2xs' : 'text-slate-500 hover:text-slate-800' }}">
                    All
                </button>
                <button type="button" wire:click="setSort('stay_time_desc')"
                        class="px-2.5 py-1 text-xs font-bold rounded-md transition-colors flex items-center gap-1 {{ $sortBy === 'stay_time_desc' ? 'bg-blue-600 text-white shadow-2xs' : 'text-slate-600 hover:text-blue-600' }}"
                        title="Sort by Most Stay Time (Highest to Lowest)">
                    <span>⏱ Most Stay</span>
                </button>
                <button type="button" wire:click="setSort('stay_time_asc')"
                        class="px-2.5 py-1 text-xs font-bold rounded-md transition-colors flex items-center gap-1 {{ $sortBy === 'stay_time_asc' ? 'bg-blue-600 text-white shadow-2xs' : 'text-slate-600 hover:text-blue-600' }}"
                        title="Sort by Less Stay Time (Lowest to Highest)">
                    <span>⏱ Less Stay</span>
                </button>
                <button type="button" wire:click="setSort('device_asc')"
                        class="px-2.5 py-1 text-xs font-bold rounded-md transition-colors flex items-center gap-1 {{ $sortBy === 'device_asc' ? 'bg-blue-600 text-white shadow-2xs' : 'text-slate-600 hover:text-blue-600' }}"
                        title="Sort Alphabetically by Device Name (A to Z)">
                    <span>🔤 Device (A-Z)</span>
                </button>
            </div>
        </div>

        <div class="flex items-center gap-2 self-end lg:self-auto">
            <button type="button" @click="$dispatch('open-import-modal')"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors shadow-xs cursor-pointer">
                <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
                Import Data
            </button>
            <button type="button" wire:click="saveAll" wire:loading.attr="disabled" wire:target="saveAll"
                class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-60 transition-colors shadow-sm cursor-pointer">
                <span wire:loading.remove wire:target="saveAll" class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                    Save All
                </span>
                <span wire:loading wire:target="saveAll" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving...
                </span>
            </button>
            <button type="button" wire:click="addRow"
                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Add Row
            </button>
        </div>
    </div>

    {{-- Active Sort / Filter Banner --}}
    @if($sortBy !== 'default' || $filterDuration !== 'all' || $search !== '')
        <div class="flex items-center justify-between bg-blue-50/70 border border-blue-200 rounded-xl px-4 py-2 text-xs text-blue-900">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-bold">Active View:</span>
                @if($sortBy === 'stay_time_desc')
                    <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-900 font-bold px-2 py-0.5 rounded-md border border-amber-300">
                        ⏱ Most Stay Time (Highest to Lowest)
                    </span>
                @elseif($sortBy === 'stay_time_asc')
                    <span class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-900 font-bold px-2 py-0.5 rounded-md border border-emerald-300">
                        ⏱ Less Stay Time (Lowest to Highest)
                    </span>
                @elseif($sortBy === 'device_asc')
                    <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-900 font-bold px-2 py-0.5 rounded-md border border-blue-300">
                        🔤 Device Name (A &rarr; Z)
                    </span>
                @elseif($sortBy === 'device_desc')
                    <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-900 font-bold px-2 py-0.5 rounded-md border border-blue-300">
                        🔤 Device Name (Z &rarr; A)
                    </span>
                @endif

                @if($filterDuration !== 'all')
                    <span class="inline-flex items-center gap-1 bg-indigo-100 text-indigo-900 font-bold px-2 py-0.5 rounded-md border border-indigo-300">
                        Duration &ge; {{ $filterDuration === '1h' ? '1 Hour' : ($filterDuration === '2h' ? '2 Hours' : '3 Hours') }}
                    </span>
                @endif

                @if($search)
                    <span class="inline-flex items-center gap-1 bg-slate-200 text-slate-800 font-medium px-2 py-0.5 rounded-md">
                        Search: "{{ $search }}"
                    </span>
                @endif

                <span class="text-slate-500 font-normal">({{ count($filteredRows) }} of {{ count($rows) }} records shown)</span>
            </div>

            <button type="button" wire:click="$set('sortBy', 'default'); $set('filterDuration', 'all'); $set('search', '')"
                    class="text-blue-700 hover:text-blue-900 font-bold underline hover:no-underline ml-2 whitespace-nowrap cursor-pointer">
                Reset Filters
            </button>
        </div>
    @endif

    {{-- Table --}}
    <div class="rounded-2xl bg-white border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-50/95 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200 sticky top-0 z-10 backdrop-blur-xs">
                    <tr>
                        <th class="py-3 px-3 text-center w-10 text-slate-400">#</th>
                        <th wire:click="toggleSort('device_name')"
                            class="py-3 px-3 min-w-[150px] cursor-pointer hover:bg-slate-100/80 hover:text-slate-800 transition-colors select-none group"
                            title="Click to sort alphabetically by Device Name">
                            <div class="inline-flex items-center gap-1.5">
                                <span>Device Name</span>
                                @if($sortBy === 'device_asc')
                                    <span class="inline-flex items-center text-[10px] text-blue-700 font-bold bg-blue-100 px-1.5 py-0.5 rounded">A&rarr;Z &uarr;</span>
                                @elseif($sortBy === 'device_desc')
                                    <span class="inline-flex items-center text-[10px] text-blue-700 font-bold bg-blue-100 px-1.5 py-0.5 rounded">Z&rarr;A &darr;</span>
                                @else
                                    <svg class="h-3 w-3 text-slate-400 group-hover:text-slate-600 opacity-60 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15 12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                                    </svg>
                                @endif
                            </div>
                        </th>
                        <th class="py-3 px-3 min-w-[140px]">IMEI</th>
                        <th class="py-3 px-3 min-w-[110px]">Model</th>
                        <th class="py-3 px-3 min-w-[95px]">State</th>
                        <th class="py-3 px-3 min-w-[105px] text-center">Start Time</th>
                        <th class="py-3 px-3 min-w-[105px] text-center">End Time</th>
                        <th wire:click="toggleSort('stay_time')"
                            class="py-3 px-3 min-w-[115px] cursor-pointer hover:bg-slate-100/80 hover:text-slate-800 transition-colors select-none group"
                            title="Click to sort by Stay Time (Most / Less)">
                            <div class="inline-flex items-center gap-1.5">
                                <span>Stay Time</span>
                                @if($sortBy === 'stay_time_desc')
                                    <span class="inline-flex items-center text-[10px] text-amber-800 font-bold bg-amber-100 px-1.5 py-0.5 rounded">&darr; Most</span>
                                @elseif($sortBy === 'stay_time_asc')
                                    <span class="inline-flex items-center text-[10px] text-emerald-800 font-bold bg-emerald-100 px-1.5 py-0.5 rounded">&uarr; Less</span>
                                @else
                                    <svg class="h-3 w-3 text-slate-400 group-hover:text-slate-600 opacity-60 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15 12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                                    </svg>
                                @endif
                            </div>
                        </th>
                        <th class="py-3 px-3 min-w-[190px]">Address</th>
                        <th class="py-3 px-3 min-w-[140px]">Coordinates</th>
                        <th class="py-3 px-3 min-w-[110px]">Remarks</th>
                        <th class="py-3 px-3 min-w-[120px] text-center">Screenshot</th>
                        <th class="py-3 px-3 text-center w-12 text-slate-400"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($filteredRows as $index => $row)
                        @php
                            $origIndex = $row['_orig_index'] ?? $index;
                            $rowKey = $row['_key'] ?? ('row-' . ($row['id'] ?? 'new-' . $origIndex));
                        @endphp
                        <tr class="{{ $row['dirty'] ? 'bg-amber-50/40 border-l-4 border-l-amber-400' : 'bg-white hover:bg-blue-50/20 border-l-4 border-l-transparent' }} transition-colors"
                            wire:key="{{ $rowKey }}"
                            @paste="handleRowPaste($event, {{ $origIndex }}, {{ $row['id'] ? $row['id'] : 'null' }})">

                            {{-- # --}}
                            <td class="py-2.5 px-3 text-center text-[11px] text-slate-400 font-mono font-bold">
                                {{ $index + 1 }}
                            </td>

                            {{-- Device Name --}}
                            <td class="py-2 px-2.5">
                                <input type="text" wire:model="rows.{{ $origIndex }}.device_name"
                                       placeholder="Device name"
                                       class="w-full rounded-lg border border-slate-200/90 bg-white/80 hover:bg-white hover:border-slate-300 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 text-xs font-bold text-slate-900 py-1.5 px-2.5 outline-none transition-all placeholder:text-slate-300">
                            </td>

                            {{-- IMEI --}}
                            <td class="py-2 px-2.5">
                                <input type="text" wire:model="rows.{{ $origIndex }}.imei"
                                       placeholder="IMEI"
                                       class="w-full rounded-lg border border-slate-200/90 bg-white/80 hover:bg-white hover:border-slate-300 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 text-[11px] font-mono text-slate-700 py-1.5 px-2 outline-none transition-all placeholder:text-slate-300">
                            </td>

                            {{-- Model --}}
                            <td class="py-2 px-2.5">
                                <input type="text" wire:model="rows.{{ $origIndex }}.model"
                                       placeholder="Model"
                                       class="w-full rounded-lg border border-slate-200/90 bg-white/80 hover:bg-white hover:border-slate-300 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 text-xs text-slate-700 py-1.5 px-2.5 outline-none transition-all placeholder:text-slate-300">
                            </td>

                            {{-- State --}}
                            <td class="py-2 px-2.5">
                                <input type="text" wire:model="rows.{{ $origIndex }}.state"
                                       placeholder="State"
                                       class="w-full rounded-lg border border-slate-200/90 bg-white/80 hover:bg-white hover:border-slate-300 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 text-xs text-slate-700 py-1.5 px-2.5 outline-none transition-all placeholder:text-slate-300">
                            </td>

                            {{-- Start Time --}}
                            <td class="py-2 px-2.5">
                                <input type="time" step="1" wire:model.lazy="rows.{{ $origIndex }}.start_time"
                                       class="w-full rounded-lg border border-slate-200/90 bg-white/80 hover:bg-white hover:border-slate-300 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 text-xs font-mono text-slate-700 text-center py-1.5 px-1.5 outline-none transition-all">
                            </td>

                            {{-- End Time --}}
                            <td class="py-2 px-2.5">
                                <input type="time" step="1" wire:model.lazy="rows.{{ $origIndex }}.end_time"
                                       class="w-full rounded-lg border border-slate-200/90 bg-white/80 hover:bg-white hover:border-slate-300 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 text-xs font-mono text-slate-700 text-center py-1.5 px-1.5 outline-none transition-all">
                            </td>

                            {{-- Stay Time (auto-computed) --}}
                            <td class="py-2 px-2.5 whitespace-nowrap">
                                @if(!empty($row['stay_time']))
                                    @php
                                        $secs = 0;
                                        $parts = explode(':', $row['stay_time']);
                                        if (count($parts) >= 2) {
                                            $secs = ((int)$parts[0] * 3600) + ((int)$parts[1] * 60) + (isset($parts[2]) ? (int)$parts[2] : 0);
                                        }
                                    @endphp
                                    @if($secs >= 7200)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-amber-50 text-amber-800 border border-amber-200 shadow-2xs" title="{{ $row['stay_time'] }} (Extended Idle: 2h+)">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                            {{ $row['stay_time'] }}
                                        </span>
                                    @elseif($secs >= 3600)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-blue-50 text-blue-800 border border-blue-200 shadow-2xs" title="{{ $row['stay_time'] }} (Long Idle: 1h+)">
                                            <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                            {{ $row['stay_time'] }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-mono font-semibold bg-slate-100 text-slate-700 border border-slate-200/70" title="{{ $row['stay_time'] }}">
                                            {{ $row['stay_time'] }}
                                        </span>
                                    @endif
                                @else
                                    <span class="text-xs font-mono text-slate-300 pl-2">—</span>
                                @endif
                            </td>

                            {{-- Address --}}
                            <td class="py-2 px-2.5">
                                <input type="text" wire:model="rows.{{ $origIndex }}.address"
                                       placeholder="Address"
                                       class="w-full rounded-lg border border-slate-200/90 bg-white/80 hover:bg-white hover:border-slate-300 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 text-xs text-slate-700 py-1.5 px-2.5 outline-none transition-all placeholder:text-slate-300">
                            </td>

                            {{-- Coordinates --}}
                            <td class="py-2 px-2.5">
                                <input type="text" wire:model="rows.{{ $origIndex }}.coordinates"
                                       placeholder="14.5995, 120.9842"
                                       class="w-full rounded-lg border border-slate-200/90 bg-white/80 hover:bg-white hover:border-slate-300 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 text-[11px] font-mono text-slate-700 py-1.5 px-2 outline-none transition-all placeholder:text-slate-300"
                                       title="Coordinates (e.g. 14.5995, 120.9842)">
                            </td>

                            {{-- Remarks --}}
                            <td class="py-2 px-2.5">
                                <input type="text" wire:model="rows.{{ $origIndex }}.remarks"
                                       placeholder="Remarks"
                                       class="w-full rounded-lg border border-slate-200/90 bg-white/80 hover:bg-white hover:border-slate-300 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 text-xs text-slate-700 py-1.5 px-2.5 outline-none transition-all placeholder:text-slate-300">
                            </td>

                            {{-- Screenshot --}}
                            <td class="py-2 px-2.5 text-center" x-data="{ uploading: false }">
                                <input type="file"
                                       id="file-input-{{ $origIndex }}"
                                       class="hidden"
                                       accept="image/jpeg,image/jpg,image/png,image/webp"
                                       @change="
                                           const f = $event.target.files[0];
                                           if (!f) return;
                                           uploading = true;
                                           uploadScreenshotFile(f, {{ $origIndex }}, {{ $row['id'] ? $row['id'] : 'null' }})
                                               .finally(() => {
                                                   uploading = false;
                                                   $event.target.value = '';
                                               });
                                       ">

                                <div class="flex items-center justify-center gap-1">
                                    {{-- Uploading indicator --}}
                                    <div x-show="uploading" class="inline-flex items-center gap-1 text-[11px] text-blue-600 font-medium py-1 px-2 bg-blue-50 rounded-lg">
                                        <svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                        <span>Uploading...</span>
                                    </div>

                                    {{-- Image Display or Upload Buttons --}}
                                    <div x-show="!uploading" class="flex items-center justify-center">
                                        @if(!empty($row['image_url']))
                                            <div class="relative group flex items-center gap-1.5">
                                                <button type="button"
                                                    @click="$dispatch('open-lightbox', '{{ $row['image_url'] }}')"
                                                    class="block w-12 h-8 rounded-lg overflow-hidden border border-slate-200 hover:border-blue-500 shadow-2xs hover:shadow transition-all cursor-pointer"
                                                    title="Click to preview full size">
                                                    <img src="{{ $row['image_url'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" alt="screenshot">
                                                </button>

                                                <div class="flex items-center gap-0.5 opacity-60 group-hover:opacity-100 transition-opacity">
                                                    <button type="button"
                                                        onclick="document.getElementById('file-input-{{ $origIndex }}').click()"
                                                        class="p-1 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors cursor-pointer"
                                                        title="Replace screenshot">
                                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                                                    </button>
                                                    <button type="button"
                                                        wire:click="removeRowImage({{ $origIndex }})"
                                                        class="p-1 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors cursor-pointer"
                                                        title="Remove screenshot">
                                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                                    </button>
                                                </div>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-1">
                                                <button type="button"
                                                    onclick="document.getElementById('file-input-{{ $origIndex }}').click()"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-700 px-2 py-1 text-[11px] font-semibold text-slate-600 shadow-2xs transition-all cursor-pointer"
                                                    title="Upload image from computer">
                                                    <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" /></svg>
                                                    Upload
                                                </button>
                                                <button type="button"
                                                    @click="
                                                        uploading = true;
                                                        pasteScreenshotFromClipboard({{ $origIndex }}, {{ $row['id'] ? $row['id'] : 'null' }})
                                                            .finally(() => uploading = false);
                                                    "
                                                    class="inline-flex items-center gap-0.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 hover:text-slate-900 px-1.5 py-1 text-[11px] font-medium text-slate-500 shadow-2xs transition-all cursor-pointer"
                                                    title="Paste screenshot from clipboard (or press Ctrl+V on this row)">
                                                    <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>
                                                    Paste
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Delete --}}
                            <td class="py-2 px-2 text-center">
                                @if(($deleteConfirmId !== null && $deleteConfirmId === $row['id']) ||
                                    ($deleteConfirmIndex !== null && $deleteConfirmIndex === $origIndex && $row['is_new']))
                                    <div class="flex items-center justify-center gap-1">
                                        <button type="button" wire:click="deleteRow"
                                            class="rounded-lg bg-red-600 px-2 py-1 text-[10px] font-bold text-white hover:bg-red-700 shadow-2xs cursor-pointer">Yes</button>
                                        <button type="button" wire:click="cancelDelete"
                                            class="rounded-lg bg-slate-200 px-2 py-1 text-[10px] font-semibold text-slate-700 hover:bg-slate-300 cursor-pointer">No</button>
                                    </div>
                                @else
                                    @if($row['id'])
                                        <button type="button" wire:click="confirmDelete({{ $row['id'] }})"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors cursor-pointer" title="Delete record">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                        </button>
                                    @else
                                        <button type="button" wire:click="confirmDeleteNew({{ $origIndex }})"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors cursor-pointer" title="Remove unsaved row">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                        </button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center max-w-sm mx-auto">
                                    <div class="h-12 w-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                    </div>
                                    <p class="text-sm font-bold text-slate-700">No records found</p>
                                    <p class="text-xs text-slate-400 mt-1">
                                        @if($search || $sortBy !== 'default' || $filterDuration !== 'all')
                                            No idling records matched your current filters or search term.
                                        @else
                                            No long idling incidents recorded for this date yet.
                                        @endif
                                    </p>
                                    @if($search || $sortBy !== 'default' || $filterDuration !== 'all')
                                        <button type="button" wire:click="$set('sortBy', 'default'); $set('filterDuration', 'all'); $set('search', '')"
                                            class="mt-3 inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors shadow-2xs cursor-pointer">
                                            Reset Filters
                                        </button>
                                    @else
                                        <button type="button" wire:click="addRow"
                                            class="mt-3 inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 transition-colors shadow-sm cursor-pointer">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                            Add First Record
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Table footer --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-3.5 bg-slate-50/90 border-t border-slate-200 text-xs">
            <div class="flex items-center gap-3">
                <button type="button" wire:click="addRow"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-white border border-slate-200 px-3.5 py-2 text-xs font-bold text-blue-600 hover:bg-blue-50 hover:border-blue-300 transition-colors shadow-2xs cursor-pointer">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    + Add Row
                </button>
                <span class="text-slate-500 font-medium">
                    Showing <strong class="text-slate-800">{{ count($filteredRows) }}</strong> of {{ count($rows) }} records
                </span>
            </div>
            <div class="flex items-center gap-3">
                @if($this->hasDirty)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200 shadow-2xs">
                        <span class="inline-block h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>
                        Unsaved changes
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 shadow-2xs">
                        <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                        All changes saved
                    </span>
                @endif
                <button type="button" wire:click="saveAll" wire:loading.attr="disabled" wire:target="saveAll"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-slate-800 px-4 py-2 text-xs font-bold text-white hover:bg-slate-900 disabled:opacity-60 transition-all shadow-2xs cursor-pointer">
                    <span wire:loading.remove wire:target="saveAll">Save All</span>
                    <span wire:loading wire:target="saveAll" class="inline-flex items-center gap-1.5">
                        <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Saving...
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- Lightbox Modal --}}
    <div x-data="{ open: false, src: '' }"
         @open-lightbox.window="open = true; src = $event.detail"
         x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="open = false"
         @keydown.escape.window="open = false"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
         style="display: none;">
        <div class="relative max-w-4xl max-h-[90vh]">
            <img :src="src" class="max-w-full max-h-[85vh] rounded-lg shadow-2xl object-contain">
            <button type="button" @click="open = false"
                class="absolute -top-3 -right-3 flex h-8 w-8 items-center justify-center rounded-full bg-white text-slate-700 shadow-lg hover:bg-slate-100 cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
        </div>
    </div>

<script>
async function uploadScreenshotFile(file, rowIndex, recordId = null) {
    const fd = new FormData();
    fd.append('image', file);
    if (recordId && recordId !== 'null') {
        fd.append('record_id', recordId);
    }
    const token = document.querySelector('meta[name=csrf-token]') ? document.querySelector('meta[name=csrf-token]').content : '{{ csrf_token() }}';
    fd.append('_token', token);

    try {
        const res = await fetch('{{ route('reports.uploadScreenshot', $reportId) }}', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data.success) {
            const root = document.getElementById('long-idling-table-container');
            const wireId = root ? root.getAttribute('wire:id') : document.querySelector('[wire\\:id]')?.getAttribute('wire:id');
            if (wireId && window.Livewire) {
                const comp = window.Livewire.find(wireId);
                if (comp) {
                    if (typeof comp.call === 'function') {
                        comp.call('updateRowImage', rowIndex, data.image_url, data.image);
                    } else if (typeof comp.updateRowImage === 'function') {
                        comp.updateRowImage(rowIndex, data.image_url, data.image);
                    }
                }
            }
        } else {
            alert(data.message || 'Image upload failed.');
        }
    } catch (e) {
        console.error(e);
        alert('Image upload failed.');
    }
}

async function pasteScreenshotFromClipboard(rowIndex, recordId = null) {
    if (!navigator.clipboard || !navigator.clipboard.read) {
        alert('Clipboard image reading is not supported directly in this browser. You can click on the row and press Ctrl+V, or use the Upload button.');
        return;
    }
    try {
        const items = await navigator.clipboard.read();
        for (const item of items) {
            const imageType = item.types.find(t => t.startsWith('image/'));
            if (imageType) {
                const blob = await item.getType(imageType);
                const file = new File([blob], 'screenshot_' + Date.now() + '.png', { type: imageType });
                await uploadScreenshotFile(file, rowIndex, recordId);
                return;
            }
        }
        alert('No image found in clipboard. Take a screenshot first (e.g. Win+Shift+S or PrtScn), then click Paste.');
    } catch (err) {
        console.error(err);
        alert('Could not access clipboard image. Click on the row and press Ctrl+V, or use the Upload button.');
    }
}

function handleRowPaste(event, rowIndex, recordId = null) {
    const items = (event.clipboardData || (event.originalEvent && event.originalEvent.clipboardData))?.items;
    if (!items) return;
    for (let i = 0; i < items.length; i++) {
        if (items[i].type && items[i].type.startsWith('image/')) {
            event.preventDefault();
            const file = items[i].getAsFile();
            if (file) {
                uploadScreenshotFile(file, rowIndex, recordId);
            }
            return;
        }
    }
}
</script>

</div>