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
     * Calculate road distance in kilometres between two points via OSRM.
     * Returns null if the routing service is unavailable or returns no route.
     */
    public function getDistanceKm(float $fromLat, float $fromLng, float $toLat, float $toLng): ?float
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

            return round((float) $distanceMeters / 1000, 2);

        } catch (Exception $e) {
            Log::warning('OSRM routing request failed: '.$e->getMessage(), ['url' => $url]);

            return null;
        }
    }

    /**
     * Calculate both leg distances for an Origin → Starting Point → Destination sequence.
     *
     * Returns an array:
     * [
     *     'origin_to_start' => float|null,  // km
     *     'start_to_dest'   => float|null,  // km
     *     'total'           => float|null,  // sum, null if either leg failed
     *     'source'          => string,       // 'osrm' or 'failed'
     * ]
     *
     * @return array{origin_to_start: ?float, start_to_dest: ?float, total: ?float, source: string}
     */
    public function calculateLegDistances(
        Location $origin,
        Location $startingPoint,
        Location $destination
    ): array {
        $d1 = $this->getDistanceKm(
            (float) $origin->latitude,
            (float) $origin->longitude,
            (float) $startingPoint->latitude,
            (float) $startingPoint->longitude
        );

        $d2 = $this->getDistanceKm(
            (float) $startingPoint->latitude,
            (float) $startingPoint->longitude,
            (float) $destination->latitude,
            (float) $destination->longitude
        );

        $total = ($d1 !== null && $d2 !== null) ? round($d1 + $d2, 2) : null;
        $source = ($d1 !== null && $d2 !== null) ? self::SOURCE_OSRM : self::SOURCE_FAILED;

        return [
            'origin_to_start' => $d1,
            'start_to_dest' => $d2,
            'total' => $total,
            'source' => $source,
        ];
    }
}
