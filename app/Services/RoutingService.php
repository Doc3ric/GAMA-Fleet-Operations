<?php

namespace App\Services;

use App\Models\Location;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RoutingService
{
    public const SOURCE_OSRM = 'osrm';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_FAILED = 'failed';

    /**
     * Calculate road distance and duration between two points via OSRM.
     * Returns null if the routing service is unavailable or returns no route.
     *
     * @return array{distance_km: float, duration_minutes: int}|null
     */
    public function getRouteSegment(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        $baseUrl = rtrim((string) config('services.osrm.base_url', 'https://router.project-osrm.org'), '/');

        // OSRM expects coordinates as lng,lat (longitude first)
        $coordinates = "{$fromLng},{$fromLat};{$toLng},{$toLat}";
        $url = "{$baseUrl}/route/v1/driving/{$coordinates}";

        try {
            $response = Http::timeout(8)->get($url, [
                'overview' => 'false',
                'steps' => 'false',
            ]);

            if (! $response->successful()) {
                Log::warning('OSRM routing HTTP error', [
                    'status' => $response->status(),
                    'url' => $url,
                ]);

                return null;
            }

            $code = $response->json('code');
            if ($code !== 'Ok') {
                Log::warning('OSRM routing returned non-Ok code', ['code' => $code, 'url' => $url]);

                return null;
            }

            $distanceMeters = $response->json('routes.0.distance');
            if ($distanceMeters === null) {
                Log::warning('OSRM routing: no distance in response', ['url' => $url]);

                return null;
            }

            $distanceKm = round((float) $distanceMeters / 1000, 2);

            $durationSeconds = $response->json('routes.0.duration');
            $durationMinutes = $durationSeconds !== null
                ? (int) round((float) $durationSeconds / 60)
                : 0;

            return [
                'distance_km' => $distanceKm,
                'duration_minutes' => $durationMinutes,
            ];

        } catch (Exception $e) {
            Log::warning('OSRM routing request failed: '.$e->getMessage(), ['url' => $url]);

            return null;
        }
    }

    /**
     * Calculate road distance in kilometres between two points via OSRM.
     * Returns null if the routing service is unavailable or returns no route.
     */
    public function getDistanceKm(float $fromLat, float $fromLng, float $toLat, float $toLng): ?float
    {
        $segment = $this->getRouteSegment($fromLat, $fromLng, $toLat, $toLng);

        return $segment ? $segment['distance_km'] : null;
    }

    /**
     * Calculate both leg distances and durations for an Origin → Starting Point → Destination sequence.
     *
     * Returns an array:
     * [
     *     'origin_to_start' => float|null,                  // km
     *     'start_to_dest'   => float|null,                  // km
     *     'total'           => float|null,                  // sum km, null if either leg failed
     *     'duration_origin_to_start_minutes' => int|null,   // min
     *     'duration_start_to_dest_minutes'   => int|null,   // min
     *     'total_duration_minutes'           => int|null,   // sum min, null if either leg failed
     *     'source'          => string,                      // 'osrm' or 'failed'
     * ]
     *
     * @return array{
     *     origin_to_start: ?float,
     *     start_to_dest: ?float,
     *     total: ?float,
     *     duration_origin_to_start_minutes: ?int,
     *     duration_start_to_dest_minutes: ?int,
     *     total_duration_minutes: ?int,
     *     source: string
     * }
     */
    public function calculateLegDistances(
        Location $origin,
        Location $startingPoint,
        Location $destination
    ): array {
        $s1 = $this->getRouteSegment(
            (float) $origin->latitude,
            (float) $origin->longitude,
            (float) $startingPoint->latitude,
            (float) $startingPoint->longitude
        );

        $s2 = $this->getRouteSegment(
            (float) $startingPoint->latitude,
            (float) $startingPoint->longitude,
            (float) $destination->latitude,
            (float) $destination->longitude
        );

        $d1 = $s1 ? $s1['distance_km'] : null;
        $d2 = $s2 ? $s2['distance_km'] : null;
        $dur1 = $s1 ? $s1['duration_minutes'] : null;
        $dur2 = $s2 ? $s2['duration_minutes'] : null;

        $hasFailed = ($d1 === null || $d2 === null);

        $totalDistance = ! $hasFailed ? round($d1 + $d2, 2) : null;
        $totalDuration = (! $hasFailed && $dur1 !== null && $dur2 !== null) ? ($dur1 + $dur2) : null;
        $source = ! $hasFailed ? self::SOURCE_OSRM : self::SOURCE_FAILED;

        return [
            'origin_to_start' => $d1,
            'start_to_dest' => $d2,
            'total' => $totalDistance,
            'duration_origin_to_start_minutes' => ! $hasFailed ? $dur1 : null,
            'duration_start_to_dest_minutes' => ! $hasFailed ? $dur2 : null,
            'total_duration_minutes' => $totalDuration,
            'source' => $source,
        ];
    }

    /**
     * Format duration in minutes into a human-readable string.
     * Examples: 45m, 1h 15m, 2h, 2h 45m.
     */
    public static function formatDuration(?int $minutes): string
    {
        if ($minutes === null || $minutes < 0) {
            return '—';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours === 0) {
            return "{$mins}m";
        }

        if ($mins === 0) {
            return "{$hours}h";
        }

        return "{$hours}h {$mins}m";
    }

    /**
     * Calculate great-circle Haversine distance between two coordinates in kilometers.
     */
    public static function calculateHaversineDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 3);
    }

    /**
     * Compute multi-point route across origin, intermediate stops, and final destination.
     * Uses OSRM driving route when available, with graceful Haversine road estimation fallback.
     *
     * @param  list<array{name?: string, address?: string, lat: float, lng: float}>  $points
     * @return array{
     *     success: bool,
     *     total_distance_km: float,
     *     total_duration_minutes: int,
     *     formatted_total_duration: string,
     *     legs: list<array<string, mixed>>,
     *     geometry: list<array{0: float, 1: float}>,
     *     source: string
     * }
     */
    public function calculateMultiPointRoute(array $points): array
    {
        $validPoints = array_values(array_filter($points, function ($pt) {
            return isset($pt['lat'], $pt['lng']) && is_numeric($pt['lat']) && is_numeric($pt['lng']);
        }));

        if (count($validPoints) < 2) {
            return [
                'success' => false,
                'total_distance_km' => 0.0,
                'total_duration_minutes' => 0,
                'formatted_total_duration' => '0m',
                'legs' => [],
                'geometry' => [],
                'source' => self::SOURCE_FAILED,
            ];
        }

        $legs = [];
        $totalDistance = 0.0;
        $totalDuration = 0;
        $fullGeometry = [];
        $overallSource = self::SOURCE_OSRM;

        for ($i = 0; $i < count($validPoints) - 1; $i++) {
            $from = $validPoints[$i];
            $to = $validPoints[$i + 1];

            $fromLat = (float) $from['lat'];
            $fromLng = (float) $from['lng'];
            $toLat = (float) $to['lat'];
            $toLng = (float) $to['lng'];

            $segment = $this->getRouteSegmentWithGeometry($fromLat, $fromLng, $toLat, $toLng);

            if ($segment !== null) {
                $legDist = $segment['distance_km'];
                $legDur = $segment['duration_minutes'];
                $legGeom = $segment['geometry'];
                $legSource = self::SOURCE_OSRM;
            } else {
                $overallSource = 'haversine_estimate';
                // Estimate driving distance via Haversine multiplied by 1.25 road curvature factor
                $straightDist = self::calculateHaversineDistanceKm($fromLat, $fromLng, $toLat, $toLng);
                $legDist = round($straightDist * 1.25, 2);
                // Estimate driving duration assuming ~40 km/h average local commercial vehicle speed
                $legDur = max(2, (int) round(($legDist / 40.0) * 60));
                $legGeom = [
                    [$fromLat, $fromLng],
                    [$toLat, $toLng],
                ];
                $legSource = 'haversine_estimate';
            }

            $totalDistance += $legDist;
            $totalDuration += $legDur;

            $legs[] = [
                'from_name' => $from['name'] ?? ($from['address'] ?? "Stop #{$i}"),
                'to_name' => $to['name'] ?? ($to['address'] ?? 'Stop #'.($i + 1)),
                'from_lat' => $fromLat,
                'from_lng' => $fromLng,
                'to_lat' => $toLat,
                'to_lng' => $toLng,
                'distance_km' => $legDist,
                'duration_minutes' => $legDur,
                'formatted_duration' => self::formatDuration($legDur),
                'source' => $legSource,
            ];

            // Append segment geometry avoiding duplicate adjacent boundary coordinates
            if (empty($fullGeometry)) {
                $fullGeometry = $legGeom;
            } else {
                // If the first point of legGeom matches the last point of fullGeometry, slice it off
                $sliced = array_slice($legGeom, 1);
                $fullGeometry = array_merge($fullGeometry, $sliced);
            }
        }

        return [
            'success' => true,
            'total_distance_km' => round($totalDistance, 2),
            'total_duration_minutes' => $totalDuration,
            'formatted_total_duration' => self::formatDuration($totalDuration),
            'legs' => $legs,
            'geometry' => $fullGeometry,
            'source' => $overallSource,
        ];
    }

    /**
     * Fetch route segment from OSRM including full GeoJSON geometry coordinates.
     *
     * @return array{distance_km: float, duration_minutes: int, geometry: list<array{0: float, 1: float}>}|null
     */
    protected function getRouteSegmentWithGeometry(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        $baseUrl = rtrim((string) config('services.osrm.base_url', 'https://router.project-osrm.org'), '/');
        $coordinates = "{$fromLng},{$fromLat};{$toLng},{$toLat}";
        $url = "{$baseUrl}/route/v1/driving/{$coordinates}";

        try {
            $response = Http::timeout(8)->get($url, [
                'overview' => 'full',
                'geometries' => 'geojson',
                'steps' => 'false',
            ]);

            if (! $response->successful() || $response->json('code') !== 'Ok') {
                return null;
            }

            $distanceMeters = $response->json('routes.0.distance');
            if ($distanceMeters === null) {
                return null;
            }

            $distanceKm = round((float) $distanceMeters / 1000, 2);
            $durationSeconds = $response->json('routes.0.duration') ?? 0;
            $durationMinutes = (int) round((float) $durationSeconds / 60);

            // GeoJSON coordinates are [longitude, latitude] -> convert to [latitude, longitude] for Leaflet
            $rawCoords = $response->json('routes.0.geometry.coordinates') ?? [];
            $leafletCoords = [];
            if (is_array($rawCoords)) {
                foreach ($rawCoords as $c) {
                    if (is_array($c) && count($c) >= 2) {
                        $leafletCoords[] = [(float) $c[1], (float) $c[0]];
                    }
                }
            }

            if (empty($leafletCoords)) {
                $leafletCoords = [
                    [$fromLat, $fromLng],
                    [$toLat, $toLng],
                ];
            }

            return [
                'distance_km' => $distanceKm,
                'duration_minutes' => $durationMinutes,
                'geometry' => $leafletCoords,
            ];
        } catch (Exception $e) {
            return null;
        }
    }
}
