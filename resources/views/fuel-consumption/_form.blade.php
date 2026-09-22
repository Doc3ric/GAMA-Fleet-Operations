@php
    $isEdit = isset($test);
@endphp

<div x-data="fuelCalculator()" x-init="init()" class="space-y-8">
    {{-- Top Alert / Notice Box --}}
    <div class="rounded-2xl border border-blue-200 bg-blue-50/60 p-4.5 text-xs text-blue-900 flex items-start gap-3">
        <div class="p-1.5 rounded-lg bg-blue-600 text-white shrink-0 mt-0.5 shadow-2xs">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>
        </div>
        <div class="space-y-1">
            <h4 class="font-extrabold text-blue-950 text-sm">Full-Tank Testing Protocol</h4>
            <p class="leading-relaxed text-blue-800">
                <strong>1. First Full Tank:</strong> Fill the vehicle to full capacity and record the <strong>Start Odometer</strong> before the test drive.<br>
                <strong>2. Second Full Tank:</strong> After the test drive, fill the tank to full again. Record the <strong>End Odometer</strong> and the liters pumped as <strong>Fuel Consumed (2nd Full Tank)</strong>.<br>
                <em>Distance Travelled</em> and <em>Average Fuel Consumption (km/L)</em> are calculated automatically by the system and verified server-side.
            </p>
        </div>
    </div>

    {{-- ==================== SECTION 1: VEHICLE & TEST METADATA ==================== --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-5">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-sm font-black text-slate-900">Vehicle & Test Identification</h3>
                <p class="text-xs text-slate-500 mt-0.5">Select the vehicle or equipment being evaluated</p>
            </div>
            <span class="text-xs font-semibold text-slate-400">Step 1 of 3</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            {{-- Test Date --}}
            <div>
                <label for="test_date" class="block text-xs font-bold text-slate-700 mb-1.5">
                    Test Date <span class="text-red-500">*</span>
                </label>
                <input type="date" id="test_date" name="test_date"
                       value="{{ old('test_date', isset($test) ? $test->test_date?->format('Y-m-d') : date('Y-m-d')) }}"
                       required
                       class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-2xs">
                @error('test_date')
                    <p class="text-red-500 text-[11px] mt-1 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- Vehicle Selection --}}
            <div>
                <label for="vehicle_id" class="block text-xs font-bold text-slate-700 mb-1.5">
                    Vehicle / Equipment <span class="text-red-500">*</span>
                </label>
                <select id="vehicle_id" name="vehicle_id" x-model="vehicleId" @change="updateVehicle()" required
                        class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-2xs font-medium">
                    <option value="">-- Select Vehicle / Equipment --</option>
                    <option value="specify" class="font-bold text-blue-700 bg-blue-50/80">
                        &#9998; -- PLEASE SPECIFY (Manual Entry) --
                    </option>
                    <optgroup label="Vehicle Master List">
                        @foreach($vehicles as $veh)
                            <option value="{{ $veh->id }}">
                                {{ $veh->equipment_code }} &mdash; {{ $veh->model ?: 'Model unspecified' }} {{ $veh->plate_number ? '('.$veh->plate_number.')' : '' }}
                            </option>
                        @endforeach
                    </optgroup>
                </select>
                @error('vehicle_id')
                    <p class="text-red-500 text-[11px] mt-1 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- Plate Number (Auto-populated or Manual) --}}
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                    Plate Number
                </label>
                <template x-if="!isSpecify">
                    <input type="text" readonly
                           :value="selectedVehicle ? (selectedVehicle.plate_number || 'No plate recorded') : 'Auto-populated on vehicle selection'"
                           class="w-full text-xs rounded-xl border border-slate-200 bg-slate-100/70 px-3 py-2.5 text-slate-600 font-mono font-bold cursor-not-allowed">
                </template>
                <template x-if="isSpecify">
                    <input type="text" name="custom_plate_number" x-model="customPlateNumber"
                           placeholder="Enter Plate No. (e.g. ABC-1234)"
                           class="w-full text-xs rounded-xl border border-blue-300 bg-blue-50/30 px-3 py-2.5 text-slate-900 font-mono font-bold focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-2xs">
                </template>
                <span class="text-[10px] text-slate-400 mt-1 block" x-text="isSpecify ? 'Manual plate number for specified vehicle' : 'Linked to Vehicle Master List'"></span>
                @error('custom_plate_number')
                    <p class="text-red-500 text-[11px] mt-1 font-medium">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Conditional Manual Specification Details Box --}}
        <div x-show="isSpecify" x-transition
             class="rounded-2xl border-2 border-blue-400/70 bg-gradient-to-br from-blue-50/70 via-white to-blue-50/40 p-4.5 space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-blue-600 text-white text-[10px] font-black tracking-wider uppercase">
                        MANUAL SPECIFY
                    </span>
                    <h4 class="text-xs font-black text-slate-900">Manually Put Vehicle / Equipment Details</h4>
                </div>
                <span class="text-[11px] font-semibold text-blue-700">
                    &bull; System will auto-generate this vehicle in the Vehicle Master List
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                <div>
                    <label for="custom_equipment_code" class="block text-xs font-bold text-slate-800 mb-1">
                        Vehicle / Equipment Code <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="custom_equipment_code" name="custom_equipment_code"
                           x-model="customEquipmentCode"
                           placeholder="e.g. TRUCK-08, BH-15, FORKLIFT-01"
                           class="w-full text-xs rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 font-bold focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-2xs">
                    <span class="text-[10px] text-slate-400 mt-0.5 block">Unique identification code for the unit</span>
                    @error('custom_equipment_code')
                        <p class="text-red-500 text-[11px] mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="custom_model" class="block text-xs font-bold text-slate-800 mb-1">
                        Make / Model
                    </label>
                    <input type="text" id="custom_model" name="custom_model"
                           x-model="customModel"
                           placeholder="e.g. Isuzu Giga Dump 6UZ1, CAT 320"
                           class="w-full text-xs rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-2xs">
                    <span class="text-[10px] text-slate-400 mt-0.5 block">Brand, chassis, or equipment model</span>
                    @error('custom_model')
                        <p class="text-red-500 text-[11px] mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Driver & Route Information --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 pt-2">
            {{-- Driver / Operator Dropdown or Custom Name --}}
            <div>
                <label for="driver_id" class="block text-xs font-bold text-slate-700 mb-1.5">
                    Registered Driver Account
                </label>
                <select id="driver_id" name="driver_id"
                        class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-2xs">
                    <option value="">-- No User Account / External Driver --</option>
                    @foreach($drivers as $driver)
                        <option value="{{ $driver->id }}" {{ old('driver_id', $test->driver_id ?? '') == $driver->id ? 'selected' : '' }}>
                            {{ $driver->name }} ({{ $driver->email }})
                        </option>
                    @endforeach
                </select>
                @error('driver_id')
                    <p class="text-red-500 text-[11px] mt-1 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- Driver Name (Snapshot or Custom text) --}}
            <div>
                <label for="driver_name" class="block text-xs font-bold text-slate-700 mb-1.5">
                    Driver / Operator Name
                </label>
                <input type="text" id="driver_name" name="driver_name" x-model="driverName"
                       placeholder="e.g. Juan Dela Cruz"
                       class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-2xs">
                <span class="text-[10px] text-slate-400 mt-1 block">Auto-filled from vehicle or enter manually</span>
                @error('driver_name')
                    <p class="text-red-500 text-[11px] mt-1 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- Test Route --}}
            <div>
                <label for="test_route" class="block text-xs font-bold text-slate-700 mb-1.5">
                    Test Route / Location
                </label>
                <input type="text" id="test_route" name="test_route"
                       value="{{ old('test_route', $test->test_route ?? '') }}"
                       placeholder="e.g. Main Plant to Coastal Bypass"
                       class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-2xs">
                @error('test_route')
                    <p class="text-red-500 text-[11px] mt-1 font-medium">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- ==================== SECTION 2: ODOMETER & FUEL CONSUMPTION CALCULATIONS ==================== --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-6">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-sm font-black text-slate-900">Readings & Fuel Calculations</h3>
                <p class="text-xs text-slate-500 mt-0.5">Enter start/end odometer and fuel pumped during the second full tank</p>
            </div>
            <span class="text-xs font-semibold text-slate-400">Step 2 of 3</span>
        </div>

        {{-- Input Fields Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            {{-- Start Odometer --}}
            <div class="bg-slate-50/80 p-4 rounded-xl border border-slate-200/80">
                <label for="start_odometer" class="block text-xs font-bold text-slate-800 mb-1">
                    START ODOMETER (km) <span class="text-red-500">*</span>
                </label>
                <p class="text-[11px] text-slate-500 mb-2">Reading after the <strong>FIRST FULL TANK</strong></p>
                <div class="relative rounded-xl shadow-2xs">
                    <input type="number" step="0.01" min="0" max="9999999.99"
                           id="start_odometer" name="start_odometer"
                           x-model="startOdometer"
                           placeholder="e.g. 38091.00"
                           required
                           class="w-full text-base font-mono font-bold rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                        <span class="text-xs font-bold text-slate-400 font-mono">km</span>
                    </div>
                </div>
                @error('start_odometer')
                    <p class="text-red-500 text-[11px] mt-1 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- End Odometer --}}
            <div class="bg-slate-50/80 p-4 rounded-xl border border-slate-200/80">
                <label for="end_odometer" class="block text-xs font-bold text-slate-800 mb-1">
                    END ODOMETER (km) <span class="text-red-500">*</span>
                </label>
                <p class="text-[11px] text-slate-500 mb-2">Reading after completing the test trip</p>
                <div class="relative rounded-xl shadow-2xs">
                    <input type="number" step="0.01" min="0" max="9999999.99"
                           id="end_odometer" name="end_odometer"
                           x-model="endOdometer"
                           placeholder="e.g. 38109.00"
                           required
                           class="w-full text-base font-mono font-bold rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                           :class="{ 'border-red-500 ring-1 ring-red-500': hasOdometerError }">
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                        <span class="text-xs font-bold text-slate-400 font-mono">km</span>
                    </div>
                </div>
                <template x-if="hasOdometerError">
                    <p class="text-red-600 text-[11px] mt-1.5 font-bold">End odometer cannot be lower than start odometer.</p>
                </template>
                @error('end_odometer')
                    <p class="text-red-500 text-[11px] mt-1 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- Fuel Consumed (2nd Full Tank) --}}
            <div class="bg-slate-50/80 p-4 rounded-xl border border-slate-200/80">
                <label for="fuel_consumed_liters" class="block text-xs font-bold text-slate-800 mb-1">
                    FUEL CONSUMED (2ND FULL TANK) <span class="text-red-500">*</span>
                </label>
                <p class="text-[11px] text-slate-500 mb-2">Amount refilled to <strong>FULL TANK</strong> again</p>
                <div class="relative rounded-xl shadow-2xs">
                    <input type="number" step="0.001" min="0.001" max="99999.999"
                           id="fuel_consumed_liters" name="fuel_consumed_liters"
                           x-model="fuelConsumed"
                           placeholder="e.g. 2.377"
                           required
                           class="w-full text-base font-mono font-bold rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                        <span class="text-xs font-bold text-slate-400 font-mono">Liters</span>
                    </div>
                </div>
                @error('fuel_consumed_liters')
                    <p class="text-red-500 text-[11px] mt-1 font-medium">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- ==================== SYSTEM-CALCULATED READ-ONLY DISPLAY CARDS ==================== --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 pt-2">
            {{-- Distance Travelled (Read-Only) --}}
            <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-5 shadow-2xs">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block">System Auto-Calculated (Read-Only)</span>
                        <h4 class="text-xs font-extrabold text-slate-800 mt-0.5">DISTANCE TRAVELLED</h4>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-200 text-slate-600 font-mono">End &minus; Start</span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-black font-mono text-slate-900 tracking-tight" x-text="distanceFormatted">
                        {{ isset($test) ? number_format($test->distance_travelled, 2).' km' : '—' }}
                    </span>
                </div>
                <p class="text-[11px] text-slate-400 mt-1">Calculated distance driven between full-tank fill-ups</p>
            </div>

            {{-- Average Fuel Consumption (Read-Only Highlight Card) --}}
            <div class="rounded-2xl border-2 border-emerald-500/40 bg-gradient-to-br from-emerald-50/60 via-white to-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-[10px] uppercase font-bold tracking-wider text-emerald-600 block">System Auto-Calculated (Read-Only)</span>
                        <h4 class="text-xs font-black text-slate-900 mt-0.5">AVERAGE FUEL CONSUMPTION</h4>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-mono">Distance &divide; Liters</span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-black font-mono text-emerald-700 tracking-tight" x-text="consumptionFormatted">
                        {{ isset($test) ? number_format($test->average_fuel_consumption, 2).' km/L' : '—' }}
                    </span>
                </div>
                <p class="text-[11px] text-slate-500 mt-1">Full-tank benchmark efficiency metric for company records</p>
            </div>
        </div>

        {{-- Non-Blocking Warning Banner for Short Distances --}}
        <div x-show="isShortDistance" x-transition
             class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-xs text-amber-900 flex items-start gap-3">
            <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            <div>
                <p class="font-extrabold text-amber-950">Notice: Short Test Distance Detected (<span x-text="distanceFormatted"></span>)</p>
                <p class="mt-0.5 text-amber-800 leading-relaxed">
                    Short test distance. The calculated fuel consumption may not represent the vehicle's typical fuel economy.
                    This record can still be saved for baseline documentation.
                </p>
            </div>
        </div>
    </div>

    {{-- ==================== SECTION 3: TEST EVIDENCE & ATTACHMENTS ==================== --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-5">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-sm font-black text-slate-900">Supporting Evidence & Attachments</h3>
                <p class="text-xs text-slate-500 mt-0.5">Upload photos of odometers and fuel receipt from the test run</p>
            </div>
            <span class="text-xs font-semibold text-slate-400">Step 3 of 3</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            {{-- 1. Start Odometer Photo --}}
            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 flex flex-col justify-between">
                <div>
                    <label class="block text-xs font-bold text-slate-800 mb-1">
                        1. Start Odometer Photo
                    </label>
                    <p class="text-[11px] text-slate-500 mb-3">Photo showing odometer after the 1st full tank</p>

                    @if(isset($test) && $test->start_odometer_image_url)
                        <div class="mb-3 relative group">
                            <img src="{{ $test->start_odometer_image_url }}" alt="Start Odometer"
                                 class="w-full h-32 object-cover rounded-lg border border-slate-200 shadow-2xs">
                            <span class="text-[10px] text-slate-500 font-medium block mt-1">Current file uploaded</span>
                        </div>
                    @endif
                </div>

                <div class="mt-2">
                    <input type="file" name="start_odometer_image" accept="image/*"
                           class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                    @error('start_odometer_image')
                        <p class="text-red-500 text-[11px] mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- 2. Second Full Tank / End Odometer Photo --}}
            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 flex flex-col justify-between">
                <div>
                    <label class="block text-xs font-bold text-slate-800 mb-1">
                        2. End Odometer Photo
                    </label>
                    <p class="text-[11px] text-slate-500 mb-3">Photo after trip and/or 2nd full tank</p>

                    @if(isset($test) && $test->end_odometer_image_url)
                        <div class="mb-3 relative group">
                            <img src="{{ $test->end_odometer_image_url }}" alt="End Odometer"
                                 class="w-full h-32 object-cover rounded-lg border border-slate-200 shadow-2xs">
                            <span class="text-[10px] text-slate-500 font-medium block mt-1">Current file uploaded</span>
                        </div>
                    @endif
                </div>

                <div class="mt-2">
                    <input type="file" name="end_odometer_image" accept="image/*"
                           class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                    @error('end_odometer_image')
                        <p class="text-red-500 text-[11px] mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- 3. Fuel Receipt --}}
            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 flex flex-col justify-between">
                <div>
                    <label class="block text-xs font-bold text-slate-800 mb-1">
                        3. Fuel Receipt
                    </label>
                    <p class="text-[11px] text-slate-500 mb-3">Receipt showing liters pumped for 2nd full tank</p>

                    @if(isset($test) && $test->fuel_receipt_image_url)
                        <div class="mb-3 relative group">
                            <img src="{{ $test->fuel_receipt_image_url }}" alt="Fuel Receipt"
                                 class="w-full h-32 object-cover rounded-lg border border-slate-200 shadow-2xs">
                            <span class="text-[10px] text-slate-500 font-medium block mt-1">Current file uploaded</span>
                        </div>
                    @endif
                </div>

                <div class="mt-2">
                    <input type="file" name="fuel_receipt_image" accept="image/*"
                           class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                    @error('fuel_receipt_image')
                        <p class="text-red-500 text-[11px] mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Remarks & Attestation --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 pt-3 border-t border-slate-100">
            <div class="md:col-span-1">
                <label for="remarks" class="block text-xs font-bold text-slate-700 mb-1.5">
                    Remarks / Observations
                </label>
                <textarea id="remarks" name="remarks" rows="3"
                          placeholder="e.g. AC running on high, urban traffic, heavy load test"
                          class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-2xs">{{ old('remarks', $test->remarks ?? '') }}</textarea>
                @error('remarks')
                    <p class="text-red-500 text-[11px] mt-1 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="attested_by" class="block text-xs font-bold text-slate-700 mb-1.5">
                    Attested By (Specialist / Witness)
                </label>
                <input type="text" id="attested_by" name="attested_by"
                       value="{{ old('attested_by', $test->attested_by ?? auth()->user()->name) }}"
                       placeholder="e.g. Fleet Monitoring Specialist"
                       class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-2xs">
                @error('attested_by')
                    <p class="text-red-500 text-[11px] mt-1 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="requested_by" class="block text-xs font-bold text-slate-700 mb-1.5">
                    Requested By (Management / Unit)
                </label>
                <input type="text" id="requested_by" name="requested_by"
                       value="{{ old('requested_by', $test->requested_by ?? '') }}"
                       placeholder="e.g. Operations Manager / Logistics Head"
                       class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-2xs">
                @error('requested_by')
                    <p class="text-red-500 text-[11px] mt-1 font-medium">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- Form Submit Action Bar --}}
    <div class="flex items-center justify-end gap-3 pt-2">
        <a href="{{ route('fuel-consumption.index') }}"
           class="px-5 py-2.5 rounded-xl border border-slate-300 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors shadow-2xs">
            Cancel
        </a>
        <button type="submit"
                class="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-colors shadow-sm">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            {{ $isEdit ? 'Update Fuel Test Record' : 'Save & Calculate Fuel Test' }}
        </button>
    </div>
</div>

@push('scripts')
<script>
    function fuelCalculator() {
        return {
            vehicleId: '{{ old('vehicle_id', isset($test) ? ($test->vehicle_id ?? 'specify') : '') }}',
            vehicles: @json($vehicles),
            selectedVehicle: null,
            customEquipmentCode: '{{ old('custom_equipment_code', $test->custom_equipment_code ?? '') }}',
            customPlateNumber: '{{ old('custom_plate_number', $test->custom_plate_number ?? '') }}',
            customModel: '{{ old('custom_model', $test->custom_model ?? '') }}',
            startOdometer: '{{ old('start_odometer', $test->start_odometer ?? '') }}',
            endOdometer: '{{ old('end_odometer', $test->end_odometer ?? '') }}',
            fuelConsumed: '{{ old('fuel_consumed_liters', $test->fuel_consumed_liters ?? '') }}',
            driverName: '{{ old('driver_name', $test->driver_name ?? '') }}',

            init() {
                this.updateVehicle();
            },

            get isSpecify() {
                return this.vehicleId === 'specify';
            },

            updateVehicle() {
                if (!this.vehicleId || this.vehicleId === 'specify') {
                    this.selectedVehicle = null;
                    return;
                }
                this.selectedVehicle = this.vehicles.find(v => String(v.id) === String(this.vehicleId)) || null;
                if (this.selectedVehicle && (!this.driverName || this.driverName === '')) {
                    if (this.selectedVehicle.operator_driver) {
                        this.driverName = this.selectedVehicle.operator_driver;
                    }
                }
            },

            get start() {
                const val = parseFloat(this.startOdometer);
                return isNaN(val) ? null : val;
            },
            get end() {
                const val = parseFloat(this.endOdometer);
                return isNaN(val) ? null : val;
            },
            get fuel() {
                const val = parseFloat(this.fuelConsumed);
                return isNaN(val) ? null : val;
            },
            get distance() {
                if (this.start === null || this.end === null) return null;
                const diff = this.end - this.start;
                return diff >= 0 ? diff : null;
            },
            get distanceFormatted() {
                if (this.distance === null) return '—';
                return this.distance.toFixed(2) + ' km';
            },
            get averageConsumption() {
                if (this.distance === null || this.fuel === null || this.fuel <= 0) return null;
                return (this.distance / this.fuel);
            },
            get consumptionFormatted() {
                if (this.averageConsumption === null) return '—';
                return this.averageConsumption.toFixed(2) + ' km/L';
            },
            get isShortDistance() {
                return this.distance !== null && this.distance > 0 && this.distance < 20;
            },
            get hasOdometerError() {
                return this.start !== null && this.end !== null && (this.end < this.start);
            }
        }
    }
</script>
@endpush
