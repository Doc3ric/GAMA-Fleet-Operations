<x-app-layout>
    @section('page-title', 'Edit Fuel PO #' . $fuelPo->id)
    @section('breadcrumb')
        <a href="{{ route('fuel-po.index') }}" class="hover:underline">Fuel PO Checklist</a> &bull;
        <a href="{{ route('fuel-po.show', $fuelPo) }}" class="hover:underline">#{{ $fuelPo->id }}</a> &bull; Edit
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

        $vehiclesList = $vehicles->map(fn($v) => [
            'id' => (string) $v->id,
            'equipment_code' => $v->equipment_code ?? '—',
            'plate_number' => $v->plate_number ?? '—',
            'model' => $v->model ?? '—',
            'driver_name' => $v->operator_driver ?? '—',
            'user' => $v->user ?? '—',
            'project_code' => $v->project_code ?? '—',
            'average_consumption' => $v->average_consumption ? (float) $v->average_consumption : null,
        ])->values()->all();
    @endphp

    <div class="max-w-6xl mx-auto space-y-6" x-data="fuelPoBuilder({
        calculateDistanceUrl: '{{ route('locations.calculateDistance') }}',
        csrfToken: '{{ csrf_token() }}',
        locationsList: {{ json_encode($locationsList) }},
        vehiclesList: {{ json_encode($vehiclesList) }},
        initialVehicleId: '{{ old('vehicle_id', $fuelPo->vehicle_id) }}',
        initialDriverName: {{ json_encode(old('driver_name', $fuelPo->driver_name !== '—' ? $fuelPo->driver_name : '')) }},
        initialLegs: {{ json_encode(old('legs', $fuelPo->legs->map(fn($leg) => [
            'origin_location_id' => (string) $leg->origin_location_id,
            'starting_point_location_id' => (string) $leg->starting_point_location_id,
            'destination_location_id' => (string) $leg->destination_location_id,
            'distance_origin_to_start' => $leg->distance_origin_to_start !== null ? (string) $leg->distance_origin_to_start : '',
            'distance_start_to_dest' => $leg->distance_start_to_dest !== null ? (string) $leg->distance_start_to_dest : '',
            'total_distance' => $leg->total_distance !== null ? (string) $leg->total_distance : '',
            'duration_origin_to_start_minutes' => $leg->duration_origin_to_start_minutes !== null ? (string) $leg->duration_origin_to_start_minutes : '',
            'duration_start_to_dest_minutes' => $leg->duration_start_to_dest_minutes !== null ? (string) $leg->duration_start_to_dest_minutes : '',
            'total_duration_minutes' => $leg->total_duration_minutes !== null ? (string) $leg->total_duration_minutes : '',
            'routing_source' => $leg->routing_source ?? 'manual',
            'purpose' => $leg->purpose ?? '',
        ])->toArray())) }}
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

        <form method="POST" action="{{ route('fuel-po.update', $fuelPo) }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Vehicle & Basic Details Card --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Itinerary & Vehicle Master Details</h2>
                        <span class="rounded-lg bg-slate-900 px-2 py-0.5 font-mono text-xs font-bold text-white">#{{ $fuelPo->id }}</span>
                    </div>
                    <span class="text-[11px] text-slate-500 font-medium">Edit Mode</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Itinerary Date <span class="text-rose-500">*</span></label>
                        <input type="date" name="itinerary_date" value="{{ old('itinerary_date', $fuelPo->itinerary_date->format('Y-m-d')) }}" required
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Assigned Vehicle <span class="text-rose-500">*</span></label>
                        <select name="vehicle_id" x-model="selectedVehicleId" @change="onVehicleChange()" required
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                            <option value="">-- Select Vehicle --</option>
                            @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" {{ old('vehicle_id', $fuelPo->vehicle_id) == $vehicle->id ? 'selected' : '' }}>
                                    {{ $vehicle->equipment_code }} &bull; {{ $vehicle->plate_number ?: 'No Plate' }} ({{ $vehicle->model }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Driver Name
                            <span class="text-[10px] text-blue-600 font-normal">(Editable)</span>
                        </label>
                        <input type="text" name="driver_name" x-model="driverName"
                               value="{{ old('driver_name', $fuelPo->driver_name !== '—' ? $fuelPo->driver_name : '') }}"
                               placeholder="e.g. Juan Dela Cruz"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Status <span class="text-rose-500">*</span></label>
                        <select name="status" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                            <option value="DRAFT" {{ old('status', $fuelPo->status) === 'DRAFT' ? 'selected' : '' }}>DRAFT (Editable)</option>
                            <option value="FINALIZED" {{ old('status', $fuelPo->status) === 'FINALIZED' ? 'selected' : '' }}>FINALIZED (Confirmed)</option>
                        </select>
                    </div>
                </div>

                {{-- Auto-Loaded Vehicle Master Info Card --}}
                <div x-show="selectedVehicle" x-cloak class="rounded-xl border border-blue-200 bg-blue-50/60 p-4 space-y-3">
                    <div class="flex items-center justify-between border-b border-blue-100 pb-2">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-blue-700" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V14.25m-18 0h18M3.375 14.25l1.5-6A1.125 1.125 0 0 1 5.97 7.5h12.06a1.125 1.125 0 0 1 1.095.75l1.5 6" />
                            </svg>
                            <span class="text-xs font-bold text-blue-900 uppercase tracking-wider">Auto-Loaded Vehicle Master Data</span>
                        </div>
                        <span class="text-[10px] font-semibold text-blue-700 bg-blue-100 px-2 py-0.5 rounded-full">Used for Fuel PO Calculation</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-3 text-xs">
                        <div class="bg-white/80 rounded-lg p-2.5 border border-blue-100/70">
                            <span class="block text-[10px] font-semibold text-slate-500 uppercase">Equipment Code</span>
                            <span class="font-mono font-bold text-slate-900 block mt-0.5" x-text="selectedVehicle?.equipment_code || '—'"></span>
                        </div>
                        <div class="bg-white/80 rounded-lg p-2.5 border border-blue-100/70">
                            <span class="block text-[10px] font-semibold text-slate-500 uppercase">Model</span>
                            <span class="font-semibold text-slate-900 block mt-0.5" x-text="selectedVehicle?.model || '—'"></span>
                        </div>
                        <div class="bg-white/80 rounded-lg p-2.5 border border-blue-100/70">
                            <span class="block text-[10px] font-semibold text-slate-500 uppercase">Driver Name</span>
                            <span class="font-semibold text-slate-900 block mt-0.5" x-text="selectedVehicle?.driver_name || '—'"></span>
                        </div>
                        <div class="bg-white/80 rounded-lg p-2.5 border border-blue-100/70">
                            <span class="block text-[10px] font-semibold text-slate-500 uppercase">Plate Number</span>
                            <span class="font-mono font-bold text-slate-900 block mt-0.5" x-text="selectedVehicle?.plate_number || '—'"></span>
                        </div>
                        <div class="bg-white/80 rounded-lg p-2.5 border border-blue-100/70">
                            <span class="block text-[10px] font-semibold text-slate-500 uppercase">User / Dept</span>
                            <span class="font-semibold text-slate-900 block mt-0.5" x-text="selectedVehicle?.user || '—'"></span>
                        </div>
                        <div class="bg-white/80 rounded-lg p-2.5 border border-blue-100/70">
                            <span class="block text-[10px] font-semibold text-slate-500 uppercase">Project Code</span>
                            <span class="font-mono font-semibold text-slate-900 block mt-0.5" x-text="selectedVehicle?.project_code || '—'"></span>
                        </div>
                        <div class="bg-white/80 rounded-lg p-2.5 border border-blue-100/70">
                            <span class="block text-[10px] font-semibold text-blue-700 uppercase">Avg. Consumption</span>
                            <div class="font-mono font-bold text-blue-900 block mt-0.5">
                                <span x-text="selectedVehicle?.average_consumption ? (selectedVehicle.average_consumption + ' KM/L') : 'Not set'"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Title / Subject (Optional)</label>
                        <input type="text" name="title" value="{{ old('title', $fuelPo->title) }}" placeholder="e.g. Cagayan de Oro to Bukidnon Mill Transfer"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Notes / Operating Guidelines (Optional)</label>
                        <textarea name="notes" rows="1" placeholder="Optional notes for purchasing or dispatch"
                                  class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">{{ old('notes', $fuelPo->notes) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Legs Section --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Itinerary Legs & Distance Calculation</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Select Origin &rarr; Starting Point &rarr; Destination from Location Directory. Distances calculate automatically via road routing.</p>
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

                            {{-- Locations Select Row --}}
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">
                                        Origin Location <span class="text-rose-500">*</span>
                                    </label>
                                    <select :name="'legs[' + index + '][origin_location_id]'"
                                            x-model="leg.origin_location_id"
                                            @change="onLocationChange(index)"
                                            required
                                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                                        <option value="">-- Select Origin --</option>
                                        @foreach($locations as $loc)
                                            <option value="{{ $loc->id }}">{{ $loc->official_name }} ({{ $loc->code ?: $loc->municipality }})</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">
                                        Starting Point (Depot/Stop) <span class="text-rose-500">*</span>
                                    </label>
                                    <select :name="'legs[' + index + '][starting_point_location_id]'"
                                            x-model="leg.starting_point_location_id"
                                            @change="onLocationChange(index)"
                                            required
                                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                                        <option value="">-- Select Starting Point --</option>
                                        @foreach($locations as $loc)
                                            <option value="{{ $loc->id }}">{{ $loc->official_name }} ({{ $loc->code ?: $loc->municipality }})</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">
                                        Destination Location <span class="text-rose-500">*</span>
                                    </label>
                                    <select :name="'legs[' + index + '][destination_location_id]'"
                                            x-model="leg.destination_location_id"
                                            @change="onLocationChange(index)"
                                            required
                                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                                        <option value="">-- Select Destination --</option>
                                        @foreach($locations as $loc)
                                            <option value="{{ $loc->id }}">{{ $loc->official_name }} ({{ $loc->code ?: $loc->municipality }})</option>
                                        @endforeach
                                    </select>
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
                                           placeholder="e.g. Delivery, Hauling, Mill Transfer"
                                           class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
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
                        <span class="block text-[11px] font-bold uppercase tracking-wider text-amber-600">Liter for PO (Distance &divide; Avg KM/L)</span>
                        <div class="flex items-baseline gap-1 font-mono font-bold text-amber-600 text-lg">
                            <template x-if="calculateLitersForPo() !== null">
                                <span>
                                    <span x-text="calculateLitersForPo()"></span>
                                    <span class="text-xs text-slate-500 font-sans font-normal">L</span>
                                </span>
                            </template>
                            <template x-if="calculateLitersForPo() === null">
                                <span class="text-xs text-slate-400 font-sans font-normal" x-text="!selectedVehicleId ? 'Select Vehicle' : 'No Avg KM/L'"></span>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('fuel-po.index') }}"
                       class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Cancel
                    </a>
                    <button type="submit"
                            class="rounded-xl bg-blue-600 px-6 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition cursor-pointer">
                        Update Fuel PO Itinerary
                    </button>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function fuelPoBuilder(config) {
            return {
                calculateDistanceUrl: config.calculateDistanceUrl,
                csrfToken: config.csrfToken,
                locationsList: config.locationsList || [],
                vehiclesList: config.vehiclesList || [],
                selectedVehicleId: config.initialVehicleId || '',
                driverName: config.initialDriverName || '',

                onVehicleChange() {
                    if (this.selectedVehicle && (!this.driverName || this.driverName.trim() === '')) {
                        this.driverName = (this.selectedVehicle.driver_name && this.selectedVehicle.driver_name !== '—') ? this.selectedVehicle.driver_name : '';
                    }
                },

                get selectedVehicle() {
                    if (!this.selectedVehicleId) return null;
                    return this.vehiclesList.find(v => String(v.id) === String(this.selectedVehicleId)) || null;
                },

                calculateLitersForPo() {
                    const vehicle = this.selectedVehicle;
                    if (!vehicle || !vehicle.average_consumption || vehicle.average_consumption <= 0) {
                        return null;
                    }
                    const distance = parseFloat(this.grandTotalDistance()) || 0;
                    if (distance <= 0) {
                        return '0';
                    }
                    const raw = Math.round((distance / vehicle.average_consumption) * 100) / 100;
                    const intPart = Math.floor(raw);
                    const decPart = Math.round((raw - intPart) * 100) / 100;
                    return (decPart >= 0.10 ? (intPart + 1) : intPart).toString();
                },

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

                        if (response.ok && data.success && data.source === 'osrm' && data.total !== null) {
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
                        }
                    } catch (err) {
                        leg.routing_source = 'manual';
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
                    leg.routing_source = 'manual';
                },

                grandTotalDistance() {
                    let sum = 0;
                    for (const leg of this.legs) {
                        const total = parseFloat(leg.total_distance) || 0;
                        sum += total;
                    }
                    return (Math.round(sum * 100) / 100).toFixed(2);
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
