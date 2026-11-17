<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Events\VehicleTelemetryStreamed;
use App\Models\Device;
use App\Models\Vehicle;
use App\Services\TrackSolidApiService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;

/**
 * GpsLiveMap — Live Fleet Monitoring Map
 *
 * This Livewire component powers the real-time GPS Fleet Map page.
 * It connects to the TrackSolid GPS API, fetches the latest location
 * of every tracked device, and passes that data to a Leaflet.js map
 * displayed in the browser.
 *
 * Key responsibilities:
 *  - Pull live GPS coordinates, speed, ignition status from TrackSolid
 *  - Classify each vehicle as: Moving | Idling | Parked | Offline
 *  - Detect issues such as expired subscriptions or stale GPS signals
 *  - Support search/filter so dispatchers can quickly find any vehicle
 *  - Auto-refresh data every 30 seconds so the map stays current
 */
class GpsLiveMap extends Component
{
    // -------------------------------------------------------------------------
    // Public Properties — these are reactive: changing them triggers a re-render
    // -------------------------------------------------------------------------

    /**
     * The current text the user typed in the search box.
     * Used to filter the vehicle list by equipment code, plate, driver, IMEI, or model.
     * Example value: "EQ-101" or "ABC 1234"
     */
    public string $search = '';

    /**
     * Which status tab/badge the user clicked to filter the vehicle list.
     * Possible values:
     *   'all'      → show every vehicle and device
     *   'moving'   → only vehicles currently driving (speed ≥ 3 km/h)
     *   'idling'   → engine ON but vehicle is stationary
     *   'parked'   → engine OFF and vehicle is not moving
     *   'offline'  → GPS tracker lost connection to the network
     *   'stale'    → last GPS signal received more than 2 hours ago
     *   'unmapped' → GPS devices not yet assigned to any vehicle
     *   'issues'   → vehicles with expired GPS, no fix, or stale heartbeat
     */
    public string $statusFilter = 'all';

    /**
     * The database ID of the vehicle the user clicked on in the list or on the map.
     * When set, the right panel shows full telematics details for that vehicle.
     * Null means no vehicle is currently selected.
     */
    public ?int $selectedVehicleId = null;

    /**
     * The IMEI number of an unmapped GPS device the user clicked on.
     * (Unmapped = a GPS tracker not yet linked to any vehicle in the system.)
     * Null means no unmapped device is selected.
     */
    public ?string $selectedImei = null;

    /**
     * A human-readable timestamp showing when the GPS data was last pulled.
     * Example: "02:35:10 PM"
     * Displayed in the map toolbar so dispatchers know how fresh the data is.
     */
    public ?string $lastRefreshedAt = null;

    /**
     * The current health status of the connection to the TrackSolid GPS API.
     * Values:
     *   'connected' → API is responding and returning location data normally
     *   'warning'   → API responded but returned no data (possible credentials issue)
     *   'error'     → API call failed completely (network/server error)
     */
    public string $apiStatus = 'connected';

    /**
     * A human-readable error message shown to the user when the API fails.
     * Null when everything is working normally.
     */
    public ?string $errorMessage = null;

    /**
     * Whether automatic background refreshing is turned ON or OFF.
     * When true, the map refreshes every $refreshInterval seconds automatically.
     * The user can toggle this with the "Auto-refresh" button on the page.
     */
    public bool $autoRefresh = true;

    /**
     * Whether real-time WebSockets streaming via Laravel Reverb is active.
     * When enabled, live coordinates stream over WebSockets without requiring continuous HTTP polling.
     */
    public bool $useWebSockets = true;

    /**
     * How many seconds to wait between automatic GPS data refreshes.
     * Default: 30 seconds. This is wired to wire:poll in the Blade template.
     */
    public int $refreshInterval = 30;

    /**
     * Whether to include unmapped GPS devices (trackers with no assigned vehicle)
     * in the vehicle list and on the map.
     * When false, only registered fleet vehicles are shown.
     */
    public bool $showUnmapped = true;

    // -------------------------------------------------------------------------
    // Constants — fixed values used internally
    // -------------------------------------------------------------------------

    /**
     * The key used to store GPS location data in the server-side cache (Redis/file).
     * Caching prevents us from hammering the TrackSolid API on every page refresh.
     */
    protected const CACHE_KEY = 'tracksolid_live_device_locations';

    /**
     * How long (in seconds) to keep the cached GPS data before fetching fresh data.
     * Set to 25 seconds — slightly less than the 30-second auto-refresh interval —
     * so that each auto-refresh cycle fetches genuinely new data from the API.
     */
    protected const CACHE_TTL_SECONDS = 25;

    // -------------------------------------------------------------------------
    // Lifecycle Hook
    // -------------------------------------------------------------------------

    /**
     * mount() — runs once when the component first loads in the browser.
     *
     * This triggers an initial GPS data fetch so the map has pins right away
     * instead of waiting 30 seconds for the first auto-refresh to fire.
     * We pass force: false so it uses any unexpired cache instead of calling
     * the API unnecessarily if the page was just recently loaded.
     */
    public function mount(): void
    {
        $this->refreshLocations(force: false);
    }

    // -------------------------------------------------------------------------
    // Public Actions — called from the Blade template via wire:click
    // -------------------------------------------------------------------------

    /**
     * refreshLocations() — pulls fresh GPS positions from the TrackSolid API.
     *
     * HOW IT WORKS:
     *  1. Loads every GPS device IMEI from the local database.
     *  2. Sends those IMEIs to the TrackSolid API to get current coordinates,
     *     speed, ignition state, and heartbeat timestamps.
     *  3. Stores the result in server cache for 25 seconds to avoid API abuse.
     *  4. Fires the 'gps-locations-refreshed' browser event so the Leaflet map
     *     JavaScript immediately moves the pins to updated positions.
     *
     * @param  bool  $force  When true, clears the cache first and forces a fresh
     *                       API call regardless of when the last call was made.
     *                       Triggered by the "Refresh Now" button.
     *                       When false (auto-refresh or page load), cached data
     *                       is reused if still valid.
     */
    public function refreshLocations(bool $force = false): void
    {
        $apiService = app(TrackSolidApiService::class);

        try {
            // If the user clicked "Refresh Now", wipe the cache so we bypass
            // the 25-second cooldown and get the latest data immediately.
            if ($force) {
                Cache::forget(self::CACHE_KEY);
            }

            // Gather all device IMEIs from our database — these are the unique
            // hardware IDs the TrackSolid API uses to identify each GPS tracker.
            $devices = Device::query()
                ->whereNotNull('imei')
                ->where('imei', '!=', '')
                ->get(['id', 'imei', 'vehicle_id', 'device_name', 'model', 'sim', 'expiration_date', 'raw_expiration', 'group_name']);

            // Build a clean, deduplicated list of IMEI strings to send to the API.
            $imeis = $devices->pluck('imei')
                ->map(fn ($i) => trim((string) $i))
                ->filter()   // remove any empty/null values
                ->unique()   // remove duplicates
                ->values()
                ->all();

            // If there are no IMEIs at all, there's nothing to query.
            // Show a warning and exit early.
            if (empty($imeis)) {
                $this->apiStatus = 'warning';
                $this->errorMessage = 'No GPS devices found in the database.';
                $this->lastRefreshedAt = now()->format('h:i:s A');

                return;
            }

            // Fetch location data from the API, using the cache to avoid
            // hitting the API on every single Livewire poll cycle.
            // The result is an array keyed by IMEI: ['864...001' => ['lat'=>8.5, 'lng'=>124.6, ...]]
            $syncResult = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () use ($apiService, $imeis) {
                return $apiService->getDeviceLocationsWithStatus($imeis);
            });
            $rawLocations = $syncResult['locations'] ?? $syncResult;

            // If the API returned nothing even though we have devices registered,
            // flag a warning so the dispatcher knows the data may be unreliable.
            if (($syncResult['status'] ?? null) === 'failure') {
                $this->apiStatus = 'error';
                $this->errorMessage = 'TrackSolid synchronization failed. Device status is unavailable until the next successful sync.';
            } elseif (($syncResult['status'] ?? null) === 'partial_failure') {
                $this->apiStatus = 'warning';
                $this->errorMessage = 'TrackSolid synchronization partially failed. Affected devices are marked with synchronization errors.';
            } elseif (empty($rawLocations) && ! empty($imeis)) {
                $this->apiStatus = 'warning';
                $this->errorMessage = 'TrackSolid API returned no location records. Check API credentials or device connectivity.';
            } else {
                $this->apiStatus = 'connected';
                $this->errorMessage = null;
            }

            // Record the exact time the data was last refreshed for display in the map toolbar.
            $this->lastRefreshedAt = now()->format('h:i:s A');

            // Notify the Leaflet map JavaScript to redraw all pins with the new positions.
            $this->dispatch('gps-locations-refreshed', vehicles: $this->getFilteredVehicles());

            // Broadcast real-time telematics via Laravel Reverb if enabled and server is available
            if ($this->useWebSockets) {
                try {
                    $canBroadcast = app()->environment('testing');
                    if (! $canBroadcast && config('broadcasting.default') === 'reverb') {
                        $socket = @fsockopen(
                            (string) config('reverb.servers.reverb.hostname', '127.0.0.1'),
                            (int) config('reverb.servers.reverb.port', 8080),
                            $errno,
                            $errstr,
                            0.02
                        );
                        if ($socket) {
                            fclose($socket);
                            $canBroadcast = true;
                        }
                    }

                    if ($canBroadcast) {
                        $fleet = $this->getNormalizedFleetData();
                        VehicleTelemetryStreamed::dispatch(
                            array_merge($fleet['vehicles'], $fleet['unmapped']),
                            $fleet['summary'],
                            $this->lastRefreshedAt
                        );
                    }
                } catch (\Throwable) {
                    // Ignore broadcast exceptions to ensure Livewire refresh is never blocked
                }
            }

        } catch (Exception $e) {
            // Something went wrong (network timeout, bad API response, etc.).
            // Log the technical details for developers and show a friendly message to the user.
            Log::error('GpsLiveMap refresh error: '.$e->getMessage());
            $this->apiStatus = 'error';
            $this->errorMessage = 'Failed to load GPS positions: '.$e->getMessage();
            $this->lastRefreshedAt = now()->format('h:i:s A');
        }
    }

    /**
     * selectVehicle() — highlights a registered fleet vehicle in the sidebar
     * and zooms the Leaflet map to its current GPS pin.
     *
     * Called when the user clicks a vehicle row in the Fleet Units List
     * or clicks a map pin belonging to a registered vehicle.
     *
     * @param  int|null  $vehicleId  The database ID of the vehicle to focus on.
     *                               Pass null to deselect.
     */
    public function selectVehicle(?int $vehicleId): void
    {
        // Remember which vehicle is selected so the detail card renders.
        $this->selectedVehicleId = $vehicleId;

        // Clear any previously selected unmapped device — only one item can be
        // highlighted in the detail panel at a time.
        $this->selectedImei = null;

        if ($vehicleId) {
            // Tell the Leaflet map JavaScript to open this vehicle's popup marker.
            $this->dispatch('focus-vehicle-on-map', vehicleId: $vehicleId);

            // If the vehicle has valid coordinates, also pan and zoom the map
            // so the pin is centered in the viewport.
            foreach ($this->getNormalizedFleetData()['vehicles'] as $vehicle) {
                if ($vehicle['id'] === $vehicleId && $vehicle['has_coords']) {
                    $this->dispatch('focus-location-on-map', lat: $vehicle['lat'], lng: $vehicle['lng']);
                    break;
                }
            }
        }
    }

    /**
     * selectUnmappedDevice() — highlights an unregistered GPS tracker in the sidebar
     * and zooms the map to its location.
     *
     * "Unmapped" means the physical GPS hardware is transmitting data to TrackSolid
     * but has not yet been assigned to any vehicle in this system.
     * This helps fleet managers quickly identify and register stray trackers.
     *
     * @param  string|null  $imei  The hardware IMEI of the unassigned GPS tracker.
     *                             Pass null to deselect.
     */
    public function selectUnmappedDevice(?string $imei): void
    {
        // Remember which unmapped device is selected for the detail card.
        $this->selectedImei = $imei;

        // Clear any registered vehicle selection.
        $this->selectedVehicleId = null;

        if ($imei) {
            // Tell the Leaflet map to open the popup for this device's pin.
            $this->dispatch('focus-device-on-map', imei: $imei);

            // Pan/zoom the map to this device's coordinates if available.
            foreach ($this->getNormalizedFleetData()['unmapped'] as $device) {
                if ($device['imei'] === $imei && $device['has_coords']) {
                    $this->dispatch('focus-location-on-map', lat: $device['lat'], lng: $device['lng']);
                    break;
                }
            }
        }
    }

    /**
     * clearSelection() — dismisses the vehicle/device detail card in the sidebar.
     *
     * Called when the user clicks the "×" button on the detail card,
     * or when navigating away from the selected item.
     * Returns the sidebar to showing only the fleet list.
     */
    public function clearSelection(): void
    {
        $this->selectedVehicleId = null;
        $this->selectedImei = null;
    }

    /**
     * setStatusFilter() — switches which group of vehicles is shown on the map and sidebar.
     *
     * Called when the user clicks one of the colored status badges at the top:
     *   All | Moving | Idling | Parked | Offline | Stale | Issues | Unmapped
     *
     * After changing the filter, the map is immediately updated to show only
     * the matching vehicles.
     *
     * @param  string  $filter  One of: 'all', 'moving', 'idling', 'parked',
     *                          'offline', 'stale', 'unmapped', 'issues'
     */
    public function setStatusFilter(string $filter): void
    {
        // Security guard: reject any value that isn't one of the known filter names.
        // This prevents invalid data from being stored in the component state.
        $allowedFilters = ['all', 'moving', 'stationary', 'idling', 'parked', 'offline', 'stale', 'gps_stale', 'unmapped', 'issues', 'unknown', 'sync_failure', 'invalid_coordinates', 'stale_heartbeat', 'invalid_timestamp'];

        if (! in_array($filter, $allowedFilters, true)) {
            return;
        }

        $this->statusFilter = $filter;

        // When filtering to "Unmapped", automatically enable the unmapped device toggle
        // so unmapped trackers actually appear on the map (they may have been hidden).
        if ($filter === 'unmapped') {
            $this->showUnmapped = true;
        }

        // Push the updated, filtered vehicle list to the Leaflet map immediately.
        $this->dispatch('gps-locations-refreshed', vehicles: $this->getFilteredVehicles());
    }

    // -------------------------------------------------------------------------
    // Protected Helpers — internal methods not directly called from the template
    // -------------------------------------------------------------------------

    /**
     * getRawLocations() — retrieves the last-fetched GPS data from server cache.
     *
     * Returns the raw API response array that was stored by refreshLocations().
     * Structure: ['<IMEI>' => ['lat' => float, 'lng' => float, 'speed' => float, ...]]
     *
     * Returns an empty array if the cache has expired or was never populated.
     * This is safe — callers handle the empty case by marking vehicles as offline.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getRawLocations(): array
    {
        $cached = Cache::get(self::CACHE_KEY, []);

        return $cached['locations'] ?? $cached;
    }

    /** @return list<string> */
    protected function getSyncFailedImeis(): array
    {
        return Cache::get(self::CACHE_KEY, [])['failed_imeis'] ?? [];
    }

    /** @return array{human: string, stale: bool, invalid: bool} */
    protected function normalizeTimestamp(?string $timestamp, string $missingLabel, int $staleMinutes): array
    {
        if ($timestamp === null || trim($timestamp) === '') {
            return ['human' => $missingLabel, 'stale' => true, 'invalid' => false];
        }

        try {
            $timezone = (string) config('fleet_reconciliation.tracksolid.source_timezone', 'Asia/Manila');
            $parsed = Carbon::parse($timestamp, $timezone);
            $futureTolerance = max(0, (int) config('fleet_reconciliation.tracksolid.future_tolerance_minutes', 0));
            $now = now();

            if ($parsed->gt($now->copy()->addMinutes($futureTolerance))) {
                return ['human' => 'Invalid future timestamp', 'stale' => true, 'invalid' => true];
            }

            return [
                'human' => $parsed->diffForHumans(),
                'stale' => $parsed->lt($now->copy()->subMinutes(max(1, $staleMinutes))),
                'invalid' => false,
            ];
        } catch (Exception) {
            return ['human' => 'Invalid timestamp', 'stale' => true, 'invalid' => true];
        }
    }

    // -------------------------------------------------------------------------
    // Public Data Builders — compute the full fleet dataset
    // -------------------------------------------------------------------------

    /**
     * getNormalizedFleetData() — the core data-building method.
     *
     * This method does the heavy lifting: it combines data from two sources —
     *   1. Our local database (vehicles, devices, driver assignments)
     *   2. The TrackSolid API cache (live GPS coordinates, speed, ignition)
     *
     * For every vehicle and every unmapped device it computes:
     *   • lat / lng coordinates (with Null Island filtering)
     *   • speed in km/h
     *   • compass direction (0–360°)
     *   • online/offline status
     *   • ignition (engine ON/OFF) state
     *   • whether the GPS signal is "stale" (older than 2 hours)
     *   • operational state label: Moving / Idling / Parked / Offline
     *   • list of active issues (expired subscription, no fix, stale heartbeat)
     *
     * Returns three sections:
     *   'vehicles' → list of registered fleet vehicles with merged GPS data
     *   'unmapped' → list of GPS trackers not assigned to any vehicle
     *   'summary'  → counts for each status badge at the top of the page
     *
     * @return array{
     *     vehicles: list<array<string, mixed>>,
     *     unmapped: list<array<string, mixed>>,
     *     summary: array<string, int>
     * }
     */
    public function getNormalizedFleetData(): array
    {
        // Load the cached GPS positions fetched from TrackSolid.
        $rawLocations = $this->getRawLocations();
        $failedImeis = $this->getSyncFailedImeis();

        // ── SECTION 1: Registered Fleet Vehicles ─────────────────────────────
        // Load every vehicle from the database, together with its linked GPS
        // device and vehicle type (e.g. "Dump Truck", "Service Vehicle").
        $vehicles = Vehicle::query()
            ->with(['device', 'vehicleType'])
            ->orderBy('equipment_code')
            ->get();

        $normalizedVehicles = [];

        // Track which device IDs are already paired with vehicles so we can
        // find the "leftover" unpaired devices later (Section 2).
        $mappedDeviceIds = [];

        // Running totals for the status badges at the top of the page.
        $counts = [
            'total' => $vehicles->count(), // total registered vehicles (adjusted later)
            'online' => 0,  // vehicles that have GPS network connectivity
            'moving' => 0,  // speed ≥ 3 km/h
            'stationary' => 0,  // speed < 3 km/h (idling + parked)
            'idling' => 0,  // stationary + engine ON
            'parked' => 0,  // stationary + engine OFF
            'offline' => 0,  // GPS tracker disconnected from network
            'stale' => 0,  // last contact received > 2 hours ago
            'gps_stale' => 0,  // online but GPS satellite fix is > 2 hours old or missing
            'issues' => 0,  // expired subscription / no GPS fix / stale
            'has_coords' => 0,  // vehicles with valid lat/lng on the map
            'unmapped' => 0,  // unregistered GPS trackers (counted later)
            'unknown' => 0,
            'sync_failure' => 0,
            'invalid_coordinates' => 0,
            'heartbeat_stale' => 0,
            'invalid_timestamp' => 0,
        ];

        foreach ($vehicles as $vehicle) {
            // Get the GPS device paired with this vehicle (may be null if no tracker).
            $device = $vehicle->device;
            $imei = $device?->imei ? trim((string) $device->imei) : null;

            if ($device) {
                // Remember this device is "spoken for" so Section 2 skips it.
                $mappedDeviceIds[] = $device->id;
            }

            // Look up this vehicle's live GPS data by matching its IMEI to
            // the cached API response. $loc is null if the tracker has no data.
            $loc = $imei && isset($rawLocations[$imei]) ? $rawLocations[$imei] : null;
            $syncFailed = $imei !== null && in_array($imei, $failedImeis, true);
            $loc = $loc ?? [];

            // ── Coordinates ──────────────────────────────────────────────────
            // Extract latitude and longitude, ensuring they are valid numbers.
            $lat = isset($loc['lat']) && is_numeric($loc['lat']) ? (float) $loc['lat'] : null;
            $lng = isset($loc['lng']) && is_numeric($loc['lng']) ? (float) $loc['lng'] : null;
            $invalidCoordinates = ($lat !== null && ($lat < -90 || $lat > 90)) || ($lng !== null && ($lng < -180 || $lng > 180));
            if ($invalidCoordinates) {
                $lat = null;
                $lng = null;
            }

            // "Null Island" guard: coordinates of exactly (0, 0) are invalid —
            // this is what TrackSolid returns when a device has no GPS fix yet.
            // Treat it the same as having no coordinates at all.
            if ($lat !== null && $lng !== null && abs($lat) < 0.0001 && abs($lng) < 0.0001) {
                $lat = null;
                $lng = null;
            }

            $hasCoords = ($lat !== null && $lng !== null);
            if ($hasCoords) {
                $counts['has_coords']++;
            }

            // ── Motion Data ───────────────────────────────────────────────────
            // Speed in km/h from the GPS tracker. Clamped to 0 minimum (no negatives).
            $speed = isset($loc['speed']) && is_numeric($loc['speed'])
                ? max(0.0, round((float) $loc['speed'], 1))
                : 0.0;

            // Compass bearing in degrees (0 = North, 90 = East, 180 = South, 270 = West).
            // Used to rotate the map pin icon in the direction the vehicle is traveling.
            $direction = isset($loc['direction']) && is_numeric($loc['direction'])
                ? (int) $loc['direction']
                : 0;

            // ── Network Connectivity ──────────────────────────────────────────
            // The TrackSolid 'status' field tells us if the GPS tracker is currently
            // connected to the cellular network.
            // Value 1 (or '1' or 'NORMAL') = online; 0 = offline / sleeping.
            $rawStatus = $loc['status'] ?? null;
            $normalizedStatus = strtoupper(trim((string) $rawStatus));
            $statusKnown = in_array($normalizedStatus, ['0', '1', 'NORMAL'], true);
            $isOnline = in_array($normalizedStatus, ['1', 'NORMAL'], true);

            // ── Ignition / Engine Status (ACC) ────────────────────────────────
            // ACC (Accessory/Ignition) tells us whether the vehicle's engine is running.
            // 1 = engine is ON; 0 = engine is OFF.
            // This is how we distinguish "Idling" from "Parked" for stationary vehicles.
            $rawAcc = $loc['accStatus'] ?? null;
            $isIgnitionOn = $rawAcc !== null
                && ((int) $rawAcc === 1 || $rawAcc === '1');

            // ── Separate Timestamps (GPS Fix vs Device Heartbeat) ─────────────
            // gpsTime = actual satellite position fix timestamp.
            // hbTime  = cellular heartbeat communication timestamp.
            $gpsTime = ! empty($loc['gpsTime']) ? (string) $loc['gpsTime'] : null;
            $hbTime = ! empty($loc['hbTime']) ? (string) $loc['hbTime'] : null;

            $gpsTimestamp = $this->normalizeTimestamp($gpsTime, 'No Fix Reported', (int) config('fleet_reconciliation.tracksolid.gps_stale_minutes', 120));
            $heartbeatTimestamp = $this->normalizeTimestamp($hbTime, 'Never', (int) config('fleet_reconciliation.tracksolid.heartbeat_stale_minutes', 120));
            $gpsTimeHuman = $gpsTimestamp['human'];
            $isGpsStale = $gpsTimestamp['stale'];
            $hbTimeHuman = $heartbeatTimestamp['human'];
            $isHbStale = $heartbeatTimestamp['stale'];
            $isStale = $isHbStale;
            $isTimestampInvalid = $gpsTimestamp['invalid'] || $heartbeatTimestamp['invalid'];
            $lastContactHuman = $hbTime !== null ? $hbTimeHuman : ($gpsTime !== null ? $gpsTimeHuman : 'Never');

            // ── Connection Health Distinction ─────────────────────────────────
            // Distinguish an online device with stale GPS fix from a genuinely offline device
            if ($syncFailed) {
                $connectionStatus = 'sync_failure';
                $connectionLabel = 'TrackSolid synchronization failed';
            } elseif (! $statusKnown) {
                $connectionStatus = 'unknown';
                $connectionLabel = 'Device status unknown';
            } elseif (! $isOnline) {
                $connectionStatus = 'offline';
                $connectionLabel = 'Offline (No Cellular Signal)';
            } elseif ($isTimestampInvalid) {
                $connectionStatus = 'invalid_timestamp';
                $connectionLabel = 'Invalid or future GPS timestamp';
            } elseif ($invalidCoordinates) {
                $connectionStatus = 'invalid_coordinates';
                $connectionLabel = 'Invalid GPS coordinates';
            } elseif ($isHbStale) {
                $connectionStatus = 'stale_heartbeat';
                $connectionLabel = 'Connected • Stale heartbeat';
            } elseif (! $hasCoords) {
                $connectionStatus = 'online_no_fix';
                $connectionLabel = 'Connected • No GPS Fix';
            } elseif ($isGpsStale) {
                $connectionStatus = 'online_stale_gps';
                $connectionLabel = 'Connected • Stale GPS Fix';
            } else {
                $connectionStatus = 'online_active';
                $connectionLabel = 'Connected • GPS Active';
            }

            // ── Movement & Operational State Classification ───────────────────
            // 4 Clear Primary States: Moving | Stationary | GPS Stale | Offline
            if ($syncFailed) {
                $state = 'sync_failure';
                $stateLabel = 'Synchronization Failed';
                $stateColor = 'rose';
                $movementState = 'offline';
                $counts['sync_failure']++;
                $counts['offline']++;
            } elseif (! $statusKnown) {
                if ($hasCoords) {
                    $state = 'parked';
                    $stateLabel = 'Parked (Last Known)';
                    $stateColor = 'blue';
                    $movementState = 'stationary';
                    $counts['parked']++;
                    $counts['stationary']++;
                } else {
                    $state = 'offline';
                    $stateLabel = 'Offline';
                    $stateColor = 'slate';
                    $movementState = 'offline';
                    $counts['offline']++;
                }
                $counts['unknown']++;
            } elseif (! $isOnline) {
                $state = 'offline';
                $stateLabel = 'Offline';
                $stateColor = 'slate';
                $movementState = 'offline';
                $counts['offline']++;
            } elseif ($isTimestampInvalid) {
                $state = 'invalid_timestamp';
                $stateLabel = 'Invalid GPS Timestamp';
                $stateColor = 'rose';
                $movementState = 'gps_stale';
                $counts['invalid_timestamp']++;
                $counts['online']++;
            } elseif ($invalidCoordinates) {
                $state = 'invalid_coordinates';
                $stateLabel = 'Invalid GPS Coordinates';
                $stateColor = 'rose';
                $movementState = 'gps_stale';
                $counts['invalid_coordinates']++;
                $counts['online']++;
            } elseif ($isGpsStale || ! $hasCoords) {
                $movementState = 'gps_stale';
                $counts['gps_stale']++;
                $counts['online']++;

                if ($speed >= 3.0) {
                    $state = 'moving';
                    $stateLabel = 'Moving (Stale GPS)';
                    $stateColor = 'amber';
                    $counts['moving']++;
                } elseif ($isIgnitionOn) {
                    $state = 'idling';
                    $stateLabel = 'Idling (Stale GPS)';
                    $stateColor = 'amber';
                    $counts['idling']++;
                    $counts['stationary']++;
                } else {
                    $state = 'parked';
                    $stateLabel = 'Parked (Stale GPS)';
                    $stateColor = 'blue';
                    $counts['parked']++;
                    $counts['stationary']++;
                }
            } elseif ($speed >= 3.0) {
                $state = 'moving';
                $stateLabel = 'Moving';
                $stateColor = 'emerald';
                $movementState = 'moving';
                $counts['moving']++;
                $counts['online']++;
            } elseif ($isIgnitionOn) {
                $state = 'idling';
                $stateLabel = 'Idling';
                $stateColor = 'amber';
                $movementState = 'stationary';
                $counts['idling']++;
                $counts['stationary']++;
                $counts['online']++;
            } else {
                $state = 'parked';
                $stateLabel = 'Parked';
                $stateColor = 'blue';
                $movementState = 'stationary';
                $counts['parked']++;
                $counts['stationary']++;
                $counts['online']++;
            }

            if ($isStale || $isGpsStale) {
                $counts['stale']++;
            }
            if ($isHbStale && $statusKnown && $isOnline) {
                $counts['heartbeat_stale']++;
            }

            // ── Issue Detection ───────────────────────────────────────────────
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
            if ($invalidCoordinates) {
                $issues[] = 'Invalid GPS coordinates';
            }
            if ($isTimestampInvalid) {
                $issues[] = 'Invalid or future GPS/heartbeat timestamp';
            }
            if ($syncFailed) {
                $issues[] = 'TrackSolid synchronization failed';
            }

            if ($isOnline && $isGpsStale && $hasCoords) {
                $issues[] = 'Stale GPS Satellite Fix (>2 hrs)';
            }

            if ($isOnline && $isHbStale) {
                $issues[] = 'Stale Device Heartbeat (>2 hrs)';
            }

            $hasGpsIssue = $hasIssue
                || (! $hasCoords && $vehicle->hasGps())
                || ($isStale && $isOnline)
                || ($isGpsStale && $isOnline)
                || $syncFailed
                || ! $statusKnown
                || $invalidCoordinates
                || $isTimestampInvalid;

            if ($hasGpsIssue) {
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
                'movement_state' => $movementState,
                'is_online' => $isOnline,
                'is_ignition_on' => $isIgnitionOn,
                'is_stale' => $isStale,
                'is_gps_stale' => $isGpsStale,
                'is_heartbeat_stale' => $isHbStale,
                'has_invalid_timestamp' => $isTimestampInvalid,
                'has_invalid_coordinates' => $invalidCoordinates,
                'connection_status' => $connectionStatus,
                'connection_label' => $connectionLabel,
                'has_issue' => $hasGpsIssue,
                'issues' => $issues,
                'gps_time' => $gpsTime,
                'gps_time_human' => $gpsTimeHuman,
                'hb_time' => $hbTime,
                'hb_time_human' => $hbTimeHuman,
                'last_contact' => $lastContactHuman,
                'pos_type' => $loc['posType'] ?? 'GPS',
                'gps_signal' => $loc['gpsSignal'] ?? null,
                'is_unmapped' => false,
            ];
        }

        // ── SECTION 2: Unmapped GPS Devices ──────────────────────────────────
        // These are physical GPS trackers that exist in TrackSolid and in our
        // device database, but have NOT been paired with any fleet vehicle.
        // They show on the map as purple pins and appear in the "Unmapped" filter.
        $unmappedDevicesQuery = Device::query()
            ->where(function ($q) use ($mappedDeviceIds) {
                // Find devices either with no vehicle link, or not in the already-mapped list.
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
            $syncFailed = in_array($imei, $failedImeis, true);
            $loc = $loc ?? [];

            // Same coordinate extraction and Null Island filtering as above.
            $lat = isset($loc['lat']) && is_numeric($loc['lat']) ? (float) $loc['lat'] : null;
            $lng = isset($loc['lng']) && is_numeric($loc['lng']) ? (float) $loc['lng'] : null;
            $invalidCoordinates = ($lat !== null && ($lat < -90 || $lat > 90)) || ($lng !== null && ($lng < -180 || $lng > 180));
            if ($invalidCoordinates) {
                $lat = null;
                $lng = null;
            }

            if ($lat !== null && $lng !== null && abs($lat) < 0.0001 && abs($lng) < 0.0001) {
                $lat = null;
                $lng = null;
            }

            $hasCoords = ($lat !== null && $lng !== null);
            $speed = isset($loc['speed']) && is_numeric($loc['speed'])
                ? max(0.0, round((float) $loc['speed'], 1))
                : 0.0;
            $direction = isset($loc['direction']) && is_numeric($loc['direction'])
                ? (int) $loc['direction']
                : 0;

            $rawStatus = $loc['status'] ?? null;
            $normalizedStatus = strtoupper(trim((string) $rawStatus));
            $statusKnown = in_array($normalizedStatus, ['0', '1', 'NORMAL'], true);
            $isOnline = in_array($normalizedStatus, ['1', 'NORMAL'], true);

            $rawAcc = $loc['accStatus'] ?? null;
            $isIgnitionOn = $rawAcc !== null
                && ((int) $rawAcc === 1 || $rawAcc === '1');

            $gpsTime = ! empty($loc['gpsTime']) ? (string) $loc['gpsTime'] : null;
            $hbTime = ! empty($loc['hbTime']) ? (string) $loc['hbTime'] : null;

            $gpsTimestamp = $this->normalizeTimestamp($gpsTime, 'No GPS Fix Reported', (int) config('fleet_reconciliation.tracksolid.gps_stale_minutes', 120));
            $heartbeatTimestamp = $this->normalizeTimestamp($hbTime, 'Never', (int) config('fleet_reconciliation.tracksolid.heartbeat_stale_minutes', 120));
            $gpsTimeHuman = $gpsTimestamp['human'];
            $isGpsStale = $gpsTimestamp['stale'];
            $hbTimeHuman = $heartbeatTimestamp['human'];
            $isHbStale = $heartbeatTimestamp['stale'];
            $isStale = $isHbStale;
            $isTimestampInvalid = $gpsTimestamp['invalid'] || $heartbeatTimestamp['invalid'];
            $lastContactHuman = $hbTime !== null ? $hbTimeHuman : ($gpsTime !== null ? $gpsTimeHuman : 'Never');

            if ($syncFailed) {
                $connectionStatus = 'sync_failure';
                $connectionLabel = 'TrackSolid synchronization failed';
            } elseif (! $statusKnown) {
                $connectionStatus = 'unknown';
                $connectionLabel = 'Device status unknown';
            } elseif (! $isOnline) {
                $connectionStatus = 'offline';
                $connectionLabel = 'Offline (No Cellular Signal)';
            } elseif ($isTimestampInvalid) {
                $connectionStatus = 'invalid_timestamp';
                $connectionLabel = 'Invalid or future GPS timestamp';
            } elseif ($invalidCoordinates) {
                $connectionStatus = 'invalid_coordinates';
                $connectionLabel = 'Invalid GPS coordinates';
            } elseif ($isHbStale) {
                $connectionStatus = 'stale_heartbeat';
                $connectionLabel = 'Connected • Stale heartbeat';
            } elseif (! $hasCoords) {
                $connectionStatus = 'online_no_fix';
                $connectionLabel = 'Connected • No GPS Fix';
            } elseif ($isGpsStale) {
                $connectionStatus = 'online_stale_gps';
                $connectionLabel = 'Connected • Stale GPS Fix';
            } else {
                $connectionStatus = 'online_active';
                $connectionLabel = 'Connected • GPS Active';
            }

            // Classify the unmapped device's motion state
            if ($syncFailed) {
                $state = 'sync_failure';
                $stateLabel = 'Synchronization Failed';
                $stateColor = 'rose';
                $movementState = 'offline';
                $counts['sync_failure']++;
                $counts['offline']++;
            } elseif (! $statusKnown) {
                if ($hasCoords) {
                    $state = 'parked';
                    $stateLabel = 'Parked (Last Known)';
                    $stateColor = 'blue';
                    $movementState = 'stationary';
                    $counts['parked']++;
                    $counts['stationary']++;
                } else {
                    $state = 'offline';
                    $stateLabel = 'Offline';
                    $stateColor = 'slate';
                    $movementState = 'offline';
                    $counts['offline']++;
                }
                $counts['unknown']++;
            } elseif (! $isOnline) {
                $state = 'offline';
                $stateLabel = 'Offline';
                $stateColor = 'slate';
                $movementState = 'offline';
                $counts['offline']++;
            } elseif ($isTimestampInvalid) {
                $state = 'invalid_timestamp';
                $stateLabel = 'Invalid GPS Timestamp';
                $stateColor = 'rose';
                $movementState = 'gps_stale';
                $counts['invalid_timestamp']++;
                $counts['online']++;
            } elseif ($invalidCoordinates) {
                $state = 'invalid_coordinates';
                $stateLabel = 'Invalid GPS Coordinates';
                $stateColor = 'rose';
                $movementState = 'gps_stale';
                $counts['invalid_coordinates']++;
                $counts['online']++;
            } elseif ($isGpsStale || ! $hasCoords) {
                $movementState = 'gps_stale';
                $counts['gps_stale']++;
                $counts['online']++;

                if ($speed >= 3.0) {
                    $state = 'moving';
                    $stateLabel = 'Moving (Stale GPS)';
                    $stateColor = 'amber';
                    $counts['moving']++;
                } elseif ($isIgnitionOn) {
                    $state = 'idling';
                    $stateLabel = 'Idling (Stale GPS)';
                    $stateColor = 'amber';
                    $counts['idling']++;
                    $counts['stationary']++;
                } else {
                    $state = 'parked';
                    $stateLabel = 'Parked (Stale GPS)';
                    $stateColor = 'blue';
                    $counts['parked']++;
                    $counts['stationary']++;
                }
            } elseif ($speed >= 3.0) {
                $state = 'moving';
                $stateLabel = 'Moving';
                $stateColor = 'emerald';
                $movementState = 'moving';
                $counts['moving']++;
                $counts['online']++;
            } elseif ($isIgnitionOn) {
                $state = 'idling';
                $stateLabel = 'Idling';
                $stateColor = 'amber';
                $movementState = 'stationary';
                $counts['idling']++;
                $counts['stationary']++;
                $counts['online']++;
            } else {
                $state = 'parked';
                $stateLabel = 'Parked';
                $stateColor = 'blue';
                $movementState = 'stationary';
                $counts['parked']++;
                $counts['stationary']++;
                $counts['online']++;
            }

            if ($hasCoords) {
                $counts['has_coords']++;
            }

            if ($isStale || $isGpsStale) {
                $counts['stale']++;
            }
            if ($isHbStale && $statusKnown && $isOnline) {
                $counts['heartbeat_stale']++;
            }

            // Every unmapped device is already a minor issue (needs to be assigned to a vehicle),
            // but only count it in the Issues badge if it also has a GPS/staleness problem.
            $deviceIssues = ['Device not mapped to any vehicle'];
            $hasIssue = false;

            if (! $hasCoords) {
                $deviceIssues[] = 'No GPS Fix Reported';
                $hasIssue = true;
            }
            if ($invalidCoordinates) {
                $deviceIssues[] = 'Invalid GPS coordinates';
                $hasIssue = true;
            }
            if ($isTimestampInvalid) {
                $deviceIssues[] = 'Invalid or future GPS/heartbeat timestamp';
                $hasIssue = true;
            }
            if ($syncFailed) {
                $deviceIssues[] = 'TrackSolid synchronization failed';
                $hasIssue = true;
            }

            if ($isOnline && $isGpsStale && $hasCoords) {
                $deviceIssues[] = 'Stale GPS Satellite Fix (>2 hrs)';
                $hasIssue = true;
            }

            if ($isStale && $isOnline) {
                $deviceIssues[] = 'Stale Heartbeat (>2 hrs)';
                $hasIssue = true;
            }

            if ($hasIssue) {
                $counts['issues']++;
            }

            // Build a vehicle-compatible array for unmapped devices so the template
            // and map JavaScript can treat them identically to registered vehicles.
            $normalizedUnmapped[] = [
                'id' => null, // no vehicle ID — this is an orphan device
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
                'state_color' => 'purple', // always purple to distinguish from registered vehicles
                'movement_state' => $movementState,
                'is_online' => $isOnline,
                'is_ignition_on' => $isIgnitionOn,
                'is_stale' => $isStale,
                'is_gps_stale' => $isGpsStale,
                'is_heartbeat_stale' => $isHbStale,
                'has_invalid_timestamp' => $isTimestampInvalid,
                'has_invalid_coordinates' => $invalidCoordinates,
                'connection_status' => $connectionStatus,
                'connection_label' => $connectionLabel,
                'has_issue' => $hasIssue,
                'issues' => $deviceIssues,
                'gps_time' => $gpsTime,
                'gps_time_human' => $gpsTimeHuman,
                'hb_time' => $hbTime,
                'hb_time_human' => $hbTimeHuman,
                'last_contact' => $lastContactHuman,
                'pos_type' => $loc['posType'] ?? 'GPS',
                'gps_signal' => $loc['gpsSignal'] ?? null,
                'is_unmapped' => true, // flag used by template to show "Unmapped" badge
            ];
        }

        // Add the unmapped count to the totals.
        $counts['unmapped'] = count($normalizedUnmapped);
        $counts['total'] += $counts['unmapped'];

        return [
            'vehicles' => $normalizedVehicles,
            'unmapped' => $normalizedUnmapped,
            'summary' => $counts,
        ];
    }

    /**
     * getFilteredVehicles() — returns only the vehicles that match the user's
     * current search text and status filter.
     *
     * This is the final list sent to both:
     *  - The Blade sidebar (Fleet Units List)
     *  - The Leaflet map (which pins to show/hide)
     *
     * Filtering logic:
     *  1. Status filter: keeps only vehicles in the selected state tab
     *     (e.g. only "Moving" vehicles if the Moving badge was clicked).
     *  2. Search text: matches against equipment code, plate number, driver name,
     *     device name, IMEI, and vehicle model.
     *
     * @return list<array<string, mixed>>
     */
    public function getFilteredVehicles(): array
    {
        $fleet = $this->getNormalizedFleetData();
        $vehicles = $fleet['vehicles'];

        // Optionally include unmapped GPS trackers in the list and map.
        if ($this->showUnmapped) {
            $vehicles = array_merge($vehicles, $fleet['unmapped']);
        }

        $search = trim(strtolower($this->search));
        $status = $this->statusFilter;

        return array_values(array_filter($vehicles, function (array $item) use ($search, $status) {

            // ── Status Filter ─────────────────────────────────────────────────
            // Each condition checks whether the item matches the selected badge.
            // If it doesn't match, the item is excluded (return false = hide it).
            if ($status === 'moving' && $item['state'] !== 'moving') {
                return false;
            }
            if ($status === 'stationary' && ($item['movement_state'] ?? '') !== 'stationary' && $item['state'] !== 'idling' && $item['state'] !== 'parked') {
                return false;
            }
            if ($status === 'idling' && $item['state'] !== 'idling') {
                return false;
            }
            if ($status === 'parked' && $item['state'] !== 'parked') {
                return false;
            }
            if ($status === 'offline' && $item['state'] !== 'offline' && ($item['movement_state'] ?? '') !== 'offline') {
                return false;
            }
            if ($status === 'stale' && ! $item['is_stale'] && ! ($item['is_gps_stale'] ?? false)) {
                return false;
            }
            if ($status === 'gps_stale' && ! ($item['is_gps_stale'] ?? false) && ($item['movement_state'] ?? '') !== 'gps_stale') {
                return false;
            }
            if ($status === 'unmapped' && ! $item['is_unmapped']) {
                return false;
            }
            if ($status === 'issues' && ! $item['has_issue']) {
                return false;
            }
            if (in_array($status, ['unknown', 'sync_failure', 'invalid_coordinates', 'invalid_timestamp'], true) && $item['connection_status'] !== $status) {
                return false;
            }
            if ($status === 'stale_heartbeat' && ! ($item['is_heartbeat_stale'] ?? false)) {
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

            // ── Search Text Filter ────────────────────────────────────────────
            // If the user has typed something in the search box, check whether
            // any of the vehicle's key fields contain that text (case-insensitive).
            if ($search !== '') {
                $code = strtolower((string) ($item['equipment_code'] ?? ''));
                $plate = strtolower((string) ($item['plate_number'] ?? ''));
                $driver = strtolower((string) ($item['operator_driver'] ?? ''));
                $devName = strtolower((string) ($item['device_name'] ?? ''));
                $imei = strtolower((string) ($item['imei'] ?? ''));
                $model = strtolower((string) ($item['model'] ?? ''));

                // The vehicle must match in at least one of these fields.
                $matches = str_contains($code, $search)
                    || str_contains($plate, $search)
                    || str_contains($driver, $search)
                    || str_contains($devName, $search)
                    || str_contains($imei, $search)
                    || str_contains($model, $search);

                if (! $matches) {
                    return false; // hide this vehicle — it doesn't match the search
                }
            }

            return true; // this vehicle passed all filters — include it
        }));
    }

    // -------------------------------------------------------------------------
    // Render — called by Livewire on every component update
    // -------------------------------------------------------------------------

    /**
     * render() — builds the final data package and renders the Blade template.
     *
     * Called automatically by Livewire every time:
     *  - A property changes (search, statusFilter, selectedVehicleId, etc.)
     *  - The wire:poll timer fires (auto-refresh)
     *  - The user clicks a button that calls a Livewire action
     *
     * It resolves which item (if any) the user has selected so the right panel
     * detail card can display that vehicle's full telemetry information.
     */
    public function render(): View
    {
        $fleetData = $this->getNormalizedFleetData();
        $filtered = $this->getFilteredVehicles();

        // Find the full data record for whichever vehicle or device is selected.
        // This is what populates the blue "detail card" in the sidebar.
        $selectedItem = null;

        if ($this->selectedVehicleId) {
            // User selected a registered vehicle — find it by its database ID.
            foreach ($fleetData['vehicles'] as $v) {
                if ($v['id'] === $this->selectedVehicleId) {
                    $selectedItem = $v;
                    break;
                }
            }
        } elseif ($this->selectedImei) {
            // User selected an unmapped device — find it by IMEI.
            foreach ($fleetData['unmapped'] as $u) {
                if ($u['imei'] === $this->selectedImei) {
                    $selectedItem = $u;
                    break;
                }
            }
        }

        return view('livewire.gps-live-map', [
            'summary' => $fleetData['summary'],  // counts for status badges
            'vehicles' => $filtered,              // filtered list for the sidebar
            'selectedItem' => $selectedItem,          // detail card data (or null)
            'totalFleetCount' => count($fleetData['vehicles']) + count($fleetData['unmapped']),
        ]);
    }
}
