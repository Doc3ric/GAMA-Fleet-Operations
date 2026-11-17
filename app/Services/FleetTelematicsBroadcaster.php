<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\VehicleTelemetryStreamed;
use App\Models\Device;
use App\Models\Vehicle;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * FleetTelematicsBroadcaster
 *
 * Coordinates live telemetry acquisition from TrackSolid, normalizes GPS
 * coordinates and vehicle status, and broadcasts updates over WebSockets
 * using Laravel Reverb.
 */
class FleetTelematicsBroadcaster
{
    public const CACHE_KEY = 'tracksolid_live_device_locations';

    public const CACHE_TTL_SECONDS = 25;

    public function __construct(
        protected TrackSolidApiService $apiService
    ) {}

    /**
     * Fetch fresh GPS locations from TrackSolid, normalize fleet telemetry,
     * and broadcast the telemetry payload over WebSockets via Reverb.
     *
     * @param  bool  $force  When true, ignores cache and forces a fresh TrackSolid API request
     * @return array{
     *     vehicles: list<array<string, mixed>>,
     *     unmapped: list<array<string, mixed>>,
     *     summary: array<string, int>,
     *     broadcasted: bool,
     *     timestamp: string
     * }
     */
    public function broadcast(bool $force = false): array
    {
        if ($force) {
            Cache::forget(self::CACHE_KEY);
        }

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

        $rawLocations = [];
        $failedImeis = [];

        if (! empty($imeis)) {
            $syncResult = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () use ($imeis) {
                return $this->apiService->getDeviceLocationsWithStatus($imeis);
            });

            $rawLocations = $syncResult['locations'] ?? $syncResult;
            $failedImeis = $syncResult['failed_imeis'] ?? [];
        }

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
            'stationary' => 0,
            'idling' => 0,
            'parked' => 0,
            'offline' => 0,
            'stale' => 0,
            'gps_stale' => 0,
            'issues' => 0,
            'unmapped' => 0,
            'sync_failure' => 0,
            'unknown' => 0,
            'invalid_timestamp' => 0,
        ];

        foreach ($vehicles as $vehicle) {
            $device = $vehicle->device;
            $imei = $device ? trim((string) $device->imei) : null;

            if ($device) {
                $mappedDeviceIds[] = $device->id;
            }

            $loc = ($imei && isset($rawLocations[$imei])) ? $rawLocations[$imei] : null;
            $syncFailed = $imei && in_array($imei, $failedImeis, true);

            $vehicleItem = $this->formatTelemetryItem(
                vehicle: $vehicle,
                device: $device,
                imei: $imei,
                loc: $loc,
                syncFailed: $syncFailed,
                counts: $counts
            );

            $normalizedVehicles[] = $vehicleItem;
        }

        // Process unmapped devices
        $unmappedDevices = $devices->whereNotIn('id', $mappedDeviceIds);
        $normalizedUnmapped = [];

        foreach ($unmappedDevices as $device) {
            $imei = trim((string) $device->imei);
            $loc = isset($rawLocations[$imei]) ? $rawLocations[$imei] : null;
            $syncFailed = in_array($imei, $failedImeis, true);

            $unmappedItem = $this->formatTelemetryItem(
                vehicle: null,
                device: $device,
                imei: $imei,
                loc: $loc,
                syncFailed: $syncFailed,
                counts: $counts
            );

            $normalizedUnmapped[] = $unmappedItem;
            $counts['unmapped']++;
        }

        $timestamp = now()->format('h:i:s A');

        // Broadcast the real-time event over WebSockets via Laravel Reverb
        $broadcasted = false;
        try {
            VehicleTelemetryStreamed::dispatch(
                array_merge($normalizedVehicles, $normalizedUnmapped),
                $counts,
                $timestamp
            );
            $broadcasted = true;
        } catch (Exception $e) {
            Log::warning('VehicleTelemetryStreamed broadcast failed: '.$e->getMessage());
        }

        return [
            'vehicles' => $normalizedVehicles,
            'unmapped' => $normalizedUnmapped,
            'summary' => $counts,
            'broadcasted' => $broadcasted,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Normalizes an individual vehicle or device into standard telemetry payload.
     *
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    protected function formatTelemetryItem(
        ?Vehicle $vehicle,
        ?Device $device,
        ?string $imei,
        ?array $loc,
        bool $syncFailed,
        array &$counts
    ): array {
        $lat = isset($loc['lat']) && $loc['lat'] !== null ? (float) $loc['lat'] : null;
        $lng = isset($loc['lng']) && $loc['lng'] !== null ? (float) $loc['lng'] : null;

        $hasCoords = $lat !== null && $lng !== null;
        if ($hasCoords && abs($lat) < 0.0001 && abs($lng) < 0.0001) {
            $hasCoords = false;
            $lat = null;
            $lng = null;
        }

        $speed = isset($loc['speed']) && $loc['speed'] !== null ? (float) $loc['speed'] : 0.0;
        $direction = isset($loc['direction']) && $loc['direction'] !== null ? (int) $loc['direction'] : 0;

        $statusKnown = $loc !== null && ! $syncFailed;
        $isOnline = false;
        if ($statusKnown) {
            $rawStatus = $loc['status'] ?? null;
            $isOnline = ($rawStatus === 1 || $rawStatus === '1' || $rawStatus === true);
        }

        $rawAcc = $loc['accStatus'] ?? null;
        $isIgnitionOn = $rawAcc !== null && ((int) $rawAcc === 1 || $rawAcc === '1');

        $gpsTime = ! empty($loc['gpsTime']) ? (string) $loc['gpsTime'] : null;
        $hbTime = ! empty($loc['hbTime']) ? (string) $loc['hbTime'] : null;

        $gpsTimestamp = $this->normalizeTimestamp($gpsTime, 'No Fix Reported', (int) config('fleet_reconciliation.tracksolid.gps_stale_minutes', 120));
        $heartbeatTimestamp = $this->normalizeTimestamp($hbTime, 'Never', (int) config('fleet_reconciliation.tracksolid.heartbeat_stale_minutes', 120));

        $isGpsStale = $gpsTimestamp['stale'];
        $isHbStale = $heartbeatTimestamp['stale'];
        $isStale = $isHbStale;
        $isTimestampInvalid = $gpsTimestamp['invalid'] || $heartbeatTimestamp['invalid'];

        // Determine Operational State
        if ($syncFailed) {
            $state = 'sync_failure';
            $stateLabel = 'Synchronization Failed';
            $stateColor = 'rose';
            $movementState = 'unknown';
            $counts['sync_failure']++;
        } elseif (! $statusKnown) {
            $state = 'unknown';
            $stateLabel = 'Status Unknown';
            $stateColor = 'slate';
            $movementState = 'unknown';
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
        } elseif ($isGpsStale && $hasCoords) {
            $state = 'gps_stale';
            $stateLabel = 'GPS Fix Stale (>2h)';
            $stateColor = 'amber';
            $movementState = 'gps_stale';
            $counts['gps_stale']++;
            $counts['online']++;
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
            $movementState = 'idling';
            $counts['idling']++;
            $counts['stationary']++;
            $counts['online']++;
        } else {
            $state = 'parked';
            $stateLabel = 'Parked';
            $stateColor = 'blue';
            $movementState = 'parked';
            $counts['parked']++;
            $counts['stationary']++;
            $counts['online']++;
        }

        if ($isStale) {
            $counts['stale']++;
        }

        $issues = [];
        if ($device?->expiration_status === 'expired') {
            $issues[] = 'GPS Subscription Expired';
        }
        if ($vehicle?->gps_status === 'FOR_CHECKUP') {
            $issues[] = 'Flagged For Checkup';
        }
        if (! $hasCoords) {
            $issues[] = 'No GPS Fix Reported';
        }
        if ($isOnline && $isGpsStale && $hasCoords) {
            $issues[] = 'Stale GPS Satellite Fix (>2 hrs)';
        }
        if ($isOnline && $isHbStale) {
            $issues[] = 'Stale Device Heartbeat (>2 hrs)';
        }

        $hasGpsIssue = ! empty($issues) || $syncFailed || ! $statusKnown || $isTimestampInvalid;
        if ($hasGpsIssue) {
            $counts['issues']++;
        }

        return [
            'id' => $vehicle?->id,
            'equipment_code' => $vehicle?->equipment_code ?? ($device?->device_name ?: 'UNMAPPED-GPS'),
            'plate_number' => $vehicle?->plate_number ?? ($device?->imei ?: 'N/A'),
            'model' => $vehicle?->model ?? ($device?->model ?: 'TrackSolid Device'),
            'vehicle_type' => $vehicle?->vehicleType?->name ?? 'General Fleet',
            'operator_driver' => $vehicle?->operator_driver ?: 'Unassigned',
            'project_code' => $vehicle?->project_code ?: 'N/A',
            'location' => $vehicle?->location ?: 'Main Yard',
            'gps_status' => $vehicle?->gps_status ?? (($device && $device->expiration_status === 'expired') ? 'EXPIRED' : 'ACTIVE'),
            'status_value' => $vehicle?->status_value ?? 'active',
            'status_label' => $vehicle?->status_label ?? 'Active',
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
            'last_contact_human' => $hbTime !== null ? $heartbeatTimestamp['human'] : ($gpsTime !== null ? $gpsTimestamp['human'] : 'Never'),
            'gps_time_human' => $gpsTimestamp['human'],
            'heartbeat_time_human' => $heartbeatTimestamp['human'],
            'has_gps_issue' => $hasGpsIssue,
            'issues' => $issues,
            'is_unmapped' => $vehicle === null,
        ];
    }

    /**
     * @return array{human: string, stale: bool, invalid: bool}
     */
    protected function normalizeTimestamp(?string $raw, string $fallback, int $staleMinutes): array
    {
        if (empty($raw)) {
            return ['human' => $fallback, 'stale' => true, 'invalid' => false];
        }

        try {
            $parsed = Carbon::parse($raw);
            $now = now();

            if ($parsed->gt($now->copy()->addMinutes(5))) {
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
}
