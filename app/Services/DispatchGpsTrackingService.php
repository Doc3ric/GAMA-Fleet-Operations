<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DispatchMonitorEvent;
use App\Models\FleetDispatch;
use App\Models\Location;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\DispatchAlertNotification;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class DispatchGpsTrackingService
{
    protected float $deviationCorridorMeters;

    protected int $deviationConsecutivePings;

    protected int $deviationDurationMinutes;

    protected int $unexpectedStopMinutes;

    protected float $waypointExclusionRadiusMeters;

    protected float $originGeofenceRadiusMeters;

    protected float $movementSpeedThresholdKmh;

    protected int $missedDepartureGraceMinutes;

    protected int $missedDepartureConfirmedMinutes;

    protected int $delayedArrivalThresholdMinutes;

    protected int $gpsUnavailableMinutes;

    protected int $duplicateCooldownMinutes;

    protected float $assumedTransitSpeedKmh;

    public function __construct(
        public TrackSolidApiService $trackSolidService,
        protected DispatchMonitoringService $dispatchService
    ) {
        $cfg = config('fleet_reconciliation.monitoring', []);

        $this->deviationCorridorMeters = (float) ($cfg['deviation_corridor_meters'] ?? 750.0);
        $this->deviationConsecutivePings = (int) ($cfg['deviation_consecutive_pings'] ?? 2);
        $this->deviationDurationMinutes = (int) ($cfg['deviation_duration_minutes'] ?? 10);
        $this->unexpectedStopMinutes = (int) ($cfg['unexpected_stop_minutes'] ?? 20);
        $this->waypointExclusionRadiusMeters = (float) ($cfg['waypoint_exclusion_radius_meters'] ?? 500.0);
        $this->originGeofenceRadiusMeters = (float) ($cfg['origin_geofence_radius_meters'] ?? 600.0);
        $this->movementSpeedThresholdKmh = (float) ($cfg['movement_speed_threshold_kmh'] ?? 10.0);
        $this->missedDepartureGraceMinutes = (int) ($cfg['missed_departure_grace_minutes'] ?? 15);
        $this->missedDepartureConfirmedMinutes = (int) ($cfg['missed_departure_confirmed_minutes'] ?? 30);
        $this->delayedArrivalThresholdMinutes = (int) ($cfg['delayed_arrival_threshold_minutes'] ?? 30);
        $this->gpsUnavailableMinutes = (int) ($cfg['gps_unavailable_minutes'] ?? 60);
        $this->duplicateCooldownMinutes = (int) ($cfg['duplicate_notification_cooldown_minutes'] ?? 30);
        $this->assumedTransitSpeedKmh = (float) ($cfg['assumed_transit_speed_kmh'] ?? 40.0);
    }

    /**
     * Run automated monitoring check for all active fleet dispatches.
     *
     * @return array{
     *     scanned: int,
     *     normal: int,
     *     possible_exceptions: int,
     *     confirmed_exceptions: int,
     *     gps_unavailable: int,
     *     events_created: int
     * }
     */
    public function monitorActiveDispatches(?Carbon $now = null): array
    {
        $now = $now ?? Carbon::now();

        // Active dispatches: scheduled for today or en-route (actual departure exists and no completed reconciliation)
        $dispatches = FleetDispatch::with(['vehicle.device', 'reconciliation', 'activeMonitorEvents'])
            ->where('dispatch_status', '!=', FleetDispatch::STATUS_CANCELLED)
            ->where(function ($q) use ($now) {
                $q->whereDate('trip_date', $now->toDateString())
                    ->orWhere(function ($sub) {
                        $sub->whereNotNull('actual_departure_time')
                            ->whereDoesntHave('reconciliation', function ($r) {
                                $r->where('status', 'COMPLETED');
                            });
                    });
            })
            ->get();

        $stats = [
            'scanned' => $dispatches->count(),
            'normal' => 0,
            'possible_exceptions' => 0,
            'confirmed_exceptions' => 0,
            'gps_unavailable' => 0,
            'events_created' => 0,
        ];

        // Gather device IMEIs for batch telemetry query
        $imeiToDispatch = [];
        foreach ($dispatches as $dispatch) {
            $vehicle = $dispatch->vehicle;
            $imei = $vehicle?->device?->imei;
            if ($imei) {
                $imeiToDispatch[trim((string) $imei)][] = $dispatch;
            }
        }

        $telemetryBatch = [];
        if (! empty($imeiToDispatch)) {
            $telemetryBatch = $this->trackSolidService->getDeviceLocationsWithStatus(array_keys($imeiToDispatch));
        }

        foreach ($dispatches as $dispatch) {
            $eventsCreated = $this->evaluateDispatch($dispatch, $telemetryBatch, $now);
            $stats['events_created'] += count($eventsCreated);

            match ($dispatch->monitoring_status) {
                FleetDispatch::MONITORING_CONFIRMED_EXCEPTION => $stats['confirmed_exceptions']++,
                FleetDispatch::MONITORING_POSSIBLE_EXCEPTION => $stats['possible_exceptions']++,
                FleetDispatch::MONITORING_GPS_UNAVAILABLE => $stats['gps_unavailable']++,
                default => $stats['normal']++,
            };
        }

        return $stats;
    }

    /**
     * Evaluate single dispatch against live and historical telemetry.
     *
     * @param  array<string, mixed>  $telemetryBatch
     * @return list<DispatchMonitorEvent>
     */
    public function evaluateDispatch(FleetDispatch $dispatch, array $telemetryBatch = [], ?Carbon $now = null): array
    {
        $now = $now ?? Carbon::now();
        $vehicle = $dispatch->vehicle;
        $createdEvents = [];

        // 1. Check GPS Device availability
        if (! $vehicle || ! $vehicle->hasGps() || ! $vehicle->device || empty($vehicle->device->imei)) {
            $this->updateDispatchStatus($dispatch, FleetDispatch::MONITORING_GPS_UNAVAILABLE, $now);
            $ev = $this->recordEvent(
                $dispatch,
                DispatchMonitorEvent::EVENT_GPS_UNAVAILABLE,
                DispatchMonitorEvent::SEVERITY_WARNING,
                DispatchMonitorEvent::STATUS_CONFIRMED,
                "Vehicle {$vehicle?->equipment_code} has no GPS device linked or GPS capability is inactive.",
                $now,
                null,
                null,
                ['reason' => 'no_device_assigned']
            );
            if ($ev) {
                $createdEvents[] = $ev;
            }

            return $createdEvents;
        }

        $imei = trim((string) $vehicle->device->imei);
        $telemetryLocations = $telemetryBatch['locations'] ?? [];
        $failedImeis = $telemetryBatch['failed_imeis'] ?? [];

        // Distinguish API failure from device failure
        if (in_array($imei, $failedImeis, true) && ($telemetryBatch['status'] ?? '') === 'failure') {
            Log::info("TrackSolid API failure when querying telemetry for IMEI {$imei}. Leaving monitoring status unchanged.");

            return $createdEvents;
        }

        $liveLoc = $telemetryLocations[$imei] ?? null;

        // If not in batch, try direct fetch for this device
        if (! $liveLoc && empty($telemetryLocations)) {
            $singleBatch = $this->trackSolidService->getDeviceLocationsWithStatus([$imei]);
            $liveLoc = $singleBatch['locations'][$imei] ?? null;
        }

        // Validate telemetry data
        $point = $this->extractValidPoint($liveLoc, $now);

        if (! $point) {
            $this->updateDispatchStatus($dispatch, FleetDispatch::MONITORING_GPS_UNAVAILABLE, $now);
            $ev = $this->recordEvent(
                $dispatch,
                DispatchMonitorEvent::EVENT_GPS_UNAVAILABLE,
                DispatchMonitorEvent::SEVERITY_WARNING,
                DispatchMonitorEvent::STATUS_CONFIRMED,
                "No valid telemetry received for {$vehicle->equipment_code} within the last {$this->gpsUnavailableMinutes} minutes.",
                $now,
                null,
                null,
                ['reason' => 'telemetry_stale_or_missing', 'imei' => $imei]
            );
            if ($ev) {
                $createdEvents[] = $ev;
            }

            return $createdEvents;
        }

        $lat = $point['lat'];
        $lng = $point['lng'];
        $speed = $point['speed'];
        $gpsTime = $point['gps_time'];

        // 2. Check Departure Detection & Missed Departure
        $departureEvents = $this->checkDeparture($dispatch, $lat, $lng, $speed, $gpsTime, $now);
        $createdEvents = array_merge($createdEvents, $departureEvents);

        // 3. If trip has departed, check Route Deviation, Unexpected Stops, and Delayed Arrival
        if ($dispatch->actual_departure_time !== null) {
            $deviationEvents = $this->checkRouteDeviation($dispatch, $point, $now);
            $createdEvents = array_merge($createdEvents, $deviationEvents);

            $stopEvents = $this->checkUnexpectedStop($dispatch, $point, $now);
            $createdEvents = array_merge($createdEvents, $stopEvents);

            $arrivalEvents = $this->checkDelayedArrival($dispatch, $point, $now);
            $createdEvents = array_merge($createdEvents, $arrivalEvents);
        }

        // 4. Update overall dispatch monitoring status
        $this->resolveOverallStatus($dispatch, $now);

        return $createdEvents;
    }

    /**
     * Check departure status and auto-detect movement away from origin.
     *
     * @return list<DispatchMonitorEvent>
     */
    protected function checkDeparture(
        FleetDispatch $dispatch,
        float $lat,
        float $lng,
        float $speed,
        Carbon $gpsTime,
        Carbon $now
    ): array {
        $events = [];

        // Already departed
        if ($dispatch->actual_departure_time !== null) {
            return $events;
        }

        $originLat = $dispatch->origin_latitude;
        $originLng = $dispatch->origin_longitude;

        // If no coordinates stored on dispatch, try origin starting point in locations
        if ($originLat === null || $originLng === null) {
            $coords = $this->resolveLocationCoordinates($dispatch->starting_point);
            if ($coords) {
                $originLat = $coords['lat'];
                $originLng = $coords['lng'];
                $dispatch->update(['origin_latitude' => $originLat, 'origin_longitude' => $originLng]);
            }
        }

        $distFromOriginMeters = 0.0;
        if ($originLat !== null && $originLng !== null) {
            $distFromOriginMeters = RoutingService::calculateHaversineDistanceKm($lat, $lng, $originLat, $originLng) * 1000.0;
        }

        // AUTO-DEPARTURE DETECTION: If vehicle has exited origin yard and is moving at valid transit speed
        if ($distFromOriginMeters > $this->originGeofenceRadiusMeters && $speed >= $this->movementSpeedThresholdKmh) {
            $dispatch->actual_departure_time = $gpsTime;
            $dispatch->departure_source = FleetDispatch::SOURCE_GPS_MOVEMENT;
            $dispatch->recalculateStatus($now);
            $dispatch->save();

            Log::info("Dispatch {$dispatch->trip_number}: Auto-detected departure via TrackSolid GPS movement at {$gpsTime->format('Y-m-d H:i')}.");

            return $events;
        }

        // MISSED DEPARTURE CHECK: Scheduled time + grace period has passed, but vehicle has not moved
        $plannedTime = $dispatch->planned_departure_time;
        if ($plannedTime && $now->greaterThan($plannedTime)) {
            $delayMinutes = (int) $plannedTime->diffInMinutes($now, false);
            $grace = $dispatch->grace_period_minutes ?? $this->missedDepartureGraceMinutes;

            if ($delayMinutes > $grace) {
                $isConfirmed = ($delayMinutes >= $this->missedDepartureConfirmedMinutes);
                $status = $isConfirmed ? DispatchMonitorEvent::STATUS_CONFIRMED : DispatchMonitorEvent::STATUS_POSSIBLE;
                $severity = $isConfirmed ? DispatchMonitorEvent::SEVERITY_DANGER : DispatchMonitorEvent::SEVERITY_WARNING;

                $label = $isConfirmed ? 'Confirmed Missed Departure' : 'Possible Missed Departure';
                $desc = "Trip {$dispatch->trip_number} scheduled for {$plannedTime->format('h:i A')} is delayed by {$delayMinutes} minutes without movement from yard.";

                $ev = $this->recordEvent(
                    $dispatch,
                    DispatchMonitorEvent::EVENT_MISSED_DEPARTURE,
                    $severity,
                    $status,
                    $desc,
                    $now,
                    $lat,
                    $lng,
                    ['delay_minutes' => $delayMinutes, 'grace_minutes' => $grace]
                );

                if ($ev) {
                    $events[] = $ev;
                }
            }
        }

        return $events;
    }

    /**
     * Check whether vehicle has deviated outside the planned route corridor.
     *
     * @param  array{lat: float, lng: float, speed: float, gps_time: Carbon}  $point
     * @return list<DispatchMonitorEvent>
     */
    protected function checkRouteDeviation(FleetDispatch $dispatch, array $point, Carbon $now): array
    {
        $events = [];
        $lat = $point['lat'];
        $lng = $point['lng'];

        $corridor = $this->deviationCorridorMeters;
        $waypoints = $this->extractRouteWaypoints($dispatch);

        if (count($waypoints) < 2) {
            return $events;
        }

        $crossTrackMeters = $this->calculateCrossTrackDistanceMeters($lat, $lng, $waypoints);

        if ($crossTrackMeters > $corridor) {
            // Check previous events to see if this is consecutive or sustained
            $existingActive = $dispatch->activeMonitorEvents()
                ->where('event_type', DispatchMonitorEvent::EVENT_ROUTE_DEVIATION)
                ->latest()
                ->first();

            $pingsOutside = 1;
            $durationMinutes = 0;

            if ($existingActive) {
                $pingsOutside = (int) ($existingActive->metadata['pings_count'] ?? 1) + 1;
                $durationMinutes = (int) $existingActive->created_at->diffInMinutes($now);
            }

            $isConfirmed = ($pingsOutside >= $this->deviationConsecutivePings || $durationMinutes >= $this->deviationDurationMinutes);
            $status = $isConfirmed ? DispatchMonitorEvent::STATUS_CONFIRMED : DispatchMonitorEvent::STATUS_POSSIBLE;
            $severity = $isConfirmed ? DispatchMonitorEvent::SEVERITY_DANGER : DispatchMonitorEvent::SEVERITY_WARNING;

            $desc = sprintf(
                'Vehicle %s deviated %.0f meters outside assigned route corridor (Limit: %.0fm). Status: %s (%d pings).',
                $dispatch->vehicle?->equipment_code ?? 'Vehicle',
                $crossTrackMeters,
                $corridor,
                $status,
                $pingsOutside
            );

            $ev = $this->recordEvent(
                $dispatch,
                DispatchMonitorEvent::EVENT_ROUTE_DEVIATION,
                $severity,
                $status,
                $desc,
                $now,
                $lat,
                $lng,
                [
                    'cross_track_meters' => round($crossTrackMeters, 1),
                    'pings_count' => $pingsOutside,
                    'duration_minutes' => $durationMinutes,
                    'corridor_limit_meters' => $corridor,
                ]
            );

            if ($ev) {
                $events[] = $ev;
            }
        } else {
            // Vehicle returned inside corridor -> resolve active route deviation events
            $openDeviations = $dispatch->activeMonitorEvents()
                ->where('event_type', DispatchMonitorEvent::EVENT_ROUTE_DEVIATION)
                ->get();

            foreach ($openDeviations as $dev) {
                $dev->resolve(1, 'Vehicle returned to assigned route corridor.');
            }
        }

        return $events;
    }

    /**
     * Check for unexpected stops outside of approved waypoints.
     * Speed = 0 alone does not trigger alert; requires duration > 20 min and distance > 500m from all stops.
     *
     * @param  array{lat: float, lng: float, speed: float, gps_time: Carbon, is_ignition_on?: bool}  $point
     * @return list<DispatchMonitorEvent>
     */
    protected function checkUnexpectedStop(FleetDispatch $dispatch, array $point, Carbon $now): array
    {
        $events = [];
        $lat = $point['lat'];
        $lng = $point['lng'];
        $speed = $point['speed'];

        // Only stationary vehicles are candidates for unexpected stop
        if ($speed > 3.0) {
            // Moving -> resolve any active unexpected stop
            $openStops = $dispatch->activeMonitorEvents()
                ->where('event_type', DispatchMonitorEvent::EVENT_UNEXPECTED_STOP)
                ->get();
            foreach ($openStops as $s) {
                $s->resolve(1, 'Vehicle resumed transit.');
            }

            return $events;
        }

        // Check distance to all valid waypoints & destination
        $waypoints = $this->extractRouteWaypoints($dispatch);
        $closestWaypointMeters = INF;

        foreach ($waypoints as $wp) {
            $d = RoutingService::calculateHaversineDistanceKm($lat, $lng, $wp['lat'], $wp['lng']) * 1000.0;
            if ($d < $closestWaypointMeters) {
                $closestWaypointMeters = $d;
            }
        }

        // If stopped within 500m of an authorized waypoint/destination, it is an authorized stop
        if ($closestWaypointMeters <= $this->waypointExclusionRadiusMeters) {
            return $events;
        }

        // Vehicle is stopped outside designated areas: evaluate stop duration
        $existingStop = $dispatch->activeMonitorEvents()
            ->where('event_type', DispatchMonitorEvent::EVENT_UNEXPECTED_STOP)
            ->latest()
            ->first();

        $stopMinutes = 0;
        if ($existingStop) {
            $stopMinutes = (int) $existingStop->created_at->diffInMinutes($now);
        } else {
            // First detection of stop outside waypoints: verify via historical track or start tracking
            $stopMinutes = 5;
        }

        if ($stopMinutes >= $this->unexpectedStopMinutes) {
            $isConfirmed = ($stopMinutes >= 35);
            $status = $isConfirmed ? DispatchMonitorEvent::STATUS_CONFIRMED : DispatchMonitorEvent::STATUS_POSSIBLE;
            $severity = $isConfirmed ? DispatchMonitorEvent::SEVERITY_DANGER : DispatchMonitorEvent::SEVERITY_WARNING;

            $desc = sprintf(
                'Vehicle %s stopped for %d minutes at unauthorized location (%.1f km from nearest waypoint).',
                $dispatch->vehicle?->equipment_code ?? 'Vehicle',
                $stopMinutes,
                $closestWaypointMeters / 1000.0
            );

            $ev = $this->recordEvent(
                $dispatch,
                DispatchMonitorEvent::EVENT_UNEXPECTED_STOP,
                $severity,
                $status,
                $desc,
                $now,
                $lat,
                $lng,
                [
                    'stop_duration_minutes' => $stopMinutes,
                    'distance_to_nearest_waypoint_meters' => round($closestWaypointMeters, 1),
                ]
            );

            if ($ev) {
                $events[] = $ev;
            }
        }

        return $events;
    }

    /**
     * Check if vehicle is delayed arriving at destination.
     *
     * @param  array{lat: float, lng: float, speed: float, gps_time: Carbon}  $point
     * @return list<DispatchMonitorEvent>
     */
    protected function checkDelayedArrival(FleetDispatch $dispatch, array $point, Carbon $now): array
    {
        $events = [];
        $destLat = $dispatch->destination_latitude;
        $destLng = $dispatch->destination_longitude;

        if ($destLat === null || $destLng === null) {
            $coords = $this->resolveLocationCoordinates($dispatch->destination);
            if ($coords) {
                $destLat = $coords['lat'];
                $destLng = $coords['lng'];
                $dispatch->update(['destination_latitude' => $destLat, 'destination_longitude' => $destLng]);
            }
        }

        if ($destLat === null || $destLng === null) {
            return $events;
        }

        // Distance remaining to final destination
        $remainingDistKm = RoutingService::calculateHaversineDistanceKm($point['lat'], $point['lng'], $destLat, $destLng);

        // If vehicle has arrived at destination (within 500m), resolve any delay alerts
        if ($remainingDistKm * 1000.0 <= $this->waypointExclusionRadiusMeters) {
            $openDelays = $dispatch->activeMonitorEvents()
                ->where('event_type', DispatchMonitorEvent::EVENT_DELAYED_ARRIVAL)
                ->get();
            foreach ($openDelays as $d) {
                $d->resolve(1, 'Vehicle arrived at destination.');
            }

            return $events;
        }

        // Estimate planned completion time: planned departure + estimated duration
        $plannedDistance = max(1.0, (float) $dispatch->planned_distance_km);
        $expectedTransitMinutes = (int) round(($plannedDistance / $this->assumedTransitSpeedKmh) * 60);

        $departureTime = $dispatch->actual_departure_time ?? $dispatch->planned_departure_time;
        if (! $departureTime) {
            return $events;
        }

        $expectedArrival = $departureTime->copy()->addMinutes($expectedTransitMinutes);

        if ($now->greaterThan($expectedArrival)) {
            $delayMinutes = (int) $expectedArrival->diffInMinutes($now, false);

            if ($delayMinutes >= $this->delayedArrivalThresholdMinutes) {
                $isConfirmed = ($delayMinutes >= $this->delayedArrivalThresholdMinutes * 2);
                $status = $isConfirmed ? DispatchMonitorEvent::STATUS_CONFIRMED : DispatchMonitorEvent::STATUS_POSSIBLE;
                $severity = $isConfirmed ? DispatchMonitorEvent::SEVERITY_DANGER : DispatchMonitorEvent::SEVERITY_WARNING;

                $desc = sprintf(
                    'Trip %s delayed by %d minutes beyond scheduled arrival window (%.1f km remaining to %s).',
                    $dispatch->trip_number,
                    $delayMinutes,
                    $remainingDistKm,
                    $dispatch->destination
                );

                $ev = $this->recordEvent(
                    $dispatch,
                    DispatchMonitorEvent::EVENT_DELAYED_ARRIVAL,
                    $severity,
                    $status,
                    $desc,
                    $now,
                    $point['lat'],
                    $point['lng'],
                    [
                        'delay_minutes' => $delayMinutes,
                        'remaining_km' => round($remainingDistKm, 2),
                        'expected_arrival' => $expectedArrival->toIso8601String(),
                    ]
                );

                if ($ev) {
                    $events[] = $ev;
                }
            }
        }

        return $events;
    }

    /**
     * Calculate perpendicular cross-track distance in meters from point to polyline.
     *
     * @param  list<array{lat: float, lng: float}>  $waypoints
     */
    public function calculateCrossTrackDistanceMeters(float $pLat, float $pLng, array $waypoints): float
    {
        if (count($waypoints) < 2) {
            return 0.0;
        }

        $minDistMeters = INF;

        for ($i = 0; $i < count($waypoints) - 1; $i++) {
            $a = $waypoints[$i];
            $b = $waypoints[$i + 1];

            $dist = $this->pointToSegmentDistanceMeters($pLat, $pLng, $a['lat'], $a['lng'], $b['lat'], $b['lng']);
            if ($dist < $minDistMeters) {
                $minDistMeters = $dist;
            }
        }

        return is_infinite($minDistMeters) ? 0.0 : $minDistMeters;
    }

    /**
     * Compute minimum distance in meters between a point and a line segment using projected equirectangular coordinates.
     */
    protected function pointToSegmentDistanceMeters(
        float $pLat,
        float $pLng,
        float $aLat,
        float $aLng,
        float $bLat,
        float $bLng
    ): float {
        $earthRadius = 6371000.0; // meters

        $meanLatRad = deg2rad(($aLat + $bLat + $pLat) / 3.0);
        $cosMean = cos($meanLatRad);

        // Convert coordinates to local Cartesian coordinates (meters)
        $px = deg2rad($pLng) * $earthRadius * $cosMean;
        $py = deg2rad($pLat) * $earthRadius;

        $ax = deg2rad($aLng) * $earthRadius * $cosMean;
        $ay = deg2rad($aLat) * $earthRadius;

        $bx = deg2rad($bLng) * $earthRadius * $cosMean;
        $by = deg2rad($bLat) * $earthRadius;

        $dx = $bx - $ax;
        $dy = $by - $ay;

        $segLenSq = ($dx * $dx) + ($dy * $dy);

        if ($segLenSq < 1.0) {
            // Degenerate segment: distance to point A
            return sqrt(($px - $ax) * ($px - $ax) + ($py - $ay) * ($py - $ay));
        }

        // Project point P onto segment AB
        $t = (($px - $ax) * $dx + ($py - $ay) * $dy) / $segLenSq;
        $t = max(0.0, min(1.0, $t));

        $projX = $ax + $t * $dx;
        $projY = $ay + $t * $dy;

        return sqrt(($px - $projX) * ($px - $projX) + ($py - $projY) * ($py - $projY));
    }

    /**
     * Extract ordered waypoints list from dispatch record.
     *
     * @return list<array{name: string, lat: float, lng: float}>
     */
    public function extractRouteWaypoints(FleetDispatch $dispatch): array
    {
        $waypoints = [];
        $data = $dispatch->planned_route_data;

        // Check if stored planned_route has structured waypoints or geometry
        if (! empty($data['waypoints']) && is_array($data['waypoints'])) {
            foreach ($data['waypoints'] as $wp) {
                if (isset($wp['lat'], $wp['lng']) && is_numeric($wp['lat']) && is_numeric($wp['lng'])) {
                    $waypoints[] = [
                        'name' => (string) ($wp['name'] ?? $wp['address'] ?? 'Waypoint'),
                        'lat' => (float) $wp['lat'],
                        'lng' => (float) $wp['lng'],
                    ];
                }
            }
        }

        // If empty, build from origin and destination fields
        if (empty($waypoints)) {
            if ($dispatch->origin_latitude !== null && $dispatch->origin_longitude !== null) {
                $waypoints[] = [
                    'name' => $dispatch->starting_point,
                    'lat' => (float) $dispatch->origin_latitude,
                    'lng' => (float) $dispatch->origin_longitude,
                ];
            }

            if ($dispatch->destination_latitude !== null && $dispatch->destination_longitude !== null) {
                $waypoints[] = [
                    'name' => $dispatch->destination,
                    'lat' => (float) $dispatch->destination_latitude,
                    'lng' => (float) $dispatch->destination_longitude,
                ];
            }
        }

        // Also check if linked to AdvancedItinerary with legs
        if (empty($waypoints) && $dispatch->itinerary) {
            $it = $dispatch->itinerary->loadMissing('legs.originLocation', 'legs.destinationLocation');
            foreach ($it->legs as $leg) {
                if ($leg->originLocation && $leg->originLocation->latitude && $leg->originLocation->longitude) {
                    $waypoints[] = [
                        'name' => $leg->originLocation->official_name,
                        'lat' => (float) $leg->originLocation->latitude,
                        'lng' => (float) $leg->originLocation->longitude,
                    ];
                }
                if ($leg->destinationLocation && $leg->destinationLocation->latitude && $leg->destinationLocation->longitude) {
                    $waypoints[] = [
                        'name' => $leg->destinationLocation->official_name,
                        'lat' => (float) $leg->destinationLocation->latitude,
                        'lng' => (float) $leg->destinationLocation->longitude,
                    ];
                }
            }
        }

        return $waypoints;
    }

    /**
     * Record monitoring event with duplicate suppression and emit in-app notification.
     *
     * @param  array<string, mixed>  $metadata
     */
    protected function recordEvent(
        FleetDispatch $dispatch,
        string $eventType,
        string $severity,
        string $status,
        string $description,
        Carbon $now,
        ?float $lat = null,
        ?float $lng = null,
        array $metadata = []
    ): ?DispatchMonitorEvent {
        // DUPLICATE NOTIFICATION SUPPRESSION:
        // Check if an existing open event of this type exists
        $recent = DispatchMonitorEvent::where('dispatch_id', $dispatch->id)
            ->where('event_type', $eventType)
            ->whereIn('status', [DispatchMonitorEvent::STATUS_POSSIBLE, DispatchMonitorEvent::STATUS_CONFIRMED])
            ->latest()
            ->first();

        if ($recent) {
            $minutesSinceLast = (int) $recent->created_at->diffInMinutes($now);

            // If status escalated from POSSIBLE to CONFIRMED, update and notify escalation
            if ($recent->status === DispatchMonitorEvent::STATUS_POSSIBLE && $status === DispatchMonitorEvent::STATUS_CONFIRMED) {
                $recent->update([
                    'status' => DispatchMonitorEvent::STATUS_CONFIRMED,
                    'severity' => $severity,
                    'description' => $description,
                    'latitude' => $lat ?? $recent->latitude,
                    'longitude' => $lng ?? $recent->longitude,
                    'metadata' => array_merge((array) $recent->metadata, $metadata),
                ]);

                $this->sendInAppNotification($recent, "CONFIRMED EXCEPTION: {$eventType}");

                return $recent;
            }

            // Suppress duplicate within cooldown period
            if ($minutesSinceLast < $this->duplicateCooldownMinutes) {
                $recent->update([
                    'metadata' => array_merge((array) $recent->metadata, $metadata),
                ]);

                return null;
            }
        }

        // Create new monitoring event
        $event = DispatchMonitorEvent::create([
            'dispatch_id' => $dispatch->id,
            'vehicle_id' => $dispatch->vehicle_id,
            'event_type' => $eventType,
            'severity' => $severity,
            'status' => $status,
            'latitude' => $lat,
            'longitude' => $lng,
            'speed_kmh' => $metadata['speed'] ?? null,
            'deviation_meters' => $metadata['cross_track_meters'] ?? null,
            'duration_minutes' => $metadata['duration_minutes'] ?? ($metadata['stop_duration_minutes'] ?? null),
            'description' => $description,
            'metadata' => $metadata,
        ]);

        $title = match ($status) {
            DispatchMonitorEvent::STATUS_CONFIRMED => "CONFIRMED ALERT: {$eventType}",
            default => "POSSIBLE EXCEPTION: {$eventType}",
        };

        $this->sendInAppNotification($event, $title);

        return $event;
    }

    /**
     * Dispatch in-app notification exclusively via Laravel database notifications (zero SMS/email).
     */
    protected function sendInAppNotification(DispatchMonitorEvent $event, string $title): void
    {
        try {
            $operatorsAndAdmins = User::whereIn('role', [
                User::ROLE_ADMIN,
                User::ROLE_OPERATOR,
            ])->get();

            if ($operatorsAndAdmins->isNotEmpty()) {
                Notification::send($operatorsAndAdmins, new DispatchAlertNotification($event, $title));
            }
        } catch (Exception $e) {
            Log::warning("In-app notification delivery error: {$e->getMessage()}");
        }
    }

    /**
     * Update dispatch overall monitoring status and update timestamp.
     */
    protected function updateDispatchStatus(FleetDispatch $dispatch, string $monitoringStatus, Carbon $now): void
    {
        $dispatch->monitoring_status = $monitoringStatus;
        $dispatch->last_monitored_at = $now;
        $dispatch->save();
    }

    /**
     * Compute and persist the overall monitoring status based on active events.
     */
    protected function resolveOverallStatus(FleetDispatch $dispatch, Carbon $now): void
    {
        $activeEvents = $dispatch->activeMonitorEvents()->get();

        if ($activeEvents->contains('status', DispatchMonitorEvent::STATUS_CONFIRMED)) {
            $status = FleetDispatch::MONITORING_CONFIRMED_EXCEPTION;
        } elseif ($activeEvents->contains('status', DispatchMonitorEvent::STATUS_POSSIBLE)) {
            $status = FleetDispatch::MONITORING_POSSIBLE_EXCEPTION;
        } elseif ($activeEvents->contains('event_type', DispatchMonitorEvent::EVENT_GPS_UNAVAILABLE)) {
            $status = FleetDispatch::MONITORING_GPS_UNAVAILABLE;
        } else {
            $status = FleetDispatch::MONITORING_NORMAL;
        }

        $this->updateDispatchStatus($dispatch, $status, $now);
    }

    /**
     * Validate and extract telemetry point, discarding invalid or excessively stale fixes.
     *
     * @param  array<string, mixed>|null  $liveLoc
     * @return array{lat: float, lng: float, speed: float, gps_time: Carbon}|null
     */
    public function extractValidPoint(?array $liveLoc, Carbon $now): ?array
    {
        if (! is_array($liveLoc)) {
            return null;
        }

        $lat = isset($liveLoc['lat']) && is_numeric($liveLoc['lat']) ? (float) $liveLoc['lat'] : null;
        $lng = isset($liveLoc['lng']) && is_numeric($liveLoc['lng']) ? (float) $liveLoc['lng'] : null;

        // Eliminate Null Island or out-of-range coordinates
        if ($lat === null || $lng === null || (abs($lat) < 0.001 && abs($lng) < 0.001)) {
            return null;
        }
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return null;
        }

        $gpsTimeStr = (string) ($liveLoc['gpsTime'] ?? $liveLoc['gps_time'] ?? '');
        if (empty($gpsTimeStr)) {
            return null;
        }

        try {
            $gpsTime = Carbon::parse($gpsTimeStr);
        } catch (Exception $e) {
            return null;
        }

        // Discard excessive staleness (> gpsUnavailableMinutes)
        if ($gpsTime->diffInMinutes($now, false) > $this->gpsUnavailableMinutes) {
            return null;
        }

        $speed = isset($liveLoc['speed']) && is_numeric($liveLoc['speed'])
            ? max(0.0, (float) $liveLoc['speed'])
            : (isset($liveLoc['gpsSpeed']) && is_numeric($liveLoc['gpsSpeed']) ? max(0.0, (float) $liveLoc['gpsSpeed']) : 0.0);

        return [
            'lat' => $lat,
            'lng' => $lng,
            'speed' => $speed,
            'gps_time' => $gpsTime,
        ];
    }

    /**
     * Resolve latitude and longitude from location directory for starting point or destination string.
     *
     * @return array{lat: float, lng: float}|null
     */
    protected function resolveLocationCoordinates(string $name): ?array
    {
        $name = trim($name);
        if (empty($name)) {
            return null;
        }

        $location = Location::where('official_name', 'like', "%{$name}%")
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->first();

        if ($location) {
            return [
                'lat' => (float) $location->latitude,
                'lng' => (float) $location->longitude,
            ];
        }

        return null;
    }
}
