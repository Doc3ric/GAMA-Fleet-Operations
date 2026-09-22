<x-app-layout>
    @section('page-title', 'Edit Vehicle')
    @section('breadcrumb', 'Fleet Management / Vehicle Master List / ' . $vehicle->equipment_code)

    <div class="max-w-4xl pb-16">
        {{-- Sticky Top Header with Prominent Save Button --}}
        <div class="sticky top-0 z-10 bg-slate-50/95 backdrop-blur-xs py-3 mb-5 border-b border-slate-200/80 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('vehicles.index') }}" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                </a>
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Edit Vehicle — <span class="text-blue-600">{{ $vehicle->equipment_code }}</span></h2>
                    <p class="text-xs text-slate-500">Modify vehicle details and click Update Vehicle</p>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <a href="{{ route('vehicles.index') }}"
                   class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-50 transition-colors shadow-xs">
                    Cancel
                </a>
                <button type="submit" form="vehicle-form"
                        class="inline-flex items-center gap-1.5 px-5 py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-sm cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    Update Vehicle
                </button>
            </div>
        </div>

        <form id="vehicle-form" method="POST" action="{{ route('vehicles.update', $vehicle) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('vehicles._form', ['vehicleTypes' => $vehicleTypes, 'vehicle' => $vehicle])

            {{-- Bottom Save Bar --}}
            <div class="flex items-center justify-between bg-white rounded-xl border border-slate-200 shadow-sm p-4 mt-6">
                <a href="{{ route('vehicles.index') }}"
                   class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-200 transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-sm cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    Update Vehicle
                </button>
            </div>
        </form>
    </div>
</x-app-layout>