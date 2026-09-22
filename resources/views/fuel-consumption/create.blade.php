<x-app-layout>
    @section('page-title', 'New Fuel Consumption Test')
    @section('page-subtitle', 'Full-Tank Method Evaluation')
    @section('breadcrumb', 'Fleet Reports / Average Fuel Consumption / Create')

    <div class="max-w-5xl mx-auto pb-16 space-y-6">
        <div class="flex items-center justify-between pb-1">
            <div>
                <h2 class="text-xl font-black text-slate-900 tracking-tight">Record Average Fuel Consumption Test</h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Record full-tank testing readings for company vehicles and test acquisitions.
                </p>
            </div>
            <a href="{{ route('fuel-consumption.index') }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition-colors">
                &larr; Back to Fuel Tests
            </a>
        </div>

        <form action="{{ route('fuel-consumption.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @include('fuel-consumption._form')
        </form>
    </div>
</x-app-layout>
