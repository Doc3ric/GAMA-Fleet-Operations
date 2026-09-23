<x-app-layout>
    @section('page-title', 'Advanced Itineraries')
    @section('breadcrumb', 'Fleet Management / Advanced Itineraries')

    <div class="space-y-6">
        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        {{-- Top Action Bar --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Advanced Itineraries</h1>
                <p class="text-xs text-slate-500">Plan, manage, and calculate distances for multi-leg vehicle trips.</p>
            </div>
            @can('create', App\Models\AdvancedItinerary::class)
                <a href="{{ route('advanced-itineraries.create') }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    New Itinerary
                </a>
            @endcan
        </div>

        {{-- Filter Card --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
            <form method="GET" action="{{ route('advanced-itineraries.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Start Date</label>
                    <input type="date" name="start_date" value="{{ request('start_date') }}"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">End Date</label>
                    <input type="date" name="end_date" value="{{ request('end_date') }}"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Vehicle</label>
                    <select name="vehicle_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option value="">All Vehicles</option>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" {{ (string) request('vehicle_id') === (string) $vehicle->id ? 'selected' : '' }}>
                                {{ $vehicle->equipment_code }} ({{ $vehicle->plate_number }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                    <select name="status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        <option value="DRAFT" {{ request('status') === 'DRAFT' ? 'selected' : '' }}>DRAFT</option>
                        <option value="FINALIZED" {{ request('status') === 'FINALIZED' ? 'selected' : '' }}>FINALIZED</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 rounded-xl bg-slate-800 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-900 transition">
                        Filter
                    </button>
                    <a href="{{ route('advanced-itineraries.index') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- Itineraries Table --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase font-semibold">
                        <tr>
                            <th class="px-4 py-3">ID / Date</th>
                            <th class="px-4 py-3">Vehicle</th>
                            <th class="px-4 py-3">Title / Notes</th>
                            <th class="px-4 py-3 text-center">Legs</th>
                            <th class="px-4 py-3 text-right">Total Dist.</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse($itineraries as $itinerary)
                            <tr class="hover:bg-slate-50/75 transition">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900">#{{ $itinerary->id }}</div>
                                    <div class="text-slate-500">{{ $itinerary->itinerary_date->format('M d, Y') }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($itinerary->vehicle)
                                        <div class="font-semibold text-slate-800">{{ $itinerary->vehicle->equipment_code }}</div>
                                        <div class="text-slate-500 text-[11px]">{{ $itinerary->vehicle->plate_number }}</div>
                                    @else
                                        <span class="text-slate-400 italic">None</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $itinerary->title ?? '-' }}</div>
                                    @if($itinerary->notes)
                                        <div class="text-slate-500 text-[11px] truncate max-w-xs">{{ $itinerary->notes }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-700">
                                        {{ $itinerary->legs->count() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-slate-900">
                                    {{ number_format($itinerary->total_distance, 2) }} km
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($itinerary->isFinalized())
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800">
                                            FINALIZED
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-bold text-amber-800">
                                            DRAFT
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('advanced-itineraries.show', $itinerary) }}" class="rounded-lg p-1.5 text-slate-600 hover:bg-slate-100 transition" title="View Details">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                        </a>
                                        <a href="{{ route('advanced-itineraries.exportPdf', $itinerary) }}" class="rounded-lg p-1.5 text-red-600 hover:bg-red-50 transition" title="Export PDF">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                        </a>
                                        @can('update', $itinerary)
                                            <a href="{{ route('advanced-itineraries.edit', $itinerary) }}" class="rounded-lg p-1.5 text-blue-600 hover:bg-blue-50 transition" title="Edit">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                </svg>
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                    No advanced itineraries found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($itineraries->hasPages())
                <div class="border-t border-slate-200 p-4">
                    {{ $itineraries->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
