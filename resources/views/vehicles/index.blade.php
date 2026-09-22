<x-app-layout>
    @section('page-title', 'Vehicle Master List')
    @section('breadcrumb', 'Fleet Management / Vehicle Master List')

    <div class="space-y-4" x-data="{
        lightbox: false,
        lightboxSrc: '',
        lightboxAlt: '',
        openLightbox(src, alt) { this.lightboxSrc = src; this.lightboxAlt = alt; this.lightbox = true; },
        deleteId: null,
        deleteForm: null,
        confirmDelete(id) {
            this.deleteId = id;
            this.deleteForm = document.getElementById('delete-form-' + id);
        },
        showImportModal: false,
        imageModal: false,
        imageVehicleId: null,
        imageVehicleCode: '',
        openImageUpload(id, code) {
            this.imageVehicleId = id;
            this.imageVehicleCode = code;
            this.imageModal = true;
        }
    }">

        {{-- Top Action Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-1">
            <div>
                <div class="flex items-center gap-2.5">
                    <h2 class="text-xl font-bold text-slate-800 tracking-tight">Vehicle Master List</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                        {{ $vehicles->total() }} {{ Str::plural('Unit', $vehicles->total()) }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Centralized inventory of all company equipment — GPS-equipped and non-GPS</p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                {{-- Download Template Button --}}
                <a href="{{ route('vehicles.downloadTemplate') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors"
                   title="Download Excel Template">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span>Template</span>
                </a>

                {{-- Import Data Button --}}
                <button type="button" @click="showImportModal = true"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors cursor-pointer"
                        title="Import vehicles from spreadsheet">
                    <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    <span>Import Data</span>
                </button>

                {{-- Add Vehicle Button --}}
                <a href="{{ route('vehicles.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>Add Vehicle</span>
                </a>
            </div>
        </div>

        {{-- Compact Integrated Search & Filter Toolbar --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-3">
            <form method="GET" action="{{ route('vehicles.index') }}" class="space-y-2.5">
                {{-- Row 1: Search & Quick Actions --}}
                <div class="flex flex-col sm:flex-row gap-2">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </div>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Search equipment code, model, plate, operator, project, location..."
                               class="w-full pl-9 pr-3 py-1.5 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 rounded-lg text-xs placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button type="submit"
                                class="px-3.5 py-1.5 bg-slate-800 text-white text-xs font-semibold rounded-lg hover:bg-slate-900 transition-colors cursor-pointer shadow-xs">
                            Search
                        </button>
                        @if(request()->hasAny(['search', 'vehicle_type_id', 'gps_status', 'status_label', 'location', 'project_code']))
                            <a href="{{ route('vehicles.index') }}"
                               class="px-2.5 py-1.5 bg-slate-100 text-slate-600 text-xs font-medium rounded-lg hover:bg-slate-200 transition-colors"
                               title="Clear all filters">
                                Reset
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Row 2: Compact Inline Filter Selectors --}}
                <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100 text-xs">
                    <span class="text-slate-400 text-[11px] font-semibold uppercase tracking-wider mr-1">Filter by:</span>

                    {{-- Vehicle Type --}}
                    <div class="min-w-[140px] flex-1 sm:flex-initial">
                        <select name="vehicle_type_id" onchange="this.form.submit()"
                                class="w-full py-1.5 px-2.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            <option value="">All Types ({{ $vehicleTypes->count() }})</option>
                            @foreach($vehicleTypes as $type)
                                <option value="{{ $type->id }}" @selected(request('vehicle_type_id') == $type->id)>{{ $type->name }} ({{ $type->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- GPS Status --}}
                    <div class="min-w-[130px] flex-1 sm:flex-initial">
                        <select name="gps_status" onchange="this.form.submit()"
                                class="w-full py-1.5 px-2.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            <option value="">All GPS Status</option>
                            <option value="YES" @selected(request('gps_status') === 'YES')>🟢 GPS: YES</option>
                            <option value="NO" @selected(request('gps_status') === 'NO')>⚫ GPS: NO</option>
                            <option value="EXPIRED" @selected(request('gps_status') === 'EXPIRED')>🔴 GPS: EXPIRED</option>
                            <option value="FOR_CHECKUP" @selected(request('gps_status') === 'FOR_CHECKUP')>🟡 FOR CHECKUP</option>
                        </select>
                    </div>

                    {{-- Condition / Status --}}
                    @if($statusLabels->isNotEmpty())
                        <div class="min-w-[130px] flex-1 sm:flex-initial">
                            <select name="status_label" onchange="this.form.submit()"
                                    class="w-full py-1.5 px-2.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <option value="">All Status</option>
                                @foreach($statusLabels as $label)
                                    <option value="{{ $label }}" @selected(request('status_label') === $label)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Location --}}
                    @if($locations->isNotEmpty())
                        <div class="min-w-[130px] flex-1 sm:flex-initial">
                            <select name="location" onchange="this.form.submit()"
                                    class="w-full py-1.5 px-2.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <option value="">All Locations</option>
                                @foreach($locations as $loc)
                                    <option value="{{ $loc }}" @selected(request('location') === $loc)>{{ $loc }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Project --}}
                    @if($projectCodes->isNotEmpty())
                        <div class="min-w-[130px] flex-1 sm:flex-initial">
                            <select name="project_code" onchange="this.form.submit()"
                                    class="w-full py-1.5 px-2.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <option value="">All Projects</option>
                                @foreach($projectCodes as $code)
                                    <option value="{{ $code }}" @selected(request('project_code') === $code)>{{ $code }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Active filter indicator count --}}
                    @if(request()->hasAny(['search', 'vehicle_type_id', 'gps_status', 'status_label', 'location', 'project_code']))
                        <span class="ml-auto text-[11px] font-medium text-blue-600 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded">
                            Filtered View
                        </span>
                    @endif
                </div>
            </form>
        </div>

        {{-- Table Card --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left divide-y divide-slate-200">
                    <thead class="bg-slate-50/90 text-slate-600 font-semibold tracking-wider uppercase text-[10px]">
                        <tr>
                            <th scope="col" class="py-3 px-3 w-16 text-center">Photo</th>
                            <th scope="col" class="py-3 px-3.5 whitespace-nowrap">Equipment Code</th>
                            <th scope="col" class="py-3 px-3 whitespace-nowrap">Vehicle Type</th>
                            <th scope="col" class="py-3 px-3 whitespace-nowrap">Model</th>
                            <th scope="col" class="py-3 px-3 whitespace-nowrap">Plate No.</th>
                            <th scope="col" class="py-3 px-3 whitespace-nowrap">Acquired</th>
                            <th scope="col" class="py-3 px-3 whitespace-nowrap">Fuel Rate</th>
                            <th scope="col" class="py-3 px-3 whitespace-nowrap">Status</th>
                            <th scope="col" class="py-3 px-3 whitespace-nowrap">Location</th>
                            <th scope="col" class="py-3 px-3 whitespace-nowrap">Project Code</th>
                            <th scope="col" class="py-3 px-3 whitespace-nowrap">Operator / Driver</th>
                            <th scope="col" class="py-3 px-3 whitespace-nowrap">Helper</th>
                            <th scope="col" class="py-3 px-3 text-center whitespace-nowrap">GPS Status</th>
                            <th scope="col" class="py-3 px-3 text-right whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($vehicles as $vehicle)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                {{-- Photo Thumbnail --}}
                                <td class="py-2 px-3 text-center">
                                    @if($vehicle->image_url)
                                        <div class="relative group/img inline-block">
                                            <button type="button"
                                                    @click="openLightbox('{{ $vehicle->image_url }}', '{{ $vehicle->equipment_code }}')"
                                                    class="block cursor-zoom-in">
                                                <img src="{{ $vehicle->image_url }}" alt="{{ $vehicle->equipment_code }}"
                                                     class="h-9 w-12 object-cover rounded-md border border-slate-200 hover:shadow-xs transition-shadow">
                                            </button>
                                            <button type="button"
                                                    @click="openImageUpload({{ $vehicle->id }}, '{{ $vehicle->equipment_code }}')"
                                                    title="Change Photo"
                                                    class="absolute -top-1 -right-1 h-4 w-4 rounded-full bg-blue-600 text-white flex items-center justify-center opacity-0 group-hover/img:opacity-100 transition-opacity shadow-xs hover:bg-blue-700">
                                                <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                                                </svg>
                                            </button>
                                        </div>
                                    @else
                                        <button type="button"
                                                @click="openImageUpload({{ $vehicle->id }}, '{{ $vehicle->equipment_code }}')"
                                                class="h-9 w-12 mx-auto rounded-md border border-dashed border-slate-300 bg-slate-50 hover:bg-blue-50 hover:border-blue-300 flex flex-col items-center justify-center text-slate-400 hover:text-blue-600 transition-colors cursor-pointer group"
                                                title="Click to add photo">
                                            <svg class="h-3.5 w-3.5 text-slate-400 group-hover:text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                            </svg>
                                        </button>
                                    @endif
                                </td>

                                {{-- Equipment Code --}}
                                <td class="py-2.5 px-3.5 font-bold text-slate-900 whitespace-nowrap">
                                    <a href="{{ route('vehicles.show', $vehicle) }}" class="hover:text-blue-600 hover:underline">
                                        {{ $vehicle->equipment_code }}
                                    </a>
                                </td>

                                {{-- Vehicle Type --}}
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    @if($vehicle->vehicleType)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700">
                                            {{ $vehicle->vehicleType->name }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- Model --}}
                                <td class="py-2.5 px-3 text-slate-700 whitespace-nowrap">
                                    {{ $vehicle->model ?: '—' }}
                                </td>

                                {{-- Plate Number --}}
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    @if($vehicle->plate_number)
                                        <span class="font-mono text-[11px] font-semibold bg-slate-100/90 text-slate-800 px-1.5 py-0.5 rounded border border-slate-200">
                                            {{ $vehicle->plate_number }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- Date Acquired --}}
                                <td class="py-2.5 px-3 text-slate-600 whitespace-nowrap">
                                    {{ $vehicle->date_acquired?->format('M d, Y') ?? '—' }}
                                </td>

                                {{-- Fuel --}}
                                <td class="py-2.5 px-3 text-slate-700 whitespace-nowrap font-medium text-[11px]">
                                    {{ $vehicle->fuel_display ?: '—' }}
                                </td>

                                {{-- Status --}}
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    @if($vehicle->status_display)
                                        <span class="text-slate-800 font-medium text-[11px]">
                                            {{ $vehicle->status_display }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- Location --}}
                                <td class="py-2.5 px-3 text-slate-600 whitespace-nowrap">
                                    {{ $vehicle->location ?: '—' }}
                                </td>

                                {{-- Project Code --}}
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    @if($vehicle->project_code)
                                        <span class="font-mono text-[11px] text-slate-700 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-200">
                                            {{ $vehicle->project_code }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- Operator / Driver --}}
                                <td class="py-2.5 px-3 text-slate-700 whitespace-nowrap">
                                    {{ $vehicle->operator_driver ?: '—' }}
                                </td>

                                {{-- Helper --}}
                                <td class="py-2.5 px-3 text-slate-600 whitespace-nowrap">
                                    {{ $vehicle->helper ?: '—' }}
                                </td>

                                {{-- GPS Status --}}
                                <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                    <div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $vehicle->gps_status_color['bg'] }} {{ $vehicle->gps_status_color['text'] }}">
                                            {{ $vehicle->gps_status_label }}
                                        </span>
                                    </div>
                                    @if($vehicle->device)
                                        <div class="mt-1">
                                            <a href="{{ route('devices.show', $vehicle->device) }}"
                                               class="inline-flex items-center gap-1 text-[10px] font-medium text-blue-600 hover:text-blue-800 hover:underline"
                                               title="Linked GPS Tracker: {{ $vehicle->device->device_name }} (IMEI: {{ $vehicle->device->imei }})">
                                                <svg class="h-2.5 w-2.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.122a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M5.106 18.894c-3.808-3.808-3.808-9.98 0-13.789m13.788 0c3.808 3.808 3.808 9.98 0 13.788M12 12h.008v.008H12V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                                </svg>
                                                <span class="truncate max-w-[85px]">{{ $vehicle->device->device_name }}</span>
                                            </a>
                                        </div>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('vehicles.show', $vehicle) }}"
                                           class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-slate-700 bg-slate-100 rounded hover:bg-slate-200 transition-colors"
                                           title="View vehicle profile">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                            View
                                        </a>
                                        <a href="{{ route('vehicles.edit', $vehicle) }}"
                                           class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 rounded hover:bg-blue-100 transition-colors"
                                           title="Edit vehicle">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                            </svg>
                                            Edit
                                        </a>
                                        <button type="button"
                                                @click="confirmDelete({{ $vehicle->id }})"
                                                class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-red-700 bg-red-50 rounded hover:bg-red-100 transition-colors cursor-pointer"
                                                title="Delete vehicle">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                    <form id="delete-form-{{ $vehicle->id }}"
                                          method="POST"
                                          action="{{ route('vehicles.destroy', $vehicle) }}"
                                          class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="py-14 px-6 text-center">
                                    <div class="max-w-sm mx-auto space-y-3">
                                        <div class="h-12 w-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                            </svg>
                                        </div>

                                        @if(request()->hasAny(['search', 'vehicle_type_id', 'gps_status', 'status_label', 'location', 'project_code']))
                                            <div>
                                                <h3 class="text-sm font-semibold text-slate-800">No matching vehicles found</h3>
                                                <p class="text-xs text-slate-500 mt-1">No equipment matches your current search filters.</p>
                                            </div>
                                            <a href="{{ route('vehicles.index') }}"
                                               class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-800 text-white text-xs font-semibold rounded-lg hover:bg-slate-900 transition-colors shadow-xs">
                                                Clear All Filters
                                            </a>
                                        @else
                                            <div>
                                                <h3 class="text-sm font-semibold text-slate-800">No vehicles in master list</h3>
                                                <p class="text-xs text-slate-500 mt-1">Get started by creating your first vehicle record or batch importing from an Excel or CSV file.</p>
                                            </div>
                                            <div class="flex items-center justify-center gap-2 pt-1">
                                                <a href="{{ route('vehicles.create') }}"
                                                   class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-xs">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                    </svg>
                                                    Add Vehicle
                                                </a>
                                                <button type="button" @click="showImportModal = true"
                                                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-slate-300 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-50 transition-colors shadow-xs cursor-pointer">
                                                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                                    </svg>
                                                    Import from Excel
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Table Footer / Pagination --}}
            <div class="border-t border-slate-200 px-4 py-3 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-slate-500">
                <div>
                    Showing <span class="font-semibold text-slate-700">{{ $vehicles->firstItem() ?? 0 }}</span>
                    to <span class="font-semibold text-slate-700">{{ $vehicles->lastItem() ?? 0 }}</span>
                    of <span class="font-semibold text-slate-700">{{ $vehicles->total() }}</span> vehicles
                </div>
                @if($vehicles->hasPages())
                    <div>
                        {{ $vehicles->links() }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Lightbox Preview Modal --}}
        <div x-show="lightbox"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click.self="lightbox = false"
             @keydown.escape.window="lightbox = false"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
             style="display: none;">
            <div class="relative max-w-4xl w-full">
                <button @click="lightbox = false" class="absolute -top-10 right-0 text-white/80 hover:text-white transition-colors cursor-pointer">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
                <img :src="lightboxSrc" :alt="lightboxAlt" class="max-h-[85vh] w-full object-contain rounded-lg shadow-2xl">
                <p class="text-center text-white/70 text-sm mt-3 font-semibold" x-text="lightboxAlt"></p>
            </div>
        </div>

        {{-- Quick Image Upload Modal --}}
        <div x-show="imageModal"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             @click.self="imageModal = false"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
             style="display: none;">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6" @click.stop
                 x-data="{ imgPreview: '', imgFileName: '', isUploading: false }">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800">Upload Vehicle Photo</h3>
                            <p class="text-xs text-slate-500">Equipment: <span class="font-semibold text-blue-600" x-text="imageVehicleCode"></span></p>
                        </div>
                    </div>
                    <button type="button" @click="imageModal = false; imgPreview = '';" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form :action="'/vehicles/' + imageVehicleId + '/image'" method="POST" enctype="multipart/form-data" @submit="isUploading = true" class="space-y-4">
                    @csrf
                    <div>
                        <div class="h-40 w-full rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 overflow-hidden flex items-center justify-center mb-3">
                            <img x-show="imgPreview" :src="imgPreview" class="h-full w-full object-contain">
                            <div x-show="!imgPreview" class="text-center p-4">
                                <svg class="mx-auto h-8 w-8 text-slate-300 mb-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                                <p class="text-xs text-slate-500 font-medium">Choose an image file below</p>
                                <p class="text-[11px] text-slate-400">JPG, PNG, GIF up to 5MB</p>
                            </div>
                        </div>

                        <input type="file" name="image" accept="image/*" required
                               @change="const f = $event.target.files[0]; if(f){ imgPreview = URL.createObjectURL(f); imgFileName = f.name; }"
                               class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 file:text-xs file:font-semibold hover:file:bg-blue-100 border border-slate-200 rounded-lg cursor-pointer">
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" @click="imageModal = false; imgPreview = '';"
                                class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" :disabled="isUploading"
                                class="inline-flex items-center gap-1.5 px-5 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors shadow-sm cursor-pointer disabled:opacity-50">
                            <span x-show="!isUploading" class="inline-flex items-center gap-1.5">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                Save Image
                            </span>
                            <span x-show="isUploading" style="display: none;">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Delete Confirmation Modal --}}
        <div x-show="deleteId !== null"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             @click.self="deleteId = null"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
             style="display: none;">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100">
                        <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-800">Delete Vehicle?</h3>
                        <p class="text-sm text-slate-500">This action cannot be undone.</p>
                    </div>
                </div>
                <div class="flex gap-3 justify-end">
                    <button @click="deleteId = null"
                            class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors cursor-pointer">
                        Cancel
                    </button>
                    <button @click="deleteForm.submit()"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors cursor-pointer">
                        Delete
                    </button>
                </div>
            </div>
        </div>

        {{-- Import Data Modal --}}
        <div x-show="showImportModal"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
             style="display: none;"
             @keydown.escape.window="showImportModal = false"
             @click.self="showImportModal = false">

            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl transition-all" @click.stop>
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-800">Import Vehicle Master List</h3>
                            <p class="text-xs text-slate-500">Upload an Excel or CSV file to batch add or update vehicles</p>
                        </div>
                    </div>
                    <button type="button" @click="showImportModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form action="{{ route('vehicles.importData') }}" method="POST" enctype="multipart/form-data" class="mt-5 space-y-4" x-data="{ isSubmitting: false }" @submit="isSubmitting = true">
                    @csrf

                    {{-- Template column info --}}
                    <div class="rounded-xl bg-slate-50 border border-slate-200/80 p-3 text-xs text-slate-600">
                        <div class="font-semibold text-slate-700 mb-1.5 flex items-center justify-between">
                            <span>Supported Template Headers:</span>
                            <a href="{{ route('vehicles.downloadTemplate') }}" class="text-blue-600 hover:text-blue-700 font-semibold inline-flex items-center gap-1 hover:underline">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                Download Template (.xlsx)
                            </a>
                        </div>
                        <div class="flex flex-wrap gap-1 text-[10px] text-slate-600 font-mono">
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5">EQUIPMENT CODE</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5">VEHICLE TYPE</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5">MODEL</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5">PLATE NUMBER</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5">DATE ACQUIRED</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5">FUEL</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5">STATUS</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5">LOCATION</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5">PROJECT CODE</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5">OPERATOR/DRIVER</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5">HELPER</span>
                            <span class="rounded bg-white border border-slate-200 px-1.5 py-0.5">GPS STATUS</span>
                        </div>
                    </div>

                    {{-- File Input --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Select Spreadsheet File (.xlsx, .xls, .csv)</label>
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                               class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-slate-300 rounded-xl cursor-pointer">
                    </div>

                    {{-- Mode Radio --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Import Mode</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                            <label class="flex items-start gap-2 rounded-xl border border-slate-200 p-2.5 cursor-pointer hover:bg-slate-50 has-checked:border-blue-600 has-checked:bg-blue-50/40">
                                <input type="radio" name="mode" value="update" checked class="mt-0.5 text-blue-600 focus:ring-blue-500">
                                <div>
                                    <span class="font-semibold text-slate-800">Update / Add</span>
                                    <p class="text-[10px] text-slate-500 mt-0.5">Update matches by code, add new</p>
                                </div>
                            </label>
                            <label class="flex items-start gap-2 rounded-xl border border-slate-200 p-2.5 cursor-pointer hover:bg-slate-50 has-checked:border-blue-600 has-checked:bg-blue-50/40">
                                <input type="radio" name="mode" value="append" class="mt-0.5 text-blue-600 focus:ring-blue-500">
                                <div>
                                    <span class="font-semibold text-slate-800">Skip Existing</span>
                                    <p class="text-[10px] text-slate-500 mt-0.5">Only add new codes, skip duplicates</p>
                                </div>
                            </label>
                            <label class="flex items-start gap-2 rounded-xl border border-slate-200 p-2.5 cursor-pointer hover:bg-slate-50 has-checked:border-red-600 has-checked:bg-red-50/40">
                                <input type="radio" name="mode" value="replace" class="mt-0.5 text-red-600 focus:ring-red-500">
                                <div>
                                    <span class="font-semibold text-slate-800">Replace All</span>
                                    <p class="text-[10px] text-slate-500 mt-0.5">Clear all vehicles and re-import</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="showImportModal = false"
                                class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" :disabled="isSubmitting"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50 cursor-pointer shadow-sm">
                            <span x-show="!isSubmitting" class="inline-flex items-center gap-1.5">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                                Import Vehicles
                            </span>
                            <span x-show="isSubmitting" style="display: none;" class="inline-flex items-center gap-1.5">
                                <svg class="animate-spin -ml-0.5 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Importing...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>