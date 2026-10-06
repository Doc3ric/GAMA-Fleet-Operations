<x-app-layout>
    @section('page-title', 'Edit Fuel PO #' . $fuelPo->id)
    @section('breadcrumb')
        <a href="{{ route('fuel-po.index') }}" class="hover:underline">Fuel PO Checklist</a> &bull;
        <a href="{{ route('fuel-po.show', $fuelPo) }}" class="hover:underline">#{{ $fuelPo->id }}</a> &bull; Edit
    @endsection

    @php
        $locationsList = $locations->map(fn($loc) => [
            'id' => (string) $loc->id,
            'official_name' => $loc->official_name,
            'code' => $loc->code ?? '',
            'municipality' => $loc->municipality ?? '',
        ])->values()->all();

        $vehiclesList = $vehicles->map(fn($v) => [
            'id' => (string) $v->id,
            'equipment_code' => $v->equipment_code ?? '—',
            'plate_number' => $v->plate_number ?? '—',
            'model' => $v->model ?? '—',
            'driver_name' => $v->operator_driver ?? '—',
            'user' => $v->user ?? '—',
            'project_code' => $v->project_code ?? '—',
            'average_consumption' => ($v->average_fuel_consumption ?? $v->average_consumption) ? (float) ($v->average_fuel_consumption ?? $v->average_consumption) : null,
        ])->values()->all();

        $oldDestinations = old('destinations');
        $initialRows = [];
        if (is_array($oldDestinations) && count($oldDestinations) > 0) {
            foreach ($oldDestinations as $row) {
                $initialRows[] = [
                    'destination' => $row['name'] ?? $row['destination'] ?? '',
                    'start_odo' => $row['start_odo'] ?? '',
                    'end_odo' => $row['end_odo'] ?? '',
                    'distance' => $row['distance'] ?? '',
                    'purpose' => $row['purpose'] ?? '',
                ];
            }
        } elseif ($fuelPo->legs && $fuelPo->legs->count() > 0) {
            $destParts = $fuelPo->destination ? array_map('trim', explode(' → ', $fuelPo->destination)) : [];
            foreach ($fuelPo->legs as $i => $leg) {
                $destName = $leg->destination?->official_name ?? ($destParts[$i] ?? $leg->purpose ?? '');
                $purpose = ($leg->purpose && $leg->purpose !== $destName) ? $leg->purpose : '';
                $initialRows[] = [
                    'destination' => $destName,
                    'start_odo' => $leg->start_odo !== null ? (string) $leg->start_odo : '',
                    'end_odo' => $leg->end_odo !== null ? (string) $leg->end_odo : '',
                    'distance' => $leg->total_distance !== null ? (string) $leg->total_distance : '',
                    'purpose' => $purpose,
                ];
            }
        } elseif ($fuelPo->destination || $fuelPo->total_distance) {
            $initialRows[] = [
                'destination' => $fuelPo->destination ?? '',
                'start_odo' => $fuelPo->start_odo !== null ? (string) $fuelPo->start_odo : '',
                'end_odo' => $fuelPo->end_odo !== null ? (string) $fuelPo->end_odo : '',
                'distance' => $fuelPo->total_distance !== null ? (string) $fuelPo->total_distance : '',
                'purpose' => '',
            ];
        }
    @endphp

    <div class="max-w-5xl mx-auto space-y-6" x-data="editItineraryWorkflow({
        vehiclesList: {{ json_encode($vehiclesList) }},
        locationsList: {{ json_encode($locationsList) }},
        initialVehicleId: '{{ old('vehicle_id', $fuelPo->vehicle_id) }}',
        initialVehicleMode: '{{ old('vehicle_mode', old('custom_equipment_code') ? 'manual' : 'dropdown') }}',
        initialCustomEquipmentCode: {{ json_encode(old('custom_equipment_code', '')) }},
        initialCustomPlateNumber: {{ json_encode(old('custom_plate_number', '')) }},
        initialCustomModel: {{ json_encode(old('custom_model', '')) }},
        initialCustomUser: {{ json_encode(old('custom_user', '')) }},
        initialCustomProjectCode: {{ json_encode(old('custom_project_code', '')) }},
        initialCustomAvgConsumption: {{ json_encode(old('custom_average_consumption', '')) }},
        initialDriverName: {{ json_encode(old('driver_name', $fuelPo->driver_name !== '—' ? $fuelPo->driver_name : '')) }},
        initialRows: {{ json_encode($initialRows) }},
        initialDestination: {{ json_encode(old('destination', $fuelPo->destination ?? '')) }},
        initialDistance: {{ json_encode(old('total_distance', $fuelPo->total_distance !== null ? (string) $fuelPo->total_distance : '')) }},
        initialCalculationMethod: {{ json_encode(old('calculation_method', $fuelPo->calculation_method ?: \App\Models\AdvancedItinerary::detectCalculationMethod($fuelPo->vehicle?->equipment_code))) }}
    })">
        {{-- Header --}}
        <div class="flex items-center justify-between border-b border-slate-200 pb-4">
            <div>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500 text-white font-bold text-sm">
                        PO
                    </span>
                    Edit Fuel PO #{{ $fuelPo->id }}
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Update registered vehicle, destination rows, Start/End ODO, distances, driver name, and operating details.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('fuel-po.show', $fuelPo) }}"
                   class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs">
                    View Details
                </a>
                <a href="{{ route('fuel-po.index') }}"
                   class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs">
                    &larr; Back to Checklist
                </a>
            </div>
        </div>

        {{-- Errors --}}
        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 text-xs font-medium space-y-1">
                <div class="font-bold flex items-center gap-1.5 text-rose-900">
                    <svg class="h-4 w-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    Please fix the following validation errors:
                </div>
                <ul class="list-disc list-inside space-y-0.5 pl-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('fuel-po.update', $fuelPo) }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Hidden computed overall inputs --}}
            <input type="hidden" name="destination" :value="combinedDestination">
            <input type="hidden" name="total_distance" :value="totalCalculatedDistance">

            {{-- Hidden vehicle mode --}}
            <input type="hidden" name="vehicle_mode" :value="vehicleMode">

            {{-- MAIN FORM CARD --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs space-y-6">
                {{-- TOP ROW: VEHICLE SELECTION & ITINERARY DATE/STATUS/DRIVER --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    {{-- 1. EQT CODE (Dropdown Selection OR Manual Entry) --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider">
                                EQT Code <span class="text-rose-500">*</span>
                            </label>
                            <div class="inline-flex rounded-lg border border-slate-200 p-0.5 bg-slate-100 text-[11px]">
                                <button type="button"
                                        @click="setVehicleMode('dropdown')"
                                        :class="vehicleMode === 'dropdown' ? 'bg-white font-bold text-blue-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                                        class="px-2.5 py-0.5 rounded-md transition cursor-pointer">
                                    Select Dropdown
                                </button>
                                <button type="button"
                                        @click="setVehicleMode('manual')"
                                        :class="vehicleMode === 'manual' ? 'bg-white font-bold text-blue-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                                        class="px-2.5 py-0.5 rounded-md transition cursor-pointer">
                                    ✍ Type Manually
                                </button>
                            </div>
                        </div>

                        {{-- Mode 1: Dropdown Selection --}}
                        <div x-show="vehicleMode === 'dropdown'">
                            <select name="vehicle_id" x-model="selectedVehicleId" @change="onVehicleChange()" :required="vehicleMode === 'dropdown'"
                                    class="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3 py-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                                <option value="">-- Choose Equipment Code --</option>
                                <option value="manual" class="font-bold text-blue-700 bg-blue-50">➕ Type Manually / Custom EQT Code</option>
                                @foreach($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}" {{ old('vehicle_id', $fuelPo->vehicle_id) == $vehicle->id ? 'selected' : '' }}>
                                        {{ $vehicle->equipment_code }} &bull; {{ $vehicle->model ?: 'No Model' }} ({{ $vehicle->plate_number ?: 'No Plate' }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-slate-500 mt-1">
                                Automatically loads Average Consumption, Model, Driver, Plate, User, and Project.
                            </p>
                        </div>

                        {{-- Mode 2: Manual Specification --}}
                        <div x-show="vehicleMode === 'manual'" x-cloak class="space-y-1.5">
                            <input type="text"
                                   name="custom_equipment_code"
                                   x-model="manualEquipmentCode"
                                   @input="onManualEquipmentCodeInput()"
                                   :required="vehicleMode === 'manual'"
                                   placeholder="e.g. VH 1371, TRUCK-08, EQ-202"
                                   class="w-full rounded-xl border-2 border-blue-400 bg-blue-50/20 px-3 py-2.5 text-xs font-bold text-slate-900 focus:bg-white focus:border-blue-600 focus:ring-1 focus:ring-blue-600 outline-none uppercase font-mono shadow-2xs">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-blue-700 font-medium">Type custom/manual equipment code.</span>
                                <button type="button" @click="setVehicleMode('dropdown')" class="text-slate-500 hover:text-blue-600 underline">
                                    Switch back to dropdown
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- 2. Itinerary Date, Driver & Status --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1.5">
                                Itinerary Date <span class="text-rose-500">*</span>
                            </label>
                            <input type="date" name="itinerary_date" value="{{ old('itinerary_date', $fuelPo->itinerary_date ? $fuelPo->itinerary_date->format('Y-m-d') : date('Y-m-d')) }}" required
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1.5">
                                Driver Name <span class="text-[10px] text-blue-600 font-normal lowercase">(editable)</span>
                            </label>
                            <input type="text" name="driver_name" x-model="driverName" placeholder="Driver name for PO"
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1.5">
                                Status <span class="text-rose-500">*</span>
                            </label>
                            <select name="status" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-900 focus:border-blue-500 outline-none">
                                <option value="FINALIZED" {{ old('status', $fuelPo->status) === 'FINALIZED' ? 'selected' : '' }}>FINALIZED</option>
                                <option value="DRAFT" {{ old('status', $fuelPo->status) === 'DRAFT' ? 'selected' : '' }}>DRAFT</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- AUTO-LOADED VEHICLE PROFILE CARD (For Dropdown Mode) --}}
                <div x-show="vehicleMode === 'dropdown' && selectedVehicle" x-cloak class="rounded-xl border border-blue-200 bg-blue-50/60 p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-blue-900 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="h-3.5 w-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V14.25m-18 0h18M3.375 14.25l1.5-6A1.125 1.125 0 0 1 5.97 7.5h12.06a1.125 1.125 0 0 1 1.095.75l1.5 6" />
                            </svg>
                            Auto-Loaded Vehicle Master Information
                        </span>
                        <span class="text-[10px] bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-semibold">No Re-entry</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2 text-xs">
                        <div class="bg-white rounded-lg p-2 border border-blue-100">
                            <span class="block text-[10px] text-slate-400 uppercase font-semibold">EQPT Code</span>
                            <span class="font-mono font-bold text-slate-900" x-text="selectedVehicle?.equipment_code || '—'"></span>
                        </div>
                        <div class="bg-white rounded-lg p-2 border border-blue-100">
                            <span class="block text-[10px] text-slate-400 uppercase font-semibold">Model</span>
                            <span class="font-semibold text-slate-900" x-text="selectedVehicle?.model || '—'"></span>
                        </div>
                        <div class="bg-white rounded-lg p-2 border border-blue-100">
                            <span class="block text-[10px] text-slate-400 uppercase font-semibold">Driver Name</span>
                            <span class="font-semibold text-slate-900" x-text="selectedVehicle?.driver_name || '—'"></span>
                        </div>
                        <div class="bg-white rounded-lg p-2 border border-blue-100">
                            <span class="block text-[10px] text-slate-400 uppercase font-semibold">Plate Number</span>
                            <span class="font-mono font-bold text-slate-900" x-text="selectedVehicle?.plate_number || '—'"></span>
                        </div>
                        <div class="bg-white rounded-lg p-2 border border-blue-100">
                            <span class="block text-[10px] text-slate-400 uppercase font-semibold">User</span>
                            <span class="font-semibold text-slate-900" x-text="selectedVehicle?.user || '—'"></span>
                        </div>
                        <div class="bg-white rounded-lg p-2 border border-blue-100">
                            <span class="block text-[10px] text-slate-400 uppercase font-semibold">Project Code</span>
                            <span class="font-mono font-semibold text-slate-900" x-text="selectedVehicle?.project_code || '—'"></span>
                        </div>
                    </div>
                </div>

                {{-- MANUAL SPECIFICATION PROFILE CARD (For Manual Mode) --}}
                <div x-show="vehicleMode === 'manual'" x-cloak class="rounded-xl border-2 border-blue-300/80 bg-gradient-to-br from-blue-50/80 via-white to-blue-50/40 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-blue-600 text-white text-[10px] font-black tracking-wider uppercase">
                                MANUAL SPECIFY
                            </span>
                            <h4 class="text-xs font-bold text-slate-900">Custom Vehicle / Equipment Details</h4>
                        </div>
                        <span class="text-[11px] text-blue-700 font-medium">
                            Auto-links with Vehicle Master List
                        </span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 text-xs">
                        <div>
                            <label class="block text-[10px] text-slate-600 uppercase font-bold mb-1">
                                Avg. Cons (KM/L) <span class="text-rose-500">*</span>
                            </label>
                            <input type="number" step="0.01" min="0.01" name="custom_average_consumption" x-model="manualAvgConsumption" placeholder="e.g. 1.60"
                                   class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-mono font-bold text-slate-900 focus:border-blue-500 outline-none">
                            <span class="text-[9px] text-slate-400 block mt-0.5">Used for Liter for PO</span>
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-600 uppercase font-bold mb-1">
                                Plate Number
                            </label>
                            <input type="text" name="custom_plate_number" x-model="manualPlateNumber" placeholder="e.g. ABC-1234"
                                   class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-mono text-slate-900 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-600 uppercase font-bold mb-1">
                                Make / Model
                            </label>
                            <input type="text" name="custom_model" x-model="manualModel" placeholder="e.g. Isuzu Giga"
                                   class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-600 uppercase font-bold mb-1">
                                User / Department
                            </label>
                            <input type="text" name="custom_user" x-model="manualUser" placeholder="e.g. Operations"
                                   class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-600 uppercase font-bold mb-1">
                                Project Code
                            </label>
                            <input type="text" name="custom_project_code" x-model="manualProjectCode" placeholder="e.g. PRJ-01"
                                   class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-blue-500 outline-none">
                        </div>
                    </div>
                </div>

                {{-- DYNAMIC DESTINATIONS & DISTANCES (WITH START/END ODO & ADD ROW FUNCTION) --}}
                <div class="space-y-3 border-t border-slate-100 pt-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                </svg>
                                Destinations & Distance Breakdown
                            </h3>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                Enter destination. You can enter <strong>Start ODO</strong> &amp; <strong>End ODO</strong> to auto-calculate the distance (e.g. 2499 &minus; 2435 = 64), or directly type the <strong>Distance (KM)</strong> manually.
                            </p>
                        </div>

                        <button type="button" @click="addRow()"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-blue-300 bg-blue-50 px-3.5 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100 shadow-2xs transition cursor-pointer">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <span>Add Row</span>
                        </button>
                    </div>

                    {{-- Dynamic Rows Table --}}
                    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                            <thead class="bg-slate-50 font-bold uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-3 py-2.5 w-8 text-center">#</th>
                                    <th class="px-3 py-2.5">Destination (Manual or Select) <span class="text-rose-500">*</span></th>
                                    <th class="px-2.5 py-2.5 w-28">Start ODO <span class="text-[10px] text-slate-400 font-normal lowercase">(opt)</span></th>
                                    <th class="px-2.5 py-2.5 w-28">End ODO <span class="text-[10px] text-slate-400 font-normal lowercase">(opt)</span></th>
                                    <th class="px-3 py-2.5 w-36">Distance (KM) <span class="text-rose-500">*</span></th>
                                    <th class="px-3 py-2.5 w-40">Purpose / Cargo</th>
                                    <th class="px-2 py-2.5 w-12 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-800">
                                <template x-for="(row, index) in destinationRows" :key="index">
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-3 py-2.5 text-center font-bold text-slate-400" x-text="index + 1"></td>
                                        <td class="px-3 py-2.5">
                                            <input type="text"
                                                   :name="'destinations[' + index + '][name]'"
                                                   x-model="row.destination"
                                                   list="location-datalist"
                                                   required
                                                   placeholder="e.g. ANICO MANOLO or select..."
                                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                                        </td>
                                        <td class="px-2.5 py-2.5">
                                            <input type="number" step="0.01" min="0"
                                                   :name="'destinations[' + index + '][start_odo]'"
                                                   x-model="row.start_odo"
                                                   @input="onOdoChange(row)"
                                                   placeholder="e.g. 2435"
                                                   class="w-full rounded-xl border border-slate-300 px-2.5 py-2 text-xs font-mono text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                                        </td>
                                        <td class="px-2.5 py-2.5">
                                            <input type="number" step="0.01" min="0"
                                                   :name="'destinations[' + index + '][end_odo]'"
                                                   x-model="row.end_odo"
                                                   @input="onOdoChange(row)"
                                                   placeholder="e.g. 2499"
                                                   class="w-full rounded-xl border border-slate-300 px-2.5 py-2 text-xs font-mono text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <div class="relative">
                                                <input type="number" step="0.01" min="0"
                                                       :name="'destinations[' + index + '][distance]'"
                                                       x-model="row.distance"
                                                       required
                                                       placeholder="e.g. 64"
                                                       class="w-full rounded-xl border border-slate-300 px-3 py-2 pr-9 text-xs font-mono font-bold text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                                                <span class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-[11px] font-bold text-slate-400 pointer-events-none">
                                                    KM
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <input type="text"
                                                   :name="'destinations[' + index + '][purpose]'"
                                                   x-model="row.purpose"
                                                   placeholder="e.g. Delivery, Hauling"
                                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-700 focus:border-blue-500 outline-none">
                                        </td>
                                        <td class="px-2 py-2.5 text-center">
                                            <button type="button"
                                                    @click="removeRow(index)"
                                                    x-show="destinationRows.length > 1"
                                                    title="Remove this row"
                                                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-rose-600 hover:text-rose-700 hover:bg-rose-50 rounded-lg border border-rose-200/60 transition cursor-pointer shadow-2xs">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                </svg>
                                                <span>Remove</span>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>

                        {{-- Table Footer with + Add Row & Subtotal --}}
                        <div class="px-4 py-2.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                            <button type="button" @click="addRow()"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-blue-200 bg-white px-3.5 py-1.5 text-xs font-bold text-blue-600 hover:bg-blue-50 transition shadow-2xs cursor-pointer">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                <span>+ Add Row</span>
                            </button>

                            <div class="text-xs font-mono font-bold text-slate-700 flex items-center gap-2">
                                <span class="text-slate-400 font-sans font-semibold uppercase text-[11px]">Sum of All Rows:</span>
                                <span class="text-blue-700 text-sm font-black" x-text="totalCalculatedDistance + ' KM'"></span>
                            </div>
                        </div>
                    </div>

                    <datalist id="location-datalist">
                        @foreach($locations as $loc)
                            <option value="{{ $loc->official_name }}">{{ $loc->code ? "({$loc->code})" : '' }}</option>
                        @endforeach
                    </datalist>
                </div>

                {{-- AUTOMATIC FUEL CALCULATION SUMMARY (Exact User Table) --}}
                <div class="border-t border-slate-100 pt-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                        <div>
                            <span class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Automatic Fuel Calculation Summary
                            </span>
                            <span class="text-[11px] text-slate-500">
                                Select formula function. Forklift (FL) equipment automatically defaults to multiplication.
                            </span>
                        </div>

                        {{-- Calculation Mode Selector Dropdown --}}
                        <div class="inline-flex items-center gap-2">
                            <label for="calculation_method_select_edit" class="text-xs font-bold text-slate-600 whitespace-nowrap">
                                Formula:
                            </label>
                            <select id="calculation_method_select_edit"
                                    name="calculation_method"
                                    x-model="calculationMethod"
                                    @change="userOverrodeMethod = true"
                                    class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none shadow-2xs">
                                <option value="divide">&divide; Divide (Default: Distance &divide; Ave. Cons)</option>
                                <option value="multiply">&times; Multiplication (FL Forklift: Distance &times; Ave. Rate)</option>
                            </select>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-slate-300 bg-white shadow-xs">
                        <table class="w-full text-xs font-mono">
                            <tbody>
                                <tr class="border-b border-slate-200 bg-slate-50/70">
                                    <td class="px-4 py-3 font-bold uppercase text-slate-600">TOTAL DISTANCE</td>
                                    <td class="px-4 py-3 font-bold text-slate-900 text-right text-sm">
                                        <span x-text="totalCalculatedDistance ? (totalCalculatedDistance + ' KM') : '0 KM'"></span>
                                    </td>
                                </tr>
                                <tr class="border-b border-slate-200 bg-slate-50/70">
                                    <td class="px-4 py-3 font-bold uppercase text-slate-600">
                                        <span x-show="calculationMethod === 'divide'">AVE. CONS OF LITER/KM</span>
                                        <span x-show="calculationMethod === 'multiply'">AVE. CONSUMPTION RATE</span>
                                    </td>
                                    <td class="px-4 py-3 font-bold text-blue-700 text-right text-sm">
                                        <span x-text="averageConsumption ? (parseFloat(averageConsumption).toFixed(2) + (calculationMethod === 'multiply' ? ' (Rate)' : ' KM/L')) : 'Requires Vehicle'"></span>
                                    </td>
                                </tr>
                                <tr class="bg-amber-100/60">
                                    <td class="px-4 py-3.5 font-extrabold uppercase text-amber-900 text-sm">
                                        LITER FOR PO
                                        <span class="block text-[10px] font-sans font-normal text-amber-700">
                                            <span x-show="calculationMethod === 'divide'">Total Distance &divide; Ave. Consumption</span>
                                            <span x-show="calculationMethod === 'multiply'">Total Distance &times; Ave. Consumption Rate (FL / Forklift)</span>
                                            &bull;
                                            <span x-show="calculatedRawLiters" x-text="'Raw: ' + calculatedRawLiters + ' L (&ge; 0.10 &rarr; ' + calculatedLiters + ' L)'"></span>
                                            <span x-show="!calculatedRawLiters">Decimal &ge; 0.10 rounds up</span>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 font-extrabold text-amber-900 text-right text-lg">
                                        <span x-text="calculatedLiters ? (calculatedLiters + ' L') : '—'"></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- OPTIONAL TITLE & NOTES --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-slate-100 pt-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1.5">
                            Title / Subject <span class="text-[10px] text-slate-400 font-normal lowercase">(optional)</span>
                        </label>
                        <input type="text" name="title" value="{{ old('title', $fuelPo->title) }}" placeholder="e.g. Cagayan de Oro to Bukidnon Mill Transfer"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:border-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1.5">
                            Notes / Operating Guidelines <span class="text-[10px] text-slate-400 font-normal lowercase">(optional)</span>
                        </label>
                        <textarea name="notes" rows="1" placeholder="Optional notes for purchasing or dispatch"
                                  class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:border-blue-500 outline-none">{{ old('notes', $fuelPo->notes) }}</textarea>
                    </div>
                </div>

                {{-- ACTION BUTTONS --}}
                <div class="border-t border-slate-100 pt-4 flex items-center justify-end gap-3">
                    <a href="{{ route('fuel-po.show', $fuelPo) }}"
                       class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Cancel
                    </a>
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-2.5 text-xs font-bold text-white hover:bg-blue-700 shadow-sm transition cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <span>SAVE CHANGES</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function editItineraryWorkflow(config) {
            return {
                vehiclesList: config.vehiclesList || [],
                locationsList: config.locationsList || [],
                vehicleMode: config.initialVehicleMode || (config.initialCustomEquipmentCode ? 'manual' : 'dropdown'),
                selectedVehicleId: config.initialVehicleId || '',
                manualEquipmentCode: config.initialCustomEquipmentCode || '',
                manualPlateNumber: config.initialCustomPlateNumber || '',
                manualModel: config.initialCustomModel || '',
                manualUser: config.initialCustomUser || '',
                manualProjectCode: config.initialCustomProjectCode || '',
                manualAvgConsumption: config.initialCustomAvgConsumption || '',
                driverName: config.initialDriverName || '',
                calculationMethod: config.initialCalculationMethod || 'divide',
                userOverrodeMethod: Boolean(config.initialCalculationMethod),

                detectVehicleMethod(code) {
                    if (!code) return 'divide';
                    return /^FL(\s*[-_\/0-9]|$)/i.test(code.trim()) ? 'multiply' : 'divide';
                },

                setVehicleMode(mode) {
                    this.vehicleMode = mode;
                    if (mode === 'dropdown' && this.selectedVehicleId === 'manual') {
                        this.selectedVehicleId = '';
                    }
                    if (!this.userOverrodeMethod) {
                        const code = mode === 'manual' ? this.manualEquipmentCode : this.selectedVehicle?.equipment_code;
                        this.calculationMethod = this.detectVehicleMethod(code);
                    }
                },

                onVehicleChange() {
                    if (this.selectedVehicleId === 'manual') {
                        this.setVehicleMode('manual');
                        return;
                    }
                    if (this.selectedVehicle) {
                        if (!this.userOverrodeMethod) {
                            this.calculationMethod = this.detectVehicleMethod(this.selectedVehicle.equipment_code);
                        }
                        if (!this.driverName || this.driverName.trim() === '') {
                            this.driverName = (this.selectedVehicle.driver_name && this.selectedVehicle.driver_name !== '—') ? this.selectedVehicle.driver_name : '';
                        }
                    }
                },

                onManualEquipmentCodeInput() {
                    const typed = (this.manualEquipmentCode || '').trim();
                    if (!this.userOverrodeMethod) {
                        this.calculationMethod = this.detectVehicleMethod(typed);
                    }
                    if (!typed) return;
                    const match = this.vehiclesList.find(v => (v.equipment_code || '').toLowerCase() === typed.toLowerCase());
                    if (match) {
                        if (!this.manualPlateNumber && match.plate_number && match.plate_number !== '—') {
                            this.manualPlateNumber = match.plate_number;
                        }
                        if (!this.manualModel && match.model && match.model !== '—') {
                            this.manualModel = match.model;
                        }
                        if (!this.manualUser && match.user && match.user !== '—') {
                            this.manualUser = match.user;
                        }
                        if (!this.manualProjectCode && match.project_code && match.project_code !== '—') {
                            this.manualProjectCode = match.project_code;
                        }
                        if (!this.manualAvgConsumption && match.average_consumption) {
                            this.manualAvgConsumption = String(match.average_consumption);
                        }
                        if (!this.driverName && match.driver_name && match.driver_name !== '—') {
                            this.driverName = match.driver_name;
                        }
                    }
                },

                // Destination & Distance rows: start with existing legs/rows or at least 1 row
                destinationRows: (config.initialRows && config.initialRows.length > 0)
                    ? config.initialRows.map(r => ({
                        destination: r.destination || r.name || '',
                        start_odo: r.start_odo !== undefined && r.start_odo !== null ? String(r.start_odo) : '',
                        end_odo: r.end_odo !== undefined && r.end_odo !== null ? String(r.end_odo) : '',
                        distance: r.distance !== undefined && r.distance !== null ? String(r.distance) : '',
                        purpose: r.purpose || ''
                    }))
                    : [{ destination: config.initialDestination || '', start_odo: '', end_odo: '', distance: config.initialDistance || '', purpose: '' }],

                onOdoChange(row) {
                    const start = parseFloat(row.start_odo);
                    const end = parseFloat(row.end_odo);
                    if (!isNaN(start) && !isNaN(end) && end >= start) {
                        const diff = Math.round((end - start) * 100) / 100;
                        row.distance = String(diff);
                    }
                },

                addRow() {
                    let nextStartOdo = '';
                    if (this.destinationRows.length > 0) {
                        const lastRow = this.destinationRows[this.destinationRows.length - 1];
                        if (lastRow.end_odo && !isNaN(parseFloat(lastRow.end_odo))) {
                            nextStartOdo = lastRow.end_odo;
                        }
                    }
                    this.destinationRows.push({
                        destination: '',
                        start_odo: nextStartOdo,
                        end_odo: '',
                        distance: '',
                        purpose: ''
                    });
                },

                removeRow(index) {
                    if (this.destinationRows.length > 1) {
                        this.destinationRows.splice(index, 1);
                    }
                },

                get selectedVehicle() {
                    if (this.vehicleMode === 'manual') {
                        return null;
                    }
                    return this.vehiclesList.find(v => String(v.id) === String(this.selectedVehicleId)) || null;
                },

                get averageConsumption() {
                    if (this.vehicleMode === 'manual') {
                        const val = parseFloat(this.manualAvgConsumption);
                        return (!isNaN(val) && val > 0) ? val : null;
                    }
                    return this.selectedVehicle?.average_consumption || null;
                },

                get totalCalculatedDistance() {
                    let sum = 0;
                    for (const row of this.destinationRows) {
                        const dist = parseFloat(row.distance);
                        if (!isNaN(dist) && dist > 0) {
                            sum += dist;
                        }
                    }
                    return String(Math.round(sum * 100) / 100);
                },

                get combinedDestination() {
                    const names = this.destinationRows
                        .map(r => (r.destination || '').trim())
                        .filter(n => n.length > 0);
                    return names.join(' → ');
                },

                get calculatedRawLiters() {
                    const dist = parseFloat(this.totalCalculatedDistance);
                    const avg = parseFloat(this.averageConsumption);
                    if (!dist || dist <= 0 || !avg || avg <= 0) {
                        return null;
                    }
                    const raw = this.calculationMethod === 'multiply' ? (dist * avg) : (dist / avg);
                    return raw.toFixed(2);
                },

                get calculatedLiters() {
                    const dist = parseFloat(this.totalCalculatedDistance);
                    const avg = parseFloat(this.averageConsumption);
                    if (!dist || dist <= 0 || !avg || avg <= 0) {
                        return null;
                    }
                    const raw = this.calculationMethod === 'multiply'
                        ? Math.round((dist * avg) * 100) / 100
                        : Math.round((dist / avg) * 100) / 100;
                    const intPart = Math.floor(raw);
                    const decPart = Math.round((raw - intPart) * 100) / 100;
                    return decPart >= 0.10 ? (intPart + 1) : intPart;
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
