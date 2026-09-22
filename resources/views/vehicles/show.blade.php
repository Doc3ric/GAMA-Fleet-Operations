<x-app-layout>
    @section('page-title', 'Vehicle: ' . $vehicle->equipment_code)
    @section('breadcrumb', 'Fleet Management / Vehicle Master List / ' . $vehicle->equipment_code)

    <div class="max-w-5xl mx-auto space-y-6 pb-16">
        {{-- Header Bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-1">
            <div class="flex items-center gap-3">
                @if($vehicle->image_url)
                    <img src="{{ $vehicle->image_url }}" alt="{{ $vehicle->equipment_code }}"
                         class="h-12 w-16 object-cover rounded-xl border border-slate-200 shadow-xs">
                @else
                    <div class="h-12 w-12 rounded-xl bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center font-black text-base shadow-xs">
                        {{ strtoupper(substr($vehicle->equipment_code, 0, 2)) }}
                    </div>
                @endif
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h2 class="text-xl font-black text-slate-900 tracking-tight">{{ $vehicle->equipment_code }}</h2>
                        @if($vehicle->plate_number)
                            <span class="inline-flex items-center px-2 py-0.5 rounded font-mono text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                {{ $vehicle->plate_number }}
                            </span>
                        @endif
                        @if($vehicle->vehicleType)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                {{ $vehicle->vehicleType->name }}
                            </span>
                        @endif
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $vehicle->gps_status_color['bg'] }} {{ $vehicle->gps_status_color['text'] }}">
                            GPS: {{ $vehicle->gps_status_label }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ $vehicle->model ?: 'Model unspecified' }} &bull; Location: {{ $vehicle->location ?: 'Unassigned' }} &bull; Project: {{ $vehicle->project_code ?: 'None' }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('vehicles.edit', $vehicle) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-blue-700 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                    <span>Edit Vehicle</span>
                </a>
                <a href="{{ route('vehicles.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition-colors">
                    &larr; Back to Vehicles
                </a>
            </div>
        </div>

        {{-- ==================== LINKED GPS TRACKER SECTION ==================== --}}
        @if($vehicle->device)
            @php
                $device = $vehicle->device;
                $badge = $device->expiration_badge;
                $isExpiring = $badge['status'] === 'expiring_soon';
                $isExpired = $badge['status'] === 'expired';
            @endphp
            <div class="rounded-2xl border {{ $isExpired ? 'border-red-300 bg-red-50/40' : ($isExpiring ? 'border-amber-300 bg-amber-50/40' : 'border-blue-200 bg-gradient-to-r from-blue-50/50 via-white to-white') }} p-6 shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200/70">
                    <div class="flex items-center gap-3">
                        <div class="h-11 w-11 rounded-xl {{ $isExpired ? 'bg-red-600' : ($isExpiring ? 'bg-amber-600' : 'bg-blue-600') }} text-white flex items-center justify-center shadow-xs">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.122a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M5.106 18.894c-3.808-3.808-3.808-9.98 0-13.789m13.788 0c3.808 3.808 3.808 9.98 0 13.788M12 12h.008v.008H12V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Auto-Linked GPS Tracker</span>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $badge['bg'] }} {{ $badge['text'] }} {{ $badge['border'] }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $badge['dot'] }} {{ $badge['pulse'] ? 'animate-ping' : '' }}"></span>
                                    {{ $badge['label'] }}
                                </span>
                            </div>
                            <h3 class="text-base font-extrabold text-slate-900 mt-0.5">{{ $device->device_name }}</h3>
                        </div>
                    </div>

                    <div>
                        <a href="{{ route('devices.show', $device) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-slate-300 px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 hover:text-blue-600 shadow-xs transition-colors">
                            <span>Open Device Page</span>
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    </div>
                </div>

                {{-- Tracker Spec Grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4 text-xs">
                    <div class="p-3 bg-white/80 rounded-xl border border-slate-200/70 shadow-2xs">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">IMEI</span>
                        <span class="font-mono font-bold text-slate-800 text-xs block mt-0.5">{{ $device->imei ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-white/80 rounded-xl border border-slate-200/70 shadow-2xs">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Tracker Model</span>
                        <span class="font-bold text-slate-800 text-xs block mt-0.5">{{ $device->model ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-white/80 rounded-xl border border-slate-200/70 shadow-2xs">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">SIM Phone No.</span>
                        <span class="font-mono font-bold text-slate-800 text-xs block mt-0.5">{{ $device->sim ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-white/80 rounded-xl border border-slate-200/70 shadow-2xs">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">GPS Mileage</span>
                        <span class="font-mono font-bold text-slate-800 text-xs block mt-0.5">
                            {{ $device->mileage !== null ? number_format($device->mileage, 2).' km' : '-' }}
                        </span>
                    </div>

                    <div class="p-3 bg-white/80 rounded-xl border border-slate-200/70 shadow-2xs">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Expiration Date</span>
                        <span class="font-bold text-slate-800 text-xs block mt-0.5">
                            {{ $device->expiration_date ? $device->expiration_date->format('F d, Y') : ($device->raw_expiration ?: '-') }}
                        </span>
                    </div>

                    <div class="p-3 bg-white/80 rounded-xl border border-slate-200/70 shadow-2xs">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Tracksolid Group</span>
                        <span class="font-bold text-slate-800 text-xs block mt-0.5">{{ $device->group_name ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-white/80 rounded-xl border border-slate-200/70 shadow-2xs sm:col-span-2">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">ICCID (SIM Serial)</span>
                        <span class="font-mono text-slate-700 text-xs block mt-0.5 truncate">{{ $device->iccid ?: '-' }}</span>
                    </div>
                </div>

                @if($isExpiring || $isExpired)
                    <div class="mt-4 p-3 rounded-xl {{ $isExpired ? 'bg-red-100/70 text-red-800' : 'bg-amber-100/70 text-amber-900' }} text-xs flex items-center gap-2">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <span>
                            @if($isExpired)
                                <strong>GPS Subscription Expired:</strong> Vehicle status automatically flipped to <strong>EXPIRED</strong>. Immediate budget allocation required for renewal.
                            @else
                                <strong>Upcoming Expiration:</strong> Expires in <strong>{{ $device->days_until_expiration }} days</strong>. Budget renewal request should be submitted to management now.
                            @endif
                        </span>
                    </div>
                @endif
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/80 p-5 text-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-slate-200 text-slate-500 flex items-center justify-center shrink-0">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-800">No GPS Tracker Linked</h4>
                        <p class="text-slate-500 mt-0.5">
                            This vehicle is not currently paired with a Tracksolid device. Once a device matching code <strong>{{ $vehicle->equipment_code }}</strong> or plate <strong>{{ $vehicle->plate_number ?: 'N/A' }}</strong> is imported, it will auto-link.
                        </p>
                    </div>
                </div>
                <a href="{{ route('devices.index') }}"
                   class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 font-bold hover:underline shrink-0">
                    <span>Manage Devices</span>
                    &rarr;
                </a>
            </div>
        @endif

        {{-- ==================== VEHICLE SPECIFICATIONS CARD ==================== --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-800">Vehicle Master Profile</h3>
                <span class="text-xs text-slate-400">Added: {{ $vehicle->created_at->format('M d, Y') }}</span>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- Vehicle Photo / Upload --}}
                <div class="md:col-span-1 flex flex-col items-center">
                    @if($vehicle->image_url)
                        <img src="{{ $vehicle->image_url }}" alt="{{ $vehicle->equipment_code }}"
                             class="w-full h-48 object-cover rounded-xl border border-slate-200 shadow-xs">
                    @else
                        <div class="w-full h-48 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 flex flex-col items-center justify-center text-slate-400">
                            <svg class="h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                            <span class="text-xs font-medium mt-2">No photo available</span>
                        </div>
                    @endif

                    <form action="{{ route('vehicles.uploadImage', $vehicle) }}" method="POST" enctype="multipart/form-data" class="w-full mt-3">
                        @csrf
                        <label class="w-full block text-center cursor-pointer px-3 py-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 transition-colors">
                            <span>{{ $vehicle->image ? 'Change Photo' : 'Upload Photo' }}</span>
                            <input type="file" name="image" accept="image/*" class="hidden" onchange="this.form.submit()">
                        </label>
                    </form>
                </div>

                {{-- Specs Grid --}}
                <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Equipment Code</span>
                        <span class="text-sm font-extrabold text-slate-900 mt-0.5 block">{{ $vehicle->equipment_code }}</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Plate Number</span>
                        <span class="text-sm font-mono font-bold text-slate-900 mt-0.5 block">{{ $vehicle->plate_number ?: 'Not assigned' }}</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Vehicle Type</span>
                        <span class="text-sm font-bold text-slate-800 mt-0.5 block">{{ $vehicle->vehicleType?->name ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Model / Make</span>
                        <span class="text-sm font-bold text-slate-800 mt-0.5 block">{{ $vehicle->model ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Operational Status</span>
                        <span class="text-sm font-bold text-slate-800 mt-0.5 block">{{ $vehicle->status_display ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Fuel Rate Standard</span>
                        <span class="text-sm font-bold text-slate-800 mt-0.5 block">{{ $vehicle->fuel_display ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Assigned Location</span>
                        <span class="text-sm font-medium text-slate-800 mt-0.5 block">{{ $vehicle->location ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Project Code</span>
                        <span class="text-sm font-mono font-medium text-slate-800 mt-0.5 block">{{ $vehicle->project_code ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Assigned Driver / Operator</span>
                        <span class="text-sm font-medium text-slate-800 mt-0.5 block">{{ $vehicle->operator_driver ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Helper / Crew</span>
                        <span class="text-sm font-medium text-slate-800 mt-0.5 block">{{ $vehicle->helper ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 sm:col-span-2">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Date Acquired</span>
                        <span class="text-sm font-medium text-slate-800 mt-0.5 block">
                            {{ $vehicle->date_acquired ? $vehicle->date_acquired->format('F d, Y') : '-' }}
                        </span>
                    </div>
                </div>
            </div>

            @if($vehicle->notes)
                <div class="px-6 pb-6">
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Notes</span>
                        <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-line">{{ $vehicle->notes }}</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- ==================== FUEL CONSUMPTION HISTORY ==================== --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-1.5 rounded-lg bg-emerald-50 text-emerald-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Average Fuel Consumption History</h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Full-Tank baseline tests recorded for this vehicle</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('fuel-consumption.create', ['vehicle_id' => $vehicle->id]) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-colors shadow-2xs">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        + Record Test
                    </a>
                </div>
            </div>

            @if($vehicle->fuelConsumptionTests && $vehicle->fuelConsumptionTests->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                                <th class="py-2.5 px-4">Date</th>
                                <th class="py-2.5 px-4">Driver / Operator</th>
                                <th class="py-2.5 px-3 text-right">Distance</th>
                                <th class="py-2.5 px-3 text-right">Fuel Consumed</th>
                                <th class="py-2.5 px-4 text-center">Avg Consumption</th>
                                <th class="py-2.5 px-4">Route</th>
                                <th class="py-2.5 px-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($vehicle->fuelConsumptionTests as $fTest)
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="py-3 px-4 font-medium text-slate-700 whitespace-nowrap">
                                        {{ $fTest->test_date?->format('M d, Y') }}
                                    </td>
                                    <td class="py-3 px-4 text-slate-800 font-medium">
                                        {{ $fTest->driver_display_name }}
                                    </td>
                                    <td class="py-3 px-3 text-right font-mono font-bold text-slate-900">
                                        {{ number_format($fTest->distance_travelled, 2) }} km
                                    </td>
                                    <td class="py-3 px-3 text-right font-mono text-slate-700">
                                        {{ number_format($fTest->fuel_consumed_liters, 3) }} L
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-mono font-black bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            {{ number_format($fTest->average_fuel_consumption, 2) }} km/L
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-slate-600 truncate max-w-[180px]">
                                        {{ $fTest->test_route ?: '—' }}
                                    </td>
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        <a href="{{ route('fuel-consumption.show', $fTest) }}"
                                           class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100 transition-colors"
                                           title="View Details">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                            <span>View</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-8 text-center text-slate-400">
                    <svg class="h-8 w-8 mx-auto text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                    </svg>
                    <p class="text-xs font-semibold text-slate-600">No fuel consumption tests recorded yet</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Run a Full-Tank test to establish a fuel performance benchmark for this vehicle.</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

