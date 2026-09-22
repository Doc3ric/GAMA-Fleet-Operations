{{-- Shared form partial for create/edit vehicle --}}
@php $v = $vehicle ?? null; @endphp

@if($errors->any())
    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="space-y-5">

    {{-- Section: Basic Information --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-4">Basic Information</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

            {{-- Equipment Code --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
                    Equipment Code <span class="text-red-500">*</span>
                </label>
                <input type="text" name="equipment_code" value="{{ old('equipment_code', $v?->equipment_code) }}"
                       placeholder="e.g. BH 5, DT 01, EX 03"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('equipment_code') border-red-400 @enderror">
                @error('equipment_code') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Vehicle Type --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Vehicle Type</label>
                <select name="vehicle_type_id"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('vehicle_type_id') border-red-400 @enderror">
                    <option value="">— Select Type —</option>
                    @foreach($vehicleTypes as $type)
                        <option value="{{ $type->id }}" {{ old('vehicle_type_id', $v?->vehicle_type_id) == $type->id ? 'selected' : '' }}>
                            {{ $type->name }} ({{ $type->code }})
                        </option>
                    @endforeach
                </select>
                @error('vehicle_type_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Model --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Model</label>
                <input type="text" name="model" value="{{ old('model', $v?->model) }}"
                       placeholder="e.g. CAT 320, HINO 700"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            {{-- Plate Number --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Plate Number</label>
                <input type="text" name="plate_number" value="{{ old('plate_number', $v?->plate_number) }}"
                       placeholder="e.g. ABC-1234"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            {{-- Date Acquired --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Date Acquired</label>
                <input type="date" name="date_acquired" value="{{ old('date_acquired', $v?->date_acquired?->format('Y-m-d')) }}"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            {{-- GPS Status --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
                    GPS Status <span class="text-red-500">*</span>
                </label>
                <select name="gps_status"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('gps_status') border-red-400 @enderror">
                    <option value="NO" {{ old('gps_status', $v?->gps_status ?? 'NO') === 'NO' ? 'selected' : '' }}>NO</option>
                    <option value="YES" {{ old('gps_status', $v?->gps_status) === 'YES' ? 'selected' : '' }}>YES</option>
                    <option value="EXPIRED" {{ old('gps_status', $v?->gps_status) === 'EXPIRED' ? 'selected' : '' }}>EXPIRED</option>
                    <option value="FOR_CHECKUP" {{ old('gps_status', $v?->gps_status) === 'FOR_CHECKUP' ? 'selected' : '' }}>FOR CHECKUP</option>
                </select>
                @error('gps_status') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Section: Fuel & Status --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-4">Fuel & Status</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

            {{-- Fuel Min / Max --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Fuel Consumption</label>
                <div class="flex gap-2 items-center">
                    <input type="number" name="fuel_min" value="{{ old('fuel_min', $v?->fuel_min) }}"
                           placeholder="Min" step="0.01" min="0"
                           class="w-24 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <span class="text-slate-400 text-sm">–</span>
                    <input type="number" name="fuel_max" value="{{ old('fuel_max', $v?->fuel_max) }}"
                           placeholder="Max" step="0.01" min="0"
                           class="w-24 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <input type="text" name="fuel_unit" value="{{ old('fuel_unit', $v?->fuel_unit ?? 'LIT/HR') }}"
                           placeholder="Unit" maxlength="20"
                           class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <p class="text-xs text-slate-400 mt-1">e.g. 16 – 20 LIT/HR</p>
                @error('fuel_min') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                @error('fuel_max') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Status --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Status</label>
                <div class="flex gap-2 items-center">
                    <input type="number" name="status_value" value="{{ old('status_value', $v?->status_value) }}"
                           placeholder="Value" step="0.01" min="0"
                           class="w-28 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <span class="text-slate-400 text-sm">/</span>
                    <input type="text" name="status_label" value="{{ old('status_label', $v?->status_label) }}"
                           placeholder="e.g. RUNNING, STANDBY, BREAKDOWN" maxlength="50"
                           class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <p class="text-xs text-slate-400 mt-1">e.g. 0.8 / RUNNING</p>
            </div>
        </div>
    </div>

    {{-- Section: Assignment --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-4">Assignment</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

            {{-- Location --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Location</label>
                <input type="text" name="location" value="{{ old('location', $v?->location) }}"
                       placeholder="e.g. Project Site A, Main Yard"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            {{-- Project Code --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Project Code</label>
                <input type="text" name="project_code" value="{{ old('project_code', $v?->project_code) }}"
                       placeholder="e.g. PRJ-2026-001"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            {{-- Operator / Driver --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Operator / Driver</label>
                <input type="text" name="operator_driver" value="{{ old('operator_driver', $v?->operator_driver) }}"
                       placeholder="Full name"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            {{-- Helper --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Helper</label>
                <input type="text" name="helper" value="{{ old('helper', $v?->helper) }}"
                       placeholder="Full name (optional)"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
    </div>

    {{-- Section: Image & Notes --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5"
         x-data="{
             preview: '{{ $v?->image_url }}',
             fileName: '',
             handleFile(e) {
                 const file = e.target.files[0];
                 if (file) {
                     this.fileName = file.name;
                     this.preview = URL.createObjectURL(file);
                 }
             }
         }">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Image & Notes</h3>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-xs sm:hidden">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                Save Vehicle
            </button>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

            {{-- Image --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Vehicle Image</label>
                <div class="flex gap-3 items-start">
                    <div class="flex-shrink-0">
                        <div class="h-24 w-32 rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 overflow-hidden flex items-center justify-center relative">
                            <img x-show="preview" :src="preview" alt="Preview"
                                 class="h-full w-full object-cover">
                            <div x-show="!preview" class="text-center p-2">
                                <svg class="mx-auto h-7 w-7 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                                <span class="text-[10px] text-slate-400">No Image</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex-1 space-y-2">
                        <input type="file" name="image" accept="image/*"
                               @change="handleFile($event)"
                               class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 file:text-xs file:font-semibold hover:file:bg-blue-100 border border-slate-200 rounded-lg cursor-pointer">
                        <p class="text-[11px] text-slate-400">Supported: JPG, PNG, GIF, WebP up to 5MB.</p>

                        {{-- File selected indicator and reminder --}}
                        <div x-show="fileName" style="display: none;" class="flex items-center gap-1.5 text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-2.5 py-1.5">
                            <svg class="h-4 w-4 text-emerald-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <span class="truncate">Selected: <strong x-text="fileName"></strong> — Remember to click <strong>Save Vehicle</strong> below!</span>
                        </div>

                        @error('image') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Notes --}}
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Notes</label>
                <textarea name="notes" rows="4" placeholder="Any additional notes..."
                          class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none">{{ old('notes', $v?->notes) }}</textarea>
            </div>
        </div>
    </div>

</div>