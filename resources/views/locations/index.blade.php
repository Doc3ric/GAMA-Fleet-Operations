<x-app-layout>
    @section('page-title', 'Location Directory')
    @section('breadcrumb', 'Fleet Management / Location Directory')

    @push('styles')
        <style>
            .alias-pill {
                max-width: 140px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        </style>
    @endpush

    <div class="space-y-4" x-data="{
        showImportModal: false,
        deleteId: null,
        deleteName: '',
        deleteTripCount: 0,
        confirmDelete(id, name, tripCount) {
            this.deleteId = id;
            this.deleteName = name;
            this.deleteTripCount = tripCount;
        }
    }">

        {{-- Top Action Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-1">
            <div>
                <div class="flex items-center gap-2.5">
                    <h2 class="text-xl font-bold text-slate-800 tracking-tight">Location Directory</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                        {{ $locations->total() }} {{ Str::plural('Location', $locations->total()) }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Central reference & dictionary for driver destinations, informal abbreviations, coordinates, and exact map points</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                {{-- Map Overview Button --}}
                <a href="{{ route('locations.map') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors"
                   title="View all locations on map">
                    <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                    </svg>
                    <span>Map View</span>
                </a>

                {{-- Download Template Button --}}
                <a href="{{ route('locations.downloadTemplate') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors"
                   title="Download Excel Template">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span>Template</span>
                </a>

                {{-- Import Button --}}
                <button type="button" @click="showImportModal = true"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors cursor-pointer"
                        title="Import locations from Excel">
                    <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    <span>Import Data</span>
                </button>

                {{-- Export Excel Button --}}
                <a href="{{ route('locations.exportExcel', request()->query()) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors"
                   title="Export filtered locations to Excel">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    <span>Export Excel</span>
                </a>

                {{-- Add Location Button --}}
                <a href="{{ route('locations.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>Add Location</span>
                </a>
            </div>
        </div>

        {{-- Metric Statistics Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <div class="bg-white rounded-xl border border-slate-200 p-3.5 shadow-xs">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Locations</p>
                <p class="text-xl font-black text-slate-800 mt-1">{{ number_format($statistics['total']) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-3.5 shadow-xs">
                <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-600">Active</p>
                <p class="text-xl font-black text-emerald-600 mt-1">{{ number_format($statistics['active']) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-3.5 shadow-xs">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Inactive</p>
                <p class="text-xl font-black text-slate-600 mt-1">{{ number_format($statistics['inactive']) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-3.5 shadow-xs">
                <p class="text-[11px] font-bold uppercase tracking-wider text-blue-600">Location Types</p>
                <p class="text-xl font-black text-blue-600 mt-1">{{ number_format($statistics['types_count']) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-3.5 shadow-xs">
                <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-600">Known Aliases</p>
                <p class="text-xl font-black text-indigo-600 mt-1">{{ number_format($statistics['aliases_count']) }}</p>
            </div>
        </div>

        {{-- Search & Filter Toolbar --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-3">
            <form method="GET" action="{{ route('locations.index') }}" class="space-y-2.5">
                <div class="flex flex-col sm:flex-row gap-2">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </div>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                               placeholder="Search code (G2), official name, alias (FARM 2), address, barangay, municipality..."
                               class="w-full pl-9 pr-3 py-1.5 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 rounded-lg text-xs placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>

                    {{-- Type Filter --}}
                    <div class="w-full sm:w-44">
                        <select name="type" class="w-full py-1.5 px-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Types</option>
                            @foreach($types as $typeOption)
                                <option value="{{ $typeOption }}" {{ ($filters['type'] ?? '') === $typeOption ? 'selected' : '' }}>
                                    {{ $typeOption }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Status Filter --}}
                    <div class="w-full sm:w-36">
                        <select name="status" class="w-full py-1.5 px-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Statuses</option>
                            @foreach($statuses as $statusOption)
                                <option value="{{ $statusOption }}" {{ ($filters['status'] ?? '') === $statusOption ? 'selected' : '' }}>
                                    {{ $statusOption }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <button type="submit"
                                class="px-3.5 py-1.5 bg-slate-800 text-white text-xs font-semibold rounded-lg hover:bg-slate-900 transition-colors cursor-pointer shadow-xs">
                            Apply
                        </button>
                        @if(!empty(array_filter($filters ?? [])))
                            <a href="{{ route('locations.index') }}"
                               class="px-2.5 py-1.5 bg-slate-100 text-slate-600 text-xs font-medium rounded-lg hover:bg-slate-200 transition-colors"
                               title="Clear all filters">
                                Reset
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        {{-- Master Data Table --}}
        <div class="rounded-2xl bg-white shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <th class="px-5 py-3.5">Code</th>
                            <th class="px-5 py-3.5">Official Location</th>
                            <th class="px-4 py-3.5">Type</th>
                            <th class="px-5 py-3.5">Aliases</th>
                            <th class="px-4 py-3.5">Coordinates</th>
                            <th class="px-4 py-3.5 text-center">Status</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        @forelse($locations as $loc)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                {{-- Code --}}
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-lg bg-slate-900 px-2.5 py-1 font-mono text-xs font-bold text-white shadow-xs">
                                        {{ $loc->code }}
                                    </span>
                                </td>

                                {{-- Official Location & Address --}}
                                <td class="px-5 py-4 max-w-sm">
                                    <a href="{{ route('locations.show', $loc) }}" class="font-bold text-slate-900 hover:text-blue-600 transition-colors">
                                        {{ $loc->official_name }}
                                    </a>
                                    <p class="text-[11px] text-slate-500 truncate mt-0.5" title="{{ $loc->address }}">
                                        {{ $loc->address }}
                                    </p>
                                </td>

                                {{-- Type --}}
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">
                                        {{ $loc->type }}
                                    </span>
                                </td>

                                {{-- Aliases Preview --}}
                                <td class="px-5 py-4 max-w-xs">
                                    @php
                                        $aliasCount = $loc->aliases->count();
                                        $previewAliases = $loc->aliases->take(3);
                                    @endphp
                                    @if($aliasCount > 0)
                                        <div class="flex flex-wrap items-center gap-1">
                                            @foreach($previewAliases as $alias)
                                                <span class="inline-block rounded bg-blue-50 border border-blue-200 px-1.5 py-0.5 text-[10px] font-medium text-blue-700 alias-pill" title="{{ $alias->alias }}">
                                                    {{ $alias->alias }}
                                                </span>
                                            @endforeach
                                            @if($aliasCount > 3)
                                                <span class="inline-block rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500">
                                                    +{{ $aliasCount - 3 }} more
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic text-[11px]">None registered</span>
                                    @endif
                                </td>

                                {{-- Coordinates --}}
                                <td class="px-4 py-4 whitespace-nowrap font-mono text-[11px] text-slate-600">
                                    <span class="inline-flex items-center gap-1 rounded bg-slate-50 border border-slate-200 px-2 py-0.5">
                                        <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                        {{ number_format($loc->latitude, 5) }}, {{ number_format($loc->longitude, 5) }}
                                    </span>
                                </td>

                                {{-- Status --}}
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    @if($loc->isActive())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 border border-slate-200 px-2.5 py-0.5 text-[11px] font-bold text-slate-500">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                            Inactive
                                        </span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="px-5 py-4 whitespace-nowrap text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        {{-- View --}}
                                        <a href="{{ route('locations.show', $loc) }}"
                                           class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition-colors shadow-xs"
                                           title="View Details">
                                            View
                                        </a>

                                        {{-- Edit --}}
                                        <a href="{{ route('locations.edit', $loc) }}"
                                           class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-amber-600 transition-colors shadow-xs"
                                           title="Edit Location">
                                            Edit
                                        </a>

                                        {{-- Deactivate / Delete --}}
                                        @php
                                            $linkedTrips = $loc->trips()->count();
                                        @endphp
                                        <button type="button"
                                                @click="confirmDelete({{ $loc->id }}, '{{ addslashes($loc->official_name) }}', {{ $linkedTrips }})"
                                                class="inline-flex items-center rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition-colors shadow-xs cursor-pointer"
                                                title="{{ $linkedTrips > 0 ? 'Deactivate Location' : 'Delete Location' }}">
                                            {{ $linkedTrips > 0 ? 'Deactivate' : 'Delete' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="mx-auto max-w-sm text-center">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                            </svg>
                                        </div>
                                        <h3 class="mt-3 text-sm font-bold text-slate-900">No locations found</h3>
                                        <p class="mt-1 text-xs text-slate-500">Get started by creating your first official location or importing a location directory spreadsheet.</p>
                                        <div class="mt-4 flex items-center justify-center gap-2">
                                            <a href="{{ route('locations.create') }}" class="rounded-xl bg-blue-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm hover:bg-blue-700">
                                                Add Location
                                            </a>
                                            <a href="{{ route('locations.downloadTemplate') }}" class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                                                Download Template
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($locations->hasPages())
                <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                    {{ $locations->links() }}
                </div>
            @endif
        </div>

        {{-- Import Modal --}}
        <div x-show="showImportModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="showImportModal = false" class="w-full max-w-md rounded-2xl bg-white shadow-2xl border border-slate-200 p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-800">Import Location Directory</h3>
                    <button type="button" @click="showImportModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>
                <form method="POST" action="{{ route('locations.importData') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Select Excel or CSV File</label>
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700">
                        <p class="text-[11px] text-slate-500 mt-1">Supports multiple aliases in a single row separated by semicolon (<code class="text-blue-600">;</code>).</p>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="showImportModal = false"
                                class="rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-sm">
                            Upload & Import
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Deactivate / Delete Confirmation Modal --}}
        <div x-show="deleteId !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="deleteId = null" class="w-full max-w-md rounded-2xl bg-white shadow-2xl border border-slate-200 p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900" x-text="deleteTripCount > 0 ? 'Deactivate Location' : 'Confirm Delete'"></h3>
                        <p class="text-xs text-slate-500" x-text="deleteName"></p>
                    </div>
                </div>

                <div class="text-xs text-slate-600 space-y-2">
                    <template x-if="deleteTripCount > 0">
                        <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-amber-800">
                            <strong>Note:</strong> This location is referenced in <span x-text="deleteTripCount" class="font-bold"></span> historical trip records. To protect audit integrity, it will be marked <strong>INACTIVE</strong> rather than permanently deleted.
                        </div>
                    </template>
                    <template x-if="deleteTripCount === 0">
                        <p>Are you sure you want to permanently delete this location master record? This action cannot be undone.</p>
                    </template>
                </div>

                <form :action="'{{ url('locations') }}/' + deleteId" method="POST" class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    @csrf
                    @method('DELETE')
                    <button type="button" @click="deleteId = null"
                            class="rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200">
                        Cancel
                    </button>
                    <button type="submit"
                            class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white hover:bg-rose-700 shadow-sm"
                            x-text="deleteTripCount > 0 ? 'Deactivate Location' : 'Delete Permanently'">
                    </button>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
