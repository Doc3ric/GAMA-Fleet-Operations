<x-app-layout>
    @section('page-title', 'Vehicle Archive Bin')
    @section('breadcrumb', 'Fleet Management / Vehicle Master List / Archive Bin')

    <div class="space-y-4" x-data="{
        confirmRestoreAll: false,
        confirmEmptyBin: false,
        deleteId: null,
        deleteCode: ''
    }">

        {{-- Top Action Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-1">
            <div>
                <div class="flex items-center gap-2.5">
                    <div class="p-1.5 rounded-lg bg-amber-50 text-amber-600 border border-amber-200">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-slate-800 tracking-tight">Archive Bin — Deleted Vehicles</h2>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                            {{ $vehicles->total() }} {{ Str::plural('Deleted Unit', $vehicles->total()) }}
                        </span>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-1">Deleted vehicles are kept here safely. You can restore them anytime or permanently remove them.</p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                {{-- Back to Active Vehicles --}}
                <a href="{{ route('vehicles.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition-colors">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    <span>Active Vehicles</span>
                </a>

                @if($vehicles->total() > 0)
                    {{-- Restore All Button --}}
                    <form method="POST" action="{{ route('vehicles.restoreAll') }}" class="inline">
                        @csrf
                        <button type="submit"
                                onclick="return confirm('Restore all archived vehicles back to the active list?');"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 transition-colors cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            <span>Restore All</span>
                        </button>
                    </form>

                    {{-- Empty Bin Button --}}
                    <form method="POST" action="{{ route('vehicles.emptyBin') }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                onclick="return confirm('PERMANENTLY delete all archived vehicles? This cannot be undone!');"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-red-700 transition-colors cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                            <span>Empty Bin</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Search in Bin --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-3">
            <form method="GET" action="{{ route('vehicles.archive') }}" class="flex gap-2">
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Search deleted vehicles by EGTP code, model, driver name, plate number, user..."
                           class="w-full pl-9 pr-3 py-2 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 rounded-lg text-xs placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-colors">
                </div>
                <button type="submit"
                        class="px-4 py-2 bg-slate-800 text-white text-xs font-semibold rounded-lg hover:bg-slate-900 transition-colors shadow-xs">
                    Search
                </button>
                @if(request('search'))
                    <a href="{{ route('vehicles.archive') }}"
                       class="px-3 py-2 bg-slate-100 text-slate-600 text-xs font-medium rounded-lg hover:bg-slate-200 transition-colors">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        {{-- Table Card --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left divide-y divide-slate-200">
                    <thead class="bg-slate-50/90 text-slate-600 font-semibold tracking-wider uppercase text-[10px]">
                        <tr>
                            <th scope="col" class="py-3 px-3.5 whitespace-nowrap">EGTP CODE</th>
                            <th scope="col" class="py-3 px-3 whitespace-nowrap">MODEL</th>
                            <th scope="col" class="py-3 px-3 whitespace-nowrap">DRIVER NAME</th>
                            <th scope="col" class="py-3 px-3 whitespace-nowrap">PLATE NUMBER</th>
                            <th scope="col" class="py-3 px-3 whitespace-nowrap">USER</th>
                            <th scope="col" class="py-3 px-3 whitespace-nowrap">PROJECT CODE</th>
                            <th scope="col" class="py-3 px-3 whitespace-nowrap">DELETED AT</th>
                            <th scope="col" class="py-3 px-3 text-right whitespace-nowrap">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($vehicles as $vehicle)
                            <tr class="hover:bg-amber-50/40 transition-colors">
                                {{-- EGTP CODE --}}
                                <td class="py-2.5 px-3.5 font-bold text-slate-900 whitespace-nowrap">
                                    {{ $vehicle->equipment_code }}
                                </td>

                                {{-- MODEL --}}
                                <td class="py-2.5 px-3 text-slate-700 whitespace-nowrap">
                                    {{ $vehicle->model ?: '—' }}
                                </td>

                                {{-- DRIVER NAME --}}
                                <td class="py-2.5 px-3 text-slate-700 whitespace-nowrap">
                                    {{ $vehicle->operator_driver ?: '—' }}
                                </td>

                                {{-- PLATE NUMBER --}}
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    @if($vehicle->plate_number)
                                        <span class="font-mono text-[11px] font-semibold bg-slate-100/90 text-slate-800 px-1.5 py-0.5 rounded border border-slate-200">
                                            {{ $vehicle->plate_number }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- USER --}}
                                <td class="py-2.5 px-3 text-slate-700 whitespace-nowrap">
                                    {{ $vehicle->user ?: '—' }}
                                </td>

                                {{-- PROJECT CODE --}}
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    @if($vehicle->project_code)
                                        <span class="font-mono text-[11px] text-slate-700 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-200">
                                            {{ $vehicle->project_code }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- DELETED AT --}}
                                <td class="py-2.5 px-3 text-slate-500 whitespace-nowrap">
                                    <span class="text-amber-800 font-medium">{{ $vehicle->deleted_at?->format('M d, Y h:i A') }}</span>
                                    <span class="text-[10px] text-slate-400 block">({{ $vehicle->deleted_at?->diffForHumans() }})</span>
                                </td>

                                {{-- Actions --}}
                                <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        {{-- Restore Form --}}
                                        <form method="POST" action="{{ route('vehicles.restore', $vehicle->id) }}" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded hover:bg-emerald-100 transition-colors cursor-pointer"
                                                    title="Restore vehicle to active list">
                                                <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                                </svg>
                                                Restore
                                            </button>
                                        </form>

                                        {{-- Permanent Delete Form --}}
                                        <form method="POST" action="{{ route('vehicles.forceDelete', $vehicle->id) }}" class="inline"
                                              onsubmit="return confirm('Permanently delete vehicle {{ $vehicle->equipment_code }}? This action CANNOT be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded hover:bg-red-100 transition-colors cursor-pointer"
                                                    title="Permanently delete vehicle">
                                                <svg class="h-3.5 w-3.5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                </svg>
                                                Delete Permanently
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-14 px-6 text-center">
                                    <div class="max-w-sm mx-auto space-y-3">
                                        <div class="h-12 w-12 mx-auto rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600 border border-emerald-100">
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-semibold text-slate-800">Archive Bin is Empty</h3>
                                            <p class="text-xs text-slate-500 mt-1">There are no deleted vehicles in the archive bin.</p>
                                        </div>
                                        <div class="pt-1">
                                            <a href="{{ route('vehicles.index') }}"
                                               class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-xs">
                                                &larr; Back to Active Vehicles
                                            </a>
                                        </div>
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
                    of <span class="font-semibold text-slate-700">{{ $vehicles->total() }}</span> archived vehicles
                </div>
                @if($vehicles->hasPages())
                    <div>
                        {{ $vehicles->links() }}
                    </div>
                @endif
            </div>
        </div>

    </div>
</x-app-layout>
