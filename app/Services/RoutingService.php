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
}
