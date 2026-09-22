<x-app-layout>
    @section('page-title', 'Driver Itinerary')
    @section('page-subtitle', 'Management & Operational Reporting')

    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #quick-view-map {
                height: 240px;
                width: 100%;
                border-radius: 0.75rem;
                z-index: 10;
            }
        </style>
    @endpush

    <div class="space-y-6" x-data="itineraryIndex()">

        {{-- KPI Cards Grid --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {{-- Total Trips --}}
            <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5 flex flex-col gap-2">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Total Trips</p>
                    <div class="rounded-xl bg-blue-50 p-2 text-blue-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-slate-900">{{ number_format($statistics['total']) }}</p>
                <p class="text-xs text-slate-400 font-medium">{{ $statistics['active_drivers'] }} active drivers &bull; {{ $statistics['active_vehicles'] }} active vehicles</p>
            </div>

            {{-- Completed Trips --}}
            <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5 flex flex-col gap-2">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Completed</p>
                    <div class="rounded-xl bg-emerald-50 p-2 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-emerald-600">{{ number_format($statistics['completed']) }}</p>
                <p class="text-xs text-slate-400 font-medium">Successfully completed trips</p>
            </div>

            {{-- In Progress Trips --}}
            <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5 flex flex-col gap-2">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">In Progress</p>
                    <div class="rounded-xl bg-amber-50 p-2 text-amber-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-amber-600">{{ number_format($statistics['in_progress']) }}</p>
                <p class="text-xs text-slate-400 font-medium">Currently active on the road</p>
            </div>

            {{-- Cancelled Trips --}}
            <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5 flex flex-col gap-2">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Cancelled</p>
                    <div class="rounded-xl bg-rose-50 p-2 text-rose-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-rose-600">{{ number_format($statistics['cancelled']) }}</p>
                <p class="text-xs text-slate-400 font-medium">Cancelled before completion</p>
            </div>
        </div>

        {{-- Action & Filter Bar --}}
        <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold text-slate-800">Trip Filters</h2>
                    @if(!empty(array_filter($filters ?? [])))
                        <span class="inline-flex items-center rounded-full bg-blue-50 border border-blue-200 px-2 py-0.5 text-[11px] font-semibold text-blue-700">
                            Filtered
                        </span>
                    @endif
                </div>

                {{-- Action Export Buttons --}}
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('itineraries.report', $filters ?? []) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-bold text-white hover:bg-slate-800 transition-colors shadow-sm">
                        <svg class="h-4 w-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        Weekly Report
                    </a>
                    <a href="{{ route('itineraries.exportExcel', $filters ?? []) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition-colors shadow-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>
                        Export Excel
                    </a>
                    <a href="{{ route('itineraries.exportPdf', $filters ?? []) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-rose-700 transition-colors shadow-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24-1.04-.54-2.14-.92-3.14m0 0C5.1 8.87 4.16 7.4 3 6m2.8 4.69c.8 2.06 1.83 4.24 3.03 6.31m0 0c1.47 2.53 3.09 4.3 4.87 5m-4.87-5c1.49-.69 3.08-1.55 4.71-2.52m0 0c2.81-1.68 5.4-3.69 7.46-5.88M15.7 13.48c-.68-.86-1.42-1.74-2.22-2.61m0 0C12.16 9.4 10.64 8 9 7m4.48 3.87c.72 1.48 1.5 2.99 2.32 4.47" />
                        </svg>
                        Export PDF
                    </a>
                </div>
            </div>

            {{-- Filter Form --}}
            <form method="GET" action="{{ route('itineraries.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 pt-2">
                {{-- Search --}}
                <div class="lg:col-span-2">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Search</label>
                    <div class="relative">
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                               placeholder="Driver, vehicle, address..."
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all">
                        @if(!empty($filters['search']))
                            <a href="{{ route('itineraries.index', array_merge($filters, ['search' => null])) }}" class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 text-xs">&times;</a>
                        @endif
                    </div>
                </div>

                {{-- Date From --}}
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">From Date</label>
                    <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all">
                </div>

                {{-- Date To --}}
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">To Date</label>
                    <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all">
                </div>

                {{-- Driver --}}
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Driver</label>
                    <select name="driver_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all">
                        <option value="">All Drivers</option>
                        @foreach($drivers as $driver)
                            <option value="{{ $driver->id }}" {{ ($filters['driver_id'] ?? '') == $driver->id ? 'selected' : '' }}>
                                {{ $driver->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Vehicle --}}
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Vehicle</label>
                    <select name="vehicle_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all">
                        <option value="">All Vehicles</option>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" {{ ($filters['vehicle_id'] ?? '') == $vehicle->id ? 'selected' : '' }}>
                                {{ $vehicle->equipment_code }} {{ $vehicle->plate_number ? "({$vehicle->plate_number})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Status & Buttons Row --}}
                <div class="sm:col-span-2 md:col-span-3 lg:col-span-6 flex flex-wrap items-center justify-between gap-3 pt-1 border-t border-slate-100">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Status:</span>
                        <div class="inline-flex rounded-xl bg-slate-100 p-1">
                            <a href="{{ route('itineraries.index', array_merge($filters, ['status' => null])) }}"
                               class="rounded-lg px-3 py-1 text-xs font-bold transition-all {{ empty($filters['status']) ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                                All
                            </a>
                            <a href="{{ route('itineraries.index', array_merge($filters, ['status' => 'COMPLETED'])) }}"
                               class="rounded-lg px-3 py-1 text-xs font-bold transition-all {{ ($filters['status'] ?? '') === 'COMPLETED' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-emerald-700' }}">
                                Completed
                            </a>
                            <a href="{{ route('itineraries.index', array_merge($filters, ['status' => 'IN_PROGRESS'])) }}"
                               class="rounded-lg px-3 py-1 text-xs font-bold transition-all {{ ($filters['status'] ?? '') === 'IN_PROGRESS' ? 'bg-white text-amber-700 shadow-sm' : 'text-slate-500 hover:text-amber-700' }}">
                                In Progress
                            </a>
                            <a href="{{ route('itineraries.index', array_merge($filters, ['status' => 'CANCELLED'])) }}"
                               class="rounded-lg px-3 py-1 text-xs font-bold transition-all {{ ($filters['status'] ?? '') === 'CANCELLED' ? 'bg-white text-rose-700 shadow-sm' : 'text-slate-500 hover:text-rose-700' }}">
                                Cancelled
                            </a>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit"
                                class="inline-flex items-center gap-1 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 transition-colors shadow-sm">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            Apply Filters
                        </button>
                        <a href="{{ route('itineraries.index') }}"
                           class="inline-flex items-center rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-200 transition-colors">
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        {{-- Itinerary Data Table --}}
        <div class="rounded-2xl bg-white shadow-sm border border-slate-200 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <h2 class="text-sm font-bold text-slate-800">Trip Records</h2>
                    <span class="inline-flex items-center rounded-full bg-slate-100 border border-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-500">
                        {{ $trips->total() }} recorded
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <th class="px-5 py-3.5">Date</th>
                            <th class="px-5 py-3.5">Driver</th>
                            <th class="px-5 py-3.5">Vehicle</th>
                            <th class="px-5 py-3.5">Origin</th>
                            <th class="px-5 py-3.5">Destination</th>
                            <th class="px-4 py-3.5 text-center">Time In</th>
                            <th class="px-4 py-3.5 text-center">Time Out</th>
                            <th class="px-4 py-3.5 text-center">Status</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        @forelse($trips as $trip)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                {{-- Date --}}
                                <td class="px-5 py-4 whitespace-nowrap font-semibold text-slate-800">
                                    {{ $trip->trip_date?->format('M j, Y') ?? 'N/A' }}
                                    <div class="text-[10px] font-mono text-slate-400">#{{ $trip->id }}</div>
                                </td>

                                {{-- Driver --}}
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-900">{{ $trip->driver?->name ?? 'Unknown Driver' }}</div>
                                    <div class="text-[11px] text-slate-400">{{ $trip->driver?->email ?? '' }}</div>
                                </td>

                                {{-- Vehicle --}}
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex rounded-lg bg-slate-900 px-2 py-0.5 font-mono text-xs font-bold text-white">
                                            {{ $trip->vehicle?->equipment_code ?? 'N/A' }}
                                        </span>
                                        <span class="text-xs text-slate-600 font-medium">
                                            {{ $trip->vehicle?->plate_number ?? '' }}
                                        </span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">{{ $trip->vehicle?->model ?? '' }}</div>
                                </td>

                                {{-- Origin --}}
                                <td class="px-5 py-4 max-w-xs">
                                    @if($trip->origin_address)
                                        <p class="font-medium text-slate-800 line-clamp-2" title="{{ $trip->origin_address }}">{{ $trip->origin_address }}</p>
                                    @elseif($trip->origin_latitude && $trip->origin_longitude)
                                        <span class="inline-flex items-center gap-1 rounded bg-slate-100 px-2 py-0.5 text-[11px] font-mono text-slate-600" title="Latitude, Longitude">
                                            <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                                            {{ number_format($trip->origin_latitude, 5) }}, {{ number_format($trip->origin_longitude, 5) }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 italic">Coordinates available</span>
                                    @endif
                                </td>

                                {{-- Destination (Integrated with Location Recognition) --}}
                                <td class="px-5 py-4 max-w-sm">
                                    @php
                                        $rec = $recognitions[$trip->id] ?? [
                                            'status' => \App\Services\LocationRecognitionService::STATUS_UNKNOWN,
                                            'location' => null,
                                            'suggested_location' => null,
                                        ];
                                    @endphp

                                    {{-- 1. Authoritative Original Driver Entry (Preserved Exactly) --}}
                                    @if($trip->destination_address)
                                        <p class="font-bold text-slate-900 line-clamp-1" title="{{ $trip->destination_address }}">
                                            {{ $trip->destination_address }}
                                        </p>
                                    @elseif($trip->destination_latitude && $trip->destination_longitude)
                                        <span class="inline-flex items-center gap-1 rounded bg-slate-100 px-2 py-0.5 text-[11px] font-mono text-slate-600" title="Latitude, Longitude">
                                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                                            {{ number_format($trip->destination_latitude, 5) }}, {{ number_format($trip->destination_longitude, 5) }}
                                        </span>
                                    @elseif($trip->isInProgress())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                            In Transit
                                        </span>
                                    @elseif($trip->isCancelled())
                                        <span class="text-slate-400 italic">Cancelled</span>
                                    @else
                                        <span class="text-slate-400 italic">Coordinates available</span>
                                    @endif

                                    {{-- 2. Recognition Status & Resolution Action --}}
                                    @if($trip->destination_address)
                                        @if($rec['status'] === \App\Services\LocationRecognitionService::STATUS_RECOGNIZED && $rec['location'])
                                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                    RECOGNIZED
                                                </span>
                                                <button type="button"
                                                        @click="openLocationQuickView({{ json_encode([
                                                            'id' => $rec['location']->id,
                                                            'code' => $rec['location']->code,
                                                            'official_name' => $rec['location']->official_name,
                                                            'type' => $rec['location']->type,
                                                            'address' => $rec['location']->address,
                                                            'latitude' => $rec['location']->latitude,
                                                            'longitude' => $rec['location']->longitude,
                                                            'image_url' => $rec['location']->image_url,
                                                            'aliases' => $rec['location']->aliases->pluck('alias')->toArray(),
                                                            'driver_entry' => $trip->destination_address,
                                                        ]) }})"
                                                        class="text-blue-600 font-semibold hover:underline truncate max-w-[170px] text-[11px] cursor-pointer inline-flex items-center gap-0.5"
                                                        title="Click to view official location & exact map point">
                                                    &rarr; {{ $rec['location']->official_name }}
                                                </button>
                                            </div>
                                        @elseif($rec['status'] === \App\Services\LocationRecognitionService::STATUS_POSSIBLE_MATCH && $rec['suggested_location'])
                                            <div class="mt-1 space-y-1">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200 px-2 py-0.5 text-[10px] font-bold text-amber-700">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                                        POSSIBLE MATCH
                                                    </span>
                                                    <span class="text-[11px] text-amber-800 font-medium truncate max-w-[140px]" title="{{ $rec['suggested_location']->official_name }}">
                                                        &rarr; {{ $rec['suggested_location']->official_name }}
                                                    </span>
                                                </div>
                                                <button type="button"
                                                        @click="openResolveModal({{ $trip->id }}, '{{ addslashes($trip->destination_address) }}', {{ $rec['suggested_location']->id }}, '{{ addslashes($rec['suggested_location']->official_name) }}')"
                                                        class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-800 hover:text-amber-900 bg-amber-100/80 hover:bg-amber-100 rounded-md px-1.5 py-0.5 transition-colors cursor-pointer">
                                                    Review & Confirm &rarr;
                                                </button>
                                            </div>
                                        @else
                                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                                <span class="inline-flex items-center rounded-full bg-slate-100 border border-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-500">
                                                    UNKNOWN LOCATION
                                                </span>
                                                <button type="button"
                                                        @click="openResolveModal({{ $trip->id }}, '{{ addslashes($trip->destination_address) }}', null, null)"
                                                        class="inline-flex items-center gap-1 text-[10px] font-bold text-blue-700 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-md px-1.5 py-0.5 transition-colors cursor-pointer"
                                                        title="Register or resolve this driver destination into directory">
                                                    Resolve &rarr;
                                                </button>
                                            </div>
                                        @endif
                                    @endif
                                </td>

                                {{-- Time In --}}
                                <td class="px-4 py-4 whitespace-nowrap text-center font-mono font-medium text-slate-700">
                                    {{ $trip->time_in ?? '—' }}
                                </td>

                                {{-- Time Out --}}
                                <td class="px-4 py-4 whitespace-nowrap text-center font-mono font-medium text-slate-700">
                                    {{ $trip->time_out ?? '—' }}
                                </td>

                                {{-- Status --}}
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    @if($trip->isCompleted())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Completed
                                        </span>
                                    @elseif($trip->isInProgress())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200 px-2.5 py-0.5 text-[11px] font-bold text-amber-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                            In Progress
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 border border-rose-200 px-2.5 py-0.5 text-[11px] font-bold text-rose-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                            Cancelled
                                        </span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="px-5 py-4 whitespace-nowrap text-right">
                                    <a href="{{ route('itineraries.show', $trip) }}"
                                       class="inline-flex items-center gap-1 rounded-lg bg-slate-100 hover:bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors">
                                        View Details
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center">
                                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-slate-700">No driver itinerary trips found</p>
                                    <p class="text-xs text-slate-400 mt-1">Try adjusting your filters or date range.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($trips->hasPages())
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
                    {{ $trips->links() }}
                </div>
            @endif
        </div>

        {{-- Location Quick View Modal (Requirement 18) --}}
        <div x-show="quickViewLocation !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="closeQuickView()" class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl border border-slate-200 p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex rounded-lg bg-slate-900 px-2.5 py-1 font-mono text-xs font-bold text-white" x-text="quickViewLocation?.code"></span>
                        <h3 class="text-base font-bold text-slate-900" x-text="quickViewLocation?.official_name"></h3>
                        <span class="inline-flex items-center rounded-md bg-blue-50 border border-blue-200 px-2 py-0.5 text-[10px] font-bold text-blue-700" x-text="quickViewLocation?.type"></span>
                    </div>
                    <button type="button" @click="closeQuickView()" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
                </div>

                {{-- Driver vs Official Comparison Card --}}
                <div class="grid grid-cols-2 gap-3 bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs">
                    <div>
                        <span class="text-[10px] font-bold uppercase text-slate-400 block">Driver Original Entry</span>
                        <span class="font-mono font-bold text-slate-900 text-sm mt-0.5 block" x-text="quickViewLocation?.driver_entry"></span>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase text-emerald-600 block">Recognized Official Location</span>
                        <span class="font-bold text-emerald-800 text-sm mt-0.5 block" x-text="quickViewLocation?.official_name"></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-[10px] font-bold uppercase text-slate-400 block">Facility Address</span>
                        <p class="text-slate-800 font-medium mt-0.5" x-text="quickViewLocation?.address"></p>

                        <div class="mt-3">
                            <span class="text-[10px] font-bold uppercase text-slate-400 block">Registered Aliases</span>
                            <div class="flex flex-wrap gap-1 mt-1">
                                <template x-for="alias in quickViewLocation?.aliases || []" :key="alias">
                                    <span class="inline-block rounded bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700" x-text="alias"></span>
                                </template>
                            </div>
                        </div>

                        <template x-if="quickViewLocation?.image_url">
                            <div class="mt-3">
                                <span class="text-[10px] font-bold uppercase text-slate-400 block mb-1">Facility Photo</span>
                                <img :src="quickViewLocation?.image_url" alt="Location" class="w-full h-28 object-cover rounded-lg border border-slate-200">
                            </div>
                        </template>
                    </div>

                    <div>
                        <span class="text-[10px] font-bold uppercase text-slate-400 block mb-1">Exact Map Waypoint</span>
                        <div id="quick-view-map" class="border border-slate-200 shadow-inner"></div>
                        <div class="flex items-center justify-between text-[11px] text-slate-500 font-mono mt-1">
                            <span x-text="quickViewLocation?.latitude + ', ' + quickViewLocation?.longitude"></span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-100 text-xs">
                    <a :href="'{{ url('locations') }}/' + quickViewLocation?.id" class="text-blue-600 font-bold hover:underline inline-flex items-center gap-1">
                        Full Location Profile &rarr;
                    </a>
                    <button type="button" @click="closeQuickView()" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200">
                        Close
                    </button>
                </div>
            </div>
        </div>

        {{-- Resolve / Register Destination Modal (Requirement 16, 21 & Adjustment 6) --}}
        <div x-show="resolveTripId !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="closeResolveModal()" class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-slate-200 p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-blue-700 text-xs font-bold">🎯</span>
                        <h3 class="text-base font-bold text-slate-900">Resolve Driver Destination</h3>
                    </div>
                    <button type="button" @click="closeResolveModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold cursor-pointer">&times;</button>
                </div>

                <div class="rounded-xl bg-slate-50 p-3 border border-slate-200 text-xs">
                    <span class="text-[10px] font-bold uppercase text-slate-400 block">Driver-Written Destination Text</span>
                    <span class="font-mono font-bold text-slate-900 text-sm mt-0.5 block" x-text="resolveDriverEntry"></span>
                    <p class="text-[10px] text-slate-500 mt-1 italic">Original text will be preserved in trip records. It will now be recognized using an official Location.</p>
                </div>

                <form :action="'{{ url('itineraries') }}/' + resolveTripId + '/resolve-location'" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Workflow A: Link to Existing Location
                        </label>
                        <select name="location_id" x-model="resolveSelectedLocationId" required
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 focus:border-blue-500 outline-none">
                            <option value="">-- Select Official Location --</option>
                            @foreach($activeLocations as $aLoc)
                                <option value="{{ $aLoc->id }}">
                                    [{{ $aLoc->code }}] {{ $aLoc->official_name }} ({{ $aLoc->type }})
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-slate-500 mt-1">Select the authoritative destination this driver abbreviation refers to.</p>
                    </div>

                    <div class="space-y-2 pt-1">
                        <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                            <input type="checkbox" name="save_as_alias" value="1" checked class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            <span>Save <strong x-text="'&quot;' + resolveDriverEntry + '&quot;'"></strong> as a new recognized alias for this location</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                            <input type="checkbox" name="backfill_historical" value="1" checked class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            <span>Automatically resolve matching historical trips without altering original text</span>
                        </label>
                    </div>

                    <div class="rounded-xl bg-blue-50/60 border border-blue-100 p-3 text-xs text-blue-900 flex items-center justify-between gap-3">
                        <div>
                            <strong class="block font-bold">Workflow B: Need a Brand New Location?</strong>
                            <span class="text-[11px] text-blue-700">If this destination is an unlisted site, create a new master record.</span>
                        </div>
                        <a :href="'{{ route('locations.create') }}?prefill_alias=' + encodeURIComponent(resolveDriverEntry)"
                           class="rounded-lg bg-white border border-blue-200 px-2.5 py-1 text-xs font-bold text-blue-700 hover:bg-blue-50 transition-colors shadow-2xs whitespace-nowrap">
                            Create New &rarr;
                        </a>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="closeResolveModal()"
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

    </div>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            function itineraryIndex() {
                return {
                    quickViewLocation: null,
                    quickViewMap: null,
                    quickViewMarker: null,

                    resolveTripId: null,
                    resolveDriverEntry: '',
                    resolveSelectedLocationId: '',

                    openLocationQuickView(data) {
                        this.quickViewLocation = data;

                        this.$nextTick(() => {
                            const mapEl = document.getElementById('quick-view-map');
                            if (!mapEl) return;

                            const lat = parseFloat(data.latitude) || 14.599512;
                            const lng = parseFloat(data.longitude) || 120.984222;

                            if (this.quickViewMap) {
                                this.quickViewMap.remove();
                                this.quickViewMap = null;
                            }

                            this.quickViewMap = L.map('quick-view-map').setView([lat, lng], 15);

                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                maxZoom: 19,
                                attribution: '&copy; OpenStreetMap'
                            }).addTo(this.quickViewMap);

                            this.quickViewMarker = L.marker([lat, lng]).addTo(this.quickViewMap);
                            this.quickViewMarker.bindPopup(`<b>${data.official_name}</b><br><small>${data.code}</small>`).openPopup();

                            setTimeout(() => {
                                if (this.quickViewMap) {
                                    this.quickViewMap.invalidateSize();
                                }
                            }, 250);
                        });
                    },

                    closeQuickView() {
                        this.quickViewLocation = null;
                        if (this.quickViewMap) {
                            this.quickViewMap.remove();
                            this.quickViewMap = null;
                        }
                    },

                    openResolveModal(tripId, driverText, suggestedLocId, suggestedName) {
                        this.resolveTripId = tripId;
                        this.resolveDriverEntry = driverText;
                        this.resolveSelectedLocationId = suggestedLocId ? String(suggestedLocId) : '';
                    },

                    closeResolveModal() {
                        this.resolveTripId = null;
                        this.resolveDriverEntry = '';
                        this.resolveSelectedLocationId = '';
                    }
                };
            }
        </script>
    @endpush
</x-app-layout>
