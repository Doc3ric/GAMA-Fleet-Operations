<x-app-layout>
    @section('page-title', $location->official_name)
    @section('page-subtitle', 'Code #' . $location->code)
    @section('breadcrumb')
        <a href="{{ route('locations.index') }}" class="hover:underline">Location Directory</a> &bull; {{ $location->code }}
    @endsection

    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #show-map {
                height: 400px;
                width: 100%;
                z-index: 10;
                border-radius: 1rem;
            }
            .leaflet-popup-content-wrapper {
                border-radius: 0.75rem;
                padding: 4px;
                box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            }
        </style>
    @endpush

    <div class="space-y-6" x-data="{
        showAddAliasModal: false,
        aliasInput: '',
        lightboxImage: null
    }">

        {{-- Top Action Header --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('locations.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Back to Locations
                </a>

                <div class="flex items-center gap-2">
                    <span class="inline-flex rounded-lg bg-slate-900 px-3 py-1 font-mono text-sm font-bold text-white shadow-xs">
                        {{ $location->code }}
                    </span>
                    <h2 class="text-xl font-bold text-slate-900">{{ $location->official_name }}</h2>
                    @if($location->isActive())
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-bold text-emerald-700">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            Active
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 border border-slate-200 px-3 py-1 text-xs font-bold text-slate-500">
                            <span class="h-2 w-2 rounded-full bg-slate-400"></span>
                            Inactive
                        </span>
                    @endif
                    <span class="inline-flex items-center rounded-lg bg-blue-50 border border-blue-200 px-2.5 py-1 text-xs font-semibold text-blue-700">
                        {{ $location->type }}
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('locations.edit', $location) }}"
                   class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 transition-colors shadow-sm">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                    Edit Location
                </a>
            </div>
        </div>

        {{-- Main Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            {{-- Left Column: Details & Aliases (6 cols) --}}
            <div class="lg:col-span-6 space-y-5">

                {{-- Master Data Card --}}
                <div class="rounded-2xl bg-white p-5 shadow-xs border border-slate-200 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Location Master Data</h3>
                        <span class="text-xs font-mono text-slate-400">ID #{{ $location->id }}</span>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div>
                            <span class="text-slate-400 block text-[10px] font-bold uppercase">Official Address</span>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $location->address }}</p>
                        </div>

                        <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100">
                            <div>
                                <span class="text-slate-400 block text-[10px] font-bold uppercase">Barangay</span>
                                <span class="text-slate-700 font-semibold">{{ $location->barangay ?: '—' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px] font-bold uppercase">Municipality / City</span>
                                <span class="text-slate-700 font-semibold">{{ $location->municipality ?: '—' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px] font-bold uppercase">Province</span>
                                <span class="text-slate-700 font-semibold">{{ $location->province ?: '—' }}</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 font-mono">
                            <div>
                                <span class="text-slate-400 block text-[10px] font-bold uppercase font-sans">Latitude</span>
                                <span class="text-slate-800 font-bold text-xs">{{ number_format($location->latitude, 7) }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block text-[10px] font-bold uppercase font-sans">Longitude</span>
                                <span class="text-slate-800 font-bold text-xs">{{ number_format($location->longitude, 7) }}</span>
                            </div>
                        </div>

                        @if($location->notes)
                            <div class="pt-2 border-t border-slate-100">
                                <span class="text-slate-400 block text-[10px] font-bold uppercase">Operational Notes</span>
                                <p class="text-slate-700 mt-0.5 leading-relaxed">{{ $location->notes }}</p>
                            </div>
                        @endif

                        @if($location->area_consultant || $location->contact_number)
                            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100">
                                <div>
                                    <span class="text-slate-400 block text-[10px] font-bold uppercase">Area Consultant</span>
                                    <span class="text-slate-700 font-semibold">{{ $location->area_consultant ?: '—' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px] font-bold uppercase">Contact Number</span>
                                    <span class="text-slate-700 font-semibold">{{ $location->contact_number ?: '—' }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Multiple Aliases Card (Requirement 5) --}}
                <div class="rounded-2xl bg-white p-5 shadow-xs border border-slate-200 space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">
                                Registered Aliases ({{ $location->aliases->count() }})
                            </h3>
                            <p class="text-[11px] text-slate-500">Driver destination terms that automatically resolve to this official location</p>
                        </div>
                        <button type="button" @click="showAddAliasModal = true"
                                class="inline-flex items-center gap-1 rounded-lg bg-blue-50 border border-blue-200 px-2.5 py-1 text-xs font-bold text-blue-700 hover:bg-blue-100 transition-colors cursor-pointer">
                            + Add Alias
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-1">
                        @forelse($location->aliases as $alias)
                            <span class="inline-flex items-center gap-2 rounded-xl bg-slate-50 border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-800 shadow-2xs">
                                <span>{{ $alias->alias }}</span>
                                <form method="POST" action="{{ route('locations.aliases.destroy', [$location, $alias]) }}" class="inline" onsubmit="return confirm('Remove alias \'{{ addslashes($alias->alias) }}\'?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-slate-400 hover:text-rose-600 font-bold ml-0.5 cursor-pointer">&times;</button>
                                </form>
                            </span>
                        @empty
                            <p class="text-slate-400 italic text-xs py-2">No aliases registered for this location yet. Click "+ Add Alias" to record driver terminology.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Photo / Image Card (Requirement 11) --}}
                <div class="rounded-2xl bg-white p-5 shadow-xs border border-slate-200 space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-2.5">
                        Location Photo / Visual Reference
                    </h3>

                    @if($location->image_path)
                        <div class="relative rounded-xl overflow-hidden border border-slate-200 cursor-pointer group" @click="lightboxImage = '{{ $location->image_url }}'">
                            <img src="{{ $location->image_url }}" alt="{{ $location->official_name }}" class="w-full h-56 object-cover group-hover:scale-105 transition-transform duration-200">
                            <div class="absolute inset-0 bg-slate-950/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <span class="rounded-xl bg-white/90 px-3 py-1.5 text-xs font-bold text-slate-900 shadow-md">Click to Enlarge</span>
                            </div>
                        </div>
                    @else
                        <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/70 p-8 text-center">
                            <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-2">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                            </div>
                            <p class="text-xs font-semibold text-slate-600">No Location Photo Uploaded</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">Edit this location to attach facility entrance or signage photos.</p>
                        </div>
                    @endif
                </div>

                {{-- Audit Trail Card --}}
                <div class="rounded-2xl bg-white p-5 shadow-xs border border-slate-200 text-xs space-y-2 text-slate-500">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-2">Audit & Metadata</h3>
                    <div class="grid grid-cols-2 gap-2 pt-1 text-[11px]">
                        <div>
                            <span class="text-slate-400 block font-semibold">CREATED BY</span>
                            <span class="text-slate-700 font-medium">{{ $location->creator?->name ?: 'System' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-semibold">CREATED AT</span>
                            <span class="text-slate-700 font-medium">{{ $location->created_at->format('M j, Y H:i') }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-semibold">LAST UPDATED BY</span>
                            <span class="text-slate-700 font-medium">{{ $location->updater?->name ?: 'System' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-semibold">LAST UPDATED AT</span>
                            <span class="text-slate-700 font-medium">{{ $location->updated_at->format('M j, Y H:i') }}</span>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Right Column: Interactive Map & Usage Reference (6 cols) --}}
            <div class="lg:col-span-6 space-y-5">

                {{-- Leaflet Map Card (Requirement 10) --}}
                <div class="rounded-2xl bg-white p-5 shadow-xs border border-slate-200 space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                        <div class="flex items-center gap-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">Exact Geographic Location</h3>
                            <span class="inline-flex items-center rounded-full bg-blue-50 border border-blue-200 px-2 py-0.5 text-[10px] font-bold text-blue-700">
                                GPS Fixed
                            </span>
                        </div>
                        <span class="font-mono text-xs text-slate-500">{{ number_format($location->latitude, 5) }}, {{ number_format($location->longitude, 5) }}</span>
                    </div>

                    <div id="show-map" class="border border-slate-200 shadow-inner"></div>

                    <div class="flex items-center justify-between text-xs text-slate-500 pt-1">
                        <span>Pinned at facility entrance / waypoint</span>
                        <a href="https://www.google.com/maps?q={{ $location->latitude }},{{ $location->longitude }}" target="_blank"
                           class="font-semibold text-blue-600 hover:underline inline-flex items-center gap-1">
                            Open in Google Maps &rarr;
                        </a>
                    </div>
                </div>

                {{-- Location Usage & Reference Card (Requirement 20) --}}
                <div class="rounded-2xl bg-white p-5 shadow-xs border border-slate-200 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                Itinerary Usage & Operational Intelligence
                            </h3>
                            <p class="text-[11px] text-slate-500">Historical trips referencing this location</p>
                        </div>
                        <span class="inline-flex items-center rounded-xl bg-slate-900 px-3 py-1 font-mono text-xs font-bold text-white shadow-xs">
                            {{ number_format($usageStats['total_trips']) }} {{ Str::plural('Trip', $usageStats['total_trips']) }}
                        </span>
                    </div>

                    {{-- Frequently used driver entries --}}
                    @if(!empty($usageStats['common_entries']))
                        <div>
                            <p class="text-[11px] font-bold uppercase text-slate-400 mb-1.5">Most Common Driver Entries</p>
                            <div class="space-y-1.5">
                                @foreach($usageStats['common_entries'] as $entryText => $count)
                                    <div class="flex items-center justify-between text-xs bg-slate-50 p-2 rounded-lg border border-slate-100">
                                        <span class="font-mono font-semibold text-slate-800 truncate" title="{{ $entryText }}">
                                            "{{ $entryText }}"
                                        </span>
                                        <span class="rounded bg-white px-2 py-0.5 font-mono text-[11px] font-bold text-blue-700 border border-slate-200">
                                            {{ $count }}x
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Recent Trips Table --}}
                    <div>
                        <p class="text-[11px] font-bold uppercase text-slate-400 mb-1.5">Recent Associated Trips</p>
                        @if($usageStats['recent_trips']->count() > 0)
                            <div class="overflow-hidden rounded-xl border border-slate-200">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                                        <tr>
                                            <th class="px-3 py-2">Trip</th>
                                            <th class="px-3 py-2">Date</th>
                                            <th class="px-3 py-2">Driver</th>
                                            <th class="px-3 py-2">Driver Entry</th>
                                            <th class="px-3 py-2 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($usageStats['recent_trips'] as $trip)
                                            <tr class="hover:bg-slate-50/70">
                                                <td class="px-3 py-2 font-mono font-bold text-slate-800">#{{ $trip->id }}</td>
                                                <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $trip->trip_date?->format('M j, Y') ?? 'N/A' }}</td>
                                                <td class="px-3 py-2 text-slate-800 font-medium whitespace-nowrap">{{ $trip->driver?->name ?? 'Unknown' }}</td>
                                                <td class="px-3 py-2 text-slate-600 truncate max-w-[120px]" title="{{ $trip->destination_address }}">
                                                    {{ $trip->destination_address }}
                                                </td>
                                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                                    <a href="{{ route('itineraries.show', $trip) }}" class="text-blue-600 font-bold hover:underline">
                                                        View &rarr;
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-xs text-slate-400 italic py-2">No driver trips recorded for this location yet.</p>
                        @endif
                    </div>
                </div>

            </div>

        </div>

        {{-- Add Alias Modal --}}
        <div x-show="showAddAliasModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="showAddAliasModal = false" class="w-full max-w-sm rounded-2xl bg-white shadow-2xl border border-slate-200 p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-800">Add New Alias</h3>
                    <button type="button" @click="showAddAliasModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>
                <form method="POST" action="{{ route('locations.aliases.store', $location) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Driver Entry / Alias Term</label>
                        <input type="text" name="alias" required placeholder="e.g. FARM 2, G2 FARM, GAMA2"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-800 focus:bg-white focus:border-blue-500 outline-none">
                        <p class="text-[11px] text-slate-500 mt-1">This term will automatically resolve to <strong>{{ $location->official_name }}</strong> in itineraries.</p>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="showAddAliasModal = false"
                                class="rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200">
                            Cancel
                        </button>
                        <button type="submit"
                                class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-sm">
                            Save Alias
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Lightbox Image Modal --}}
        <div x-show="lightboxImage !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm" @click="lightboxImage = null">
            <div class="max-w-3xl max-h-[90vh] p-2 bg-white rounded-2xl shadow-2xl overflow-hidden" @click.stop>
                <img :src="lightboxImage" alt="Location Photo" class="w-full h-auto max-h-[80vh] object-contain rounded-xl">
                <div class="p-3 text-right">
                    <button type="button" @click="lightboxImage = null" class="rounded-xl bg-slate-800 px-4 py-1.5 text-xs font-bold text-white hover:bg-slate-900">Close</button>
                </div>
            </div>
        </div>

    </div>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const mapEl = document.getElementById('show-map');
                if (!mapEl) return;

                const lat = {{ $location->latitude }};
                const lng = {{ $location->longitude }};

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
                    attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                });

                const esriSatellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    maxZoom: 19,
                    attribution: '&copy; Esri World Imagery'
                });

                const map = L.map('show-map', {
                    center: [lat, lng],
                    zoom: 15,
                    layers: [googleHybrid] // Default to Google Satellite / Hybrid
                });

                const baseLayers = {
                    "🛰️ Google Satellite": googleHybrid,
                    "🗺️ Google Streets": googleStreets,
                    "🗺️ OpenStreetMap": osmLayer,
                    "🛰️ Esri Satellite": esriSatellite
                };
                L.control.layers(baseLayers, null, { position: 'topright' }).addTo(map);

                const marker = L.marker([lat, lng]).addTo(map);
                marker.bindPopup(`
                    <div style="font-family: inherit;">
                        <span style="font-weight: 800; font-size: 13px; color: #0f172a; display: block;">{{ addslashes($location->official_name) }}</span>
                        <span style="font-size: 11px; color: #2563eb; font-weight: 700;">Code: {{ $location->code }} &bull; {{ $location->type }}</span>
                        <p style="font-size: 11px; color: #475569; margin: 4px 0 0 0;">{{ addslashes($location->address) }}</p>
                    </div>
                `).openPopup();
            });
        </script>
    @endpush
</x-app-layout>
