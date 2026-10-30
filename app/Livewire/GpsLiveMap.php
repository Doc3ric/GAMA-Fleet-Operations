<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Device;
use App\Models\Vehicle;
use App\Services\TrackSolidApiService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;

class GpsLiveMap extends Component
{
    /** Search filter for vehicles or devices */
    public string $search = '';

    /** Status filter: all, moving, idling, parked, offline, stale, unmapped, issues */
    public string $statusFilter = 'all';

    /** ID of currently focused vehicle */
    public ?int $selectedVehicleId = null;

    /** IMEI of currently focused device */
    public ?string $selectedImei = null;

    /** Timestamp string of last data refresh */
    public ?string $lastRefreshedAt = null;

    /** API connectivity status: 'connected', 'warning', 'error' */
    public string $apiStatus = 'connected';

    /** Error message if API call fails */
    public ?string $errorMessage = null;

    /** Auto-refresh toggle */
    public bool $autoRefresh = true;

    /** Auto-refresh poll interval in seconds */
    public int $refreshInterval = 30;

    /** Toggle to show/hide unmapped devices on map */
    public bool $showUnmapped = true;

    /** Cache key for live location data */
    protected const CACHE_KEY = 'tracksolid_live_device_locations';

    /** Cache TTL in seconds (less than polling interval to ensure fresh updates) */
    protected const CACHE_TTL_SECONDS = 25;

    public function mount(): void
    {
        $this->refreshLocations(force: false);
    }

    /**
     * Refresh location data from TrackSolid API or server cache.
     */
    public function refreshLocations(bool $force = false): void
    {
        $apiService = app(TrackSolidApiService::class);

        try {
            if ($force) {
                Cache::forget(self::CACHE_KEY);
            }

            // Gather all device IMEIs to query
            $devices = Device::query()
                ->whereNotNull('imei')
                ->where('imei', '!=', '')
                ->get(['id', 'imei', 'vehicle_id', 'device_name', 'model', 'sim', 'expiration_date', 'raw_expiration', 'group_name']);

            $imeis = $devices->pluck('imei')
                ->map(fn ($i) => trim((string) $i))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($imeis)) {
                $this->apiStatus = 'warning';
                $this->errorMessage = 'No GPS devices found in the database.';
                $this->lastRefreshedAt = now()->format('h:i:s A');

                return;
            }

            // Fetch with caching to prevent excessive API calls
            $rawLocations = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () use ($apiService, $imeis) {
                return $apiService->getDeviceLocations($imeis);
            });

            if (empty($rawLocations) && ! empty($imeis)) {
                $this->apiStatus = 'warning';
                $this->errorMessage = 'TrackSolid API returned no location records. Check API credentials or device connectivity.';
            } else {
                $this->apiStatus = 'connected';
                $this->errorMessage = null;
            }

            $this->lastRefreshedAt = now()->format('h:i:s A');

            // Dispatch event for Alpine / Leaflet map update
            $this->dispatch('gps-locations-refreshed', vehicles: $this->getFilteredVehicles());
        } catch (Exception $e) {
            Log::error('GpsLiveMap refresh error: '.$e->getMessage());
            $this->apiStatus = 'error';
            $this->errorMessage = 'Failed to load GPS positions: '.$e->getMessage();
            $this->lastRefreshedAt = now()->format('h:i:s A');
        }
    }

    /**
     * Select a vehicle to focus on the map and view details.
     */
    public function selectVehicle(?int $vehicleId): void
    {
        $this->selectedVehicleId = $vehicleId;
        $this->selectedImei = null;

        if ($vehicleId) {
            $this->dispatch('focus-vehicle-on-map', vehicleId: $vehicleId);
        }
    }

    /**
     * Select an unmapped device to focus on the map.
     */
    public function selectUnmappedDevice(?string $imei): void
    {
        $this->selectedImei = $imei;
        $this->selectedVehicleId = null;

        if ($imei) {
            $this->dispatch('focus-device-on-map', imei: $imei);
        }
    }

    /**
     * Clear selected vehicle or device.
     */
    public function clearSelection(): void
    {
        $this->selectedVehicleId = null;
        $this->selectedImei = null;
    }

    /**
     * Set status filter.
     */
    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $filter;
        $this->dispatch('gps-locations-refreshed', vehicles: $this->getFilteredVehicles());
    }

    /**
     * Get raw cached or fresh location records.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getRawLocations(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }

    /**
     * Build normalized vehicle and unmapped device list with GPS data.
     *
     * @return array{
     *     vehicles: list<array<string, mixed>>,
     *     unmapped: list<array<string, mixed>>,
     *     summary: array<string, int>
     * }
     */
    public function getNormalizedFleetData(): array
    {
        $rawLocations = $this->getRawLocations();

        // 1. Load vehicles with their linked device and vehicle type
        $vehicles = Vehicle::query()
            ->with(['device', 'vehicleType'])
            ->orderBy('equipment_code')
            ->get();

        $normalizedVehicles = [];
        $mappedDeviceIds = [];

        $counts = [
            'total' => $vehicles->count(),
            'online' => 0,
            'moving' => 0,
            'idling' => 0,
            'parked' => 0,
            'offline' => 0,
            'stale' => 0,
            'issues' => 0,
            'has_coords' => 0,
            'unmapped' => 0,
        ];

        foreach ($vehicles as $vehicle) {
            $device = $vehicle->device;
            $imei = $device?->imei ? trim((string) $device->imei) : null;

            if ($device) {
                $mappedDeviceIds[] = $device->id;
            }

            $loc = $imei && isset($rawLocations[$imei]) ? $rawLocations[$imei] : null;

            // Extract & sanitize coordinates
            $lat = isset($loc['lat']) && is_numeric($loc['lat']) ? (float) $loc['lat'] : null;
            $lng = isset($loc['lng']) && is_numeric($loc['lng']) ? (float) $loc['lng'] : null;

            // Eliminate Null Island (0, 0)
            if ($lat !== null && $lng !== null && abs($lat) < 0.0001 && abs($lng) < 0.0001) {
                $lat = null;
                $lng = null;
            }

            $hasCoords = ($lat !== null && $lng !== null);
            if ($hasCoords) {
                $counts['has_coords']++;
            }

            // Extract motion & connectivity
            $speed = isset($loc['speed']) && is_numeric($loc['speed']) ? max(0.0, round((float) $loc['speed'], 1)) : 0.0;
            $direction = isset($loc['direction']) && is_numeric($loc['direction']) ? (int) $loc['direction'] : 0;

            // Connectivity status: 1 = online, 0 = offline
            $rawStatus = $loc['status'] ?? null;
            $isOnline = $rawStatus !== null && ((int) $rawStatus === 1 || $rawStatus === '1' || $rawStatus === 'NORMAL');

            // ACC status: 1 = engine ON, 0 = engine OFF
            $rawAcc = $loc['accStatus'] ?? null;
            $isIgnitionOn = $rawAcc !== null && ((int) $rawAcc === 1 || $rawAcc === '1');

            // Timestamps: gpsTime (fix) and hbTime (heartbeat)
            $gpsTime = ! empty($loc['gpsTime']) ? (string) $loc['gpsTime'] : null;
            $hbTime = ! empty($loc['hbTime']) ? (string) $loc['hbTime'] : null;

            // Calculate staleness (last contact older than 2 hours or no contact)
            $isStale = false;
            $lastContact = $hbTime ?? $gpsTime;
            $lastContactHuman = 'Never';

            if ($lastContact) {
                try {
                    $parsed = Carbon::parse($lastContact);
                    $lastContactHuman = $parsed->diffForHumans();
                    if ($parsed->diffInHours(now()) >= 2) {
                        $isStale = true;
                    }
                } catch (Exception) {
                    $lastContactHuman = $lastContact;
                }
            } else {
                $isStale = true;
            }

            // Semantic operational status classification
            if (! $isOnline) {
                $state = 'offline';
                $stateLabel = 'Offline';
                $stateColor = 'slate';
                $counts['offline']++;
            } elseif ($speed >= 3.0) {
                $state = 'moving';
                $stateLabel = 'Moving';
                $stateColor = 'emerald';
                $counts['moving']++;
                $counts['online']++;
            } elseif ($isIgnitionOn) {
                $state = 'idling';
                $stateLabel = 'Idling';
                $stateColor = 'amber';
                $counts['idling']++;
                $counts['online']++;
            } else {
                $state = 'parked';
                $stateLabel = 'Parked';
                $stateColor = 'blue';
                $counts['parked']++;
                $counts['online']++;
            }

            if ($isStale) {
                $counts['stale']++;
            }

            // Check for issues (subscription expiration, missing coordinates, checkup)
            $issues = [];
            $hasIssue = false;

            if ($vehicle->gps_status === 'EXPIRED' || ($device && $device->expiration_status === 'expired')) {
                $hasIssue = true;
                $issues[] = 'GPS Subscription Expired';
            } elseif ($vehicle->gps_status === 'FOR_CHECKUP') {
                $hasIssue = true;
                $issues[] = 'Flagged For Checkup';
            }

            if (! $hasCoords) {
                $issues[] = 'No GPS Fix Reported';
            }

            if ($isStale && $isOnline) {
                $issues[] = 'Stale Heartbeat (>2 hrs)';
            }

            if ($hasIssue || (! $hasCoords && $vehicle->hasGps())) {
                $counts['issues']++;
            }

            $normalizedVehicles[] = [
                'id' => $vehicle->id,
                'equipment_code' => $vehicle->equipment_code,
                'plate_number' => $vehicle->plate_number,
                'model' => $vehicle->model,
                'vehicle_type' => $vehicle->vehicleType?->name ?? 'General Fleet',
                'operator_driver' => $vehicle->operator_driver ?: 'Unassigned',
                'project_code' => $vehicle->project_code ?: 'N/A',
                'location' => $vehicle->location ?: 'Main Yard',
                'gps_status' => $vehicle->gps_status,
                'status_value' => $vehicle->status_value,
                'status_label' => $vehicle->status_label,
                'device_id' => $device?->id,
                'device_name' => $device?->device_name ?: ($loc['deviceName'] ?? null),
                'imei' => $imei,
                'sim' => $device?->sim ?: ($loc['sim'] ?? null),
                'device_model' => $device?->model ?: ($loc['mcType'] ?? null),
                'lat' => $lat,
                'lng' => $lng,
                'has_coords' => $hasCoords,
                'speed' => $speed,
                'direction' => $direction,
                'state' => $state,
                'state_label' => $stateLabel,
                'state_color' => $stateColor,
                'is_online' => $isOnline,
                'is_ignition_on' => $isIgnitionOn,
                'is_stale' => $isStale,
                'has_issue' => $hasIssue,
                'issues' => $issues,
                'gps_time' => $gpsTime,
                'hb_time' => $hbTime,
                'last_contact' => $lastContactHuman,
                'pos_type' => $loc['posType'] ?? 'GPS',
                'gps_signal' => $loc['gpsSignal'] ?? null,
                'is_unmapped' => false,
            ];
        }

        // 2. Load unmapped devices
        $unmappedDevicesQuery = Device::query()
            ->where(function ($q) use ($mappedDeviceIds) {
                $q->whereNull('vehicle_id')
                    ->orWhereNotIn('id', $mappedDeviceIds);
            })
            ->whereNotNull('imei')
            ->where('imei', '!=', '')
            ->get();

        $normalizedUnmapped = [];
        foreach ($unmappedDevicesQuery as $dev) {
            $imei = trim((string) $dev->imei);
            $loc = isset($rawLocations[$imei]) ? $rawLocations[$imei] : null;

            $lat = isset($loc['lat']) && is_numeric($loc['lat']) ? (float) $loc['lat'] : null;
            $lng = isset($loc['lng']) && is_numeric($loc['lng']) ? (float) $loc['lng'] : null;

            if ($lat !== null && $lng !== null && abs($lat) < 0.0001 && abs($lng) < 0.0001) {
                $lat = null;
                $lng = null;
            }

            $hasCoords = ($lat !== null && $lng !== null);
            $speed = isset($loc['speed']) && is_numeric($loc['speed']) ? max(0.0, round((float) $loc['speed'], 1)) : 0.0;
            $direction = isset($loc['direction']) && is_numeric($loc['direction']) ? (int) $loc['direction'] : 0;

            $rawStatus = $loc['status'] ?? null;
            $isOnline = $rawStatus !== null && ((int) $rawStatus === 1 || $rawStatus === '1' || $rawStatus === 'NORMAL');

            $rawAcc = $loc['accStatus'] ?? null;
            $isIgnitionOn = $rawAcc !== null && ((int) $rawAcc === 1 || $rawAcc === '1');

            $gpsTime = ! empty($loc['gpsTime']) ? (string) $loc['gpsTime'] : null;
            $hbTime = ! empty($loc['hbTime']) ? (string) $loc['hbTime'] : null;

            $isStale = false;
            $lastContact = $hbTime ?? $gpsTime;
            $lastContactHuman = 'Never';

            if ($lastContact) {
                try {
                    $parsed = Carbon::parse($lastContact);
                    $lastContactHuman = $parsed->diffForHumans();
                    if ($parsed->diffInHours(now()) >= 2) {
                        $isStale = true;
                    }
                } catch (Exception) {
                    $lastContactHuman = $lastContact;
                }
            } else {
                $isStale = true;
            }

            if (! $isOnline) {
                $state = 'offline';
                $stateLabel = 'Offline';
                $stateColor = 'slate';
            } elseif ($speed >= 3.0) {
                $state = 'moving';
                $stateLabel = 'Moving';
                $stateColor = 'emerald';
            } elseif ($isIgnitionOn) {
                $state = 'idling';
                $stateLabel = 'Idling';
                $stateColor = 'amber';
            } else {
                $state = 'parked';
                $stateLabel = 'Parked';
                $stateColor = 'blue';
            }

            $normalizedUnmapped[] = [
                'id' => null,
                'equipment_code' => $dev->device_name ?: "DEV-{$dev->id}",
                'plate_number' => 'Unmapped GPS Device',
                'model' => $dev->model ?: 'TrackSolid Unit',
                'vehicle_type' => 'Stand-alone Tracker',
                'operator_driver' => 'None',
                'project_code' => $dev->group_name ?: 'Inventory',
                'location' => 'Unassigned',
                'gps_status' => 'YES',
                'status_value' => null,
                'status_label' => 'Unmapped',
                'device_id' => $dev->id,
                'device_name' => $dev->device_name,
                'imei' => $imei,
                'sim' => $dev->sim ?: ($loc['sim'] ?? null),
                'device_model' => $dev->model,
                'lat' => $lat,
                'lng' => $lng,
                'has_coords' => $hasCoords,
                'speed' => $speed,
                'direction' => $direction,
                'state' => $state,
                'state_label' => $stateLabel,
                'state_color' => 'purple',
                'is_online' => $isOnline,
                'is_ignition_on' => $isIgnitionOn,
                'is_stale' => $isStale,
                'has_issue' => false,
                'issues' => ['Device not mapped to any vehicle'],
                'gps_time' => $gpsTime,
                'hb_time' => $hbTime,
                'last_contact' => $lastContactHuman,
                'pos_type' => $loc['posType'] ?? 'GPS',
                'gps_signal' => $loc['gpsSignal'] ?? null,
                'is_unmapped' => true,
            ];
        }

        $counts['unmapped'] = count($normalizedUnmapped);

        return [
            'vehicles' => $normalizedVehicles,
            'unmapped' => $normalizedUnmapped,
            'summary' => $counts,
        ];
    }

    /**
     * Get filtered vehicle items for display and map pinning.
     *
     * @return list<array<string, mixed>>
     */
    public function getFilteredVehicles(): array
    {
        $fleet = $this->getNormalizedFleetData();
        $vehicles = $fleet['vehicles'];

        if ($this->showUnmapped) {
            $vehicles = array_merge($vehicles, $fleet['unmapped']);
        }

        $search = trim(strtolower($this->search));
        $status = $this->statusFilter;

        return array_values(array_filter($vehicles, function (array $item) use ($search, $status) {
            // Status filtering
            if ($status === 'moving' && $item['state'] !== 'moving') {
                return false;
            }
            if ($status === 'idling' && $item['state'] !== 'idling') {
                return false;
            }
            if ($status === 'parked' && $item['state'] !== 'parked') {
                return false;
            }
            if ($status === 'offline' && $item['state'] !== 'offline') {
                return false;
            }
            if ($status === 'stale' && ! $item['is_stale']) {
                return false;
            }
            if ($status === 'unmapped' && ! $item['is_unmapped']) {
                return false;
            }
            if ($status === 'issues' && ! $item['has_issue']) {
                return false;
            }

            // Search filtering
            if ($search !== '') {
                $code = strtolower((string) ($item['equipment_code'] ?? ''));
                $plate = strtolower((string) ($item['plate_number'] ?? ''));
                $driver = strtolower((string) ($item['operator_driver'] ?? ''));
                $devName = strtolower((string) ($item['device_name'] ?? ''));
                $imei = strtolower((string) ($item['imei'] ?? ''));
                $model = strtolower((string) ($item['model'] ?? ''));

                $matches = str_contains($code, $search)
                    || str_contains($plate, $search)
                    || str_contains($driver, $search)
                    || str_contains($devName, $search)
                    || str_contains($imei, $search)
                    || str_contains($model, $search);

                if (! $matches) {
                    return false;
                }
            }

            return true;
        }));
    }

    public function render(): View
    {
        $fleetData = $this->getNormalizedFleetData();
        $filtered = $this->getFilteredVehicles();

        $selectedItem = null;
        if ($this->selectedVehicleId) {
            foreach ($fleetData['vehicles'] as $v) {
                if ($v['id'] === $this->selectedVehicleId) {
                    $selectedItem = $v;
                    break;
                }
            }
        } elseif ($this->selectedImei) {
            foreach ($fleetData['unmapped'] as $u) {
                if ($u['imei'] === $this->selectedImei) {
                    $selectedItem = $u;
                    break;
                }
            }
        }

        return view('livewire.gps-live-map', [
            'summary' => $fleetData['summary'],
            'vehicles' => $filtered,
            'selectedItem' => $selectedItem,
            'totalFleetCount' => count($fleetData['vehicles']) + count($fleetData['unmapped']),
        ]);
    }
}
