<x-app-layout>
    @section('page-title', 'Trip History & Route Replay')
    @section('breadcrumb')
        <a href="{{ route('gps-map.index') }}" class="hover:underline">Fleet Tracking</a> &bull; Trip History & Route Replay
    @endsection

    <livewire:trip-history-replay :vehicle-id="request('vehicle_id')" :imei="request('imei')" />
</x-app-layout>
