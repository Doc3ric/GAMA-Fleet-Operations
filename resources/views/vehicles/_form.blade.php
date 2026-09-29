{{-- Shared form partial for create/edit vehicle - 6 fields strictly matching requirements --}}
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

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
    <div class="border-b border-slate-100 pb-4 mb-5 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Vehicle Details</h3>
            <p class="text-xs text-slate-500 mt-0.5">Enter registered vehicle master fields</p>
        </div>
        <span class="text-[11px] font-semibold text-blue-600 bg-blue-50 border border-blue-200 px-2.5 py-0.5 rounded-full">
            6 Master Fields
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        {{-- EGTP CODE --}}
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                EGTP CODE <span class="text-red-500">*</span>
            </label>
            <input type="text" name="equipment_code" value="{{ old('equipment_code', $v?->equipment_code) }}"
                   placeholder="e.g. SV 12" required autofocus
                   class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm font-semibold uppercase focus:outline-none focus:ring-2 focus:ring-blue-500 @error('equipment_code') border-red-400 @enderror">
            @error('equipment_code') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- MODEL --}}
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                MODEL
            </label>
            <input type="text" name="model" value="{{ old('model', $v?->model) }}"
                   placeholder="e.g. D-MAX"
                   class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('model') border-red-400 @enderror">
            @error('model') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- DRIVER NAME --}}
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                DRIVER NAME
            </label>
            <input type="text" name="operator_driver" value="{{ old('operator_driver', $v?->operator_driver) }}"
                   placeholder="e.g. JOSEPH HENEDO"
                   class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('operator_driver') border-red-400 @enderror">
            @error('operator_driver') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- PLATE NUMBER --}}
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                PLATE NUMBER
            </label>
            <input type="text" name="plate_number" value="{{ old('plate_number', $v?->plate_number) }}"
                   placeholder="e.g. KAF 6079"
                   class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm font-mono uppercase focus:outline-none focus:ring-2 focus:ring-blue-500 @error('plate_number') border-red-400 @enderror">
            @error('plate_number') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- USER --}}
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                USER
            </label>
            <input type="text" name="user" value="{{ old('user', $v?->user) }}"
                   placeholder="e.g. PURCHASING"
                   class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('user') border-red-400 @enderror">
            @error('user') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- PROJECT CODE --}}
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                PROJECT CODE
            </label>
            <input type="text" name="project_code" value="{{ old('project_code', $v?->project_code) }}"
                   placeholder="e.g. UTILITY VAN"
                   class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 @error('project_code') border-red-400 @enderror">
            @error('project_code') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</div>