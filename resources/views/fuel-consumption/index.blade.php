<x-app-layout>
    @section('page-title', 'Average Fuel Consumption')
    @section('page-subtitle', 'Full-Tank Testing & Fleet Fuel Economy')
    @section('breadcrumb', 'Fleet Reports / Average Fuel Consumption')

    <div class="space-y-6 pb-16">

        {{-- Top Summary KPI Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
            {{-- Total Tests --}}
            <div class="rounded-2xl bg-white p-4 border border-slate-200 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-[10px] uppercase font-bold tracking-wider">Total Tests</span>
                    <div class="p-1.5 rounded-lg bg-blue-50 text-blue-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2">
                    <span class="text-2xl font-black text-slate-900">{{ number_format($totalTests) }}</span>
                    <span class="text-[11px] text-slate-400 block mt-0.5">Recorded tests</span>
                </div>
            </div>

            {{-- Fleet Average KM/L --}}
            <div class="rounded-2xl bg-white p-4 border border-slate-200 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-[10px] uppercase font-bold tracking-wider">Average KM/L</span>
                    <div class="p-1.5 rounded-lg bg-emerald-50 text-emerald-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2">
                    <span class="text-2xl font-black text-emerald-600">{{ $avgKmL > 0 ? number_format($avgKmL, 2) : '—' }}</span>
                    <span class="text-[11px] text-slate-400 block mt-0.5">km/L across tests</span>
                </div>
            </div>

            {{-- Best KM/L --}}
            <div class="rounded-2xl bg-white p-4 border border-slate-200 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-[10px] uppercase font-bold tracking-wider">Best Economy</span>
                    <div class="p-1.5 rounded-lg bg-blue-50 text-blue-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2">
                    <span class="text-2xl font-black text-blue-700">{{ $bestKmL > 0 ? number_format($bestKmL, 2) : '—' }}</span>
                    <span class="text-[11px] text-slate-400 block mt-0.5">Highest km/L</span>
                </div>
            </div>

            {{-- Lowest KM/L --}}
            <div class="rounded-2xl bg-white p-4 border border-slate-200 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-[10px] uppercase font-bold tracking-wider">Lowest Economy</span>
                    <div class="p-1.5 rounded-lg bg-amber-50 text-amber-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6 9 12.75l4.286-4.286a11.948 11.948 0 0 1 4.306 6.43l.776 2.898m0 0 3.182-5.511m-3.182 5.51-5.511-3.181" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2">
                    <span class="text-2xl font-black text-slate-800">{{ $lowestKmL > 0 ? number_format($lowestKmL, 2) : '—' }}</span>
                    <span class="text-[11px] text-slate-400 block mt-0.5">Lowest km/L</span>
                </div>
            </div>

            {{-- Total Distance Tested --}}
            <div class="rounded-2xl bg-white p-4 border border-slate-200 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-[10px] uppercase font-bold tracking-wider">Total Distance</span>
                    <div class="p-1.5 rounded-lg bg-indigo-50 text-indigo-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2">
                    <span class="text-xl font-black text-slate-900">{{ number_format($totalDistance, 1) }}</span>
                    <span class="text-[11px] text-slate-400 block mt-0.5">kilometers driven</span>
                </div>
            </div>

            {{-- Total Fuel Consumed --}}
            <div class="rounded-2xl bg-white p-4 border border-slate-200 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-[10px] uppercase font-bold tracking-wider">Fuel Consumed</span>
                    <div class="p-1.5 rounded-lg bg-red-50 text-red-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2">
                    <span class="text-xl font-black text-slate-900">{{ number_format($totalFuel, 2) }}</span>
                    <span class="text-[11px] text-slate-400 block mt-0.5">liters refilled</span>
                </div>
            </div>
        </div>

        {{-- Filter & Action Bar --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
            <form method="GET" action="{{ route('fuel-consumption.index') }}" class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 flex-1">
                    {{-- Search Input --}}
                    <div>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Search vehicle, route, driver..."
                               class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-slate-800 placeholder-slate-400 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>

                    {{-- Vehicle Filter --}}
                    <div>
                        <select name="vehicle_id" class="w-full text-xs rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <option value="">All Vehicles</option>
                            @foreach($vehicles as $veh)
                                <option value="{{ $veh->id }}" {{ request('vehicle_id') == $veh->id ? 'selected' : '' }}>
                                    {{ $veh->equipment_code }} {{ $veh->plate_number ? '('.$veh->plate_number.')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Date Filter / Buttons --}}
                    <div class="flex items-center gap-2">
                        <input type="date" name="start_date" value="{{ request('start_date') }}"
                               class="w-1/2 text-xs rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-2 text-slate-700">
                        <input type="date" name="end_date" value="{{ request('end_date') }}"
                               class="w-1/2 text-xs rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-2 text-slate-700">
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-800 text-white rounded-xl text-xs font-semibold hover:bg-slate-700 transition-colors shadow-2xs">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        Filter
                    </button>

                    @if(request()->hasAny(['search', 'vehicle_id', 'start_date', 'end_date']))
                        <a href="{{ route('fuel-consumption.index') }}" class="px-3 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                            Reset
                        </a>
                    @endif

                    {{-- Export Menu --}}
                    <a href="{{ route('fuel-consumption.exportExcel', request()->query()) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-colors shadow-2xs">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Excel
                    </a>

                    <a href="{{ route('fuel-consumption.exportAllPdf', request()->query()) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold transition-colors shadow-2xs">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        PDF
                    </a>

                    {{-- Add New Button --}}
                    <a href="{{ route('fuel-consumption.create') }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-colors shadow-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        + Record Fuel Test
                    </a>
                </div>
            </form>
        </div>

        {{-- Main Table Card --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Vehicle / Equipment</th>
                            <th class="py-3 px-4">Driver / Operator</th>
                            <th class="py-3 px-3 text-right">Start Odo</th>
                            <th class="py-3 px-3 text-right">End Odo</th>
                            <th class="py-3 px-3 text-right">Distance</th>
                            <th class="py-3 px-3 text-right">Fuel Consumed</th>
                            <th class="py-3 px-4 text-center">Avg Consumption</th>
                            <th class="py-3 px-4">Route / Status</th>
                            <th class="py-3 px-4 text-center">Evidence</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($tests as $test)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                {{-- Date --}}
                                <td class="py-3.5 px-4 whitespace-nowrap font-medium text-slate-700">
                                    {{ $test->test_date?->format('M d, Y') }}
                                </td>

                                {{-- Vehicle --}}
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-2">
                                        <div class="h-8 w-8 rounded-lg bg-blue-50 text-blue-600 font-black text-[11px] flex items-center justify-center shrink-0 border border-blue-100">
                                            {{ strtoupper(substr($test->equipment_code_display, 0, 2)) }}
                                        </div>
                                        <div>
                                            @if($test->vehicle_id)
                                                <a href="{{ route('vehicles.show', $test->vehicle_id) }}" class="font-bold text-slate-900 hover:text-blue-600 transition-colors">
                                                    {{ $test->equipment_code_display }}
                                                </a>
                                            @else
                                                <span class="font-bold text-slate-900">{{ $test->equipment_code_display }}</span>
                                            @endif
                                            <div class="text-[11px] text-slate-400 font-mono">
                                                {{ $test->plate_number_display ?: ($test->model_display ?: 'No plate') }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Driver --}}
                                <td class="py-3.5 px-4 text-slate-700">
                                    <span class="font-medium block">{{ $test->driver_display_name }}</span>
                                </td>

                                {{-- Start Odometer --}}
                                <td class="py-3.5 px-3 text-right font-mono text-slate-600">
                                    {{ number_format($test->start_odometer, 2) }}
                                </td>

                                {{-- End Odometer --}}
                                <td class="py-3.5 px-3 text-right font-mono text-slate-600">
                                    {{ number_format($test->end_odometer, 2) }}
                                </td>

                                {{-- Distance Travelled --}}
                                <td class="py-3.5 px-3 text-right font-mono font-bold text-slate-900">
                                    {{ number_format($test->distance_travelled, 2) }} km
                                </td>

                                {{-- Fuel Consumed (2nd Full Tank) --}}
                                <td class="py-3.5 px-3 text-right font-mono font-bold text-slate-700">
                                    {{ number_format($test->fuel_consumed_liters, 3) }} L
                                </td>

                                {{-- Average Fuel Consumption --}}
                                <td class="py-3.5 px-4 text-center">
                                    @php
                                        $kmL = (float) $test->average_fuel_consumption;
                                        $badgeColor = $kmL >= 10.0
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                            : ($kmL >= 6.0 ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-amber-50 text-amber-700 border-amber-200');
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black border {{ $badgeColor }} shadow-2xs font-mono">
                                        {{ number_format($kmL, 2) }} km/L
                                    </span>
                                </td>

                                {{-- Route / Status --}}
                                <td class="py-3.5 px-4">
                                    <div class="max-w-[200px]">
                                        <p class="truncate text-slate-700 font-medium" title="{{ $test->test_route }}">{{ $test->test_route ?: 'Unspecified route' }}</p>
                                        @if($test->is_short_distance)
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200 mt-1"
                                                  title="{{ $test->short_distance_warning }}">
                                                <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                                </svg>
                                                Short Dist ({{ number_format($test->distance_travelled, 1) }} km)
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Evidence Icons --}}
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1 text-slate-400">
                                        <span title="Start Odometer Photo" class="{{ $test->start_odometer_image ? 'text-blue-600' : 'text-slate-200' }}">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" /></svg>
                                        </span>
                                        <span title="End Odometer Photo" class="{{ $test->end_odometer_image ? 'text-indigo-600' : 'text-slate-200' }}">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                                        </span>
                                        <span title="Fuel Receipt" class="{{ $test->fuel_receipt_image ? 'text-emerald-600' : 'text-slate-200' }}">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                        </span>
                                    </div>
                                </td>

                                {{-- Actions --}}
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        {{-- View --}}
                                        <a href="{{ route('fuel-consumption.show', $test) }}"
                                           class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100 transition-colors"
                                           title="View Details">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                            <span>View</span>
                                        </a>

                                        {{-- PDF --}}
                                        <a href="{{ route('fuel-consumption.exportPdf', $test) }}"
                                           class="inline-flex items-center gap-1 rounded-lg bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition-colors"
                                           title="Download PDF Certificate">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                            <span>PDF</span>
                                        </a>

                                        {{-- Edit --}}
                                        <a href="{{ route('fuel-consumption.edit', $test) }}"
                                           class="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-100 transition-colors"
                                           title="Edit Record">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                            </svg>
                                            <span>Edit</span>
                                        </a>

                                        {{-- Delete --}}
                                        <form method="POST" action="{{ route('fuel-consumption.destroy', $test) }}"
                                              onsubmit="return confirm('Are you sure you want to delete this fuel consumption test for {{ $test->vehicle?->equipment_code }}? This action cannot be undone.');"
                                              class="inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 rounded-lg bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 transition-colors cursor-pointer"
                                                    title="Delete Record">
                                                <svg class="h-3.5 w-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                </svg>
                                                <span>Delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="py-12 text-center text-slate-400">
                                    <div class="max-w-sm mx-auto">
                                        <div class="h-12 w-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mx-auto mb-3">
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-sm font-bold text-slate-800">No fuel consumption tests found</h3>
                                        <p class="text-xs text-slate-500 mt-1 mb-4">Record your first Full-Tank fuel test to monitor vehicle economy and baseline new acquisitions.</p>
                                        <a href="{{ route('fuel-consumption.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700 transition-colors shadow-sm">
                                            + Record Fuel Test
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($tests->hasPages())
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
                    {{ $tests->links() }}
                </div>
            @endif
        </div>

    </div>
</x-app-layout>
