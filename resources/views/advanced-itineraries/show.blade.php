<x-app-layout>
    @section('page-title', 'Itinerary #' . $advancedItinerary->id)
    @section('breadcrumb')
        <a href="{{ route('advanced-itineraries.index') }}" class="hover:underline">Advanced Itineraries</a> &bull; #{{ $advancedItinerary->id }}
    @endsection

    <div class="space-y-6">
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        {{-- Top Header --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('advanced-itineraries.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Back
                </a>
                <h1 class="text-xl font-bold text-slate-900">Itinerary #{{ $advancedItinerary->id }}</h1>
                @if($advancedItinerary->isFinalized())
                    <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">
                        FINALIZED
                    </span>
                @else
                    <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">
                        DRAFT
                    </span>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @can('recalculate', $advancedItinerary)
                    <form method="POST" action="{{ route('advanced-itineraries.recalculate', $advancedItinerary) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50 transition shadow-xs">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            Recalculate Distances
                        </button>
                    </form>
                @endcan

                <a href="{{ route('advanced-itineraries.exportPdf', $advancedItinerary) }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-white px-3.5 py-2 text-xs font-bold text-red-700 hover:bg-red-50 transition shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    PDF Export
                </a>

                @can('update', $advancedItinerary)
                    <a href="{{ route('advanced-itineraries.edit', $advancedItinerary) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-blue-700 transition shadow-xs">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                        Edit
                    </a>
                @endcan

                @can('delete', $advancedItinerary)
                    <form method="POST" action="{{ route('advanced-itineraries.destroy', $advancedItinerary) }}" onsubmit="return confirm('Are you sure you want to delete this itinerary?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-white px-3.5 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50 transition shadow-xs">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                            Delete
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        {{-- Itinerary Overview Card --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">Overview</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 text-xs">
                <div>
                    <span class="block text-slate-400 font-semibold mb-1">Itinerary Date</span>
                    <span class="font-bold text-slate-900 text-sm">{{ $advancedItinerary->itinerary_date->format('F d, Y') }}</span>
                </div>
                <div>
                    <span class="block text-slate-400 font-semibold mb-1">Assigned Vehicle</span>
                    @if($advancedItinerary->vehicle)
                        <span class="font-bold text-slate-900 text-sm">{{ $advancedItinerary->vehicle->equipment_code }}</span>
                        <span class="text-slate-500">({{ $advancedItinerary->vehicle->plate_number }})</span>
                    @else
                        <span class="text-slate-400 italic">None assigned</span>
                    @endif
                </div>
                <div>
                    <span class="block text-slate-400 font-semibold mb-1">Total Distance</span>
                    <span class="font-mono font-bold text-blue-600 text-base">{{ number_format($advancedItinerary->total_distance, 2) }} km</span>
                </div>
                <div>
                    <span class="block text-slate-400 font-semibold mb-1">Est. Driving Time</span>
                    <span class="font-mono font-bold text-slate-900 text-base">{{ $advancedItinerary->formatted_total_duration }}</span>
                </div>
                <div>
                    <span class="block text-slate-400 font-semibold mb-1">Created / Updated By</span>
                    <span class="font-medium text-slate-800">{{ $advancedItinerary->creator?->name ?? 'System' }}</span>
                    @if($advancedItinerary->updater)
                        <div class="text-[11px] text-slate-400">Updated: {{ $advancedItinerary->updater->name }}</div>
                    @endif
                </div>
            </div>

            @if($advancedItinerary->title || $advancedItinerary->notes)
                <div class="mt-6 border-t border-slate-100 pt-4 space-y-3">
                    @if($advancedItinerary->title)
                        <div>
                            <span class="block text-slate-400 font-semibold text-xs mb-0.5">Title</span>
                            <span class="text-slate-900 text-xs font-medium">{{ $advancedItinerary->title }}</span>
                        </div>
                    @endif
                    @if($advancedItinerary->notes)
                        <div>
                            <span class="block text-slate-400 font-semibold text-xs mb-0.5">Notes</span>
                            <p class="text-slate-700 text-xs whitespace-pre-line">{{ $advancedItinerary->notes }}</p>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Legs List --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4">
                <h3 class="text-sm font-bold text-slate-900">Itinerary Legs ({{ $advancedItinerary->legs->count() }})</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase font-semibold">
                        <tr>
                            <th class="px-4 py-3 text-center">#</th>
                            <th class="px-4 py-3">Origin Location</th>
                            <th class="px-4 py-3">Starting Point</th>
                            <th class="px-4 py-3">Destination</th>
                            <th class="px-4 py-3 text-right">Origin &rarr; Start</th>
                            <th class="px-4 py-3 text-right">Start &rarr; Dest</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-center">Source</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse($advancedItinerary->legs as $index => $leg)
                            <tr>
                                <td class="px-4 py-3 text-center font-bold text-slate-900">{{ $index + 1 }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900">{{ $leg->origin?->official_name ?? 'N/A' }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono">{{ $leg->origin?->code }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900">{{ $leg->startingPoint?->official_name ?? 'N/A' }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono">{{ $leg->startingPoint?->code }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900">{{ $leg->destination?->official_name ?? 'N/A' }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono">{{ $leg->destination?->code }}</div>
                                    @if($leg->purpose)
                                        <div class="text-[11px] text-blue-600 mt-0.5">Purpose: {{ $leg->purpose }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    <div>{{ $leg->distance_origin_to_start !== null ? number_format($leg->distance_origin_to_start, 2).' km' : '-' }}</div>
                                    @if($leg->duration_origin_to_start_minutes)
                                        <div class="text-[10px] text-slate-500 font-sans">⏱ {{ $leg->formatted_origin_to_start_duration }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    <div>{{ $leg->distance_start_to_dest !== null ? number_format($leg->distance_start_to_dest, 2).' km' : '-' }}</div>
                                    @if($leg->duration_start_to_dest_minutes)
                                        <div class="text-[10px] text-slate-500 font-sans">⏱ {{ $leg->formatted_start_to_dest_duration }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                    <div>{{ $leg->total_distance !== null ? number_format($leg->total_distance, 2).' km' : '-' }}</div>
                                    @if($leg->total_duration_minutes)
                                        <div class="text-[11px] text-blue-700 font-sans font-semibold">⏱ {{ $leg->formatted_total_duration }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($leg->isAutomatic() || $leg->routing_source === 'osrm')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            AUTOMATIC (OSRM)
                                        </span>
                                    @elseif($leg->isManual() || $leg->routing_source === 'manual')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 border border-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                            MANUAL
                                        </span>
                                    @elseif($leg->routing_source === 'failed')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200 px-2 py-0.5 text-[10px] font-bold text-amber-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                                            ROUTING FAILED
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-mono uppercase text-slate-700">
                                            {{ $leg->routing_source ?? 'N/A' }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-slate-500">
                                    No itinerary legs recorded.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
