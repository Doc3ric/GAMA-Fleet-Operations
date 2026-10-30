<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Device;
use App\Models\TelemetryEvent;
use App\Models\Vehicle;
use App\Services\IdlingParkingDetectionService;
use App\Services\TrackSolidApiService;
use Carbon\Carbon;
use Exception;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class IdlingParkingMonitor extends Component
{
    use WithPagination;

    public string $datePreset = 'today';

    public string $startDate = '';

    public string $endDate = '';

    public ?int $selectedVehicleId = null;

    public string $search = '';

    public string $eventType = 'all';

    public string $statusMode = 'all'; // 'all', 'ongoing', 'completed'

    public string $dataQuality = 'all';

    public ?int $viewingEventId = null;

    public ?string $feedbackMessage = null;

    public ?string $errorMessage = null;

    public bool $isScanning = false;

    public function mount(): void
    {
        $this->setDateRange('today');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingEventType(): void
    {
        $this->resetPage();
    }

    public function updatingStatusMode(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedVehicleId(): void
    {
        $this->resetPage();
    }

    public function setDateRange(string $preset): void
    {
        $this->datePreset = $preset;

        switch ($preset) {
            case 'today':
                $this->startDate = Carbon::today()->format('Y-m-d');
                $this->endDate = Carbon::today()->format('Y-m-d');
                break;
            case 'yesterday':
                $this->startDate = Carbon::yesterday()->format('Y-m-d');
                $this->endDate = Carbon::yesterday()->format('Y-m-d');
                break;
            case '7days':
                $this->startDate = Carbon::today()->subDays(6)->format('Y-m-d');
                $this->endDate = Carbon::today()->format('Y-m-d');
                break;
            case '30days':
                $this->startDate = Carbon::today()->subDays(29)->format('Y-m-d');
                $this->endDate = Carbon::today()->format('Y-m-d');
                break;
        }

        $this->resetPage();
    }

    /**
     * Trigger live snapshot scan across all registered fleet devices.
     */
    public function scanLiveFleet(
        TrackSolidApiService $api,
        IdlingParkingDetectionService $detector
    ): void {
        try {
            $locations = $api->getDeviceLocations();
            if (empty($locations)) {
                $this->errorMessage = 'TrackSolid API returned no location records for live scan.';

                return;
            }

            $res = $detector->processTelemetrySnapshot($locations);
            $this->feedbackMessage = "Processed {$res['processed']} live devices: {$res['transitioned']} events transitioned/opened, {$res['updated']} durations updated.";
            $this->errorMessage = null;
        } catch (Exception $e) {
            $this->errorMessage = 'Live fleet scan failed: '.$e->getMessage();
        }
    }

    /**
     * Reconstruct idling and parking intervals from TrackSolid GPS history for selected vehicle and date.
     */
    public function reconstructHistory(
        TrackSolidApiService $api,
        IdlingParkingDetectionService $detector
    ): void {
        if (! $this->selectedVehicleId) {
            $this->errorMessage = 'Please select a specific vehicle to reconstruct historical GPS tracks.';

            return;
        }

        $vehicle = Vehicle::with('device')->find($this->selectedVehicleId);
        if (! $vehicle) {
            $this->errorMessage = 'Selected vehicle not found.';

            return;
        }

        $device = $vehicle->device ?: Device::where('vehicle_id', $vehicle->id)->first();
        if (! $device || empty($device->imei)) {
            $this->errorMessage = "Vehicle '{$vehicle->equipment_code}' has no linked GPS device IMEI.";

            return;
        }

        $fromTime = Carbon::parse($this->startDate)->startOfDay()->format('Y-m-d H:i:s');
        $toTime = Carbon::parse($this->endDate)->endOfDay()->format('Y-m-d H:i:s');

        try {
            $track = $api->getHistoricalTrack($device->imei, $fromTime, $toTime);

            if (empty($track['points'])) {
                $this->errorMessage = "No historical GPS track points returned from TrackSolid for '{$vehicle->equipment_code}' between {$this->startDate} and {$this->endDate}.";

                return;
            }

            $result = $detector->reconstructFromHistoricalTrack(
                imei: $device->imei,
                trackPoints: $track['points'],
                vehicleId: $vehicle->id,
                deviceId: $device->id
            );

            $this->feedbackMessage = "Successfully analyzed {$track['total_points']} GPS track points! Reconstructed {$result['created_count']} stationary and movement event intervals.";
            $this->errorMessage = null;
        } catch (Exception $e) {
            $this->errorMessage = 'Reconstruction failed: '.$e->getMessage();
        }
    }

    public function viewEvent(?int $eventId): void
    {
        $this->viewingEventId = $eventId;
    }

    public function closeEventModal(): void
    {
        $this->viewingEventId = null;
    }

    public function closeOngoingEvent(int $eventId): void
    {
        $event = TelemetryEvent::find($eventId);
        if ($event && $event->is_ongoing) {
            $event->update([
                'is_ongoing' => false,
                'end_time' => now(),
                'duration_seconds' => max(0, (int) now()->diffInSeconds($event->start_time)),
            ]);
            $this->feedbackMessage = 'Ongoing event was successfully marked completed.';
        }
    }

    public function render(IdlingParkingDetectionService $detector): View
    {
        $from = $this->startDate ? Carbon::parse($this->startDate)->startOfDay()->toDateTimeString() : null;
        $to = $this->endDate ? Carbon::parse($this->endDate)->endOfDay()->toDateTimeString() : null;

        $stats = $detector->getStatistics($from, $to, $this->selectedVehicleId);

        $query = TelemetryEvent::query()
            ->with(['vehicle.vehicleType', 'device'])
            ->when($from, fn ($q) => $q->where('start_time', '>=', $from))
            ->when($to, fn ($q) => $q->where('start_time', '<=', $to))
            ->when($this->selectedVehicleId, fn ($q) => $q->where('vehicle_id', $this->selectedVehicleId))
            ->when($this->eventType !== 'all', fn ($q) => $q->where('event_type', $this->eventType))
            ->when($this->statusMode === 'ongoing', fn ($q) => $q->where('is_ongoing', true))
            ->when($this->statusMode === 'completed', fn ($q) => $q->where('is_ongoing', false))
            ->when($this->dataQuality !== 'all', fn ($q) => $q->where('data_quality', $this->dataQuality));

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('imei', 'like', "%{$search}%")
                    ->orWhereHas('vehicle', function ($vq) use ($search) {
                        $vq->where('equipment_code', 'like', "%{$search}%")
                            ->orWhere('plate_number', 'like', "%{$search}%")
                            ->orWhere('operator_driver', 'like', "%{$search}%");
                    })
                    ->orWhereHas('device', function ($dq) use ($search) {
                        $dq->where('device_name', 'like', "%{$search}%");
                    });
            });
        }

        $events = $query->orderByDesc('start_time')->paginate(20);

        $vehicles = Vehicle::query()
            ->whereNotNull('equipment_code')
            ->orderBy('equipment_code')
            ->get(['id', 'equipment_code', 'plate_number']);

        $viewingEvent = $this->viewingEventId ? TelemetryEvent::with(['vehicle', 'device'])->find($this->viewingEventId) : null;

        return view('livewire.idling-parking-monitor', [
            'events' => $events,
            'stats' => $stats,
            'vehicles' => $vehicles,
            'viewingEvent' => $viewingEvent,
        ]);
    }
}
