<x-app-layout>
    @section('page-title', 'Device Details: ' . $device->device_name)
    @section('breadcrumb', 'Fleet Management / GPS Devices / ' . $device->device_name)

    @php
        $badge = $device->expiration_badge;
        $isExpiring = $badge['status'] === 'expiring_soon';
        $isExpired = $badge['status'] === 'expired';
    @endphp

    <div class="max-w-4xl mx-auto space-y-5">
        {{-- Header Bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-1">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center font-extrabold text-lg shadow-sm">
                    {{ strtoupper(substr($device->device_name, 0, 2)) }}
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-black text-slate-900 tracking-tight">{{ $device->device_name }}</h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $badge['bg'] }} {{ $badge['text'] }} {{ $badge['border'] }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $badge['dot'] }} mr-1.5 {{ $badge['pulse'] ? 'animate-ping' : '' }}"></span>
                            {{ $badge['label'] }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 font-mono mt-0.5">IMEI: {{ $device->imei ?: 'Not assigned' }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('devices.edit', $device) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-blue-700 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                    <span>Edit Device</span>
                </a>
                <a href="{{ route('devices.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition-colors">
                    &larr; Back to Devices
                </a>
            </div>
        </div>

        {{-- Expiration Alert Card (If expiring soon or expired) --}}
        @if($isExpiring || $isExpired)
            <div class="rounded-2xl border {{ $isExpired ? 'border-red-200 bg-red-50/50' : 'border-amber-200 bg-amber-50/50' }} p-5 shadow-xs">
                <div class="flex items-start gap-4">
                    <div class="h-10 w-10 shrink-0 rounded-xl {{ $isExpired ? 'bg-red-600' : 'bg-amber-600' }} text-white flex items-center justify-center shadow-sm">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold {{ $isExpired ? 'text-red-900' : 'text-amber-900' }}">
                                {{ $isExpired ? 'GPS Device Subscription Expired' : 'Action Required: Upcoming Expiration in 30 Days' }}
                            </h3>
                            <span class="rounded-full {{ $isExpired ? 'bg-red-600' : 'bg-amber-600' }} px-2 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider">
                                Budget Request Needed
                            </span>
                        </div>
                        <p class="text-xs {{ $isExpired ? 'text-red-700' : 'text-amber-700' }} mt-1">
                            @if($isExpired)
                                The subscription for this device expired on <strong>{{ $device->expiration_date ? $device->expiration_date->format('F d, Y') : 'Unknown Date' }}</strong>. Tracking may be interrupted or halted. Request company budget to renew this unit immediately.
                            @else
                                This device will expire on <strong>{{ $device->expiration_date->format('F d, Y') }}</strong> (<strong>{{ $device->days_until_expiration }} days remaining</strong>). Please coordinate with management and prepare budgeting for GPS subscription renewal.
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- ==================== LINKED VEHICLE SECTION ==================== --}}
        @if($device->vehicle)
            @php
                $v = $device->vehicle;
            @endphp
            <div class="rounded-2xl border border-emerald-200 bg-gradient-to-r from-emerald-50/50 via-white to-white p-6 shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-emerald-100">
                    <div class="flex items-center gap-3">
                        <div class="h-11 w-11 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-xs">
                            <span class="text-xl">🚗</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Auto-Linked Vehicle</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold {{ $v->gps_status_color['bg'] }} {{ $v->gps_status_color['text'] }}">
                                    GPS Status: {{ $v->gps_status_label }}
                                </span>
                            </div>
                            <h3 class="text-base font-extrabold text-slate-900 mt-0.5">
                                {{ $v->equipment_code }}
                                @if($v->plate_number)
                                    <span class="text-slate-500 font-normal text-xs ml-1">({{ $v->plate_number }})</span>
                                @endif
                            </h3>
                        </div>
                    </div>

                    <div>
                        <a href="{{ route('vehicles.show', $v) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-slate-300 px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 hover:text-blue-600 shadow-xs transition-colors">
                            <span>View Vehicle Profile</span>
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    </div>
                </div>

                {{-- Vehicle Info Grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4 text-xs">
                    <div class="p-3 bg-white rounded-xl border border-slate-200/70 shadow-2xs">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Equipment Code</span>
                        <span class="font-bold text-slate-800 text-xs block mt-0.5">{{ $v->equipment_code }}</span>
                    </div>

                    <div class="p-3 bg-white rounded-xl border border-slate-200/70 shadow-2xs">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Plate Number</span>
                        <span class="font-mono font-bold text-slate-800 text-xs block mt-0.5">{{ $v->plate_number ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-white rounded-xl border border-slate-200/70 shadow-2xs">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Vehicle Type</span>
                        <span class="font-bold text-slate-800 text-xs block mt-0.5">{{ $v->vehicleType?->name ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-white rounded-xl border border-slate-200/70 shadow-2xs">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Model</span>
                        <span class="font-bold text-slate-800 text-xs block mt-0.5">{{ $v->model ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-white rounded-xl border border-slate-200/70 shadow-2xs">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Assigned Driver</span>
                        <span class="font-medium text-slate-800 text-xs block mt-0.5">{{ $v->operator_driver ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-white rounded-xl border border-slate-200/70 shadow-2xs">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Location</span>
                        <span class="font-medium text-slate-800 text-xs block mt-0.5">{{ $v->location ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-white rounded-xl border border-slate-200/70 shadow-2xs">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Project Code</span>
                        <span class="font-mono text-slate-800 text-xs block mt-0.5">{{ $v->project_code ?: '-' }}</span>
                    </div>

                    <div class="p-3 bg-white rounded-xl border border-slate-200/70 shadow-2xs">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Operating Status</span>
                        <span class="font-bold text-slate-800 text-xs block mt-0.5">{{ $v->status_display ?: '-' }}</span>
                    </div>
                </div>
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/80 p-5 text-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-slate-200 text-slate-500 flex items-center justify-center shrink-0">
                        <span class="text-base">🚗</span>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-800">No Vehicle Linked</h4>
                        <p class="text-slate-500 mt-0.5">
                            This device name (<strong>{{ $device->device_name }}</strong>) has not been linked to a vehicle record yet. Click below to synchronize or add a matching vehicle.
                        </p>
                    </div>
                </div>
                <form action="{{ route('devices.syncVehicles') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 font-bold hover:underline shrink-0 cursor-pointer">
                        <span>Sync with Vehicles</span>
                        &rarr;
                    </button>
                </form>
            </div>
        @endif

        {{-- Main Specs Card --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-800">Hardware & Telemetry Specifications</h3>
                <span class="text-xs text-slate-400">Created: {{ $device->created_at->format('M d, Y') }}</span>
            </div>

            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 text-xs">
                {{-- Device Name --}}
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Device Name</span>
                    <span class="block text-sm font-extrabold text-slate-800 mt-0.5">{{ $device->device_name }}</span>
                </div>

                {{-- IMEI --}}
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">IMEI</span>
                    <span class="block text-sm font-mono font-bold text-slate-800 mt-0.5">{{ $device->imei ?: '-' }}</span>
                </div>

                {{-- Model --}}
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Model</span>
                    <span class="block text-sm font-bold text-slate-800 mt-0.5">{{ $device->model ?: '-' }}</span>
                </div>

                {{-- SIM --}}
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">SIM Phone Number</span>
                    <span class="block text-sm font-mono font-bold text-slate-800 mt-0.5">{{ $device->sim ?: '-' }}</span>
                </div>

                {{-- Expiration Date --}}
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">User Expiration Date</span>
                    <span class="block text-sm font-bold text-slate-800 mt-0.5">
                        {{ $device->expiration_date ? $device->expiration_date->format('Y-m-d') : ($device->raw_expiration ?: '-') }}
                    </span>
                    @if($device->raw_expiration && $device->raw_expiration !== $device->expiration_date?->format('Y-m-d'))
                        <span class="block text-[10px] text-slate-400 mt-0.5">Raw: {{ $device->raw_expiration }}</span>
                    @endif
                </div>

                {{-- Group --}}
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Assigned Group</span>
                    <span class="block text-sm font-bold text-slate-800 mt-0.5">{{ $device->group_name ?: '-' }}</span>
                </div>

                {{-- Activated Date --}}
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Activated Date</span>
                    <span class="block text-sm font-medium text-slate-700 mt-0.5">
                        {{ $device->activated_date ? $device->activated_date->format('F d, Y') : '-' }}
                    </span>
                </div>

                {{-- Sales Time --}}
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Sales Time</span>
                    <span class="block text-sm font-medium text-slate-700 mt-0.5">
                        {{ $device->sales_time ? $device->sales_time->format('F d, Y') : '-' }}
                    </span>
                </div>

                {{-- Mileage --}}
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Mileage</span>
                    <span class="block text-sm font-mono font-bold text-slate-800 mt-0.5">
                        {{ $device->mileage !== null ? number_format($device->mileage, 2).' km' : '-' }}
                    </span>
                </div>

                {{-- ICCID --}}
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 sm:col-span-2">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">ICCID (SIM Card Serial)</span>
                    <span class="block text-sm font-mono text-slate-700 mt-0.5">{{ $device->iccid ?: '-' }}</span>
                </div>

                {{-- IMSI --}}
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">IMSI</span>
                    <span class="block text-sm font-mono text-slate-700 mt-0.5">{{ $device->imsi ?: '-' }}</span>
                </div>
            </div>

            @if($device->notes)
                <div class="px-6 pb-6">
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Notes & History</span>
                        <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-line">{{ $device->notes }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
