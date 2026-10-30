<x-app-layout>
    @section('page-title', 'Idling & Parking Detection')
    @section('breadcrumb')
        <a href="{{ route('gps-map.index') }}" class="hover:underline">Fleet Tracking</a> &bull; Idling & Parking Monitor
    @endsection

    <livewire:idling-parking-monitor />
</x-app-layout>
