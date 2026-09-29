<x-app-layout>
    @section('page-title', 'Average Fuel Consumption')
    @section('page-subtitle', 'Fleet Vehicle Fuel Economy & PO Calculation Rates')
    @section('breadcrumb', 'Fleet Reports / Average Fuel Consumption')

    <div class="space-y-6 pb-16" x-data="{
        showModal: false,
        modalVehicleId: '',
        modalVehicleCode: '',
        modalAvgConsumption: '',
        isEditing: false,
        openSetModal(id = '', code = '', current = '') {
            this.modalVehicleId = id;
            this.modalVehicleCode = code;
            this.modalAvgConsumption = current;
            this.isEditing = (id !== '');
            this.showModal = true;
        }
    }">

        {{-- Navigation Sub-Tabs --}}
        <div class="flex items-center gap-2 border-b border-slate-200 pb-3">
            <a href="{{ route('average-fuel-consumption.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-blue-600 text-white shadow-xs">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
                Average Fuel Consumption List
            </a>
            <a href="{{ route('fuel-consumption.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors">
                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                Full-Tank Test Logs
            </a>
        </div>

        {{-- Top Summary KPI Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Total Configured --}}
            <div class="rounded-2xl bg-white p-4 border border-slate-200 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-[10px] uppercase font-bold tracking-wider">Vehicles with Avg. Rate</span>
                    <div class="p-1.5 rounded-lg bg-blue-50 text-blue-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-2xl font-black text-slate-900">{{ $totalConfigured }}</span>
                        <span class="text-xs text-slate-400">/ {{ $totalVehicles }} units</span>
                    </div>
                    <span class="text-[11px] text-slate-500 block mt-0.5">Ready for Fuel PO calculation</span>
                </div>
            </div>

            {{-- Fleet Average KM/L --}}
            <div class="rounded-2xl bg-white p-4 border border-slate-200 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-[10px] uppercase font-bold tracking-wider">Fleet Average Rate</span>
                    <div class="p-1.5 rounded-lg bg-emerald-50 text-emerald-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2">
                    <span class="text-2xl font-black text-emerald-600">{{ $fleetAvg > 0 ? number_format($fleetAvg, 2) : '—' }}</span>
                    <span class="text-[11px] text-slate-400 block mt-0.5">KM/L overall average</span>
                </div>
            </div>

            {{-- Best KM/L --}}
            <div class="rounded-2xl bg-white p-4 border border-slate-200 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-[10px] uppercase font-bold tracking-wider">Highest Economy</span>
                    <div class="p-1.5 rounded-lg bg-blue-50 text-blue-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2">
                    <span class="text-2xl font-black text-blue-700">{{ $highestAvg > 0 ? number_format($highestAvg, 2) : '—' }}</span>
                    <span class="text-[11px] text-slate-400 block mt-0.5">KM/L best vehicle</span>
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
                    <span class="text-2xl font-black text-amber-700">{{ $lowestAvg > 0 ? number_format($lowestAvg, 2) : '—' }}</span>
                    <span class="text-[11px] text-slate-400 block mt-0.5">KM/L lowest vehicle</span>
                </div>
            </div>
        </div>

        {{-- Filter & Action Bar --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs">
            <form method="GET" action="{{ route('average-fuel-consumption.index') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                <div class="flex flex-col sm:flex-row gap-2 flex-1">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </div>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Search EGTP code, model, plate, driver, user, project..."
                               class="w-full pl-9 pr-3 py-2 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 rounded-xl text-xs placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                    </div>

                    <div class="min-w-[170px]">
                        <select name="filter" onchange="this.form.submit()"
                                class="w-full py-2 px-3 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 rounded-xl text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Vehicles</option>
                            <option value="configured" @selected(request('filter') === 'configured')>Only Set (Configured)</option>
                            <option value="unconfigured" @selected(request('filter') === 'unconfigured')>Not Set Yet</option>
                        </select>
                    </div>

                    <button type="submit"
                            class="px-4 py-2 bg-slate-800 text-white text-xs font-semibold rounded-xl hover:bg-slate-900 transition-colors shadow-xs">
                        Search
                    </button>
                    @if(request()->hasAny(['search', 'filter']))
                        <a href="{{ route('average-fuel-consumption.index') }}"
                           class="px-3 py-2 bg-slate-100 text-slate-600 text-xs font-medium rounded-xl hover:bg-slate-200 transition-colors">
                            Reset
                        </a>
                    @endif
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    {{-- Open Set Modal Button --}}
                    <button type="button" @click="openSetModal()"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>+ Set Average Consumption</span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Main Table Card --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                            <th class="py-3 px-4">EGTP CODE</th>
                            <th class="py-3 px-3">MODEL</th>
                            <th class="py-3 px-3">DRIVER NAME</th>
                            <th class="py-3 px-3">PLATE NUMBER</th>
                            <th class="py-3 px-3">USER</th>
                            <th class="py-3 px-3">PROJECT CODE</th>
                            <th class="py-3 px-4 text-center">AVERAGE FUEL CONSUMPTION</th>
                            <th class="py-3 px-4 text-right">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($vehicles as $vehicle)
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                {{-- EGTP CODE --}}
                                <td class="py-3 px-4 font-bold text-slate-900 whitespace-nowrap">
                                    <a href="{{ route('vehicles.show', $vehicle) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                        {{ $vehicle->equipment_code }}
                                    </a>
                                </td>

                                {{-- MODEL --}}
                                <td class="py-3 px-3 text-slate-700 whitespace-nowrap">
                                    {{ $vehicle->model ?: '—' }}
                                </td>

                                {{-- DRIVER NAME --}}
                                <td class="py-3 px-3 text-slate-700 whitespace-nowrap">
                                    {{ $vehicle->operator_driver ?: '—' }}
                                </td>

                                {{-- PLATE NUMBER --}}
                                <td class="py-3 px-3 whitespace-nowrap">
                                    @if($vehicle->plate_number)
                                        <span class="font-mono text-[11px] font-semibold bg-slate-100/90 text-slate-800 px-1.5 py-0.5 rounded border border-slate-200">
                                            {{ $vehicle->plate_number }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- USER --}}
                                <td class="py-3 px-3 text-slate-700 whitespace-nowrap">
                                    {{ $vehicle->user ?: '—' }}
                                </td>

                                {{-- PROJECT CODE --}}
                                <td class="py-3 px-3 whitespace-nowrap">
                                    @if($vehicle->project_code)
                                        <span class="font-mono text-[11px] text-slate-700 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-200">
                                            {{ $vehicle->project_code }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- AVERAGE FUEL CONSUMPTION --}}
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    @if($vehicle->average_consumption)
                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 font-mono">
                                            {{ number_format($vehicle->average_consumption, 2) }} KM/L
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-400">
                                            Not configured
                                        </span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        {{-- Edit / Set Button --}}
                                        <button type="button"
                                                @click="openSetModal('{{ $vehicle->id }}', '{{ $vehicle->equipment_code }}', '{{ $vehicle->average_fuel_consumption ?? '' }}')"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors cursor-pointer"
                                                title="Set or update average fuel consumption">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                                            </svg>
                                            {{ $vehicle->average_consumption ? 'Edit Rate' : 'Set Rate' }}
                                        </button>

                                        @if($vehicle->average_consumption)
                                            {{-- Clear Button --}}
                                            <form method="POST" action="{{ route('average-fuel-consumption.destroy', $vehicle) }}" class="inline"
                                                  onsubmit="return confirm('Clear average consumption rate for {{ $vehicle->equipment_code }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-slate-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors cursor-pointer"
                                                        title="Clear average consumption">
                                                    Clear
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 px-6 text-center">
                                    <div class="max-w-sm mx-auto space-y-3">
                                        <div class="h-12 w-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-semibold text-slate-800">No vehicles found</h3>
                                            <p class="text-xs text-slate-500 mt-1">Try adjusting your search or filter.</p>
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
                    of <span class="font-semibold text-slate-700">{{ $vehicles->total() }}</span> vehicles
                </div>
                @if($vehicles->hasPages())
                    <div>
                        {{ $vehicles->links() }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Set / Update Average Fuel Consumption Modal --}}
        <div x-show="showModal"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
             style="display: none;"
             @keydown.escape.window="showModal = false"
             @click.self="showModal = false">

            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl transition-all" @click.stop>
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-800" x-text="isEditing ? 'Update Average Fuel Consumption' : 'Set Average Fuel Consumption'"></h3>
                            <p class="text-xs text-slate-500">Only the average fuel consumption rate (KM/L)</p>
                        </div>
                    </div>
                    <button type="button" @click="showModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('average-fuel-consumption.store') }}" class="mt-5 space-y-4">
                    @csrf

                    {{-- Vehicle Selection (shown when adding or fixed when editing) --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            SELECT VEHICLE (EGTP CODE) <span class="text-red-500">*</span>
                        </label>
                        <select name="vehicle_id" x-model="modalVehicleId" required
                                class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">— Select Vehicle —</option>
                            @foreach($allVehicles as $veh)
                                <option value="{{ $veh->id }}">
                                    {{ $veh->equipment_code }} — {{ $veh->model ?: 'No model' }} {{ $veh->plate_number ? '('.$veh->plate_number.')' : '' }} {{ $veh->average_fuel_consumption ? '('.number_format($veh->average_fuel_consumption, 2).' KM/L)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Average Fuel Consumption Rate --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            AVERAGE CONSUMPTION (KM/L) <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="number" name="average_fuel_consumption" x-model="modalAvgConsumption"
                                   placeholder="e.g. 3.00" step="0.01" min="0.01" max="999.99" required
                                   class="w-full px-3.5 py-2.5 pr-16 border border-slate-300 rounded-xl text-sm font-mono font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-xs font-bold text-slate-400">
                                KM/L
                            </div>
                        </div>
                        <p class="text-xs text-slate-400 mt-1.5">
                            Formula used in Fuel PO: <strong>Distance &divide; Average Consumption = Liter for PO</strong>
                        </p>
                    </div>

                    {{-- Action buttons --}}
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="showModal = false"
                                class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-5 py-2 text-xs font-bold text-white hover:bg-blue-700 cursor-pointer shadow-sm">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            <span>Save Rate</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
