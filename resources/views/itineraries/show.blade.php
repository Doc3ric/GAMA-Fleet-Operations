<x-app-layout>
    @section('page-title', 'Trip Details')
    @section('page-subtitle', 'Ticket #' . $trip->id)
    @section('breadcrumb')
        <a href="{{ route('itineraries.index') }}" class="hover:underline">Driver Itinerary</a> &bull; Trip #{{ $trip->id }}
    @endsection

    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #trip-map {
                height: 460px;
                width: 100%;
                z-index: 10;
                border-radius: 1rem;
            }
            .leaflet-popup-content-wrapper {
                border-radius: 0.75rem;
                padding: 4px;
                box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            }
            .leaflet-popup-content {
                font-family: inherit;
                font-size: 12px;
                line-height: 1.4;
                margin: 8px 12px;
            }
        </style>
    @endpush

    <div class="space-y-6" x-data="{ showResolveModal: false, selectedLocationId: '{{ $recognition['suggested_location']?->id ?? '' }}' }">

        {{-- Top Bar / Actions --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('itineraries.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors shadow-sm">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Back to Itineraries
                </a>

                <div class="flex items-center gap-2">
                    <span class="text-xl font-bold text-slate-900">Trip #{{ $trip->id }}</span>
                    @if($trip->isCompleted())
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-bold text-emerald-700">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            Completed
                        </span>
                    @elseif($trip->isInProgress())
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200 px-3 py-1 text-xs font-bold text-amber-700">
                            <span class="h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>
                            In Progress
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 border border-rose-200 px-3 py-1 text-xs font-bold text-rose-700">
                            <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                            Cancelled
                        </span>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-500 font-medium">Trip Date:</span>
                <span class="rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-800">
                    {{ $trip->trip_date?->format('l, F j, Y') ?? 'N/A' }}
                </span>
            </div>
        </div>

        {{-- Main Grid: Details Left, Map Right --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            {{-- Left Column: Details & Audit (5 cols) --}}
            <div class="lg:col-span-5 space-y-5">

                {{-- Driver & Vehicle Summary Card --}}
                <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200 space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Assignment Profile</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                        {{-- Driver --}}
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 font-bold text-sm">
                                {{ strtoupper(substr($trip->driver?->name ?? 'D', 0, 2)) }}
                            </div>
                            <div>
                                <p class="text-[11px] font-bold uppercase text-slate-400">Driver</p>
                                <p class="text-sm font-bold text-slate-900">{{ $trip->driver?->name ?? 'Unassigned Driver' }}</p>
                                <p class="text-xs text-slate-500">{{ $trip->driver?->email ?? 'No email' }}</p>
                            </div>
                        </div>

                        {{-- Vehicle --}}
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white font-mono font-bold text-xs">
                                {{ $trip->vehicle?->equipment_code ?? 'VEH' }}
                            </div>
                            <div>
                                <p class="text-[11px] font-bold uppercase text-slate-400">Vehicle</p>
                                <p class="text-sm font-bold text-slate-900">{{ $trip->vehicle?->plate_number ?? 'No Plate' }}</p>
                                <p class="text-xs text-slate-500">{{ $trip->vehicle?->model ?? 'Model N/A' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Departure (Origin) Card --}}
                <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold">A</span>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-800">Departure / Origin</h3>
                        </div>
                        <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-mono font-bold text-slate-800">
                            {{ $trip->time_in ?? 'N/A' }}
                        </span>
                    </div>

                    <div class="pt-1">
                        <p class="text-[11px] font-bold uppercase text-slate-400">Street Address</p>
                        @if($trip->origin_address)
                            <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $trip->origin_address }}</p>
                        @else
                            <p class="text-xs font-medium text-slate-500 italic mt-0.5">Coordinates available (Address not geocoded)</p>
                        @endif
                    </div>

                    <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100 text-xs">
                        <div>
                            <span class="text-[10px] text-slate-400 block font-semibold">LATITUDE</span>
                            <span class="font-mono font-bold text-slate-700">{{ $trip->origin_latitude ? number_format($trip->origin_latitude, 6) : 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 block font-semibold">LONGITUDE</span>
                            <span class="font-mono font-bold text-slate-700">{{ $trip->origin_longitude ? number_format($trip->origin_longitude, 6) : 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 block font-semibold">ACCURACY</span>
                            <span class="font-mono font-bold text-slate-700">{{ $trip->origin_accuracy ? '±' . round($trip->origin_accuracy) . 'm' : '—' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Arrival (Destination) Card --}}
                <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-100 text-rose-700 text-xs font-bold">B</span>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-rose-800">Arrival / Destination</h3>
                        </div>
                        <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-mono font-bold text-slate-800">
                            {{ $trip->time_out ?? ($trip->isInProgress() ? 'In Transit' : 'N/A') }}
                        </span>
                    </div>

                    {{-- Raw Driver Destination Entry (Preserved Exactly) --}}
                    <div class="pt-1">
                        <p class="text-[11px] font-bold uppercase text-slate-400">Driver Destination Entry</p>
                        @if($trip->destination_address)
                            <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $trip->destination_address }}</p>
                        @elseif($trip->isInProgress())
                            <p class="text-xs font-medium text-amber-600 italic mt-0.5">Trip is currently in progress on the road.</p>
                        @elseif($trip->isCancelled())
                            <p class="text-xs font-medium text-slate-500 italic mt-0.5">Trip was cancelled before arrival.</p>
                        @else
                            <p class="text-xs font-medium text-slate-500 italic mt-0.5">Coordinates available (Address not geocoded)</p>
                        @endif
                    </div>

                    {{-- Location Directory Recognition Section --}}
                    @if($trip->destination_address)
                        <div class="pt-2 border-t border-slate-100 text-xs">
                            <span class="text-[10px] font-bold uppercase text-slate-400 block mb-1">Directory Recognition</span>
                            @if($recognition['status'] === \App\Services\LocationRecognitionService::STATUS_RECOGNIZED && $recognition['location'])
                                <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3 space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-1.5">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                RECOGNIZED
                                            </span>
                                            <span class="font-mono text-xs font-bold text-slate-800">[{{ $recognition['location']->code }}]</span>
                                        </div>
                                        <a href="{{ route('locations.show', $recognition['location']) }}"
                                           class="text-[11px] font-bold text-emerald-700 hover:underline inline-flex items-center gap-0.5">
                                            View Directory &rarr;
                                        </a>
                                    </div>
                                    <div class="font-bold text-slate-900 text-sm">{{ $recognition['location']->official_name }}</div>
                                    <p class="text-[11px] text-slate-600 leading-tight">{{ $recognition['location']->address }}</p>
                                </div>
                            @elseif($recognition['status'] === \App\Services\LocationRecognitionService::STATUS_POSSIBLE_MATCH && $recognition['suggested_location'])
                                <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                            POSSIBLE MATCH
                                        </span>
                                        <span class="text-[10px] text-amber-700 font-mono">{{ $recognition['confidence'] }}% Match</span>
                                    </div>
                                    <div>
                                        <p class="text-[11px] text-slate-600">Suggested facility based on terminology similarity:</p>
                                        <p class="font-bold text-slate-900 text-xs mt-0.5">
                                            [{{ $recognition['suggested_location']->code }}] {{ $recognition['suggested_location']->official_name }}
                                        </p>
                                    </div>
                                    <button type="button" @click="showResolveModal = true"
                                            class="w-full rounded-lg bg-amber-600 hover:bg-amber-700 px-3 py-1.5 text-xs font-bold text-white shadow-xs transition-colors">
                                        Review & Confirm Link &rarr;
                                    </button>
                                </div>
                            @else
                                <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                                            UNKNOWN LOCATION
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-500">This destination term has not been registered in the Location Directory yet.</p>
                                    <button type="button" @click="showResolveModal = true"
                                            class="w-full rounded-lg bg-blue-600 hover:bg-blue-700 px-3 py-1.5 text-xs font-bold text-white shadow-xs transition-colors">
                                        Resolve / Register Location &rarr;
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100 text-xs">
                        <div>
                            <span class="text-[10px] text-slate-400 block font-semibold">LATITUDE</span>
                            <span class="font-mono font-bold text-slate-700">{{ $trip->destination_latitude ? number_format($trip->destination_latitude, 6) : '—' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 block font-semibold">LONGITUDE</span>
                            <span class="font-mono font-bold text-slate-700">{{ $trip->destination_longitude ? number_format($trip->destination_longitude, 6) : '—' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 block font-semibold">ACCURACY</span>
                            <span class="font-mono font-bold text-slate-700">{{ $trip->destination_accuracy ? '±' . round($trip->destination_accuracy) . 'm' : '—' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Remarks & Audit Card --}}
                <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200 space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Audit & Remarks</h3>
                    <div class="space-y-2 text-xs">
                        <div>
                            <span class="text-slate-400 block text-[11px] font-semibold">REMARKS</span>
                            <span class="text-slate-700 font-medium">{{ $trip->remarks ?: 'No remarks recorded.' }}</span>
                        </div>
                        <div class="pt-2 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                            <div>
                                <span class="text-slate-400 block font-semibold">CLIENT UUID</span>
                                <span class="font-mono text-slate-600 truncate block" title="{{ $trip->client_id }}">{{ $trip->client_id ?: 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-semibold">RECORDED AT</span>
                                <span class="text-slate-600 font-medium">{{ $trip->created_at->format('M j, Y H:i:s') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Right Column: Leaflet Historical Map View (7 cols) --}}
            <div class="lg:col-span-7 space-y-4">
                <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-slate-800">Trip Route Map</h3>
                            <span class="inline-flex items-center rounded-full bg-slate-100 border border-slate-200 px-2 py-0.5 text-[10px] font-semibold text-slate-500">
                                Authoritative GPS Points
                            </span>
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <span class="inline-flex items-center gap-1.5 font-medium text-slate-600">
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 inline-block"></span>
                                Origin (A)
                            </span>
                            <span class="inline-flex items-center gap-1.5 font-medium text-slate-600">
                                <span class="h-2.5 w-2.5 rounded-full bg-rose-500 inline-block"></span>
                                Destination (B)
                            </span>
                        </div>
                    </div>

                    {{-- Leaflet Map Container or Fallback --}}
                    @php
                        $hasOriginGps = !empty($trip->origin_latitude) && !empty($trip->origin_longitude);
                        $hasDestGps = !empty($trip->destination_latitude) && !empty($trip->destination_longitude);
                    @endphp

                    @if($hasOriginGps || $hasDestGps)
                        <div id="trip-map" class="border border-slate-200 shadow-inner"></div>
                        <p class="text-[11px] text-slate-400 text-center italic">
                            Static historical GPS plot representing start fix, end fix, and direct trajectory line. Real-time tracking is handled separately by TrackSolidPro.
                        </p>
                    @else
                        <div class="h-96 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col items-center justify-center text-center p-6 text-slate-400">
                            <svg class="h-12 w-12 text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                            </svg>
                            <p class="text-sm font-bold text-slate-700">GPS Coordinates Unavailable</p>
                            <p class="text-xs text-slate-400 mt-1">No valid GPS location fix was captured for this trip.</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>

    </div>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const mapEl = document.getElementById('trip-map');
                if (!mapEl) return;

                const originLat = @json($trip->origin_latitude);
                const originLng = @json($trip->origin_longitude);
                const destLat = @json($trip->destination_latitude);
                const destLng = @json($trip->destination_longitude);

                @php
                    $originFallback = ($trip->origin_latitude && $trip->origin_longitude)
                        ? 'Coordinates: ' . number_format((float)$trip->origin_latitude, 5) . ', ' . number_format((float)$trip->origin_longitude, 5)
                        : 'Origin';
                    $destFallback = ($trip->destination_latitude && $trip->destination_longitude)
                        ? 'Coordinates: ' . number_format((float)$trip->destination_latitude, 5) . ', ' . number_format((float)$trip->destination_longitude, 5)
                        : 'Destination';
                @endphp

                const originAddr = @json($trip->origin_address ?: $originFallback);
                const destAddr = @json($trip->destination_address ?: $destFallback);
                const timeIn = @json($trip->time_in ?? 'N/A');
                const timeOut = @json($trip->time_out ?? 'N/A');

                // Determine default center
                let defaultCenter = [14.5995, 120.9842]; // Manila default
                if (originLat && originLng) {
                    defaultCenter = [originLat, originLng];
                } else if (destLat && destLng) {
                    defaultCenter = [destLat, destLng];
                }

                const map = L.map('trip-map', {
                    scrollWheelZoom: false,
                }).setView(defaultCenter, 13);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

                const markers = [];

                // Custom Origin Icon (Green)
                if (originLat && originLng) {
                    const originIcon = L.divIcon({
                        className: 'custom-map-icon',
                        html: '<div style="background-color:#10b981;width:24px;height:24px;border-radius:50%;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:11px;">A</div>',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12],
                    });

                    const originMarker = L.marker([originLat, originLng], { icon: originIcon })
                        .addTo(map)
                        .bindPopup(`
                            <div style="min-width: 160px;">
                                <div style="font-weight: bold; color: #10b981; font-size: 11px; text-transform: uppercase;">Origin / Start</div>
                                <div style="font-weight: 600; color: #1e293b; margin: 3px 0;">${originAddr}</div>
                                <div style="font-size: 11px; color: #64748b;">Departure: <b>${timeIn}</b></div>
                            </div>
                        `);

                    markers.push([originLat, originLng]);
                }

                // Custom Destination Icon (Red)
                if (destLat && destLng) {
                    const destIcon = L.divIcon({
                        className: 'custom-map-icon',
                        html: '<div style="background-color:#ef4444;width:24px;height:24px;border-radius:50%;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:11px;">B</div>',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12],
                    });

                    const destMarker = L.marker([destLat, destLng], { icon: destIcon })
                        .addTo(map)
                        .bindPopup(`
                            <div style="min-width: 160px;">
                                <div style="font-weight: bold; color: #ef4444; font-size: 11px; text-transform: uppercase;">Destination / End</div>
                                <div style="font-weight: 600; color: #1e293b; margin: 3px 0;">${destAddr}</div>
                                <div style="font-size: 11px; color: #64748b;">Arrival: <b>${timeOut}</b></div>
                            </div>
                        `);

                    markers.push([destLat, destLng]);
                }

                {{-- Authoritative Facility Location Marker (If Recognized) --}}
                @if(!empty($recognition['location']) && !empty($recognition['location']->latitude) && !empty($recognition['location']->longitude))
                    const facilityLat = {{ $recognition['location']->latitude }};
                    const facilityLng = {{ $recognition['location']->longitude }};
                    const facilityName = @json($recognition['location']->official_name);
                    const facilityCode = @json($recognition['location']->code);
                    const facilityType = @json($recognition['location']->type);
                    const facilityAddr = @json($recognition['location']->address);

                    const facilityIcon = L.divIcon({
                        className: 'custom-map-icon',
                        html: '<div style="background-color:#2563eb;width:26px;height:26px;border-radius:50%;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:12px;">📍</div>',
                        iconSize: [26, 26],
                        iconAnchor: [13, 13],
                    });

                    L.marker([facilityLat, facilityLng], { icon: facilityIcon })
                        .addTo(map)
                        .bindPopup(`
                            <div style="min-width: 170px;">
                                <div style="font-weight: bold; color: #2563eb; font-size: 11px; text-transform: uppercase;">Official Facility</div>
                                <div style="font-weight: 700; color: #0f172a; margin: 3px 0;">${facilityName} (${facilityCode})</div>
                                <div style="font-size: 11px; color: #64748b;">${facilityAddr}</div>
                            </div>
                        `);

                    markers.push([facilityLat, facilityLng]);
                @endif

                // Connect with direct vector line if both exist
                if (originLat && originLng && destLat && destLng) {
                    const trajectory = L.polyline([
                        [originLat, originLng],
                        [destLat, destLng]
                    ], {
                        color: '#2563eb',
                        weight: 3,
                        dashArray: '6, 8',
                        opacity: 0.85
                    }).addTo(map);

                    map.fitBounds(markers, { padding: [50, 50] });
                } else if (markers.length > 0) {
                    if (markers.length === 1) {
                        map.setView(markers[0], 14);
                    } else {
                        map.fitBounds(markers, { padding: [50, 50] });
                    }
                }
            });
        </script>
    @endpush

    {{-- Resolve Destination Modal (Adjustment 6) --}}
    <div x-show="showResolveModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="showResolveModal = false" class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-slate-200 p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-blue-700 text-xs font-bold">🎯</span>
                    <h3 class="text-base font-bold text-slate-900">Resolve Trip Destination</h3>
                </div>
                <button type="button" @click="showResolveModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
            </div>

            <div class="rounded-xl bg-slate-50 p-3 border border-slate-200 text-xs">
                <span class="text-[10px] font-bold uppercase text-slate-400 block">Driver-Written Destination Text</span>
                <span class="font-mono font-bold text-slate-900 text-sm mt-0.5 block">{{ $trip->destination_address }}</span>
                <p class="text-[10px] text-slate-500 mt-1 italic">Original driver text will remain completely unchanged. It will now be linked to an official Location.</p>
            </div>

            <form action="{{ route('itineraries.resolveLocation', $trip) }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">
                        Workflow A: Link to Existing Location
                    </label>
                    <select name="location_id" x-model="selectedLocationId" required
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 focus:border-blue-500 outline-none">
                        <option value="">-- Select Official Location --</option>
                        @foreach($activeLocations as $aLoc)
                            <option value="{{ $aLoc->id }}">
                                [{{ $aLoc->code }}] {{ $aLoc->official_name }} ({{ $aLoc->type }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2 pt-1">
                    <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                        <input type="checkbox" name="save_as_alias" value="1" checked class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span>Save "<strong>{{ $trip->destination_address }}</strong>" as a new recognized alias for this location</span>
                    </label>
                    <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                        <input type="checkbox" name="backfill_historical" value="1" checked class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span>Automatically resolve matching historical trips without altering original text</span>
                    </label>
                </div>

                <div class="rounded-xl bg-blue-50/60 border border-blue-100 p-3 text-xs text-blue-900 flex items-center justify-between gap-3">
                    <div>
                        <strong class="block font-bold">Workflow B: Need a Brand New Location?</strong>
                        <span class="text-[11px] text-blue-700">Create a brand new Location Master record for this destination.</span>
                    </div>
                    <a href="{{ route('locations.create', ['prefill_alias' => $trip->destination_address]) }}"
                       class="rounded-lg bg-white border border-blue-200 px-2.5 py-1 text-xs font-bold text-blue-700 hover:bg-blue-50 transition-colors shadow-2xs whitespace-nowrap">
                        Create New &rarr;
                    </a>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="showResolveModal = false"
                            class="rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200">
                        Cancel
                    </button>
                    <button type="submit"
                            class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-sm">
                        Confirm & Link Destination
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
