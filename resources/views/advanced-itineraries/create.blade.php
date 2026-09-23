<x-app-layout>
    @section('page-title', 'Create Advanced Itinerary')
    @section('breadcrumb')
        <a href="{{ route('advanced-itineraries.index') }}" class="hover:underline">Advanced Itineraries</a> &bull; Create
    @endsection

    @php
        $locationsList = $locations->map(fn($loc) => [
            'id' => (string) $loc->id,
            'code' => $loc->code ?? '',
            'official_name' => $loc->official_name,
            'type' => $loc->type ?? '',
            'address' => $loc->address ?? '',
            'municipality' => $loc->municipality ?? '',
            'province' => $loc->province ?? '',
            'aliases' => $loc->relationLoaded('aliases') ? $loc->aliases->pluck('alias')->values()->all() : [],
        ])->values()->all();
    @endphp

    <div class="max-w-6xl mx-auto space-y-6" x-data="itineraryBuilder({
        calculateDistanceUrl: '{{ route('locations.calculateDistance') }}',
        csrfToken: '{{ csrf_token() }}',
        locationsList: {{ json_encode($locationsList) }},
        initialLegs: {{ json_encode(old('legs', [
            [
                'origin_location_id' => '',
                'starting_point_location_id' => '',
                'destination_location_id' => '',
                'distance_origin_to_start' => '',
                'distance_start_to_dest' => '',
                'total_distance' => '',
                'duration_origin_to_start_minutes' => '',
                'duration_start_to_dest_minutes' => '',
                'total_duration_minutes' => '',
                'routing_source' => 'osrm',
                'purpose' => '',
            ]
        ])) }}
    })">
        {{-- Errors --}}
        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 text-xs font-medium space-y-1">
                <div class="font-bold flex items-center gap-1.5 text-rose-900">
                    <svg class="h-4 w-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    Please fix the following validation errors:
                </div>
                <ul class="list-disc list-inside space-y-0.5 ml-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('advanced-itineraries.store') }}" class="space-y-6">
            @csrf

            {{-- Basic Info Card --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Itinerary Details</h2>
                    <span class="text-[11px] text-slate-500 font-medium">Paper-Based Fleet Planning</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Itinerary Date <span class="text-rose-500">*</span></label>
                        <input type="date" name="itinerary_date" value="{{ old('itinerary_date', date('Y-m-d')) }}" required
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Assigned Vehicle</label>
                        <select name="vehicle_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                            <option value="">-- Unassigned / Optional --</option>
                            @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" {{ old('vehicle_id') == $vehicle->id ? 'selected' : '' }}>
                                    {{ $vehicle->equipment_code }} &bull; {{ $vehicle->plate_number }} ({{ $vehicle->model }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Status <span class="text-rose-500">*</span></label>
                        <select name="status" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                            <option value="DRAFT" {{ old('status', 'DRAFT') === 'DRAFT' ? 'selected' : '' }}>DRAFT (Editable)</option>
                            <option value="FINALIZED" {{ old('status') === 'FINALIZED' ? 'selected' : '' }}>FINALIZED (Confirmed)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Title / Subject (Optional)</label>
                        <input type="text" name="title" value="{{ old('title') }}" placeholder="e.g. Northern Mindanao Feed Mill Route"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Notes / Operating Guidelines</label>
                        <textarea name="notes" rows="1" placeholder="Optional notes for operations or dispatch team"
                                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Legs Section --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Itinerary Legs & Distance Calculation</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Select Origin &rarr; Starting Point &rarr; Destination from the Location Directory. Distances and estimated driving times calculate automatically via road routing.</p>
                    </div>
                    <button type="button" @click="addLeg()"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-xs transition cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add Another Leg
                    </button>
                </div>

                <div class="space-y-4">
                    <template x-for="(leg, index) in legs" :key="index">
                        <div class="rounded-xl border border-slate-200 p-4 bg-slate-50/60 space-y-3 transition">
                            {{-- Leg Header --}}
                            <div class="flex items-center justify-between border-b border-slate-200/80 pb-2">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-slate-900 text-[10px] font-bold text-white" x-text="index + 1"></span>
                                    <span class="font-bold text-xs text-slate-800" x-text="'Leg #' + (index + 1)"></span>

                                    {{-- Routing Source Badge --}}
                                    <template x-if="leg.routing_source === 'osrm' && leg.total_distance">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            AUTOMATIC (OSRM)
                                        </span>
                                    </template>
                                    <template x-if="leg.routing_source === 'manual' && leg.total_distance">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 border border-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                            MANUAL
                                        </span>
                                    </template>
                                </div>

                                <div class="flex items-center gap-2">
                                    {{-- Optional Helper: Copy Previous Destination as Origin --}}
                                    <template x-if="index > 0 && legs[index - 1].destination_location_id">
                                        <button type="button" @click="copyPreviousDestination(index)"
                                                class="text-[11px] text-blue-600 hover:text-blue-800 hover:underline font-medium cursor-pointer">
                                            &larr; Use Leg #<span x-text="index"></span> Dest as Origin
                                        </button>
                                    </template>

                                    <button type="button" @click="removeLeg(index)" x-show="legs.length > 1"
                                            class="text-rose-600 hover:text-rose-800 text-xs font-semibold ml-2 cursor-pointer">
                                        Remove Leg
                                    </button>
                                </div>
                            </div>

                            <input type="hidden" :name="'legs[' + index + '][sort_order]'" :value="index">
                            <input type="hidden" :name="'legs[' + index + '][routing_source]'" x-model="leg.routing_source">
                            <input type="hidden" :name="'legs[' + index + '][duration_origin_to_start_minutes]'" x-model="leg.duration_origin_to_start_minutes">
                            <input type="hidden" :name="'legs[' + index + '][duration_start_to_dest_minutes]'" x-model="leg.duration_start_to_dest_minutes">
                            <input type="hidden" :name="'legs[' + index + '][total_duration_minutes]'" x-model="leg.total_duration_minutes">

                            {{-- Locations Row (Searchable Comboboxes: Origin, Starting Point, Destination) --}}
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                {{-- 1. Origin Combobox --}}
                                <div>
                                    <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">
                                        Origin Location <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative"
                                         x-data="locationCombobox({
                                             selectedId: leg.origin_location_id,
                                             inputName: 'legs[' + index + '][origin_location_id]',
                                             required: true,
                                             placeholder: 'Type to search Origin...',
                                             locations: locationsList,
                                             onSelect: (id) => {
                                                 leg.origin_location_id = id;
                                                 onLocationChange(index);
                                             }
                                         })"
                                         x-effect="if (leg.origin_location_id !== selectedId) { selectedId = leg.origin_location_id; syncDisplayFromId(); }">
                                        <div class="relative">
                                            <input type="text"
                                                   x-model="searchQuery"
                                                   @input="onInput()"
                                                   @focus="onFocus()"
                                                   @blur="onBlur()"
                                                   @keydown.down.prevent="onArrowDown()"
                                                   @keydown.up.prevent="onArrowUp()"
                                                   @keydown.enter.prevent="onEnter()"
                                                   @keydown.escape.prevent="onEscape()"
                                                   @keydown.tab="onTab()"
                                                   :placeholder="placeholder"
                                                   class="w-full rounded-xl border border-slate-200 bg-white pl-8 pr-7 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none shadow-2xs font-medium">

                                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                                </svg>
                                            </div>

                                            <button type="button"
                                                    x-show="selectedId"
                                                    @click.stop="clear()"
                                                    class="absolute inset-y-0 right-0 flex items-center pr-2 text-slate-400 hover:text-slate-600 cursor-pointer"
                                                    title="Clear selection">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </div>

                                        <input type="hidden" :name="inputName" :value="selectedId" :required="required">

                                        <div x-show="open && filteredLocations.length > 0"
                                             x-cloak
                                             class="absolute z-40 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-slate-200 bg-white py-1 text-xs shadow-lg focus:outline-none"
                                             x-ref="list">
                                            <template x-for="(loc, lIdx) in filteredLocations" :key="loc.id">
                                                <div @mousedown.prevent="selectLocation(loc)"
                                                     @mouseenter="highlightedIndex = lIdx"
                                                     :class="{
                                                         'bg-blue-50 text-blue-900 font-semibold': highlightedIndex === lIdx,
                                                         'bg-blue-600 text-white': String(selectedId) === String(loc.id) && highlightedIndex === lIdx,
                                                         'text-slate-900': highlightedIndex !== lIdx
                                                     }"
                                                     class="cursor-pointer select-none px-3 py-2 transition-colors border-b border-slate-50 last:border-0">
                                                    <div class="flex items-center justify-between gap-1">
                                                        <span class="font-bold text-xs truncate" x-text="loc.official_name"></span>
                                                        <template x-if="loc.code">
                                                            <span class="text-[10px] font-mono shrink-0 px-1.5 py-0.5 rounded"
                                                                  :class="highlightedIndex === lIdx && String(selectedId) === String(loc.id) ? 'bg-blue-700 text-white' : 'bg-slate-100 text-slate-600'"
                                                                  x-text="loc.code"></span>
                                                        </template>
                                                    </div>
                                                    <div class="text-[10px] mt-0.5 truncate"
                                                         :class="highlightedIndex === lIdx && String(selectedId) === String(loc.id) ? 'text-blue-100' : 'text-slate-400'">
                                                        <span x-text="loc.type || 'Location'"></span>
                                                        <template x-if="loc.municipality || loc.province">
                                                            <span> &bull; <span x-text="[loc.municipality, loc.province].filter(Boolean).join(', ')"></span></span>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>

                                        <div x-show="open && searchQuery.trim() && filteredLocations.length === 0"
                                             x-cloak
                                             class="absolute z-40 mt-1 w-full rounded-xl border border-slate-200 bg-white p-3 text-center text-xs text-slate-500 shadow-lg">
                                            No locations found matching "<span class="font-medium text-slate-700" x-text="searchQuery"></span>"
                                        </div>
                                    </div>
                                </div>

                                {{-- 2. Starting Point Combobox --}}
                                <div>
                                    <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">
                                        Starting Point (Depot/Stop) <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative"
                                         x-data="locationCombobox({
                                             selectedId: leg.starting_point_location_id,
                                             inputName: 'legs[' + index + '][starting_point_location_id]',
                                             required: true,
                                             placeholder: 'Type to search Starting Point...',
                                             locations: locationsList,
                                             onSelect: (id) => {
                                                 leg.starting_point_location_id = id;
                                                 onLocationChange(index);
                                             }
                                         })"
                                         x-effect="if (leg.starting_point_location_id !== selectedId) { selectedId = leg.starting_point_location_id; syncDisplayFromId(); }">
                                        <div class="relative">
                                            <input type="text"
                                                   x-model="searchQuery"
                                                   @input="onInput()"
                                                   @focus="onFocus()"
                                                   @blur="onBlur()"
                                                   @keydown.down.prevent="onArrowDown()"
                                                   @keydown.up.prevent="onArrowUp()"
                                                   @keydown.enter.prevent="onEnter()"
                                                   @keydown.escape.prevent="onEscape()"
                                                   @keydown.tab="onTab()"
                                                   :placeholder="placeholder"
                                                   class="w-full rounded-xl border border-slate-200 bg-white pl-8 pr-7 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none shadow-2xs font-medium">

                                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                                </svg>
                                            </div>

                                            <button type="button"
                                                    x-show="selectedId"
                                                    @click.stop="clear()"
                                                    class="absolute inset-y-0 right-0 flex items-center pr-2 text-slate-400 hover:text-slate-600 cursor-pointer"
                                                    title="Clear selection">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </div>

                                        <input type="hidden" :name="inputName" :value="selectedId" :required="required">

                                        <div x-show="open && filteredLocations.length > 0"
                                             x-cloak
                                             class="absolute z-40 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-slate-200 bg-white py-1 text-xs shadow-lg focus:outline-none"
                                             x-ref="list">
                                            <template x-for="(loc, lIdx) in filteredLocations" :key="loc.id">
                                                <div @mousedown.prevent="selectLocation(loc)"
                                                     @mouseenter="highlightedIndex = lIdx"
                                                     :class="{
                                                         'bg-blue-50 text-blue-900 font-semibold': highlightedIndex === lIdx,
                                                         'bg-blue-600 text-white': String(selectedId) === String(loc.id) && highlightedIndex === lIdx,
                                                         'text-slate-900': highlightedIndex !== lIdx
                                                     }"
                                                     class="cursor-pointer select-none px-3 py-2 transition-colors border-b border-slate-50 last:border-0">
                                                    <div class="flex items-center justify-between gap-1">
                                                        <span class="font-bold text-xs truncate" x-text="loc.official_name"></span>
                                                        <template x-if="loc.code">
                                                            <span class="text-[10px] font-mono shrink-0 px-1.5 py-0.5 rounded"
                                                                  :class="highlightedIndex === lIdx && String(selectedId) === String(loc.id) ? 'bg-blue-700 text-white' : 'bg-slate-100 text-slate-600'"
                                                                  x-text="loc.code"></span>
                                                        </template>
                                                    </div>
                                                    <div class="text-[10px] mt-0.5 truncate"
                                                         :class="highlightedIndex === lIdx && String(selectedId) === String(loc.id) ? 'text-blue-100' : 'text-slate-400'">
                                                        <span x-text="loc.type || 'Location'"></span>
                                                        <template x-if="loc.municipality || loc.province">
                                                            <span> &bull; <span x-text="[loc.municipality, loc.province].filter(Boolean).join(', ')"></span></span>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>

                                        <div x-show="open && searchQuery.trim() && filteredLocations.length === 0"
                                             x-cloak
                                             class="absolute z-40 mt-1 w-full rounded-xl border border-slate-200 bg-white p-3 text-center text-xs text-slate-500 shadow-lg">
                                            No locations found matching "<span class="font-medium text-slate-700" x-text="searchQuery"></span>"
                                        </div>
                                    </div>
                                </div>

                                {{-- 3. Destination Combobox --}}
                                <div>
                                    <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">
                                        Final Destination <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative"
                                         x-data="locationCombobox({
                                             selectedId: leg.destination_location_id,
                                             inputName: 'legs[' + index + '][destination_location_id]',
                                             required: true,
                                             placeholder: 'Type to search Destination...',
                                             locations: locationsList,
                                             onSelect: (id) => {
                                                 leg.destination_location_id = id;
                                                 onLocationChange(index);
                                             }
                                         })"
                                         x-effect="if (leg.destination_location_id !== selectedId) { selectedId = leg.destination_location_id; syncDisplayFromId(); }">
                                        <div class="relative">
                                            <input type="text"
                                                   x-model="searchQuery"
                                                   @input="onInput()"
                                                   @focus="onFocus()"
                                                   @blur="onBlur()"
                                                   @keydown.down.prevent="onArrowDown()"
                                                   @keydown.up.prevent="onArrowUp()"
                                                   @keydown.enter.prevent="onEnter()"
                                                   @keydown.escape.prevent="onEscape()"
                                                   @keydown.tab="onTab()"
                                                   :placeholder="placeholder"
                                                   class="w-full rounded-xl border border-slate-200 bg-white pl-8 pr-7 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none shadow-2xs font-medium">

                                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                                </svg>
                                            </div>

                                            <button type="button"
                                                    x-show="selectedId"
                                                    @click.stop="clear()"
                                                    class="absolute inset-y-0 right-0 flex items-center pr-2 text-slate-400 hover:text-slate-600 cursor-pointer"
                                                    title="Clear selection">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </div>

                                        <input type="hidden" :name="inputName" :value="selectedId" :required="required">

                                        <div x-show="open && filteredLocations.length > 0"
                                             x-cloak
                                             class="absolute z-40 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-slate-200 bg-white py-1 text-xs shadow-lg focus:outline-none"
                                             x-ref="list">
                                            <template x-for="(loc, lIdx) in filteredLocations" :key="loc.id">
                                                <div @mousedown.prevent="selectLocation(loc)"
                                                     @mouseenter="highlightedIndex = lIdx"
                                                     :class="{
                                                         'bg-blue-50 text-blue-900 font-semibold': highlightedIndex === lIdx,
                                                         'bg-blue-600 text-white': String(selectedId) === String(loc.id) && highlightedIndex === lIdx,
                                                         'text-slate-900': highlightedIndex !== lIdx
                                                     }"
                                                     class="cursor-pointer select-none px-3 py-2 transition-colors border-b border-slate-50 last:border-0">
                                                    <div class="flex items-center justify-between gap-1">
                                                        <span class="font-bold text-xs truncate" x-text="loc.official_name"></span>
                                                        <template x-if="loc.code">
                                                            <span class="text-[10px] font-mono shrink-0 px-1.5 py-0.5 rounded"
                                                                  :class="highlightedIndex === lIdx && String(selectedId) === String(loc.id) ? 'bg-blue-700 text-white' : 'bg-slate-100 text-slate-600'"
                                                                  x-text="loc.code"></span>
                                                        </template>
                                                    </div>
                                                    <div class="text-[10px] mt-0.5 truncate"
                                                         :class="highlightedIndex === lIdx && String(selectedId) === String(loc.id) ? 'text-blue-100' : 'text-slate-400'">
                                                        <span x-text="loc.type || 'Location'"></span>
                                                        <template x-if="loc.municipality || loc.province">
                                                            <span> &bull; <span x-text="[loc.municipality, loc.province].filter(Boolean).join(', ')"></span></span>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>

                                        <div x-show="open && searchQuery.trim() && filteredLocations.length === 0"
                                             x-cloak
                                             class="absolute z-40 mt-1 w-full rounded-xl border border-slate-200 bg-white p-3 text-center text-xs text-slate-500 shadow-lg">
                                            No locations found matching "<span class="font-medium text-slate-700" x-text="searchQuery"></span>"
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Routing Engine Banner / Trigger --}}
                            <div class="flex flex-wrap items-center justify-between gap-2 bg-white px-3 py-2 rounded-xl border border-slate-200 text-xs">
                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            @click="calculateLeg(index)"
                                            :disabled="leg.is_calculating || !leg.origin_location_id || !leg.starting_point_location_id || !leg.destination_location_id"
                                            class="rounded-lg bg-slate-900 px-3 py-1.5 text-[11px] font-bold text-white hover:bg-blue-600 transition shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                                        <template x-if="leg.is_calculating">
                                            <svg class="h-3 w-3 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                            </svg>
                                        </template>
                                        <span x-text="leg.is_calculating ? 'Calculating Road Route...' : 'Calculate Road Route'"></span>
                                    </button>

                                    <template x-if="leg.calc_success">
                                        <div class="text-[11px] text-emerald-700 font-semibold flex items-center gap-1.5 flex-wrap">
                                            <span>✓ Calculated by OSRM:</span>
                                            <span class="font-mono font-bold" x-text="leg.total_distance + ' km total'"></span>
                                            <template x-if="leg.total_duration_minutes">
                                                <span class="inline-flex items-center gap-1 rounded bg-emerald-100 text-emerald-800 px-1.5 py-0.5 text-[10px]">
                                                    ⏱ Est. Driving Time: <span class="font-bold" x-text="formatDuration(leg.total_duration_minutes)"></span>
                                                </span>
                                            </template>
                                        </div>
                                    </template>

                                    <template x-if="leg.calc_error">
                                        <div class="text-[11px] text-amber-700 font-semibold flex items-center gap-1">
                                            <span x-text="leg.calc_error"></span>
                                        </div>
                                    </template>
                                </div>

                                <div class="text-[11px] text-slate-400">
                                    Road formula: <span class="font-mono">Origin &rarr; Start + Start &rarr; Dest</span>
                                </div>
                            </div>

                            {{-- Distance & Duration Inputs Row --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">
                                        Dist. 1: Origin &rarr; Start (km)
                                    </label>
                                    <input type="number" step="0.01" min="0"
                                           :name="'legs[' + index + '][distance_origin_to_start]'"
                                           x-model="leg.distance_origin_to_start"
                                           @input="onManualDistanceChange(index)"
                                           placeholder="0.00"
                                           class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-mono text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">
                                        Dist. 2: Start &rarr; Dest (km)
                                    </label>
                                    <input type="number" step="0.01" min="0"
                                           :name="'legs[' + index + '][distance_start_to_dest]'"
                                           x-model="leg.distance_start_to_dest"
                                           @input="onManualDistanceChange(index)"
                                           placeholder="0.00"
                                           class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-mono text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 mb-1">
                                        Leg Total Distance (km)
                                    </label>
                                    <input type="number" step="0.01" min="0"
                                           :name="'legs[' + index + '][total_distance]'"
                                           x-model="leg.total_distance"
                                           readonly
                                           placeholder="0.00"
                                           class="w-full rounded-lg border border-slate-200 bg-slate-100/70 px-2.5 py-1.5 text-xs font-mono font-bold text-blue-700 outline-none">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">
                                        Purpose / Cargo (Optional)
                                    </label>
                                    <input type="text"
                                           :name="'legs[' + index + '][purpose]'"
                                           x-model="leg.purpose"
                                           placeholder="e.g. Delivery, Pick-up, Transfer"
                                           class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                                </div>
                            </div>

                            {{-- Estimated Driving Time Breakdown --}}
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-slate-200/60 text-xs">
                                <div class="rounded-xl bg-white border border-slate-200 p-2.5">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-500">Est. Driving Time: Origin &rarr; Start</span>
                                    <div class="font-mono font-bold text-xs text-slate-800 mt-0.5" x-text="formatDuration(leg.duration_origin_to_start_minutes)"></div>
                                </div>
                                <div class="rounded-xl bg-white border border-slate-200 p-2.5">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-500">Est. Driving Time: Start &rarr; Dest</span>
                                    <div class="font-mono font-bold text-xs text-slate-800 mt-0.5" x-text="formatDuration(leg.duration_start_to_dest_minutes)"></div>
                                </div>
                                <div class="rounded-xl bg-blue-50 border border-blue-100 p-2.5">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-blue-600">Leg Total Est. Driving Time</span>
                                    <div class="font-mono font-bold text-xs text-blue-800 mt-0.5" x-text="formatDuration(leg.total_duration_minutes)"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Summary & Submission Sticky Footer --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-6">
                    <div>
                        <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Legs</span>
                        <span class="font-bold text-slate-900 text-sm" x-text="legs.length"></span>
                    </div>
                    <div class="h-8 border-r border-slate-200"></div>
                    <div>
                        <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Road Distance</span>
                        <div class="flex items-baseline gap-1 font-mono font-bold text-blue-600 text-lg">
                            <span x-text="grandTotalDistance()"></span>
                            <span class="text-xs text-slate-500 font-sans font-normal">km</span>
                        </div>
                    </div>
                    <div class="h-8 border-r border-slate-200"></div>
                    <div>
                        <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Est. Driving Time</span>
                        <div class="flex items-baseline gap-1 font-mono font-bold text-slate-800 text-lg">
                            <span x-text="grandTotalDuration()"></span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('advanced-itineraries.index') }}"
                       class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Cancel
                    </a>
                    <button type="submit"
                            class="rounded-xl bg-blue-600 px-6 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition cursor-pointer">
                        Save Itinerary Record
                    </button>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function locationCombobox(config) {
            return {
                open: false,
                searchQuery: '',
                highlightedIndex: 0,
                locations: config.locations || [],
                selectedId: config.selectedId || '',
                inputName: config.inputName || '',
                required: !!config.required,
                placeholder: config.placeholder || 'Type to search location...',

                init() {
                    this.syncDisplayFromId();
                },

                syncDisplayFromId() {
                    if (!this.selectedId) {
                        this.searchQuery = '';
                        return;
                    }
                    const found = this.locations.find(l => String(l.id) === String(this.selectedId));
                    if (found) {
                        this.searchQuery = found.official_name;
                    } else {
                        this.searchQuery = '';
                    }
                },

                get filteredLocations() {
                    if (!this.searchQuery || this.searchQuery.trim() === '') {
                        return this.locations.slice(0, 30);
                    }
                    const q = this.searchQuery.toLowerCase().trim();
                    return this.locations.filter(loc => {
                        const code = (loc.code || '').toLowerCase();
                        const name = (loc.official_name || '').toLowerCase();
                        const type = (loc.type || '').toLowerCase();
                        const address = (loc.address || '').toLowerCase();
                        const muni = (loc.municipality || '').toLowerCase();
                        const prov = (loc.province || '').toLowerCase();
                        const aliases = (loc.aliases || []).map(a => String(a).toLowerCase());

                        return code.includes(q) ||
                            name.includes(q) ||
                            type.includes(q) ||
                            address.includes(q) ||
                            muni.includes(q) ||
                            prov.includes(q) ||
                            aliases.some(a => a.includes(q));
                    }).slice(0, 30);
                },

                onInput() {
                    this.open = true;
                    this.highlightedIndex = 0;
                    if (!this.searchQuery.trim()) {
                        this.selectLocation(null);
                    }
                },

                onFocus() {
                    this.open = true;
                    this.highlightedIndex = 0;
                },

                onBlur() {
                    setTimeout(() => {
                        this.open = false;
                        this.syncDisplayFromId();
                    }, 250);
                },

                onArrowDown() {
                    if (!this.open) {
                        this.open = true;
                        return;
                    }
                    const count = this.filteredLocations.length;
                    if (count > 0) {
                        this.highlightedIndex = (this.highlightedIndex + 1) % count;
                        this.scrollToHighlighted();
                    }
                },

                onArrowUp() {
                    if (!this.open) {
                        this.open = true;
                        return;
                    }
                    const count = this.filteredLocations.length;
                    if (count > 0) {
                        this.highlightedIndex = (this.highlightedIndex - 1 + count) % count;
                        this.scrollToHighlighted();
                    }
                },

                onEnter() {
                    if (this.open && this.filteredLocations.length > 0) {
                        const item = this.filteredLocations[this.highlightedIndex];
                        if (item) {
                            this.selectLocation(item);
                        }
                    }
                },

                onEscape() {
                    this.open = false;
                    this.syncDisplayFromId();
                },

                onTab() {
                    if (this.open && this.filteredLocations.length > 0 && this.highlightedIndex >= 0) {
                        const item = this.filteredLocations[this.highlightedIndex];
                        if (item && !this.selectedId) {
                            this.selectLocation(item);
                        }
                    }
                    this.open = false;
                },

                selectLocation(item) {
                    if (item) {
                        this.selectedId = String(item.id);
                        this.searchQuery = item.official_name;
                    } else {
                        this.selectedId = '';
                        this.searchQuery = '';
                    }
                    this.open = false;
                    if (config.onSelect) {
                        config.onSelect(this.selectedId);
                    }
                },

                clear() {
                    this.selectLocation(null);
                },

                scrollToHighlighted() {
                    this.$nextTick(() => {
                        const el = this.$refs.list?.children[this.highlightedIndex];
                        if (el) {
                            el.scrollIntoView({ block: 'nearest' });
                        }
                    });
                }
            };
        }

        function itineraryBuilder(config) {
            return {
                calculateDistanceUrl: config.calculateDistanceUrl,
                csrfToken: config.csrfToken,
                locationsList: config.locationsList || [],
                legs: (config.initialLegs || []).map(l => ({
                    origin_location_id: l.origin_location_id ? String(l.origin_location_id) : '',
                    starting_point_location_id: l.starting_point_location_id ? String(l.starting_point_location_id) : '',
                    destination_location_id: l.destination_location_id ? String(l.destination_location_id) : '',
                    distance_origin_to_start: l.distance_origin_to_start ?? '',
                    distance_start_to_dest: l.distance_start_to_dest ?? '',
                    total_distance: l.total_distance ?? '',
                    duration_origin_to_start_minutes: l.duration_origin_to_start_minutes ?? '',
                    duration_start_to_dest_minutes: l.duration_start_to_dest_minutes ?? '',
                    total_duration_minutes: l.total_duration_minutes ?? '',
                    routing_source: l.routing_source || 'manual',
                    purpose: l.purpose || '',
                    is_calculating: false,
                    calc_error: null,
                    calc_success: (l.routing_source === 'osrm' && l.total_distance)
                })),

                addLeg() {
                    this.legs.push({
                        origin_location_id: '',
                        starting_point_location_id: '',
                        destination_location_id: '',
                        distance_origin_to_start: '',
                        distance_start_to_dest: '',
                        total_distance: '',
                        duration_origin_to_start_minutes: '',
                        duration_start_to_dest_minutes: '',
                        total_duration_minutes: '',
                        routing_source: 'manual',
                        purpose: '',
                        is_calculating: false,
                        calc_error: null,
                        calc_success: false
                    });
                },

                copyPreviousDestination(index) {
                    if (index > 0 && this.legs[index - 1].destination_location_id) {
                        this.legs[index].origin_location_id = this.legs[index - 1].destination_location_id;
                        this.onLocationChange(index);
                    }
                },

                removeLeg(index) {
                    if (this.legs.length > 1) {
                        this.legs.splice(index, 1);
                    }
                },

                onLocationChange(index) {
                    const leg = this.legs[index];
                    if (leg.origin_location_id && leg.starting_point_location_id && leg.destination_location_id) {
                        this.calculateLeg(index);
                    }
                },

                async calculateLeg(index) {
                    const leg = this.legs[index];
                    if (!leg.origin_location_id || !leg.starting_point_location_id || !leg.destination_location_id) {
                        return;
                    }

                    leg.is_calculating = true;
                    leg.calc_error = null;
                    leg.calc_success = false;

                    try {
                        const response = await fetch(this.calculateDistanceUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                origin_id: leg.origin_location_id,
                                waypoint_id: leg.starting_point_location_id,
                                destination_id: leg.destination_location_id
                            })
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            if (data.source === 'osrm' && data.total !== null) {
                                leg.distance_origin_to_start = data.origin_to_start !== null ? data.origin_to_start.toFixed(2) : '';
                                leg.distance_start_to_dest = data.start_to_dest !== null ? data.start_to_dest.toFixed(2) : '';
                                leg.total_distance = data.total !== null ? data.total.toFixed(2) : '';
                                leg.duration_origin_to_start_minutes = data.duration_origin_to_start_minutes ?? '';
                                leg.duration_start_to_dest_minutes = data.duration_start_to_dest_minutes ?? '';
                                leg.total_duration_minutes = data.total_duration_minutes ?? '';
                                leg.routing_source = 'osrm';
                                leg.calc_success = true;
                            } else {
                                leg.routing_source = 'manual';
                                leg.duration_origin_to_start_minutes = '';
                                leg.duration_start_to_dest_minutes = '';
                                leg.total_duration_minutes = '';
                                leg.calc_error = '⚠ Automatic routing unavailable for this road sequence. Please enter manual distance.';
                            }
                        } else {
                            leg.routing_source = 'manual';
                            leg.duration_origin_to_start_minutes = '';
                            leg.duration_start_to_dest_minutes = '';
                            leg.total_duration_minutes = '';
                            leg.calc_error = data.message || '⚠ Automatic routing unavailable. Please enter manual distance.';
                        }
                    } catch (err) {
                        leg.routing_source = 'manual';
                        leg.duration_origin_to_start_minutes = '';
                        leg.duration_start_to_dest_minutes = '';
                        leg.total_duration_minutes = '';
                        leg.calc_error = '⚠ Could not connect to routing service. Please enter manual distance.';
                    } finally {
                        leg.is_calculating = false;
                    }
                },

                onManualDistanceChange(index) {
                    const leg = this.legs[index];
                    const d1 = parseFloat(leg.distance_origin_to_start) || 0;
                    const d2 = parseFloat(leg.distance_start_to_dest) || 0;

                    if (d1 > 0 || d2 > 0) {
                        leg.total_distance = (Math.round((d1 + d2) * 100) / 100).toFixed(2);
                    } else {
                        leg.total_distance = '';
                    }

                    leg.duration_origin_to_start_minutes = '';
                    leg.duration_start_to_dest_minutes = '';
                    leg.total_duration_minutes = '';
                    leg.routing_source = 'manual';
                    leg.calc_success = false;
                },

                formatDuration(minutes) {
                    if (minutes === null || minutes === undefined || minutes === '' || isNaN(minutes) || minutes < 0) {
                        return '—';
                    }
                    const mins = parseInt(minutes, 10);
                    const h = Math.floor(mins / 60);
                    const m = mins % 60;
                    if (h === 0) return m + 'm';
                    if (m === 0) return h + 'h';
                    return h + 'h ' + m + 'm';
                },

                grandTotalDistance() {
                    let sum = 0;
                    for (const leg of this.legs) {
                        const total = parseFloat(leg.total_distance) || 0;
                        sum += total;
                    }
                    return (Math.round(sum * 100) / 100).toFixed(2);
                },

                grandTotalDuration() {
                    let sum = 0;
                    let hasAny = false;
                    for (const leg of this.legs) {
                        if (leg.total_duration_minutes !== null && leg.total_duration_minutes !== undefined && leg.total_duration_minutes !== '') {
                            const mins = parseInt(leg.total_duration_minutes, 10);
                            if (!isNaN(mins) && mins > 0) {
                                sum += mins;
                                hasAny = true;
                            }
                        }
                    }
                    return hasAny ? this.formatDuration(sum) : '—';
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
