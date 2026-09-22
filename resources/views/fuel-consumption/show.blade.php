<x-app-layout>
    @section('page-title', 'Fuel Test: ' . ($test->vehicle?->equipment_code ?? '#' . $test->id))
    @section('page-subtitle', $test->test_date?->format('F d, Y'))
    @section('breadcrumb', 'Fleet Reports / Average Fuel Consumption / #' . $test->id)

    @push('styles')
    <style>
        @media print {
            aside, header, footer, .no-print {
                display: none !important;
            }
            .ml-60 {
                margin-left: 0 !important;
            }
            body {
                background: white !important;
                color: black !important;
            }
            .print-full {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .print-border {
                border: 1px solid #cbd5e1 !important;
            }
        }
    </style>
    @endpush

    <div class="max-w-5xl mx-auto space-y-6 pb-16 print-full">

        {{-- Header & Action Bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-1 no-print">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-black text-lg shadow-sm">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-black text-slate-900 tracking-tight">
                            Fuel Consumption Test: {{ $test->equipment_code_display }}
                        </h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                            #{{ $test->id }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Conducted on {{ $test->test_date?->format('F d, Y') }} &bull; Recorded by {{ $test->creator?->name ?? 'System' }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                {{-- Print --}}
                <button onclick="window.print()"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24-1.076-.672-2.03-1.272-2.842a6.37 6.37 0 0 0-1.89-1.53m15.42 4.372c.24-1.076.672-2.03 1.272-2.842a6.37 6.37 0 0 0 1.89-1.53m-15.42 4.372A17.97 17.97 0 0 0 12 15c2.72 0 5.27-.604 7.54-1.688m-15.08 0A17.96 17.96 0 0 1 12 13c2.72 0 5.27.604 7.54 1.688m-15.08 0c.26 1.15.65 2.23 1.15 3.2m12.78-3.2c-.26 1.15-.65 2.23-1.15 3.2" />
                    </svg>
                    Print
                </button>

                {{-- PDF Export --}}
                <a href="{{ route('fuel-consumption.exportPdf', $test) }}"
                   class="inline-flex items-center gap-1.5 rounded-xl bg-red-600 hover:bg-red-700 text-white px-3.5 py-2 text-xs font-bold shadow-2xs transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    Export PDF
                </a>

                {{-- Edit --}}
                <a href="{{ route('fuel-consumption.edit', $test) }}"
                   class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white px-3.5 py-2 text-xs font-bold shadow-2xs transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                    Edit
                </a>

                <a href="{{ route('fuel-consumption.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition-colors">
                    &larr; All Tests
                </a>
            </div>
        </div>

        {{-- ==================== OFFICIAL REPORT DOCUMENT CONTAINER ==================== --}}
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden p-6 sm:p-8 space-y-6">

            {{-- Document Header --}}
            <div class="flex items-start justify-between border-b-2 border-slate-900 pb-5">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-base font-black tracking-wider text-slate-900 uppercase">
                            {{ config('foms.company_name', 'GAMA FOODS CORPORATION') }}
                        </span>
                        <span class="h-2 w-2 rounded-full bg-red-500 inline-block"></span>
                    </div>
                    <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-widest mt-0.5">
                        {{ config('foms.company_tagline', 'Fleet Operations Management System') }}
                    </div>
                    <h1 class="text-xl font-black text-blue-900 uppercase tracking-tight mt-3">
                        AVERAGE FUEL CONSUMPTION TEST REPORT
                    </h1>
                </div>

                <div class="text-right text-xs text-slate-500 space-y-1">
                    <div><strong>Test Date:</strong> {{ $test->test_date?->format('F d, Y') }}</div>
                    <div><strong>Document ID:</strong> AFC-{{ str_pad($test->id, 5, '0', STR_PAD_LEFT) }}</div>
                    <div><strong>Protocol:</strong> Full-Tank Method</div>
                </div>
            </div>

            {{-- ==================== PROMINENT FINAL RESULT BANNER ==================== --}}
            <div class="rounded-2xl border-2 border-emerald-500 bg-gradient-to-r from-emerald-50 via-emerald-100/40 to-emerald-50 p-6 shadow-sm">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-emerald-800 block">
                            OFFICIAL BENCHMARK RESULT
                        </span>
                        <h2 class="text-sm font-extrabold text-slate-800 mt-0.5">
                            AVERAGE FUEL CONSUMPTION:
                        </h2>
                        <p class="text-xs text-emerald-900 mt-1">
                            Calculated as Distance Travelled ({{ number_format($test->distance_travelled, 2) }} km) &divide; Fuel Consumed ({{ number_format($test->fuel_consumed_liters, 3) }} L)
                        </p>
                    </div>
                    <div class="text-left sm:text-right shrink-0 bg-white px-5 py-3 rounded-2xl border border-emerald-300 shadow-2xs">
                        <div class="text-4xl sm:text-5xl font-black font-mono text-emerald-700 tracking-tight">
                            {{ number_format($test->average_fuel_consumption, 2) }} <span class="text-xl font-bold text-emerald-600">KM/L</span>
                        </div>
                    </div>
                </div>

                @if($test->is_short_distance)
                    <div class="mt-4 pt-3 border-t border-emerald-200/60 flex items-center gap-2 text-xs text-amber-800">
                        <svg class="h-4 w-4 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        <span><strong>Data Quality Notice:</strong> {{ $test->short_distance_warning }}</span>
                    </div>
                @endif
            </div>

            {{-- ==================== VEHICLE & TEST DETAILS GRID ==================== --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- Left: Vehicle & Operator Metadata --}}
                <div class="rounded-2xl border border-slate-200 p-5 space-y-4">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-2">
                        Vehicle & Personnel Information
                    </h3>

                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="block text-[10px] uppercase font-bold text-slate-400">VEHICLE / EQUIPMENT</span>
                            <span class="font-black text-slate-900 text-sm mt-0.5 block">{{ $test->equipment_code_display }}</span>
                        </div>

                        <div>
                            <span class="block text-[10px] uppercase font-bold text-slate-400">PLATE NUMBER</span>
                            <span class="font-mono font-bold text-slate-800 text-sm mt-0.5 block">{{ $test->plate_number_display ?: 'Not assigned' }}</span>
                        </div>

                        <div>
                            <span class="block text-[10px] uppercase font-bold text-slate-400">MODEL / MAKE</span>
                            <span class="font-medium text-slate-800 mt-0.5 block">{{ $test->model_display ?: '-' }}</span>
                        </div>

                        <div>
                            <span class="block text-[10px] uppercase font-bold text-slate-400">VEHICLE TYPE</span>
                            <span class="font-medium text-slate-800 mt-0.5 block">{{ $test->vehicle?->vehicleType?->name ?: '-' }}</span>
                        </div>

                        <div class="col-span-2">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">DRIVER / OPERATOR</span>
                            <span class="font-bold text-slate-900 mt-0.5 block">{{ $test->driver_display_name }}</span>
                        </div>

                        <div class="col-span-2">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">TEST ROUTE / DESTINATION</span>
                            <span class="font-medium text-slate-800 mt-0.5 block">{{ $test->test_route ?: 'Standard test circuit' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Right: Readings & Calculations Breakdown --}}
                <div class="rounded-2xl border border-slate-200 p-5 space-y-4">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-2">
                        Full-Tank Calculation Breakdown
                    </h3>

                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                            <div>
                                <span class="font-bold text-slate-700 block">START ODOMETER (1st Full Tank)</span>
                                <span class="text-[10px] text-slate-400">Baseline mileage before drive</span>
                            </div>
                            <span class="font-mono font-bold text-slate-900 text-sm">{{ number_format($test->start_odometer, 2) }} km</span>
                        </div>

                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                            <div>
                                <span class="font-bold text-slate-700 block">END ODOMETER</span>
                                <span class="text-[10px] text-slate-400">Mileage after completing route</span>
                            </div>
                            <span class="font-mono font-bold text-slate-900 text-sm">{{ number_format($test->end_odometer, 2) }} km</span>
                        </div>

                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-blue-50/60 border border-blue-100">
                            <div>
                                <span class="font-black text-blue-900 block">DISTANCE TRAVELLED</span>
                                <span class="text-[10px] text-blue-700">Auto-calculated: End &minus; Start</span>
                            </div>
                            <span class="font-mono font-black text-blue-900 text-base">{{ number_format($test->distance_travelled, 2) }} km</span>
                        </div>

                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                            <div>
                                <span class="font-bold text-slate-700 block">FUEL CONSUMED (2nd Full Tank)</span>
                                <span class="text-[10px] text-slate-400">Liters required to refill to FULL</span>
                            </div>
                            <span class="font-mono font-bold text-slate-900 text-sm">{{ number_format($test->fuel_consumed_liters, 3) }} L</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Remarks --}}
            @if($test->remarks)
                <div class="rounded-2xl border border-slate-200 p-5 bg-slate-50/50">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">REMARKS / OPERATIONAL NOTES</span>
                    <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-line">{{ $test->remarks }}</p>
                </div>
            @endif

            {{-- ==================== SUPPORTING PHOTOS / EVIDENCE ==================== --}}
            <div class="rounded-2xl border border-slate-200 p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-400">
                        Supporting Evidence & Attachments
                    </h3>
                    <span class="text-[11px] text-slate-400">Verification Photos</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    {{-- 1. Start Odometer Photo --}}
                    <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3 flex flex-col">
                        <span class="text-[10px] font-bold uppercase text-slate-500 mb-2 block">1. Start Odometer Photo</span>
                        @if($test->start_odometer_image_url)
                            <a href="{{ $test->start_odometer_image_url }}" target="_blank" class="block group relative overflow-hidden rounded-lg border border-slate-200">
                                <img src="{{ $test->start_odometer_image_url }}" alt="Start Odometer Photo"
                                     class="w-full h-44 object-cover group-hover:scale-105 transition-transform duration-200">
                                <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-xs font-bold">
                                    Click to view
                                </div>
                            </a>
                        @else
                            <div class="w-full h-44 rounded-lg border border-dashed border-slate-300 bg-white flex flex-col items-center justify-center text-slate-400 text-xs">
                                <svg class="h-8 w-8 text-slate-300 mb-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                                No photo attached
                            </div>
                        @endif
                    </div>

                    {{-- 2. End Odometer Photo --}}
                    <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3 flex flex-col">
                        <span class="text-[10px] font-bold uppercase text-slate-500 mb-2 block">2. End Odometer / 2nd Full Tank</span>
                        @if($test->end_odometer_image_url)
                            <a href="{{ $test->end_odometer_image_url }}" target="_blank" class="block group relative overflow-hidden rounded-lg border border-slate-200">
                                <img src="{{ $test->end_odometer_image_url }}" alt="End Odometer Photo"
                                     class="w-full h-44 object-cover group-hover:scale-105 transition-transform duration-200">
                                <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-xs font-bold">
                                    Click to view
                                </div>
                            </a>
                        @else
                            <div class="w-full h-44 rounded-lg border border-dashed border-slate-300 bg-white flex flex-col items-center justify-center text-slate-400 text-xs">
                                <svg class="h-8 w-8 text-slate-300 mb-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                                No photo attached
                            </div>
                        @endif
                    </div>

                    {{-- 3. Fuel Receipt --}}
                    <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3 flex flex-col">
                        <span class="text-[10px] font-bold uppercase text-slate-500 mb-2 block">3. Fuel Receipt (2nd Full Tank)</span>
                        @if($test->fuel_receipt_image_url)
                            <a href="{{ $test->fuel_receipt_image_url }}" target="_blank" class="block group relative overflow-hidden rounded-lg border border-slate-200">
                                <img src="{{ $test->fuel_receipt_image_url }}" alt="Fuel Receipt"
                                     class="w-full h-44 object-cover group-hover:scale-105 transition-transform duration-200">
                                <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-xs font-bold">
                                    Click to view
                                </div>
                            </a>
                        @else
                            <div class="w-full h-44 rounded-lg border border-dashed border-slate-300 bg-white flex flex-col items-center justify-center text-slate-400 text-xs">
                                <svg class="h-8 w-8 text-slate-300 mb-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                No receipt attached
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ==================== SIGNATURE & ATTESTATION BLOCK ==================== --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-6 pt-8 border-t border-slate-200 text-xs">
                <div>
                    <span class="block text-[10px] uppercase font-bold text-slate-400">RECORDED BY:</span>
                    <span class="font-bold text-slate-800 mt-1 block">{{ $test->creator?->name ?? 'Specialist' }}</span>
                    <span class="text-[10px] text-slate-400">GPS Fleet Operations</span>
                </div>

                <div>
                    <span class="block text-[10px] uppercase font-bold text-slate-400">ATTESTED BY:</span>
                    <span class="font-bold text-slate-800 mt-1 block">{{ $test->attested_by ?: '—' }}</span>
                    <div class="w-32 border-b border-slate-300 mt-6"></div>
                    <span class="text-[9px] text-slate-400 mt-1 block">Signature / Verification</span>
                </div>

                <div>
                    <span class="block text-[10px] uppercase font-bold text-slate-400">REQUESTED BY:</span>
                    <span class="font-bold text-slate-800 mt-1 block">{{ $test->requested_by ?: '—' }}</span>
                    <div class="w-32 border-b border-slate-300 mt-6"></div>
                    <span class="text-[9px] text-slate-400 mt-1 block">Signature / Unit Approval</span>
                </div>
            </div>

        </div>

    </div>
</x-app-layout>
