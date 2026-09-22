<div class="grid grid-cols-1 md:grid-cols-2 gap-5">

    {{-- Device Name --}}
    <div>
        <label for="device_name" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
            Device Name <span class="text-red-500">*</span>
        </label>
        <input type="text" name="device_name" id="device_name" required
               value="{{ old('device_name', $device->device_name ?? '') }}"
               placeholder="e.g. GAJ-5943, KAR 7806"
               class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs text-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-colors @error('device_name') border-red-500 bg-red-50 @enderror">
        @error('device_name')
            <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- IMEI --}}
    <div>
        <label for="imei" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
            IMEI Number
        </label>
        <input type="text" name="imei" id="imei"
               value="{{ old('imei', $device->imei ?? '') }}"
               placeholder="e.g. 865135060611447"
               class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs font-mono text-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-colors @error('imei') border-red-500 bg-red-50 @enderror">
        @error('imei')
            <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- Model --}}
    <div>
        <label for="model" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
            Model
        </label>
        <input type="text" name="model" id="model"
               value="{{ old('model', $device->model ?? '') }}"
               placeholder="e.g. X3, VG01U, GT06N"
               list="models-list"
               class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs text-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-colors @error('model') border-red-500 bg-red-50 @enderror">
        <datalist id="models-list">
            @foreach($models as $m)
                <option value="{{ $m }}"></option>
            @endforeach
        </datalist>
        @error('model')
            <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- SIM --}}
    <div>
        <label for="sim" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
            SIM Phone Number
        </label>
        <input type="text" name="sim" id="sim"
               value="{{ old('sim', $device->sim ?? '') }}"
               placeholder="e.g. 9982474024"
               class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs font-mono text-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-colors @error('sim') border-red-500 bg-red-50 @enderror">
        @error('sim')
            <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- Activated Date --}}
    <div>
        <label for="activated_date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
            Activated Date
        </label>
        <input type="date" name="activated_date" id="activated_date"
               value="{{ old('activated_date', isset($device->activated_date) && $device->activated_date ? $device->activated_date->format('Y-m-d') : '') }}"
               class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs text-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-colors @error('activated_date') border-red-500 bg-red-50 @enderror">
        @error('activated_date')
            <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- Sales Time --}}
    <div>
        <label for="sales_time" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
            Sales Time / Date
        </label>
        <input type="date" name="sales_time" id="sales_time"
               value="{{ old('sales_time', isset($device->sales_time) && $device->sales_time ? $device->sales_time->format('Y-m-d') : '') }}"
               class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs text-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-colors @error('sales_time') border-red-500 bg-red-50 @enderror">
        @error('sales_time')
            <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- User Expiration Date --}}
    <div class="p-3.5 rounded-xl border border-amber-200 bg-amber-50/40">
        <label for="expiration_date" class="block text-xs font-bold uppercase tracking-wider text-amber-900 mb-1">
            User Expiration Date <span class="text-[10px] font-normal text-amber-700">(Subscription Deadline)</span>
        </label>
        <input type="date" name="expiration_date" id="expiration_date"
               value="{{ old('expiration_date', isset($device->expiration_date) && $device->expiration_date ? $device->expiration_date->format('Y-m-d') : '') }}"
               class="w-full rounded-xl border border-amber-300 bg-white px-3.5 py-2 text-xs text-slate-800 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 transition-colors @error('expiration_date') border-red-500 bg-red-50 @enderror">
        <p class="text-[11px] text-amber-700 mt-1">
            Automatic alerts will trigger 30 days prior to this date for renewal & budgeting.
        </p>
        @error('expiration_date')
            <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- Group --}}
    <div>
        <label for="group_name" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
            Group / Team / Department
        </label>
        <input type="text" name="group_name" id="group_name"
               value="{{ old('group_name', $device->group_name ?? '') }}"
               placeholder="e.g. Default Group, LIVE OPERATION UNIT, Cebu"
               list="groups-list"
               class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs text-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-colors @error('group_name') border-red-500 bg-red-50 @enderror">
        <datalist id="groups-list">
            @foreach($groups as $g)
                <option value="{{ $g }}"></option>
            @endforeach
        </datalist>
        @error('group_name')
            <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- ICCID --}}
    <div>
        <label for="iccid" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
            ICCID (SIM Chip ID)
        </label>
        <input type="text" name="iccid" id="iccid"
               value="{{ old('iccid', $device->iccid ?? '') }}"
               placeholder="e.g. 89630324227008132231"
               class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs font-mono text-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-colors @error('iccid') border-red-500 bg-red-50 @enderror">
        @error('iccid')
            <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- IMSI --}}
    <div>
        <label for="imsi" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
            IMSI Number
        </label>
        <input type="text" name="imsi" id="imsi"
               value="{{ old('imsi', $device->imsi ?? '') }}"
               placeholder="e.g. 515039232540055"
               class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs font-mono text-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-colors @error('imsi') border-red-500 bg-red-50 @enderror">
        @error('imsi')
            <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- Mileage --}}
    <div>
        <label for="mileage" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
            Mileage (Kilometers)
        </label>
        <input type="number" step="0.01" name="mileage" id="mileage"
               value="{{ old('mileage', $device->mileage ?? '') }}"
               placeholder="e.g. 19228.42"
               class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs font-mono text-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-colors @error('mileage') border-red-500 bg-red-50 @enderror">
        @error('mileage')
            <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- Notes --}}
    <div class="md:col-span-2">
        <label for="notes" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
            Notes / Observations
        </label>
        <textarea name="notes" id="notes" rows="3"
                  placeholder="Optional remarks regarding this tracker, installation history, SIM provider..."
                  class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-xs text-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-colors @error('notes') border-red-500 bg-red-50 @enderror">{{ old('notes', $device->notes ?? '') }}</textarea>
        @error('notes')
            <p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

</div>
