<x-app-layout>
    @section('page-title', 'Edit GPS Device')
    @section('breadcrumb', 'Fleet Management / GPS Devices / Edit')

    <div class="max-w-4xl mx-auto space-y-4">
        <div class="flex items-center justify-between pb-1">
            <div>
                <h2 class="text-xl font-bold text-slate-800 tracking-tight">Edit Device: {{ $device->device_name }}</h2>
                <p class="text-xs text-slate-500 mt-0.5">Update device specifications, SIM attributes, and expiration dates</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('devices.show', $device) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition-colors">
                    View Device
                </a>
                <a href="{{ route('devices.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition-colors">
                    &larr; Back to Devices
                </a>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6">
            <form method="POST" action="{{ route('devices.update', $device) }}" class="space-y-6">
                @csrf
                @method('PUT')

                @include('devices._form')

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100">
                    <a href="{{ route('devices.index') }}"
                       class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-5 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 active:bg-blue-800 rounded-xl shadow-xs transition-colors cursor-pointer">
                        Update Device
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
