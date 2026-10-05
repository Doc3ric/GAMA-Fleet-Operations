<x-app-layout>
    @section('page-title', 'Fuel PO Record #' . $fuelPo->id)
    @section('breadcrumb')
        <a href="{{ route('fuel-po.index') }}" class="hover:underline">Fuel PO Checklist</a> &bull; #{{ $fuelPo->id }}
    @endsection

    @php
        $v = $fuelPo->vehicle;
        $avgConsumption = $v?->average_consumption;
        $fuelLiters = $fuelPo->fuel_liters;
    @endphp

    <div class="max-w-5xl mx-auto space-y-6" x-data="{ showDeleteModal: false }">
        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 text-xs font-semibold flex items-center justify-between shadow-2xs">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        {{-- Top Bar & Actions --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500 text-white font-mono font-extrabold text-sm shadow-xs">
                    PO
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Fuel PO #{{ $fuelPo->id }}</h1>
                        <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $fuelPo->status === 'FINALIZED' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                            {{ $fuelPo->status }}
                        </span>
                        <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $fuelPo->po_checked ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $fuelPo->po_checked ? '☑ CHECKED' : '☐ UNCHECKED' }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Date: <span class="font-semibold text-slate-600">{{ $fuelPo->itinerary_date->format('F d, Y') }}</span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('fuel-po.pdf', $fuelPo) }}" target="_blank"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-red-300 bg-red-50 px-3.5 py-2 text-xs font-bold text-red-800 hover:bg-red-100 shadow-2xs transition">
                    <svg class="h-4 w-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    PDF Export
                </a>

                <a href="{{ route('fuel-po.edit', $fuelPo) }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-2xs transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                    Edit
                </a>

                @can('delete', $fuelPo)
                    <button type="button"
                            @click="showDeleteModal = true"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-rose-300 bg-rose-50 px-3.5 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100 shadow-2xs transition cursor-pointer">
                        <svg class="h-4 w-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        Delete
                    </button>
                @endcan

                <a href="{{ route('fuel-po.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-2xs transition">
                    &larr; Back to Checklist
                </a>
            </div>
        </div>

        {{-- Fuel PO Processing & Checklist Action Card --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                            </svg>
                        </span>
                        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Fuel Purchase Order (PO) Processing</h2>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Checklist belongs to this specific itinerary and survives downloads and logouts.</p>
                </div>

                {{-- Interactive Toggle Button --}}
                <div class="flex items-center gap-3">
                    <form method="POST" action="{{ route('fuel-po.toggle-checklist', $fuelPo) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition shadow-xs cursor-pointer border {{ $fuelPo->po_checked ? 'bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-600' : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-300' }}">
                            @if($fuelPo->po_checked)
                                <span>&#9745; CHECKED / COMPLETED</span>
                                <span class="text-[10px] bg-emerald-700/60 px-2 py-0.5 rounded-md font-medium text-emerald-100">Click to Uncheck</span>
                            @else
                                <span class="text-slate-400 font-bold text-sm leading-none">&#9744;</span>
                                <span>MARK AS CHECKED (PO PROCESSED)</span>
                            @endif
                        </button>
                    </form>
                </div>
            </div>

            {{-- 4-Col Metric Breakdown --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs">
                <div class="rounded-xl border border-slate-200 p-3.5 bg-slate-50/50">
                    <span class="block text-slate-400 font-medium">Final Destination</span>
                    <span class="mt-1 block font-bold text-slate-900 text-sm">{{ $fuelPo->destination_name }}</span>
                    @if($fuelPo->start_odo !== null || $fuelPo->end_odo !== null)
                        <span class="block text-[11px] font-mono text-slate-500 mt-1">
                            ODO: {{ $fuelPo->start_odo !== null ? number_format($fuelPo->start_odo, 2) : '—' }} &rarr; {{ $fuelPo->end_odo !== null ? number_format($fuelPo->end_odo, 2) : '—' }}
                        </span>
                    @endif
                </div>

                <div class="rounded-xl border border-slate-200 p-3.5 bg-slate-50/50">
                    <span class="block text-slate-400 font-medium">Total Road Distance</span>
                    <span class="mt-1 block font-mono font-bold text-slate-900 text-base">
                        {{ number_format($fuelPo->total_distance, 2) }} <span class="text-xs font-normal text-slate-500 font-sans">KM</span>
                    </span>
                </div>

                <div class="rounded-xl border border-slate-200 p-3.5 bg-slate-50/50">
                    <span class="block text-slate-400 font-medium">
                        {{ ($fuelPo->calculation_method === 'multiply') ? 'Consumption Rate' : 'Average Consumption' }}
                    </span>
                    <span class="mt-1 block font-mono font-bold text-blue-700 text-base">
                        @if($avgConsumption)
                            {{ number_format($avgConsumption, 2) }} <span class="text-xs font-normal text-slate-500 font-sans">{{ ($fuelPo->calculation_method === 'multiply') ? 'Rate' : 'KM/L' }}</span>
                        @else
                            <span class="text-slate-400 font-sans text-xs">Not set</span>
                        @endif
                    </span>
                </div>

                <div class="rounded-xl border border-blue-200 bg-blue-50/50 p-3.5">
                    @php
                        $isMultiply = ($fuelPo->calculation_method === 'multiply');
                    @endphp
                    <span class="block text-blue-700 font-medium">
                        Liter for PO ({{ $isMultiply ? 'Distance × Rate [FL]' : 'Distance ÷ Avg Consump' }})
                    </span>
                    <span class="mt-1 block font-mono font-bold text-blue-900 text-lg">
                        @if($fuelLiters !== null)
                            {{ (float) $fuelLiters == round($fuelLiters) ? number_format($fuelLiters, 0) : number_format($fuelLiters, 2) }} <span class="text-xs font-normal text-slate-500 font-sans">L</span>
                            @if($avgConsumption && $fuelPo->total_distance)
                                @php
                                    $rawLiters = round($isMultiply ? ($fuelPo->total_distance * $avgConsumption) : ($fuelPo->total_distance / $avgConsumption), 2);
                                @endphp
                                @if($rawLiters != $fuelLiters)
                                    <span class="block text-[10px] text-blue-600 font-sans font-normal mt-0.5">
                                        Raw: {{ number_format($rawLiters, 2) }} L (&ge; 0.10 &rarr; {{ number_format($fuelLiters, 0) }} L)
                                    </span>
                                @endif
                            @endif
                        @else
                            <span class="text-slate-400 text-xs italic font-sans">Requires Avg KM/L</span>
                        @endif
                    </span>
                </div>
            </div>

            {{-- Audit Details --}}
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3.5 text-xs text-slate-600 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-slate-700">Checklist Audit:</span>
                    @if($fuelPo->po_checked)
                        <span class="text-emerald-700 font-semibold">
                            Checked by <strong>{{ $fuelPo->poChecker?->name ?? 'User #' . $fuelPo->po_checked_by }}</strong> on {{ $fuelPo->po_checked_at?->format('F d, Y \a\t h:i A') }}
                        </span>
                    @else
                        <span class="text-slate-500 italic">Not yet checked by purchasing.</span>
                    @endif
                </div>

                <div class="text-[11px] text-slate-400">
                    Created by {{ $fuelPo->creator?->name ?? 'System' }} &bull; {{ $fuelPo->created_at->format('M d, Y') }}
                </div>
            </div>
        </div>

        {{-- Auto-Loaded Vehicle Master Profile --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Assigned Vehicle Master Profile</h3>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3 text-xs">
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] uppercase font-bold text-slate-400">Equipment Code</span>
                    <span class="font-mono font-bold text-slate-900 block mt-0.5">{{ $v?->equipment_code ?? '—' }}</span>
                </div>

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] uppercase font-bold text-slate-400">Model</span>
                    <span class="font-semibold text-slate-900 block mt-0.5">{{ $v?->model ?? '—' }}</span>
                </div>

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] uppercase font-bold text-slate-400">Driver Name</span>
                    <span class="font-semibold text-slate-900 block mt-0.5">{{ $fuelPo->driver_name }}</span>
                </div>

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] uppercase font-bold text-slate-400">Plate Number</span>
                    <span class="font-mono font-bold text-slate-900 block mt-0.5">{{ $v?->plate_number ?? '—' }}</span>
                </div>

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] uppercase font-bold text-slate-400">User / Dept</span>
                    <span class="font-semibold text-slate-900 block mt-0.5">{{ $v?->user ?? '—' }}</span>
                </div>

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] uppercase font-bold text-slate-400">Project Code</span>
                    <span class="font-mono font-semibold text-slate-900 block mt-0.5">{{ $v?->project_code ?? '—' }}</span>
                </div>

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <span class="block text-[10px] uppercase font-bold text-blue-600">Avg. Consumption</span>
                    <span class="font-mono font-bold text-blue-900 block mt-0.5">
                        {{ $avgConsumption ? number_format($avgConsumption, 2) . ' KM/L' : 'Not set' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Itinerary Legs Table --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4">
                <h3 class="text-sm font-bold text-slate-900">Itinerary Legs ({{ $fuelPo->legs->count() }})</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase font-semibold">
                        <tr>
                            <th class="px-4 py-3 text-center">#</th>
                            <th class="px-4 py-3">Destination / Route</th>
                            <th class="px-4 py-3 text-right">Start ODO</th>
                            <th class="px-4 py-3 text-right">End ODO</th>
                            <th class="px-4 py-3 text-right">Total Distance</th>
                            <th class="px-4 py-3">Purpose</th>
                            <th class="px-4 py-3 text-center">Source</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse($fuelPo->legs as $leg)
                            <tr>
                                <td class="px-4 py-3 text-center font-bold text-slate-400">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3 font-bold text-blue-900">
                                    {{ $leg->destination?->official_name ?? $leg->purpose ?? '—' }}
                                    @if($leg->origin || $leg->startingPoint)
                                        <span class="block text-[10px] text-slate-400 font-normal">
                                            From: {{ $leg->origin?->official_name ?? '—' }} &rarr; Via: {{ $leg->startingPoint?->official_name ?? '—' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-slate-700">{{ $leg->start_odo !== null ? number_format($leg->start_odo, 2) : '—' }}</td>
                                <td class="px-4 py-3 text-right font-mono text-slate-700">{{ $leg->end_odo !== null ? number_format($leg->end_odo, 2) : '—' }}</td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-blue-700">{{ $leg->total_distance !== null ? number_format($leg->total_distance, 2).' km' : '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $leg->purpose ?? '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase {{ $leg->routing_source === 'osrm' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $leg->routing_source }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">No legs recorded for this Fuel PO.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Delete Confirmation Modal --}}
        <div x-show="showDeleteModal"
             x-cloak
             x-transition:enter="ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @keydown.escape.window="showDeleteModal = false"
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="display: none;">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-2xs" @click="showDeleteModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-xl p-6 w-full max-w-md border border-slate-200 z-10 space-y-4" @click.stop>
                <div class="flex items-start gap-3.5">
                    <div class="h-10 w-10 rounded-xl bg-rose-100 border border-rose-200 flex items-center justify-center shrink-0 text-rose-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-bold text-slate-900">Delete Fuel Purchase Order (PO)</h3>
                        <p class="text-xs font-semibold text-slate-600 mt-1 truncate">Fuel PO #{{ $fuelPo->id }} &bull; {{ $v?->equipment_code ?? 'Vehicle' }} ({{ $fuelPo->destination_name }})</p>
                    </div>
                    <button type="button" @click="showDeleteModal = false" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="rounded-xl bg-rose-50/70 border border-rose-100 p-3 text-xs text-rose-800 space-y-1">
                    <p class="font-bold">Are you sure you want to delete this record?</p>
                    <p class="text-[11px] text-rose-700">This will permanently delete this Fuel PO, its destinations, distance breakdown, and calculated liters. This action cannot be undone.</p>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button type="button" @click="showDeleteModal = false"
                            class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                        Cancel
                    </button>
                    <form method="POST" action="{{ route('fuel-po.destroy', $fuelPo) }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white hover:bg-rose-700 shadow-sm transition cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                            <span>Delete Record</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
