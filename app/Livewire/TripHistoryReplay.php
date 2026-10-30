<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\AdvancedItinerary;
use App\Models\Device;
use App\Models\Vehicle;
use App\Services\TrackSolidApiService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;

class TripHistoryReplay extends Component
{
    /** Selected target identifier in format "v_{vehicleId}" or "d_{deviceId}" */
    public string $selectedTarget = '';

    /** Active search filter in the vehicle selector */
    public string $targetSearch = '';

    /** Start date in Y-m-d format */
    public string $startDate = '';

    /** Start time in H:i format */
    public string $startTime = '00:00';

    /** End date in Y-m-d format */
    public string $endDate = '';

    /** End time in H:i format */
    public string $endTime = '23:59';

    /** Preset filter: 'today', 'yesterday', 'past_24h', 'past_7d', 'custom' */
    public string $preset = 'today';

    /** Replay data retrieved from API and normalized (points stripped for lightweight payload) */
    public ?array $trackData = null;

    /** Extracted stop and idling intervals along the route */
    public array $stops = [];

    /** Transient raw track points for initial HTML view render (never serialized into Livewire payload) */
    protected array $cachedPoints = [];

    /** Cache key for current batch of track points (served via /trip-history/points/{key}) */
    public string $pointsCacheKey = '';

    /** Comparison against planned AdvancedItinerary if exists */
    public ?array $itineraryComparison = null;

    /** Loading / processing flag */
    public bool $isLoading = false;

    /** Error / warning message */
    public ?string $errorMessage = null;

    /** Success notice message */
    public ?string $noticeMessage = null;

    public function mount(?int $vehicleId = null, ?string $imei = null): void
    {
        $now = Carbon::now();
        $this->startDate = $now->format('Y-m-d');
        $this->endDate = $now->format('Y-m-d');
        $this->startTime = '00:00';
        $this->endTime = $now->format('H:i');

        // Check if preselected by vehicle ID or IMEI
        if ($vehicleId) {
            $this->selectedTarget = "v_{$vehicleId}";
        } elseif ($imei) {
            $device = Device::where('imei', $imei)->first();
            if ($device) {
                $this->selectedTarget = $device->vehicle_id ? "v_{$device->vehicle_id}" : "d_{$device->id}";
            }
        }

        // If no target provided, pick the first available vehicle with an IMEI
        if (empty($this->selectedTarget)) {
            $firstVehicle = Vehicle::whereHas('device', function ($q) {
                $q->whereNotNull('imei')->where('imei', '!=', '');
            })->first();

            if ($firstVehicle) {
                $this->selectedTarget = "v_{$firstVehicle->id}";
            } else {
                $firstDevice = Device::whereNotNull('imei')->where('imei', '!=', '')->first();
                if ($firstDevice) {
                    $this->selectedTarget = "d_{$firstDevice->id}";
                }
            }
        }

        if (! empty($this->selectedTarget)) {
            $this->loadTrack();
        }
    }

    /**
     * Apply date range preset and reload track.
     */
    public function applyPreset(string $preset): void
    {
        $this->preset = $preset;
        $now = Carbon::now();

        switch ($preset) {
            case 'today':
                $this->startDate = $now->format('Y-m-d');
                $this->endDate = $now->format('Y-m-d');
                $this->startTime = '00:00';
                $this->endTime = $now->format('H:i');
                break;

            case 'yesterday':
                $yesterday = $now->copy()->subDay();
                $this->startDate = $yesterday->format('Y-m-d');
                $this->endDate = $yesterday->format('Y-m-d');
                $this->startTime = '00:00';
                $this->endTime = '23:59';
                break;

            case 'past_24h':
                $yesterday = $now->copy()->subDay();
                $this->startDate = $yesterday->format('Y-m-d');
                $this->startTime = $yesterday->format('H:i');
                $this->endDate = $now->format('Y-m-d');
                $this->endTime = $now->format('H:i');
                break;

            case 'past_7d':
                $pastWeek = $now->copy()->subDays(6);
                $this->startDate = $pastWeek->format('Y-m-d');
                $this->startTime = '00:00';
                $this->endDate = $now->format('Y-m-d');
                $this->endTime = $now->format('H:i');
                break;

            case 'custom':
            default:
                // Keep current values
                break;
        }

        $this->loadTrack();
    }

    /**
     * Fetch historical track from TrackSolid API and normalize points, stops, and metrics.
     */
    public function loadTrack(): void
    {
        $this->errorMessage = null;
        $this->noticeMessage = null;
        $this->trackData = null;
        $this->stops = [];
        $this->itineraryComparison = null;
        $this->pointsCacheKey = '';

        // Allow up to 120 seconds for large date range queries (e.g. 7 days) to complete
        set_time_limit(120);

        if (empty($this->selectedTarget)) {
            $this->errorMessage = 'Please select a vehicle or device to view trip history.';

            return;
        }

        // Resolve IMEI, vehicle, and device details
        $resolved = $this->resolveTargetDetails($this->selectedTarget);
        if (! $resolved || empty($resolved['imei'])) {
            $this->errorMessage = 'Selected item has no valid TrackSolid GPS device IMEI assigned.';

            return;
        }

        $imei = $resolved['imei'];
        $vehicle = $resolved['vehicle'];
        $device = $resolved['device'];

        // Validate time range
        $startStr = "{$this->startDate} {$this->startTime}:00";
        $endStr = "{$this->endDate} {$this->endTime}:59";

        try {
            $startCarbon = Carbon::parse($startStr);
            $endCarbon = Carbon::parse($endStr);
        } catch (Exception $e) {
            $this->errorMessage = 'Invalid start or end date format: '.$e->getMessage();

            return;
        }

        if ($endCarbon->lt($startCarbon)) {
            $this->errorMessage = 'End date & time cannot be earlier than start date & time.';

            return;
        }

        // TrackSolid API limits query range to at most 30 days
        if ($startCarbon->diffInDays($endCarbon) > 31) {
            $this->errorMessage = 'Historical track queries are limited to a maximum range of 30 days.';

            return;
        }

        $cacheKey = "tracksolid_track_{$imei}_{$this->startDate}_{$this->startTime}_{$this->endDate}_{$this->endTime}";

        try {
            $apiService = app(TrackSolidApiService::class);

            // Cache track results for 60 seconds (or 10 minutes if past date)
            $isPastDate = $endCarbon->lt(Carbon::now()->subMinutes(10));
            $ttl = $isPastDate ? 600 : 45;

            $track = Cache::remember($cacheKey, $ttl, function () use ($apiService, $imei, $startStr, $endStr) {
                return $apiService->getHistoricalTrack($imei, $startStr, $endStr);
            });

            if (empty($track['points'])) {
                $this->errorMessage = "No GPS track points found for {$resolved['title']} ({$imei}) between {$startCarbon->format('M d, Y h:i A')} and {$endCarbon->format('M d, Y h:i A')}. The vehicle may have been stationary, offline, or outside cellular coverage.";
                $this->dispatch('track-data-cleared');

                return;
            }

            // Extract stops and categorize operational durations
            $durations = $this->calculateDurations($track['points']);
            $track['moving_duration_seconds'] = $durations['moving'];
            $track['idling_duration_seconds'] = $durations['idling'];
            $track['parking_duration_seconds'] = $durations['parking'];
            $track['moving_duration_human'] = $this->formatDurationHuman($durations['moving']);
            $track['idling_duration_human'] = $this->formatDurationHuman($durations['idling']);
            $track['parking_duration_human'] = $this->formatDurationHuman($durations['parking']);

            // Extract stop events
            $this->stops = $this->extractStopEvents($track['points']);

            // Attach resolved vehicle metadata
            $track['vehicle_code'] = $resolved['title'];
            $track['vehicle_plate'] = $resolved['plate'];
            $track['vehicle_model'] = $resolved['model'];
            $track['imei'] = $imei;

            // Keep points in protected memory for initial HTML render, avoiding bloated Livewire state payload
            $this->cachedPoints = $track['points'] ?? [];

            // Cache points server-side so Alpine can fetch them via a lightweight JSON endpoint
            // instead of embedding thousands of coordinates into the Livewire response body.
            // Store the raw hash only; the route prefixes "trip_points_" when looking up the cache.
            $pointsHash = md5("{$imei}_{$this->startDate}_{$this->startTime}_{$this->endDate}_{$this->endTime}");
            $isPastDate = $endCarbon->lt(Carbon::now()->subMinutes(10));
            Cache::put("trip_points_{$pointsHash}", $this->cachedPoints, $isPastDate ? 600 : 120);
            $this->pointsCacheKey = $pointsHash;

            // Strip heavy coordinate points from Livewire public component state to keep network payloads under ~5 KB
            $summary = $track;
            unset($summary['points']);
            $this->trackData = $summary;

            // Dispatch lightweight event — Alpine fetches points via /trip-history/points/{hash} endpoint
            $this->dispatch('track-data-loaded', track: $summary, stops: $this->stops, pointsKey: $pointsHash);

            // Check planned AdvancedItinerary for itinerary comparison
            if ($vehicle) {
                $this->itineraryComparison = $this->findItineraryComparison($vehicle, $this->startDate, (float) $track['total_distance_km']);
            }

            $this->noticeMessage = "Loaded {$track['total_points']} GPS points covering {$track['total_distance_km']} km.";
        } catch (Exception $e) {
            Log::error("Failed to load historical track for IMEI {$imei}: {$e->getMessage()}");
            $this->errorMessage = 'Failed to retrieve GPS history: '.$e->getMessage();
        }
    }

    /**
     * Resolve target selection into vehicle/device details.
     *
     * @return array{title: string, plate: string, model: string, imei: ?string, vehicle: ?Vehicle, device: ?Device}|null
     */
    protected function resolveTargetDetails(string $target): ?array
    {
        if (str_starts_with($target, 'v_')) {
            $vehicleId = (int) substr($target, 2);
            $vehicle = Vehicle::with('device')->find($vehicleId);
            if (! $vehicle) {
                return null;
            }

            $device = $vehicle->device;
            $imei = $device?->imei;

            return [
                'title' => $vehicle->equipment_code ?: "VEH-{$vehicle->id}",
                'plate' => $vehicle->plate_number ?: 'No Plate',
                'model' => $vehicle->model ?: 'Fleet Vehicle',
                'imei' => $imei ? trim((string) $imei) : null,
                'vehicle' => $vehicle,
                'device' => $device,
            ];
        }

        if (str_starts_with($target, 'd_')) {
            $deviceId = (int) substr($target, 2);
            $device = Device::with('vehicle')->find($deviceId);
            if (! $device) {
                return null;
            }

            $vehicle = $device->vehicle;

            return [
                'title' => $device->device_name ?: "DEV-{$device->id}",
                'plate' => $vehicle?->plate_number ?: 'Standalone Tracker',
                'model' => $device->model ?: 'TrackSolid Unit',
                'imei' => $device->imei ? trim((string) $device->imei) : null,
                'vehicle' => $vehicle,
                'device' => $device,
            ];
        }

        return null;
    }

    /**
     * Calculate total moving, idling, and parked duration in seconds from track points.
     *
     * @param  list<array<string, mixed>>  $points
     * @return array{moving: int, idling: int, parking: int}
     */
    protected function calculateDurations(array $points): array
    {
        $movingSec = 0;
        $idlingSec = 0;
        $parkingSec = 0;

        $count = count($points);
        if ($count < 2) {
            return ['moving' => 0, 'idling' => 0, 'parking' => 0];
        }

        for ($i = 0; $i < $count - 1; $i++) {
            $curr = $points[$i];
            $next = $points[$i + 1];

            try {
                $t1 = Carbon::parse($curr['gps_time']);
                $t2 = Carbon::parse($next['gps_time']);
                $diff = abs($t2->getTimestamp() - $t1->getTimestamp());

                // Reject gaps longer than 2 hours (offline / powered down)
                if ($diff > 7200) {
                    $diff = 60;
                }

                $state = $curr['state'] ?? 'parked';
                if ($state === 'moving') {
                    $movingSec += $diff;
                } elseif ($state === 'idling') {
                    $idlingSec += $diff;
                } else {
                    $parkingSec += $diff;
                }
            } catch (Exception) {
                continue;
            }
        }

        return [
            'moving' => $movingSec,
            'idling' => $idlingSec,
            'parking' => $parkingSec,
        ];
    }

    /**
     * Extract stationary stops (idling or parking intervals >= 60 seconds) along the route.
     *
     * @param  list<array<string, mixed>>  $points
     * @return list<array<string, mixed>>
     */
    protected function extractStopEvents(array $points): array
    {
        $stops = [];
        $count = count($points);
        if ($count < 2) {
            return [];
        }

        $cluster = [];
        $clusterState = null;
        $startIndex = 0;

        for ($i = 0; $i < $count; $i++) {
            $pt = $points[$i];
            $state = $pt['state'] ?? 'parked';

            if ($state === 'moving') {
                if ($clusterState !== null && count($cluster) > 0) {
                    $stop = $this->buildStopFromCluster($cluster, $clusterState, $startIndex);
                    if ($stop) {
                        $stops[] = $stop;
                    }
                    $cluster = [];
                    $clusterState = null;
                }
            } else {
                if ($clusterState === null) {
                    $clusterState = $state;
                    $startIndex = $i;
                    $cluster[] = $pt;
                } elseif ($clusterState === $state) {
                    $cluster[] = $pt;
                } else {
                    // State changed between idling and parked
                    $stop = $this->buildStopFromCluster($cluster, $clusterState, $startIndex);
                    if ($stop) {
                        $stops[] = $stop;
                    }
                    $clusterState = $state;
                    $startIndex = $i;
                    $cluster = [$pt];
                }
            }
        }

        // Check if trailing cluster is a stop
        if ($clusterState !== null && count($cluster) > 0) {
            $stop = $this->buildStopFromCluster($cluster, $clusterState, $startIndex);
            if ($stop) {
                $stops[] = $stop;
            }
        }

        return $stops;
    }

    /**
     * Build a single stop record from a stationary cluster of points.
     *
     * @param  list<array<string, mixed>>  $cluster
     * @return array<string, mixed>|null
     */
    protected function buildStopFromCluster(array $cluster, string $type, int $startIndex): ?array
    {
        if (count($cluster) < 1) {
            return null;
        }

        $firstPt = $cluster[0];
        $lastPt = $cluster[count($cluster) - 1];

        try {
            $t1 = Carbon::parse($firstPt['gps_time']);
            $t2 = Carbon::parse($lastPt['gps_time']);
            $durationSeconds = abs($t2->getTimestamp() - $t1->getTimestamp());
        } catch (Exception) {
            $durationSeconds = 0;
        }

        // Filter out brief sensor jitters (less than 60s)
        if ($durationSeconds < 60 && count($cluster) < 3) {
            return null;
        }

        return [
            'type' => $type, // 'idling' or 'parked'
            'start_time' => $firstPt['gps_time'],
            'end_time' => $lastPt['gps_time'],
            'duration_seconds' => $durationSeconds,
            'duration_human' => $this->formatDurationHuman($durationSeconds),
            'lat' => (float) $firstPt['lat'],
            'lng' => (float) $firstPt['lng'],
            'point_index' => $startIndex,
            'point_count' => count($cluster),
        ];
    }

    /**
     * Compare actual GPS track against planned AdvancedItinerary for the vehicle and date.
     *
     * @return array<string, mixed>|null
     */
    protected function findItineraryComparison(Vehicle $vehicle, string $date, float $actualDistanceKm): ?array
    {
        $itinerary = AdvancedItinerary::where('vehicle_id', $vehicle->id)
            ->whereDate('itinerary_date', $date)
            ->with('legs')
            ->first();

        if (! $itinerary) {
            return null;
        }

        $plannedDistance = (float) $itinerary->total_distance;
        $varianceKm = round($actualDistanceKm - $plannedDistance, 2);
        $variancePercent = $plannedDistance > 0 ? round(($varianceKm / $plannedDistance) * 100, 1) : 0.0;

        return [
            'has_itinerary' => true,
            'id' => $itinerary->id,
            'title' => $itinerary->title ?: 'Planned Daily Itinerary',
            'destination' => $itinerary->destination ?: 'Operational Site',
            'driver_name' => $itinerary->driver_name ?: 'Assigned Driver',
            'status' => $itinerary->status,
            'planned_distance' => $plannedDistance,
            'actual_distance' => $actualDistanceKm,
            'variance_km' => $varianceKm,
            'variance_percent' => $variancePercent,
            'legs_count' => $itinerary->legs->count(),
            'notes' => $itinerary->notes,
        ];
    }

    /**
     * Format duration seconds into a human-friendly string.
     */
    protected function formatDurationHuman(int|float $seconds): string
    {
        $seconds = (int) round((float) $seconds);
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

    /**
     * Get target choices for dropdown selector.
     *
     * @return list<array{key: string, label: string, sublabel: string, is_mapped: bool}>
     */
    public function getTargetOptions(): array
    {
        $search = trim(strtolower($this->targetSearch));

        // Vehicles with devices
        $vehicles = Vehicle::with('device')
            ->whereHas('device', function ($q) {
                $q->whereNotNull('imei')->where('imei', '!=', '');
            })
            ->get();

        $options = [];

        foreach ($vehicles as $v) {
            $code = $v->equipment_code ?: "VEH-{$v->id}";
            $plate = $v->plate_number ?: 'No Plate';
            $imei = $v->device?->imei ?: '';

            if ($search !== '' && ! str_contains(strtolower("{$code} {$plate} {$imei}"), $search)) {
                continue;
            }

            $options[] = [
                'key' => "v_{$v->id}",
                'label' => "{$code} ({$plate})",
                'sublabel' => "IMEI: {$imei} • {$v->vehicle_type}",
                'is_mapped' => true,
            ];
        }

        // Unmapped devices with IMEIs
        $unmappedDevices = Device::whereNull('vehicle_id')
            ->whereNotNull('imei')
            ->where('imei', '!=', '')
            ->get();

        foreach ($unmappedDevices as $d) {
            $name = $d->device_name ?: "Device {$d->id}";
            $imei = $d->imei;
            $model = $d->model ?: 'TrackSolid Unit';

            if ($search !== '' && ! str_contains(strtolower("{$name} {$imei} {$model}"), $search)) {
                continue;
            }

            $options[] = [
                'key' => "d_{$d->id}",
                'label' => "{$name} [Unmapped]",
                'sublabel' => "IMEI: {$imei} • {$model}",
                'is_mapped' => false,
            ];
        }

        return $options;
    }

    public function render(): View
    {
        return view('livewire.trip-history-replay', [
            'targetOptions' => $this->getTargetOptions(),
            'initialPoints' => [],
            'pointsCacheKey' => $this->pointsCacheKey,
        ]);
    }
}
