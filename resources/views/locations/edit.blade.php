<x-app-layout>
    @section('page-title', 'Edit Location')
    @section('breadcrumb')
        <a href="{{ route('locations.index') }}" class="hover:underline">Location Directory</a> &bull;
        <a href="{{ route('locations.show', $location) }}" class="hover:underline">{{ $location->code }}</a> &bull; Edit
    @endsection

    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #picker-map {
                height: 480px;
                width: 100%;
                z-index: 10;
                border-radius: 1rem;
            }
        </style>
    @endpush

    <div class="max-w-7xl mx-auto space-y-6" x-data="locationForm({
        initialLat: '{{ old('latitude', $location->latitude) }}',
        initialLng: '{{ old('longitude', $location->longitude) }}',
        initialAliases: {{ json_encode(old('aliases', $location->aliases->pluck('alias')->toArray())) }}
    })">

        {{-- Top Bar --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('locations.show', $location) }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Back to Location Details
                </a>
                <h2 class="text-lg font-bold text-slate-900">Edit Location: {{ $location->official_name }}</h2>
            </div>
        </div>

        @if($errors->any())
            <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-xs text-rose-800 space-y-1">
                <div class="font-bold flex items-center gap-1.5 text-rose-900">
                    <svg class="h-4 w-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    Please fix the following validation errors:
                </div>
                <ul class="list-disc list-inside space-y-0.5 ml-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('locations.update', $location) }}" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            @csrf
            @method('PUT')

            {{-- Left Column: Master Form (7 cols) --}}
            <div class="lg:col-span-7 space-y-5">

                {{-- Primary Identifiers Card --}}
                <div class="rounded-2xl bg-white p-5 shadow-xs border border-slate-200 space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-2">
                        Location Identification
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        {{-- Location Code --}}
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                Location Code <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="code" value="{{ old('code', $location->code) }}" required placeholder="e.g. G2, CWH, GHO"
                                   class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold uppercase text-slate-900 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                            <p class="text-[10px] text-slate-400 mt-1">Short unique master code</p>
                        </div>

                        {{-- Location Type --}}
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                Location Type <span class="text-rose-500">*</span>
                            </label>
                            <select name="type" required
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                                @foreach($types as $typeOption)
                                    <option value="{{ $typeOption }}" {{ old('type', $location->type) === $typeOption ? 'selected' : '' }}>
                                        {{ $typeOption }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Status --}}
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                Status <span class="text-rose-500">*</span>
                            </label>
                            <select name="status" required
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                                @foreach($statuses as $statusOption)
                                    <option value="{{ $statusOption }}" {{ old('status', $location->status) === $statusOption ? 'selected' : '' }}>
                                        {{ $statusOption }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Official Location Name --}}
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                            Official Location Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="official_name" value="{{ old('official_name', $location->official_name) }}" required placeholder="e.g. GAMA FARM 2, CENTRAL WAREHOUSE"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    </div>
                </div>

                {{-- Multiple Location Aliases Card (Requirement 5) --}}
                <div class="rounded-2xl bg-white p-5 shadow-xs border border-slate-200 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">
                                Location Aliases & Driver Terminology
                            </h3>
                            <p class="text-[11px] text-slate-500">Informal names, abbreviations, or common driver slang resolving to this location</p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-blue-50 border border-blue-200 px-2 py-0.5 text-[10px] font-bold text-blue-700"
                              x-text="aliases.length + ' Registered'">
                        </span>
                    </div>

                    {{-- Alias Input Toolbar --}}
                    <div class="flex gap-2">
                        <input type="text" x-model="newAlias" @keydown.enter.prevent="addAlias()"
                               placeholder="e.g. FARM 2, GAMA 2, G2 FARM, BODEGA"
                               class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                        <button type="button" @click="addAlias()"
                                class="rounded-xl bg-slate-800 px-4 py-2 text-xs font-bold text-white hover:bg-slate-900 transition-colors shadow-xs">
                            + Add Alias
                        </button>
                    </div>

                    {{-- Alias Tag Chips --}}
                    <div class="flex flex-wrap gap-1.5 min-h-[36px] p-2.5 rounded-xl border border-dashed border-slate-200 bg-slate-50/50">
                        <template x-for="(alias, index) in aliases" :key="index">
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-800 shadow-2xs">
                                <span x-text="alias"></span>
                                <input type="hidden" name="aliases[]" :value="alias">
                                <button type="button" @click="removeAlias(index)" class="text-slate-400 hover:text-rose-600 font-bold ml-1">&times;</button>
                            </span>
                        </template>
                        <template x-if="aliases.length === 0">
                            <span class="text-slate-400 text-xs italic self-center">No aliases registered.</span>
                        </template>
                    </div>
                </div>

                {{-- Address Information Card --}}
                <div class="rounded-2xl bg-white p-5 shadow-xs border border-slate-200 space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-2">
                        Address & Geography
                    </h3>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                            Full Street Address <span class="text-rose-500">*</span>
                        </label>
                        <textarea name="address" rows="2" required placeholder="Street address, building, km marker..."
                                  class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">{{ old('address', $location->address) }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Barangay</label>
                            <input type="text" name="barangay" value="{{ old('barangay', $location->barangay) }}"
                                   class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Municipality / City</label>
                            <input type="text" name="municipality" value="{{ old('municipality', $location->municipality) }}"
                                   class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Province</label>
                            <input type="text" name="province" value="{{ old('province', $location->province) }}"
                                   class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                        </div>
                    </div>

                    {{-- Area Consultant & Contact Number --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-slate-100">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Area Consultant</label>
                            <input type="text" name="area_consultant" value="{{ old('area_consultant', $location->area_consultant) }}" placeholder="e.g. Juan Dela Cruz"
                                   class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                            <p class="text-[10px] text-slate-400 mt-1">Sales or area consultant responsible for this location (optional)</p>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Contact Number</label>
                            <input type="text" name="contact_number" value="{{ old('contact_number', $location->contact_number) }}" placeholder="e.g. 09XX-XXX-XXXX"
                                   class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                            <p class="text-[10px] text-slate-400 mt-1">Primary contact number for this location (optional)</p>
                        </div>
                    </div>
                </div>

                {{-- Media & Notes Card --}}
                <div class="rounded-2xl bg-white p-5 shadow-xs border border-slate-200 space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-2">
                        Location Image & Notes
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Image Upload --}}
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                Location Photo / Entrance Sign
                            </label>
                            <input type="file" name="image" accept="image/*" @change="previewImage($event)"
                                   class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700">

                            @if($location->image_path)
                                <div class="mt-2.5 relative rounded-xl overflow-hidden border border-slate-200 max-h-40" x-show="!imagePreview">
                                    <img src="{{ $location->image_url }}" alt="{{ $location->official_name }}" class="w-full h-40 object-cover">
                                    <span class="absolute bottom-1 right-1 rounded bg-slate-900/80 text-white text-[10px] px-1.5 py-0.5">Current Photo</span>
                                </div>
                            @endif

                            <template x-if="imagePreview">
                                <div class="mt-2.5 relative rounded-xl overflow-hidden border border-slate-200 max-h-40">
                                    <img :src="imagePreview" alt="New Preview" class="w-full h-40 object-cover">
                                    <span class="absolute bottom-1 right-1 rounded bg-blue-600 text-white text-[10px] px-1.5 py-0.5">New Replacement</span>
                                </div>
                            </template>
                        </div>

                        {{-- Notes --}}
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Description / Operational Notes</label>
                            <textarea name="notes" rows="4" placeholder="Access guidelines, landmarks, gate hours, contact personnel..."
                                      class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">{{ old('notes', $location->notes) }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Action Submit Buttons --}}
                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('locations.show', $location) }}"
                       class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="rounded-xl bg-blue-600 px-6 py-2.5 text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition-colors cursor-pointer">
                        Update Location Record
                    </button>
                </div>

            </div>

            {{-- Right Column: Interactive Leaflet Map Picker (5 cols) --}}
            <div class="lg:col-span-5 space-y-4">
                <div class="rounded-2xl bg-white p-5 shadow-xs border border-slate-200 space-y-3 sticky top-20">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <div class="flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-blue-700 text-xs font-bold">
                                📍
                            </span>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                Exact Geographic Location
                            </h3>
                        </div>
                        <span class="text-[10px] text-slate-400">Search, paste, or click pin</span>
                    </div>

                    {{-- Search Toolbar & Google Maps Integration --}}
                    <div class="space-y-2">
                        <div class="relative">
                            <div class="flex items-center gap-1.5">
                                <div class="relative flex-1">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <input type="text"
                                           x-model="searchQuery"
                                           @keydown.enter.prevent="executeSearch()"
                                           @paste="handlePaste($event)"
                                           placeholder="Search place, city, or paste Google Maps link / coords..."
                                           class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-8 py-2 text-xs font-medium text-slate-800 placeholder-slate-400 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                                    <button type="button" x-show="searchQuery" @click="searchQuery = ''; searchResults = []"
                                            class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-400 hover:text-slate-600">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <button type="button" @click="executeSearch()" :disabled="searching"
                                        class="rounded-xl bg-blue-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-blue-700 transition-colors shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                                    <template x-if="searching">
                                        <svg class="h-3.5 w-3.5 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                    </template>
                                    <span x-text="searching ? 'Searching...' : 'Search'"></span>
                                </button>
                            </div>

                            {{-- Autocomplete Dropdown --}}
                            <div x-show="searchResults.length > 0" @click.away="searchResults = []"
                                 class="absolute z-50 mt-1.5 max-h-72 w-full overflow-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl space-y-1">
                                <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 flex items-center justify-between">
                                    <span>Matching Places (<span x-text="searchResults.length"></span>)</span>
                                    <span class="text-[9px] text-blue-600 font-semibold">Click to select & pin</span>
                                </div>
                                <template x-for="(item, idx) in searchResults" :key="idx">
                                    <button type="button" @click="selectSearchResult(item)"
                                            class="w-full text-left p-2.5 rounded-lg hover:bg-blue-50 transition-colors border border-transparent hover:border-blue-100 group">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="font-bold text-xs text-slate-800 group-hover:text-blue-700 flex items-center gap-1.5">
                                                <span>📍</span>
                                                <span x-text="item.title"></span>
                                            </div>
                                            <span class="shrink-0 text-[9px] font-bold px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-600 group-hover:bg-blue-100 group-hover:text-blue-800"
                                                  x-text="item.source_label">
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-slate-500 mt-0.5 truncate" x-text="item.subtitle" x-show="item.subtitle"></div>
                                        <div class="text-[10px] font-mono text-slate-400 mt-1 flex items-center gap-2">
                                            <span>Lat: <strong x-text="item.latitude.toFixed(6)"></strong></span>
                                            <span>Lng: <strong x-text="item.longitude.toFixed(6)"></strong></span>
                                        </div>
                                    </button>
                                </template>
                            </div>

                            {{-- No Results Message --}}
                            <div x-show="hasSearched && searchResults.length === 0 && !searching" @click.away="hasSearched = false"
                                 class="absolute z-50 mt-1.5 w-full rounded-xl border border-slate-200 bg-white p-3 shadow-xl text-center">
                                <p class="text-xs text-slate-600 font-medium">No results found for "<span x-text="searchQuery"></span>".</p>
                                <p class="text-[11px] text-slate-400 mt-1">Try a landmark, town, or click "Find on Google Maps" below to copy exact coordinates.</p>
                            </div>
                        </div>

                        {{-- Google Maps Assistant Bar --}}
                        <div class="flex items-center justify-between gap-2 bg-slate-50 p-2 rounded-xl border border-slate-200 text-xs">
                            <div class="flex items-center gap-1.5 text-slate-600 text-[11px] min-w-0">
                                <span class="text-emerald-600 font-bold shrink-0">💡 Tip:</span>
                                <span class="truncate">Paste any Google Maps link or coordinates</span>
                            </div>
                            <a :href="'https://www.google.com/maps/search/' + encodeURIComponent(searchQuery || 'Philippines')"
                               target="_blank"
                               class="shrink-0 inline-flex items-center gap-1 rounded-lg bg-white border border-slate-200 px-2 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-100 hover:text-blue-600 transition-colors shadow-2xs">
                                <svg class="h-3 w-3 text-red-500" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                </svg>
                                <span>Find on Google Maps &rarr;</span>
                            </a>
                        </div>
                    </div>

                    {{-- Selected Result Notice & Address Auto-fill --}}
                    <div x-show="selectedResultNotice" class="bg-blue-50 border border-blue-200 rounded-xl p-2.5 text-xs text-blue-900 flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-bold truncate">Pin updated!</div>
                            <div class="text-[11px] text-blue-700 truncate" x-text="lastSelectedResult?.title"></div>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button type="button" @click="fillAddressFields()" x-show="lastSelectedResult?.address || lastSelectedResult?.municipality"
                                    class="rounded-lg bg-blue-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-blue-700 transition-colors shadow-2xs cursor-pointer">
                                Auto-fill Address
                            </button>
                            <button type="button" @click="selectedResultNotice = false" class="text-blue-400 hover:text-blue-700 font-bold px-1">&times;</button>
                        </div>
                    </div>

                    {{-- Interactive Coordinate Inputs --}}
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-0.5">
                                Latitude <span class="text-rose-500">*</span>
                            </label>
                            <input type="number" step="any" name="latitude" x-model="lat" @input="updateMarkerFromInput()" required
                                   class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-mono font-bold text-slate-800 focus:bg-white focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-0.5">
                                Longitude <span class="text-rose-500">*</span>
                            </label>
                            <input type="number" step="any" name="longitude" x-model="lng" @input="updateMarkerFromInput()" required
                                   class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-mono font-bold text-slate-800 focus:bg-white focus:border-blue-500 outline-none">
                        </div>
                    </div>

                    {{-- Leaflet Map Container --}}
                    <div id="picker-map" class="border border-slate-200 shadow-inner rounded-xl overflow-hidden"></div>

                    <div class="flex items-center justify-between text-[11px] text-slate-500 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                        <span><strong>Controls:</strong> Drag pin or click map to reposition.</span>
                        <span class="text-[10px] text-slate-400">Toggle Satellite at top-right</span>
                    </div>
                </div>
            </div>

        </form>

    </div>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            function locationForm(config) {
                return {
                    lat: config.initialLat,
                    lng: config.initialLng,
                    newAlias: '',
                    aliases: config.initialAliases || [],
                    imagePreview: null,
                    map: null,
                    marker: null,

                    // Search state
                    searchQuery: '',
                    searching: false,
                    searchResults: [],
                    hasSearched: false,
                    selectedResultNotice: false,
                    lastSelectedResult: null,

                    init() {
                        this.$nextTick(() => {
                            this.initMap();
                        });
                    },

                    addAlias() {
                        const trimmed = this.newAlias.trim();
                        if (trimmed && !this.aliases.includes(trimmed)) {
                            this.aliases.push(trimmed);
                            this.newAlias = '';
                        }
                    },

                    removeAlias(index) {
                        this.aliases.splice(index, 1);
                    },

                    previewImage(event) {
                        const file = event.target.files[0];
                        if (file) {
                            this.imagePreview = URL.createObjectURL(file);
                        }
                    },

                    async executeSearch() {
                        const q = this.searchQuery.trim();
                        if (!q) return;

                        this.searching = true;
                        this.hasSearched = true;
                        this.searchResults = [];

                        try {
                            const res = await fetch(`{{ route('locations.searchPlaces') }}?q=` + encodeURIComponent(q));
                            const data = await res.json();
                            if (data.success && Array.isArray(data.results)) {
                                this.searchResults = data.results;
                                if (this.searchResults.length === 1 && this.searchResults[0].is_exact) {
                                    this.selectSearchResult(this.searchResults[0]);
                                }
                            }
                        } catch (err) {
                            console.error('Search error:', err);
                        } finally {
                            this.searching = false;
                        }
                    },

                    async handlePaste(e) {
                        const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                        if (!pastedText) return;

                        setTimeout(async () => {
                            try {
                                const res = await fetch(`{{ route('locations.resolveCoordinates') }}`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify({ input: pastedText })
                                });
                                const data = await res.json();
                                if (data.success && data.latitude && data.longitude) {
                                    this.lat = parseFloat(data.latitude).toFixed(7);
                                    this.lng = parseFloat(data.longitude).toFixed(7);
                                    this.updateMarkerFromInput(16);
                                    this.lastSelectedResult = {
                                        title: 'Pasted Location (' + this.lat + ', ' + this.lng + ')',
                                        latitude: parseFloat(data.latitude),
                                        longitude: parseFloat(data.longitude)
                                    };
                                    this.selectedResultNotice = true;
                                    this.searchResults = [];
                                }
                            } catch (err) {
                                // Fallback
                            }
                        }, 50);
                    },

                    selectSearchResult(item) {
                        this.lat = parseFloat(item.latitude).toFixed(7);
                        this.lng = parseFloat(item.longitude).toFixed(7);
                        this.updateMarkerFromInput(16);
                        this.lastSelectedResult = item;
                        this.selectedResultNotice = true;
                        this.searchResults = [];
                    },

                    fillAddressFields() {
                        if (!this.lastSelectedResult) return;
                        const item = this.lastSelectedResult;

                        const addressInput = document.querySelector('textarea[name="address"]');
                        const barangayInput = document.querySelector('input[name="barangay"]');
                        const muniInput = document.querySelector('input[name="municipality"]');
                        const provInput = document.querySelector('input[name="province"]');

                        if (addressInput && item.address && !addressInput.value) {
                            addressInput.value = item.address;
                        }
                        if (barangayInput && item.barangay) barangayInput.value = item.barangay;
                        if (muniInput && item.municipality) muniInput.value = item.municipality;
                        if (provInput && item.province) provInput.value = item.province;

                        this.selectedResultNotice = false;
                    },

                    initMap() {
                        const el = document.getElementById('picker-map');
                        if (!el) return;

                        let latNum = parseFloat(this.lat) || 14.599512;
                        let lngNum = parseFloat(this.lng) || 120.984222;

                        // Base tile layers
                        const googleHybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
                            maxZoom: 20,
                            attribution: '&copy; Google Maps Satellite'
                        });

                        const googleStreets = L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
                            maxZoom: 20,
                            attribution: '&copy; Google Maps'
                        });

                        const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>'
                        });

                        const esriSatellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                            maxZoom: 19,
                            attribution: '&copy; Esri World Imagery'
                        });

                        this.map = L.map('picker-map', {
                            center: [latNum, lngNum],
                            zoom: 15,
                            layers: [googleHybrid], // Default to Google Satellite / Hybrid
                            worldCopyJump: true
                        });

                        // Layer Switcher
                        const baseLayers = {
                            "🛰️ Google Satellite": googleHybrid,
                            "🗺️ Google Streets": googleStreets,
                            "🗺️ OpenStreetMap": osmLayer,
                            "🛰️ Esri Satellite": esriSatellite
                        };
                        L.control.layers(baseLayers, null, { position: 'topright' }).addTo(this.map);

                        this.marker = L.marker([latNum, lngNum], {
                            draggable: true
                        }).addTo(this.map);

                        this.marker.on('dragend', (e) => {
                            const pos = e.target.getLatLng().wrap();
                            this.lat = pos.lat.toFixed(7);
                            this.lng = pos.lng.toFixed(7);
                        });

                        this.map.on('click', (e) => {
                            const pos = e.latlng.wrap();
                            this.lat = pos.lat.toFixed(7);
                            this.lng = pos.lng.toFixed(7);
                            this.marker.setLatLng(pos);
                        });
                    },

                    updateMarkerFromInput(zoom = null) {
                        const latNum = parseFloat(this.lat);
                        let lngNum = parseFloat(this.lng);
                        if (!isNaN(latNum) && !isNaN(lngNum) && this.marker && this.map) {
                            if (lngNum > 180 || lngNum < -180) {
                                lngNum = ((lngNum + 180) % 360 + 360) % 360 - 180;
                                this.lng = lngNum.toFixed(7);
                            }
                            const newPos = [latNum, lngNum];
                            this.marker.setLatLng(newPos);
                            if (zoom) {
                                this.map.setView(newPos, zoom);
                            } else {
                                this.map.panTo(newPos);
                            }
                        }
                    }
                };
            }
        </script>
    @endpush
</x-app-layout>
