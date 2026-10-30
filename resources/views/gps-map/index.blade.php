<x-app-layout>
    @section('page-title', 'Live GPS Fleet Map')
    @section('breadcrumb')
        <a href="{{ route('devices.index') }}" class="hover:underline">Fleet Management</a> &bull; Live GPS Map & Vehicle Status
    @endsection

    <livewire:gps-live-map />
</x-app-layout>
