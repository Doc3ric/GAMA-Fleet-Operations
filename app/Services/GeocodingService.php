<?php

namespace App\Services;

use App\Models\Location;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    /**
     * Search for places, addresses, landmarks, or parse coordinates and Google Maps URLs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $results = [];

        // 1. Check direct coordinates or full Google Maps URL
        $parsedCoords = $this->parseCoordinates($query);
        if ($parsedCoords !== null) {
            $results[] = [
                'title' => 'GPS Coordinate Fixed: '.number_format($parsedCoords['latitude'], 6).', '.number_format($parsedCoords['longitude'], 6),
                'subtitle' => 'Exact latitude and longitude entered',
                'latitude' => $parsedCoords['latitude'],
                'longitude' => $parsedCoords['longitude'],
                'address' => '',
                'barangay' => '',
                'municipality' => '',
                'province' => '',
                'source' => 'coordinate',
                'source_label' => 'Exact GPS Coordinates',
                'is_exact' => true,
            ];
        }

        // 2. Check shortened Google Maps URL (maps.app.goo.gl or goo.gl/maps)
        if ($parsedCoords === null && $this->isGoogleMapsUrl($query)) {
            $shortUrlCoords = $this->resolveGoogleUrl($query);
            if ($shortUrlCoords !== null) {
                $results[] = [
                    'title' => 'Google Maps Location Fixed: '.number_format($shortUrlCoords['latitude'], 6).', '.number_format($shortUrlCoords['longitude'], 6),
                    'subtitle' => 'Extracted from Google Maps URL',
                    'latitude' => $shortUrlCoords['latitude'],
                    'longitude' => $shortUrlCoords['longitude'],
                    'address' => '',
                    'barangay' => '',
                    'municipality' => '',
                    'province' => '',
                    'source' => 'google_url',
                    'source_label' => 'Google Maps Link',
                    'is_exact' => true,
                ];
            }
        }

        // 3. Search local company locations directory
        try {
            $localLocations = Location::active()
                ->where(function ($q) use ($query) {
                    $q->where('official_name', 'like', "%{$query}%")
                        ->orWhere('code', 'like', "%{$query}%")
                        ->orWhere('address', 'like', "%{$query}%")
                        ->orWhere('municipality', 'like', "%{$query}%")
                        ->orWhereHas('aliases', function ($aq) use ($query) {
                            $aq->where('alias', 'like', "%{$query}%");
                        });
                })
                ->limit(3)
                ->get();

            foreach ($localLocations as $loc) {
                $results[] = [
                    'title' => $loc->official_name.' ('.$loc->code.')',
                    'subtitle' => trim($loc->address.', '.($loc->barangay ? $loc->barangay.', ' : '').$loc->municipality.', '.$loc->province, ', '),
                    'latitude' => (float) $loc->latitude,
                    'longitude' => (float) $loc->longitude,
                    'address' => $loc->address,
                    'barangay' => $loc->barangay ?? '',
                    'municipality' => $loc->municipality ?? '',
                    'province' => $loc->province ?? '',
                    'source' => 'local_directory',
                    'source_label' => 'Company Directory ('.$loc->type.')',
                    'is_exact' => true,
                ];
            }
        } catch (Exception $e) {
            Log::warning('Local directory search error: '.$e->getMessage());
        }

        // 4. Commercial GIS Search: ArcGIS World Geocoding Engine (Free, high-precision POI/commercial data)
        $arcGisCandidates = $this->queryArcGis($query);
        foreach ($arcGisCandidates as $cand) {
            $results[] = $cand;
        }

        // 5. Fallback: If no commercial candidates found, query Photon
        if (count($results) <= 1) {
            $photonCandidates = $this->queryPhoton($query);
            foreach ($photonCandidates as $cand) {
                $results[] = $cand;
            }
        }

        // Return deduplicated results, capped at 10
        return array_slice($results, 0, 10);
    }

    /**
     * Query ArcGIS World Geocoding Service.
     *
     * @return array<int, array<string, mixed>>
     */
    public function queryArcGis(string $query): array
    {
        try {
            $response = Http::timeout(5)->get('https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates', [
                'f' => 'json',
                'singleLine' => $query,
                'outFields' => 'PlaceName,Type,Place_addr,LongLabel,City,Subregion,Region,Postal',
                'maxLocations' => 8,
            ]);

            if (! $response->successful()) {
                return [];
            }

            $candidates = $response->json('candidates') ?? [];
            $formatted = [];

            foreach ($candidates as $c) {
                $location = $c['location'] ?? null;
                if (! $location || ! isset($location['y']) || ! isset($location['x'])) {
                    continue;
                }

                $attrs = $c['attributes'] ?? [];
                $placeName = ! empty($attrs['PlaceName']) ? $attrs['PlaceName'] : ($c['address'] ?? 'Unknown Place');
                $address = $c['address'] ?? '';

                $formatted[] = [
                    'title' => $placeName,
                    'subtitle' => $address,
                    'latitude' => round((float) $location['y'], 7),
                    'longitude' => round((float) $location['x'], 7),
                    'address' => ! empty($attrs['Place_addr']) ? $attrs['Place_addr'] : $address,
                    'barangay' => '',
                    'municipality' => $attrs['City'] ?? '',
                    'province' => $attrs['Subregion'] ?? $attrs['Region'] ?? '',
                    'source' => 'arcgis',
                    'source_label' => 'High-Precision Commercial POI',
                    'is_exact' => false,
                ];
            }

            return $formatted;
        } catch (Exception $e) {
            Log::warning('ArcGIS Geocoding error: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Query Photon Geocoding Service as supplementary/fallback search.
     *
     * @return array<int, array<string, mixed>>
     */
    public function queryPhoton(string $query): array
    {
        try {
            $response = Http::timeout(5)->get('https://photon.komoot.io/api/', [
                'q' => $query,
                'limit' => 5,
            ]);

            if (! $response->successful()) {
                return [];
            }

            $features = $response->json('features') ?? [];
            $formatted = [];

            foreach ($features as $f) {
                $coords = $f['geometry']['coordinates'] ?? null;
                if (! $coords || count($coords) < 2) {
                    continue;
                }

                $props = $f['properties'] ?? [];
                $name = $props['name'] ?? 'Location';
                $city = $props['city'] ?? $props['town'] ?? $props['district'] ?? '';
                $state = $props['state'] ?? '';
                $country = $props['country'] ?? '';

                $parts = array_filter([$name, $city, $state, $country]);
                $subtitle = implode(', ', $parts);

                $formatted[] = [
                    'title' => $name,
                    'subtitle' => $subtitle,
                    'latitude' => round((float) $coords[1], 7),
                    'longitude' => round((float) $coords[0], 7),
                    'address' => $props['street'] ?? $subtitle,
                    'barangay' => $props['district'] ?? '',
                    'municipality' => $city,
                    'province' => $state,
                    'source' => 'photon',
                    'source_label' => 'OpenStreetMap POI',
                    'is_exact' => false,
                ];
            }

            return $formatted;
        } catch (Exception $e) {
            Log::warning('Photon geocoding error: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Parse raw string for geographic coordinates or Google Maps URL pattern.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function parseCoordinates(string $input): ?array
    {
        $input = trim($input);

        // 1. Direct decimal coordinates: "14.8523, 120.8164" or "14.8523 120.8164"
        if (preg_match('/^(-?\d{1,2}(?:\.\d+)?)[,\s]+(-?\d{1,3}(?:\.\d+)?)$/', $input, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if ($this->isValidCoordinate($lat, $lng)) {
                return ['latitude' => round($lat, 7), 'longitude' => round($lng, 7)];
            }
        }

        // 2. Degrees Minutes Seconds (DMS): e.g. 14°51'08.3"N 120°48'59.0"E
        if (preg_match('/(\d+)°\s*(\d+)\'\s*([\d.]+)"\s*([NSEW])[,\s]+(\d+)°\s*(\d+)\'\s*([\d.]+)"\s*([NSEW])/i', $input, $m)) {
            $lat = (int) $m[1] + ((int) $m[2] / 60) + ((float) $m[3] / 3600);
            if (strtoupper($m[4]) === 'S') {
                $lat = -$lat;
            }
            $lng = (int) $m[5] + ((int) $m[6] / 60) + ((float) $m[7] / 3600);
            if (strtoupper($m[8]) === 'W') {
                $lng = -$lng;
            }

            if ($this->isValidCoordinate($lat, $lng)) {
                return ['latitude' => round($lat, 7), 'longitude' => round($lng, 7)];
            }
        }

        // 3. Google Maps URL pattern: /@14.802148,120.9254397,14z
        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $input, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if ($this->isValidCoordinate($lat, $lng)) {
                return ['latitude' => round($lat, 7), 'longitude' => round($lng, 7)];
            }
        }

        // 4. Google Maps query parameter: ?q=14.852345,120.816412 or &query=... or ?ll=...
        if (preg_match('/[?&](?:q|query|ll)=(-?\d+\.\d+),(-?\d+\.\d+)/', $input, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if ($this->isValidCoordinate($lat, $lng)) {
                return ['latitude' => round($lat, 7), 'longitude' => round($lng, 7)];
            }
        }

        // 5. Google Maps place path: /place/.../(-?\d+\.\d+),(-?\d+\.\d+)
        if (preg_match('/place\/[^@\/]*\/(-?\d+\.\d+),(-?\d+\.\d+)/', $input, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if ($this->isValidCoordinate($lat, $lng)) {
                return ['latitude' => round($lat, 7), 'longitude' => round($lng, 7)];
            }
        }

        return null;
    }

    /**
     * Check if string contains a Google Maps domain.
     */
    public function isGoogleMapsUrl(string $input): bool
    {
        return (bool) preg_match('/(?:maps\.app\.goo\.gl|goo\.gl\/maps|google\.[a-z.]+\/maps)/i', $input);
    }

    /**
     * Resolve a shortened or full Google Maps URL by following HTTP redirects if necessary.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function resolveGoogleUrl(string $url): ?array
    {
        $url = trim($url);

        // Check if full URL already contains coordinates
        $coords = $this->parseCoordinates($url);
        if ($coords !== null) {
            return $coords;
        }

        try {
            // For shortened URLs (e.g. maps.app.goo.gl or goo.gl/maps), make a request without redirect to read Location header
            $response = Http::timeout(5)->withoutRedirecting()->head($url);
            $redirectUrl = $response->header('Location');

            if (! $redirectUrl) {
                // If HEAD doesn't return redirect, try GET
                $response = Http::timeout(5)->withoutRedirecting()->get($url);
                $redirectUrl = $response->header('Location');
            }

            if ($redirectUrl) {
                return $this->parseCoordinates($redirectUrl);
            }
        } catch (Exception $e) {
            Log::warning('Failed to resolve Google Maps URL: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Validate coordinate boundaries (-90..90, -180..180).
     */
    protected function isValidCoordinate(float $lat, float $lng): bool
    {
        return $lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0;
    }
}
