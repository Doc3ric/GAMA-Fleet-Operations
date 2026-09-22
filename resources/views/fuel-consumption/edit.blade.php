<x-app-layout>
    @section('page-title', 'Edit Fuel Consumption Test: #' . $test->id)
    @section('page-subtitle', $test->vehicle?->equipment_code . ' &bull; ' . $test->test_date?->format('M d, Y'))
    @section('breadcrumb', 'Fleet Reports / Average Fuel Consumption / #' . $test->id . ' / Edit')

    <div class="max-w-5xl mx-auto pb-16 space-y-6">
        <div class="flex items-center justify-between pb-1">
            <div>
                <h2 class="text-xl font-black text-slate-900 tracking-tight">
                    Edit Fuel Test: {{ $test->vehicle?->equipment_code }}
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Update odometer readings, fuel consumed, or test attachments.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('fuel-consumption.show', $test) }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition-colors">
                    View Record
                </a>
                <a href="{{ route('fuel-consumption.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition-colors">
                    &larr; Back to Fuel Tests
                </a>
            </div>
        </div>

        <form action="{{ route('fuel-consumption.update', $test) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('fuel-consumption._form')
        </form>
    </div>
</x-app-layout>
