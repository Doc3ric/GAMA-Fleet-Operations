<x-app-layout>
    @section('page-title', 'Location Directory Map')
    @section('breadcrumb')
        <a href="{{ route('locations.index') }}" class="hover:underline">Location Directory</a> &bull; Map Overview
    @endsection

    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #directory-map {
                height: 650px;
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

    <div class="space-y-4">

        {{-- Top Toolbar --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-1">
            <div class="flex items-center gap-3">
                <a href="{{ route('locations.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Table View
                </a>
                <div>
                    <h2 class="text-xl font-bold text-slate-800">Operational Locations Map</h2>
                    <p class="text-xs text-slate-500">Interactive geographic overview of all registered company facilities and destination waypoints</p>
                </div>
            </div>

            {{-- Filter Form --}}
            <form method="GET" action="{{ route('locations.map') }}" class="flex flex-wrap items-center gap-2">
                <select name="type" class="py-1.5 px-3 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Types</option>
                    @foreach($types as $typeOption)
                        <option value="{{ $typeOption }}" {{ ($filters['type'] ?? '') === $typeOption ? 'selected' : '' }}>
                            {{ $typeOption }}
                        </option>
                    @endforeach
                </select>

                <select name="status" class="py-1.5 px-3 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $statusOption)
                        <option value="{{ $statusOption }}" {{ ($filters['status'] ?? '') === $statusOption ? 'selected' : '' }}>
                            {{ $statusOption }}
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="px-3.5 py-1.5 bg-slate-800 text-white text-xs font-bold rounded-xl hover:bg-slate-900 shadow-xs">
                    Filter
                </button>
                @if(!empty(array_filter($filters ?? [])))
                    <a href="{{ route('locations.map') }}" class="px-2.5 py-1.5 bg-slate-100 text-slate-600 text-xs font-semibold rounded-xl hover:bg-slate-200">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        {{-- Map Container Card --}}
        <div class="rounded-2xl bg-white p-4 shadow-sm border border-slate-200 space-y-3">
            <div class="flex items-center justify-between text-xs text-slate-500 px-1">
                <span class="font-bold text-slate-800">{{ $locations->count() }} Pinned Locations</span>
                <span>Click any pin to inspect facility details and aliases</span>
            </div>

            <div id="directory-map" class="border border-slate-200 shadow-inner"></div>
        </div>

    </div>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const mapEl = document.getElementById('directory-map');
                if (!mapEl) return;

                const locationsData = @json($locations);

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

                // Initialize map centered at average or default Philippines center
                const map = L.map('directory-map', {
                    center: [14.599512, 120.984222],
                    zoom: 10,
                    layers: [googleHybrid] // Default to Google Satellite / Hybrid
                });

                const baseLayers = {
                    "🛰️ Google Satellite": googleHybrid,
                    "🗺️ Google Streets": googleStreets,
                    "🗺️ OpenStreetMap": osmLayer,
                    "🛰️ Esri Satellite": esriSatellite
                };
                L.control.layers(baseLayers, null, { position: 'topright' }).addTo(map);

                const bounds = [];

                locationsData.forEach(loc => {
                    const lat = parseFloat(loc.latitude);
                    const lng = parseFloat(loc.longitude);
                    if (isNaN(lat) || isNaN(lng)) return;

                    bounds.push([lat, lng]);

                    const marker = L.marker([lat, lng]).addTo(map);

                    const aliases = (loc.aliases || []).map(a => a.alias).slice(0, 4).join(', ');

                    const popupContent = `
                        <div style="font-family: inherit; min-width: 180px;">
                            <span style="font-size: 10px; font-weight: 800; color: #2563eb; text-transform: uppercase;">${loc.code} &bull; ${loc.type}</span>
                            <span style="font-weight: 800; font-size: 13px; color: #0f172a; display: block; margin-top: 2px;">${loc.official_name}</span>
                            <p style="font-size: 11px; color: #475569; margin: 4px 0 6px 0;">${loc.address}</p>
                            ${aliases ? `<p style="font-size: 10px; color: #64748b; margin: 0 0 6px 0;"><strong>Aliases:</strong> ${aliases}</p>` : ''}
                            <div style="border-top: 1px solid #e2e8f0; padding-top: 6px; text-align: right;">
                                <a href="/locations/${loc.id}" style="color: #2563eb; font-weight: 700; font-size: 11px; text-decoration: none;">View Details &rarr;</a>
                            </div>
                        </div>
                    `;

                    marker.bindPopup(popupContent);
                });

                if (bounds.length > 0) {
                    map.fitBounds(bounds, { padding: [40, 40] });
                }
            });
        </script>
    @endpush
</x-app-layout>
