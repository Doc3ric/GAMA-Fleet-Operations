<?php

namespace App\Services;

use App\Models\Location;
use Exception;
use Illuminate\Support\Facades\Cache;
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

        $coordinates = implode(';', array_map(
            fn (array $point): string => ((float) $point['lng']).','.((float) $point['lat']),
            $validPoints,
        ));
        $cacheKey = 'routing:osrm:'.sha1(json_encode(array_map(
            fn (array $point): array => [(float) $point['lat'], (float) $point['lng']],
            $validPoints,
        ), JSON_THROW_ON_ERROR));
        $cachedRoutes = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($coordinates): ?array {
            $baseUrl = rtrim((string) config('services.osrm.base_url', 'https://router.project-osrm.org'), '/');

            try {
                $response = Http::connectTimeout(3)
                    ->timeout(8)
                    ->get("{$baseUrl}/route/v1/driving/{$coordinates}", [
                        'alternatives' => 'true',
                        'overview' => 'full',
                        'geometries' => 'geojson',
                        'steps' => 'false',
                    ]);

                $routes = $response->successful() && $response->json('code') === 'Ok'
                    ? $response->json('routes', [])
                    : null;

                return is_array($routes) && $routes !== [] ? $routes : null;
            } catch (Exception $e) {
                return null;
            }
        });
        $osrmRoutes = is_array($cachedRoutes) ? $cachedRoutes : [];

        $alternatives = [];
        foreach ($osrmRoutes as $routeIndex => $route) {
            $rawGeometry = $route['geometry']['coordinates'] ?? [];
            $rawLegs = $route['legs'] ?? [];
            if (count($rawGeometry) < 2 || count($rawLegs) !== count($validPoints) - 1) {
                continue;
            }

            $geometry = [];
            foreach ($rawGeometry as $coordinate) {
                if (is_array($coordinate) && count($coordinate) >= 2) {
                    $geometry[] = [(float) $coordinate[1], (float) $coordinate[0]];
                }
            }

            if (count($geometry) < 2) {
                continue;
            }

            $legs = [];
            foreach ($rawLegs as $legIndex => $leg) {
                $from = $validPoints[$legIndex];
                $to = $validPoints[$legIndex + 1];
                $distanceKm = round((float) ($leg['distance'] ?? 0) / 1000, 2);
                $durationMinutes = (int) round((float) ($leg['duration'] ?? 0) / 60);
                $legs[] = [
                    'from_name' => $from['name'] ?? $from['address'] ?? 'Origin',
                    'to_name' => $to['name'] ?? $to['address'] ?? 'Destination',
                    'from_lat' => (float) $from['lat'],
                    'from_lng' => (float) $from['lng'],
                    'to_lat' => (float) $to['lat'],
                    'to_lng' => (float) $to['lng'],
                    'distance_km' => $distanceKm,
                    'duration_minutes' => $durationMinutes,
                    'formatted_duration' => self::formatDuration($durationMinutes),
                    'source' => self::SOURCE_OSRM,
                ];
            }

            $alternatives[] = [
                'id' => 'osrm-'.$routeIndex,
                'distance_km' => round((float) ($route['distance'] ?? 0) / 1000, 2),
                'duration_minutes' => (int) round((float) ($route['duration'] ?? 0) / 60),
                'formatted_duration' => self::formatDuration((int) round((float) ($route['duration'] ?? 0) / 60)),
                'legs' => $legs,
                'geometry' => $geometry,
                'geojson' => [
                    'type' => 'LineString',
                    'coordinates' => array_map(fn (array $coordinate): array => [$coordinate[1], $coordinate[0]], $geometry),
                ],
                'source' => self::SOURCE_OSRM,
            ];
        }

        if ($alternatives !== []) {
            $preferredRoute = $alternatives[0];

            return [
                'success' => true,
                'total_distance_km' => $preferredRoute['distance_km'],
                'total_duration_minutes' => $preferredRoute['duration_minutes'],
                'formatted_total_duration' => $preferredRoute['formatted_duration'],
                'legs' => $preferredRoute['legs'],
                'geometry' => $preferredRoute['geometry'],
                'geojson' => $preferredRoute['geojson'],
                'source' => self::SOURCE_OSRM,
                'alternatives' => $alternatives,
            ];
        }

        $legs = [];
        $totalDistance = 0.0;
        $totalDuration = 0;
        $fullGeometry = [];
        $overallSource = 'haversine_estimate';

        for ($i = 0; $i < count($validPoints) - 1; $i++) {
            $from = $validPoints[$i];
            $to = $validPoints[$i + 1];

            $fromLat = (float) $from['lat'];
            $fromLng = (float) $from['lng'];
            $toLat = (float) $to['lat'];
            $toLng = (float) $to['lng'];

            // Preserve the existing Haversine road estimate without retrying a failed OSRM request per leg.
            $straightDistance = self::calculateHaversineDistanceKm($fromLat, $fromLng, $toLat, $toLng);
            $legDist = round($straightDistance * 1.25, 2);
            $legDur = max(2, (int) round(($legDist / 40.0) * 60));
            $legGeom = [[$fromLat, $fromLng], [$toLat, $toLng]];
            $legSource = 'haversine_estimate';

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
            'geojson' => [
                'type' => 'LineString',
                'coordinates' => array_map(fn (array $coordinate): array => [$coordinate[1], $coordinate[0]], $fullGeometry),
            ],
            'source' => $overallSource,
            'alternatives' => [[
                'id' => 'fallback',
                'distance_km' => round($totalDistance, 2),
                'duration_minutes' => $totalDuration,
                'formatted_duration' => self::formatDuration($totalDuration),
                'legs' => $legs,
                'geometry' => $fullGeometry,
                'geojson' => [
                    'type' => 'LineString',
                    'coordinates' => array_map(fn (array $coordinate): array => [$coordinate[1], $coordinate[0]], $fullGeometry),
                ],
                'source' => $overallSource,
            ]],
        ];
    }
}
