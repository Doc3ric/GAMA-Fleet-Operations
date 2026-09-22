<x-app-layout>
    @section('page-title', 'Weekly Itinerary Report')
    @section('page-subtitle', 'Consolidated Driver Trip Logs')
    @section('breadcrumb')
        <a href="{{ route('itineraries.index') }}" class="hover:underline">Driver Itinerary</a> &bull; Weekly Report
    @endsection

    <div class="space-y-6">

        {{-- Controls & Date Filter --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-800">Generate Itinerary Report</h2>
                    <p class="text-xs text-slate-400">Select reporting period and scope to generate official consolidated log.</p>
                </div>

                {{-- Action Export Buttons --}}
                <div class="flex items-center gap-2">
                    <a href="{{ route('itineraries.exportPdf', request()->all()) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white hover:bg-rose-700 transition-colors shadow-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24-1.04-.54-2.14-.92-3.14m0 0C5.1 8.87 4.16 7.4 3 6m2.8 4.69c.8 2.06 1.83 4.24 3.03 6.31m0 0c1.47 2.53 3.09 4.3 4.87 5m-4.87-5c1.49-.69 3.08-1.55 4.71-2.52m0 0c2.81-1.68 5.4-3.69 7.46-5.88M15.7 13.48c-.68-.86-1.42-1.74-2.22-2.61m0 0C12.16 9.4 10.64 8 9 7m4.48 3.87c.72 1.48 1.5 2.99 2.32 4.47" />
                        </svg>
                        Download PDF
                    </a>
                    <a href="{{ route('itineraries.exportExcel', request()->all()) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition-colors shadow-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>
                        Download Excel
                    </a>
                </div>
            </div>

            <form method="GET" action="{{ route('itineraries.report') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 pt-2">
                {{-- Date From --}}
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Period From</label>
                    <input type="date" name="start_date" value="{{ $start_date->format('Y-m-d') }}"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>

                {{-- Date To --}}
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Period To</label>
                    <input type="date" name="end_date" value="{{ $end_date->format('Y-m-d') }}"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>

                {{-- Driver --}}
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Driver (Optional)</label>
                    <select name="driver_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                        <option value="">All Drivers</option>
                        @foreach($drivers as $d)
                            <option value="{{ $d->id }}" {{ ($options['driver_id'] ?? '') == $d->id ? 'selected' : '' }}>
                                {{ $d->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Vehicle --}}
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Vehicle (Optional)</label>
                    <select name="vehicle_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-800 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                        <option value="">All Vehicles</option>
                        @foreach($vehicles as $v)
                            <option value="{{ $v->id }}" {{ ($options['vehicle_id'] ?? '') == $v->id ? 'selected' : '' }}>
                                {{ $v->equipment_code }} {{ $v->plate_number ? "({$v->plate_number})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Quick Presets & Submit --}}
                <div class="sm:col-span-2 md:col-span-4 flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-400">Quick Presets:</span>
                        <a href="{{ route('itineraries.report', ['start_date' => now()->startOfWeek(\Carbon\Carbon::MONDAY)->format('Y-m-d'), 'end_date' => now()->endOfWeek(\Carbon\Carbon::SUNDAY)->format('Y-m-d')]) }}"
                           class="rounded-lg bg-slate-100 hover:bg-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 transition-colors">
                            Current Week
                        </a>
                        <a href="{{ route('itineraries.report', ['start_date' => now()->subWeek()->startOfWeek(\Carbon\Carbon::MONDAY)->format('Y-m-d'), 'end_date' => now()->subWeek()->endOfWeek(\Carbon\Carbon::SUNDAY)->format('Y-m-d')]) }}"
                           class="rounded-lg bg-slate-100 hover:bg-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 transition-colors">
                            Last Week
                        </a>
                        <a href="{{ route('itineraries.report', ['start_date' => now()->startOfMonth()->format('Y-m-d'), 'end_date' => now()->endOfMonth()->format('Y-m-d')]) }}"
                           class="rounded-lg bg-slate-100 hover:bg-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 transition-colors">
                            This Month
                        </a>
                    </div>

                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-5 py-2 text-xs font-bold text-white hover:bg-blue-700 transition-colors shadow-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Generate Report
                    </button>
                </div>
            </form>
        </div>

        {{-- Printable Report Preview Card --}}
        <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-8 space-y-6">

            {{-- Document Header --}}
            <div class="border-b-2 border-slate-900 pb-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-xl font-black uppercase tracking-wider text-slate-900">
                            {{ config('foms.company_name', 'GAMA FOODS CORPORATION') }}
                        </h1>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-widest mt-0.5">
                            {{ config('foms.company_tagline', 'Fleet Operations Management System') }}
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-bold uppercase tracking-wider text-blue-700">Official Report</div>
                        <div class="text-xs text-slate-400 mt-0.5">Generated: {{ now()->format('F j, Y H:i:s') }}</div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">DRIVER ITINERARY REPORT</h2>
                        <p class="text-xs font-semibold text-slate-600 mt-0.5">
                            Period: <span class="text-blue-700">{{ $start_date->format('F j, Y') }} &ndash; {{ $end_date->format('F j, Y') }}</span>
                        </p>
                    </div>
                    @if($driver || $vehicle)
                        <div class="flex items-center gap-2 text-xs">
                            @if($driver)
                                <span class="rounded-lg bg-blue-50 border border-blue-200 px-2.5 py-1 font-semibold text-blue-700">
                                    Driver: {{ $driver->name }}
                                </span>
                            @endif
                            @if($vehicle)
                                <span class="rounded-lg bg-slate-100 border border-slate-200 px-2.5 py-1 font-semibold text-slate-700">
                                    Vehicle: {{ $vehicle->equipment_code }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- Summary Metrics Bar --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 border border-slate-200 rounded-xl p-4 text-center">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Trips</div>
                    <div class="text-xl font-black text-slate-900 mt-0.5">{{ number_format($statistics['total']) }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">Completed</div>
                    <div class="text-xl font-black text-emerald-600 mt-0.5">{{ number_format($statistics['completed']) }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Active Drivers</div>
                    <div class="text-xl font-black text-slate-800 mt-0.5">{{ number_format($statistics['active_drivers']) }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Active Vehicles</div>
                    <div class="text-xl font-black text-slate-800 mt-0.5">{{ number_format($statistics['active_vehicles']) }}</div>
                </div>
            </div>

            {{-- Chronological Itinerary Table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse border border-slate-200 text-xs">
                    <thead>
                        <tr class="bg-slate-900 text-white text-[11px] font-bold uppercase tracking-wider">
                            <th class="px-3 py-2.5 border border-slate-700 w-10 text-center">#</th>
                            <th class="px-3 py-2.5 border border-slate-700 w-24">Date</th>
                            <th class="px-3 py-2.5 border border-slate-700 w-36">Driver</th>
                            <th class="px-3 py-2.5 border border-slate-700 w-32">Vehicle</th>
                            <th class="px-4 py-2.5 border border-slate-700">Origin</th>
                            <th class="px-4 py-2.5 border border-slate-700">Destination</th>
                            <th class="px-3 py-2.5 border border-slate-700 w-20 text-center">Time In</th>
                            <th class="px-3 py-2.5 border border-slate-700 w-20 text-center">Time Out</th>
                            <th class="px-3 py-2.5 border border-slate-700 w-24 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($trips as $index => $trip)
                            <tr class="hover:bg-slate-50/70 {{ $index % 2 === 0 ? 'bg-white' : 'bg-slate-50/40' }}">
                                <td class="px-3 py-3 border border-slate-200 text-center font-mono text-slate-400">
                                    {{ $index + 1 }}
                                </td>
                                <td class="px-3 py-3 border border-slate-200 font-semibold text-slate-800 whitespace-nowrap">
                                    {{ $trip->trip_date?->format('Y-m-d') ?? 'N/A' }}
                                </td>
                                <td class="px-3 py-3 border border-slate-200 font-bold text-slate-900">
                                    {{ $trip->driver?->name ?? 'N/A' }}
                                </td>
                                <td class="px-3 py-3 border border-slate-200 whitespace-nowrap">
                                    <span class="font-bold text-slate-800">{{ $trip->vehicle?->equipment_code ?? 'N/A' }}</span>
                                    <span class="text-[10px] text-slate-400 block">{{ $trip->vehicle?->plate_number ?? '' }}</span>
                                </td>
                                <td class="px-4 py-3 border border-slate-200">
                                    @if($trip->origin_address)
                                        <div class="font-medium text-slate-800">{{ $trip->origin_address }}</div>
                                    @elseif($trip->origin_latitude && $trip->origin_longitude)
                                        <div class="font-mono text-[11px] text-slate-600">Coordinates: {{ number_format($trip->origin_latitude, 5) }}, {{ number_format($trip->origin_longitude, 5) }}</div>
                                    @else
                                        <div class="text-slate-400 italic">Coordinates available</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 border border-slate-200">
                                    @if($trip->destination_address)
                                        <div class="font-medium text-slate-800">{{ $trip->destination_address }}</div>
                                    @elseif($trip->destination_latitude && $trip->destination_longitude)
                                        <div class="font-mono text-[11px] text-slate-600">Coordinates: {{ number_format($trip->destination_latitude, 5) }}, {{ number_format($trip->destination_longitude, 5) }}</div>
                                    @elseif($trip->isInProgress())
                                        <div class="text-amber-600 font-medium italic">In Progress</div>
                                    @elseif($trip->isCancelled())
                                        <div class="text-rose-600 font-medium italic">Cancelled</div>
                                    @else
                                        <div class="text-slate-400 italic">Coordinates available</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3 border border-slate-200 text-center font-mono font-medium text-slate-700 whitespace-nowrap">
                                    {{ $trip->time_in ?? '—' }}
                                </td>
                                <td class="px-3 py-3 border border-slate-200 text-center font-mono font-medium text-slate-700 whitespace-nowrap">
                                    {{ $trip->time_out ?? ($trip->isInProgress() ? 'In Transit' : '—') }}
                                </td>
                                <td class="px-3 py-3 border border-slate-200 text-center whitespace-nowrap">
                                    @if($trip->isCompleted())
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200">
                                            COMPLETED
                                        </span>
                                    @elseif($trip->isInProgress())
                                        <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-200">
                                            IN PROGRESS
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700 border border-rose-200">
                                            CANCELLED
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-10 text-center text-slate-400 italic">
                                    No trips recorded for the selected period ({{ $start_date->format('M j, Y') }} to {{ $end_date->format('M j, Y') }}).
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Document Sign-off Footer --}}
            <div class="pt-6 border-t border-slate-200 flex flex-wrap items-center justify-between gap-4 text-xs text-slate-500">
                <div>
                    <span class="font-bold text-slate-700">Prepared by:</span> {{ config('foms.prepared_by', 'GPS Monitoring Specialist') }}
                </div>
                <div>
                    <span>Total Rows: <b>{{ $trips->count() }}</b></span> &bull; <span>GAMA FOMS Automated Itinerary System</span>
                </div>
            </div>

        </div>

    </div>
</x-app-layout>
