<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Device;
use App\Models\TelemetryEvent;
use App\Models\Vehicle;
use Carbon\Carbon;
use Exception;

class IdlingParkingDetectionService
{
    /** Movement threshold in km/h to distinguish stationary vs moving (filters GPS jitter) */
    public const MOVEMENT_THRESHOLD_KMH = 3.0;

    /** Minimum stationary duration in seconds to confirm an idling or parking event */
    public const CONFIRMATION_MIN_SECONDS = 60;

    /**
     * Classify operational state based strictly on connectivity, ignition, and speed signals.
     * Speed = 0 does NOT mean offline.
     */
    public function classifyState(bool $isOnline, bool $isIgnitionOn, float $speedKmh): string
    {
        if (! $isOnline) {
            return TelemetryEvent::TYPE_OFFLINE;
        }

        if ($speedKmh >= self::MOVEMENT_THRESHOLD_KMH) {
            return TelemetryEvent::TYPE_MOVING;
        }

        if ($isIgnitionOn) {
            return TelemetryEvent::TYPE_IDLING;
        }

        return TelemetryEvent::TYPE_PARKING;
    }

    /**
     * Process live telemetry snapshots, transitioning ongoing events and opening new ones.
     *
     * @param  array<string, array<string, mixed>>  $locations  Keyed by IMEI
     * @return array{processed: int, transitioned: int, updated: int}
     */
    public function processTelemetrySnapshot(array $locations): array
    {
        if (empty($locations)) {
            return ['processed' => 0, 'transitioned' => 0, 'updated' => 0];
        }

        $devices = Device::query()
            ->whereIn('imei', array_keys($locations))
            ->with('vehicle')
            ->get()
            ->keyBy('imei');

        // Load all active ongoing events for these IMEIs
        $ongoingEvents = TelemetryEvent::query()
            ->where('is_ongoing', true)
            ->whereIn('imei', array_keys($locations))
            ->get()
            ->keyBy('imei');

        $processed = 0;
        $transitioned = 0;
        $updated = 0;

        foreach ($locations as $imei => $loc) {
            $device = $devices->get($imei);
            $vehicle = $device?->vehicle;

            $speed = isset($loc['speed']) && is_numeric($loc['speed']) ? max(0.0, (float) $loc['speed']) : 0.0;
            $rawStatus = $loc['status'] ?? null;
            $isOnline = $rawStatus !== null && ((int) $rawStatus === 1 || $rawStatus === '1' || $rawStatus === 'NORMAL');

            $rawAcc = $loc['accStatus'] ?? null;
            $isIgnitionOn = $rawAcc !== null && ((int) $rawAcc === 1 || $rawAcc === '1' || strtoupper((string) $rawAcc) === 'ON');

            $currentState = $this->classifyState($isOnline, $isIgnitionOn, $speed);

            $lat = isset($loc['lat']) && is_numeric($loc['lat']) ? (float) $loc['lat'] : null;
            $lng = isset($loc['lng']) && is_numeric($loc['lng']) ? (float) $loc['lng'] : null;
            if ($lat !== null && $lng !== null && abs($lat) < 0.0001 && abs($lng) < 0.0001) {
                $lat = null;
                $lng = null;
            }

            $now = now();
            $ongoing = $ongoingEvents->get($imei);

            if (! $ongoing) {
                // No ongoing event: start tracking
                TelemetryEvent::create([
                    'vehicle_id' => $vehicle?->id,
                    'device_id' => $device?->id,
                    'imei' => $imei,
                    'event_type' => $currentState,
                    'start_time' => $now,
                    'duration_seconds' => 0,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'is_ongoing' => true,
                    'data_quality' => TelemetryEvent::QUALITY_GOOD,
                    'source' => 'live_sync',
                ]);
                $transitioned++;
            } elseif ($ongoing->event_type === $currentState) {
                // Same state: update duration and coordinates
                $duration = max(0, (int) $now->diffInSeconds($ongoing->start_time));
                $ongoing->updateQuietly([
                    'duration_seconds' => $duration,
                    'latitude' => $lat ?? $ongoing->latitude,
                    'longitude' => $lng ?? $ongoing->longitude,
                ]);
                $updated++;
            } else {
                // State changed: close old ongoing event and open new one
                $duration = max(0, (int) $now->diffInSeconds($ongoing->start_time));
                $ongoing->update([
                    'end_time' => $now,
                    'duration_seconds' => $duration,
                    'is_ongoing' => false,
                ]);

                TelemetryEvent::create([
                    'vehicle_id' => $vehicle?->id,
                    'device_id' => $device?->id,
                    'imei' => $imei,
                    'event_type' => $currentState,
                    'start_time' => $now,
                    'duration_seconds' => 0,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'is_ongoing' => true,
                    'data_quality' => TelemetryEvent::QUALITY_GOOD,
                    'source' => 'live_sync',
                ]);
                $transitioned++;
            }

            $processed++;
        }

        return [
            'processed' => $processed,
            'transitioned' => $transitioned,
            'updated' => $updated,
        ];
    }

    /**
     * Reconstruct confirmed idling, parking, and movement intervals from historical track points.
     *
     * @param  list<array<string, mixed>>  $trackPoints
     * @return array{created_count: int, events: list<TelemetryEvent>}
     */
    public function reconstructFromHistoricalTrack(
        string $imei,
        array $trackPoints,
        ?int $vehicleId = null,
        ?int $deviceId = null
    ): array {
        if (empty($trackPoints)) {
            return ['created_count' => 0, 'events' => []];
        }

        // Sort points chronologically
        usort($trackPoints, fn ($a, $b) => strcmp((string) $a['gps_time'], (string) $b['gps_time']));

        $events = [];
        $createdCount = 0;

        $currentCluster = [];
        $currentClusterType = null;

        foreach ($trackPoints as $pt) {
            $speed = (float) ($pt['speed'] ?? 0.0);
            $rawAcc = strtoupper((string) ($pt['acc_status'] ?? ''));
            $isIgnitionOn = in_array($rawAcc, ['ON', '1', 'TRUE'], true);

            $type = $speed >= self::MOVEMENT_THRESHOLD_KMH
                ? TelemetryEvent::TYPE_MOVING
                : ($isIgnitionOn ? TelemetryEvent::TYPE_IDLING : TelemetryEvent::TYPE_PARKING);

            if ($currentClusterType === null) {
                $currentClusterType = $type;
                $currentCluster[] = $pt;
            } elseif ($currentClusterType === $type) {
                $currentCluster[] = $pt;
            } else {
                // Segment closed: process previous cluster
                $event = $this->saveReconstructedSegment($imei, $currentClusterType, $currentCluster, $vehicleId, $deviceId);
                if ($event) {
                    $events[] = $event;
                    $createdCount++;
                }

                // Start new cluster
                $currentClusterType = $type;
                $currentCluster = [$pt];
            }
        }

        // Process final cluster
        if (! empty($currentCluster) && $currentClusterType !== null) {
            $event = $this->saveReconstructedSegment($imei, $currentClusterType, $currentCluster, $vehicleId, $deviceId);
            if ($event) {
                $events[] = $event;
                $createdCount++;
            }
        }

        return [
            'created_count' => $createdCount,
            'events' => $events,
        ];
    }

    /**
     * Save a single reconstructed segment into the database, preventing duplicates.
     *
     * @param  list<array<string, mixed>>  $cluster
     */
    protected function saveReconstructedSegment(
        string $imei,
        string $type,
        array $cluster,
        ?int $vehicleId = null,
        ?int $deviceId = null
    ): ?TelemetryEvent {
        if (empty($cluster)) {
            return null;
        }

        $first = $cluster[0];
        $last = end($cluster);

        $startTimeStr = (string) $first['gps_time'];
        $endTimeStr = (string) $last['gps_time'];

        try {
            $start = Carbon::parse($startTimeStr);
            $end = Carbon::parse($endTimeStr);
        } catch (Exception) {
            return null;
        }

        $duration = max(0, (int) $start->diffInSeconds($end));

        // For stationary idling or parking, enforce confirmation duration threshold
        if (in_array($type, [TelemetryEvent::TYPE_IDLING, TelemetryEvent::TYPE_PARKING], true) && $duration < self::CONFIRMATION_MIN_SECONDS) {
            return null;
        }

        // Deduplication check: do not insert if an event with same IMEI and start_time exists
        $existing = TelemetryEvent::query()
            ->where('imei', $imei)
            ->where('start_time', $start->format('Y-m-d H:i:s'))
            ->first();

        if ($existing) {
            return $existing;
        }

        return TelemetryEvent::create([
            'vehicle_id' => $vehicleId,
            'device_id' => $deviceId,
            'imei' => $imei,
            'event_type' => $type,
            'start_time' => $start,
            'end_time' => $end,
            'duration_seconds' => $duration,
            'latitude' => $first['lat'] ?? null,
            'longitude' => $first['lng'] ?? null,
            'is_ongoing' => false,
            'data_quality' => TelemetryEvent::QUALITY_GOOD,
            'source' => 'track_reconstruction',
        ]);
    }

    /**
     * Compute summary statistics for a date range and optional vehicle filter.
     *
     * @return array{
     *     ongoing_idling: int,
     *     ongoing_parking: int,
     *     ongoing_moving: int,
     *     total_idling_events: int,
     *     total_parking_events: int,
     *     total_idling_seconds: int,
     *     total_parking_seconds: int,
     *     total_idling_hours: float,
     *     total_parking_hours: float
     * }
     */
    public function getStatistics(?string $from = null, ?string $to = null, ?int $vehicleId = null): array
    {
        $baseQuery = fn () => TelemetryEvent::query()
            ->when($vehicleId, fn ($q) => $q->where('vehicle_id', $vehicleId))
            ->when($from, fn ($q) => $q->where('start_time', '>=', $from))
            ->when($to, fn ($q) => $q->where('start_time', '<=', $to));

        $ongoingIdling = TelemetryEvent::query()
            ->where('is_ongoing', true)
            ->where('event_type', TelemetryEvent::TYPE_IDLING)
            ->when($vehicleId, fn ($q) => $q->where('vehicle_id', $vehicleId))
            ->count();

        $ongoingParking = TelemetryEvent::query()
            ->where('is_ongoing', true)
            ->where('event_type', TelemetryEvent::TYPE_PARKING)
            ->when($vehicleId, fn ($q) => $q->where('vehicle_id', $vehicleId))
            ->count();

        $ongoingMoving = TelemetryEvent::query()
            ->where('is_ongoing', true)
            ->where('event_type', TelemetryEvent::TYPE_MOVING)
            ->when($vehicleId, fn ($q) => $q->where('vehicle_id', $vehicleId))
            ->count();

        $totalIdlingEvents = (clone $baseQuery())
            ->where('event_type', TelemetryEvent::TYPE_IDLING)
            ->count();

        $totalParkingEvents = (clone $baseQuery())
            ->where('event_type', TelemetryEvent::TYPE_PARKING)
            ->count();

        $totalIdlingSeconds = (int) (clone $baseQuery())
            ->where('event_type', TelemetryEvent::TYPE_IDLING)
            ->sum('duration_seconds');

        $totalParkingSeconds = (int) (clone $baseQuery())
            ->where('event_type', TelemetryEvent::TYPE_PARKING)
            ->sum('duration_seconds');

        return [
            'ongoing_idling' => $ongoingIdling,
            'ongoing_parking' => $ongoingParking,
            'ongoing_moving' => $ongoingMoving,
            'total_idling_events' => $totalIdlingEvents,
            'total_parking_events' => $totalParkingEvents,
            'total_idling_seconds' => $totalIdlingSeconds,
            'total_parking_seconds' => $totalParkingSeconds,
            'total_idling_hours' => round($totalIdlingSeconds / 3600.0, 1),
            'total_parking_hours' => round($totalParkingSeconds / 3600.0, 1),
        ];
    }
}
