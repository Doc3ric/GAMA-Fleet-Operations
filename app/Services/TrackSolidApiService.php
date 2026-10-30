<?php

namespace App\Services;

use App\Models\Device;
use App\Models\FleetDispatch;
use App\Models\Vehicle;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TrackSolidApiService
{
    protected string $baseUrl;

    protected string $appKey;

    protected string $appSecret;

    protected string $userId;

    protected string $userPwdMd5;

    protected int $timeout;

    public function __construct()
    {
        $config = config('fleet_reconciliation.tracksolid', []);
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://open.tracksolidpro.com/route/rest'), '/');
        $this->appKey = (string) ($config['app_key'] ?? '');
        $this->appSecret = (string) ($config['app_secret'] ?? '');
        $this->userId = (string) ($config['user_id'] ?? '');
        $this->userPwdMd5 = (string) ($config['user_pwd_md5'] ?? '');
        $this->timeout = (int) ($config['timeout_seconds'] ?? 15);
    }

    /**
     * Generate uppercase MD5 signature according to Jimi IoT / TrackSolid Pro Open API specification:
     * 1. Sort all parameters alphabetically by key.
     * 2. Concatenate keys and values without any delimiters.
     * 3. Wrap with app_secret: app_secret + paramStr + app_secret.
     * 4. Return uppercase MD5 hash.
     *
     * @param  array<string, mixed>  $params
     */
    public function generateSignature(array $params): string
    {
        ksort($params);

        $paramString = '';
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $paramString .= $key.$value;
            }
        }

        return strtoupper(md5($this->appSecret.$paramString.$this->appSecret));
    }

    /**
     * Authenticate with TrackSolid Pro API, returning cached or newly issued access token.
     * Sanitizes credentials and never logs secrets.
     */
    public function getAccessToken(bool $forceRefresh = false): ?string
    {
        if (empty($this->appKey) || empty($this->appSecret) || empty($this->userId)) {
            Log::info('TrackSolid API: app_key, app_secret, or user_id not configured in environment.');

            return null;
        }

        $cacheKey = 'tracksolid_api_access_token_'.md5($this->appKey);

        if (! $forceRefresh && Cache::has($cacheKey)) {
            return (string) Cache::get($cacheKey);
        }

        try {
            $params = [
                'app_key' => $this->appKey,
                'expires_in' => 7200,
                'format' => 'json',
                'method' => 'jimi.oauth.token.get',
                'sign_method' => 'md5',
                'timestamp' => gmdate('Y-m-d H:i:s'),
                'user_id' => $this->userId,
                'v' => '1.0',
            ];

            if (! empty($this->userPwdMd5)) {
                $params['user_pwd_md5'] = $this->userPwdMd5;
            }

            $params['sign'] = $this->generateSignature($params);

            $response = Http::timeout($this->timeout)->asForm()->post($this->baseUrl, $params);

            if (! $response->successful()) {
                Log::warning('TrackSolid API auth HTTP failed', ['status' => $response->status()]);

                return null;
            }

            $data = $response->json();
            $code = $data['code'] ?? null;

            if ($code !== 0 && $code !== '0') {
                Log::warning('TrackSolid API auth returned error code', [
                    'code' => $code,
                    'message' => $data['message'] ?? $data['msg'] ?? 'Unknown',
                ]);

                return null;
            }

            $accessToken = $data['result']['accessToken'] ?? $data['result']['access_token'] ?? null;
            $expiresIn = (int) ($data['result']['expiresIn'] ?? $data['result']['expires_in'] ?? 7200);

            if ($accessToken) {
                // Cache token slightly less than expiry to avoid edge-of-expiry requests
                Cache::put($cacheKey, $accessToken, max(60, $expiresIn - 300));

                return $accessToken;
            }

            return null;
        } catch (Exception $e) {
            Log::error('TrackSolid API authentication exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Retrieve authorized device list from TrackSolid.
     *
     * @return list<array<string, mixed>>
     */
    public function getDeviceList(): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return [];
        }

        try {
            $params = [
                'access_token' => $token,
                'app_key' => $this->appKey,
                'format' => 'json',
                'method' => 'jimi.user.device.list',
                'sign_method' => 'md5',
                'target' => $this->userId,
                'timestamp' => gmdate('Y-m-d H:i:s'),
                'v' => '1.0',
            ];

            $params['sign'] = $this->generateSignature($params);

            $response = Http::timeout($this->timeout)->asForm()->post($this->baseUrl, $params);

            if ($response->successful() && ($response->json('code') === 0 || $response->json('code') === '0')) {
                return (array) ($response->json('result') ?? []);
            }

            // Fallback for generic device list interface
            $fallbackParams = [
                'access_token' => $token,
                'app_key' => $this->appKey,
                'format' => 'json',
                'method' => 'jimi.device.list',
                'page_size' => 100,
                'sign_method' => 'md5',
                'timestamp' => gmdate('Y-m-d H:i:s'),
                'v' => '1.0',
            ];
            $fallbackParams['sign'] = $this->generateSignature($fallbackParams);
            $fallbackResponse = Http::timeout($this->timeout)->get($this->baseUrl, $fallbackParams);

            if ($fallbackResponse->successful() && ($fallbackResponse->json('code') === 0 || $fallbackResponse->json('code') === '0')) {
                return (array) ($fallbackResponse->json('result') ?? []);
            }

            Log::warning('TrackSolid getDeviceList failed', ['code' => $response->json('code')]);

            return [];
        } catch (Exception $e) {
            Log::error('TrackSolid getDeviceList exception: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Retrieve mileage / trip data for an IMEI device over a date-time window.
     *
     * @return array{run_distance_km: float, start_odometer: ?float, end_odometer: ?float}|null
     */
    public function getMileageForDevice(string $imei, string $startTime, string $endTime): ?array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return null;
        }

        try {
            $params = [
                'access_token' => $token,
                'app_key' => $this->appKey,
                'begin_time' => $startTime,
                'end_time' => $endTime,
                'format' => 'json',
                'imeis' => $imei,
                'method' => 'jimi.device.track.mileage',
                'sign_method' => 'md5',
                'timestamp' => gmdate('Y-m-d H:i:s'),
                'v' => '1.0',
            ];

            $params['sign'] = $this->generateSignature($params);

            $response = Http::timeout($this->timeout)->asForm()->post($this->baseUrl, $params);

            if ($response->successful() && ($response->json('code') === 0 || $response->json('code') === '0')) {
                $data = $response->json('data');
                $result = $response->json('result');

                $totalMeters = 0.0;
                if (! empty($data) && is_array($data) && isset($data[0]['totalMileage'])) {
                    $totalMeters = (float) $data[0]['totalMileage'];
                } elseif (is_array($result)) {
                    foreach ($result as $leg) {
                        $totalMeters += (float) ($leg['distance'] ?? 0.0);
                    }
                }

                $startOdometer = null;
                $endOdometer = null;
                if (is_array($result) && ! empty($result)) {
                    $odometerReadings = [];
                    foreach ($result as $leg) {
                        if (isset($leg['startMileage'])) {
                            $odometerReadings[] = ((float) $leg['startMileage']) / 1000.0;
                        }
                        if (isset($leg['endMileage'])) {
                            $odometerReadings[] = ((float) $leg['endMileage']) / 1000.0;
                        }
                    }
                    if (! empty($odometerReadings)) {
                        $startOdometer = round(min($odometerReadings), 2);
                        $endOdometer = round(max($odometerReadings), 2);
                    }
                }

                return [
                    'run_distance_km' => round($totalMeters / 1000.0, 2),
                    'start_odometer' => $startOdometer,
                    'end_odometer' => $endOdometer,
                ];
            }

            // Fallback for older jimi.device.mileage.get endpoint
            $fallbackParams = [
                'access_token' => $token,
                'app_key' => $this->appKey,
                'end_time' => $endTime,
                'format' => 'json',
                'imei' => $imei,
                'method' => 'jimi.device.mileage.get',
                'sign_method' => 'md5',
                'start_time' => $startTime,
                'timestamp' => gmdate('Y-m-d H:i:s'),
                'v' => '1.0',
            ];
            $fallbackParams['sign'] = $this->generateSignature($fallbackParams);

            $fallbackResponse = Http::timeout($this->timeout)->get($this->baseUrl, $fallbackParams);

            if ($fallbackResponse->successful() && ($fallbackResponse->json('code') === 0 || $fallbackResponse->json('code') === '0')) {
                $res = $fallbackResponse->json('result');
                $runDistance = (float) ($res['run_distance'] ?? $res['distance'] ?? 0.0);

                return [
                    'run_distance_km' => round($runDistance, 2),
                    'start_odometer' => isset($res['start_odometer']) ? (float) $res['start_odometer'] : null,
                    'end_odometer' => isset($res['end_odometer']) ? (float) $res['end_odometer'] : null,
                ];
            }

            return null;
        } catch (Exception $e) {
            Log::error('TrackSolid getMileageForDevice exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Attempt automated GPS sync for a dispatch if vehicle has an associated GPS device.
     */
    public function syncDispatchMileage(FleetDispatch $dispatch): ?float
    {
        $vehicle = $dispatch->vehicle;
        if (! $vehicle || ! $vehicle->hasGps()) {
            return null;
        }

        $device = $vehicle->device ?: Device::where('vehicle_id', $vehicle->id)->first();
        if (! $device || empty($device->imei)) {
            return null;
        }

        $start = $dispatch->actual_departure_time ?: $dispatch->planned_departure_time;
        if (! $start) {
            return null;
        }

        // Window: from departure until end of trip date or actual arrival
        $startTime = $start->format('Y-m-d H:i:s');
        $endTime = $start->copy()->endOfDay()->format('Y-m-d H:i:s');

        $mileageData = $this->getMileageForDevice($device->imei, $startTime, $endTime);

        return $mileageData['run_distance_km'] ?? null;
    }

    /**
     * Retrieve latest live GPS location and status for multiple devices in batches.
     * Uses TrackSolid Pro Open API 'jimi.device.location.get'.
     *
     * @param  list<string>  $imeis
     * @return array<string, array<string, mixed>> Keyed by IMEI
     */
    public function getDeviceLocations(array $imeis = []): array
    {
        if (empty($imeis)) {
            $imeis = Device::whereNotNull('imei')
                ->where('imei', '!=', '')
                ->pluck('imei')
                ->map(fn ($i) => trim((string) $i))
                ->filter()
                ->unique()
                ->values()
                ->all();
        } else {
            $imeis = array_values(array_unique(array_filter(array_map('strval', $imeis))));
        }

        if (empty($imeis)) {
            return [];
        }

        $token = $this->getAccessToken();
        if (! $token) {
            Log::info('TrackSolid API: Cannot fetch device locations without valid access token.');

            return [];
        }

        $locationsByImei = [];
        $chunks = array_chunk($imeis, 50);

        foreach ($chunks as $chunk) {
            try {
                $params = [
                    'access_token' => $token,
                    'app_key' => $this->appKey,
                    'format' => 'json',
                    'imeis' => implode(',', $chunk),
                    'method' => 'jimi.device.location.get',
                    'sign_method' => 'md5',
                    'timestamp' => gmdate('Y-m-d H:i:s'),
                    'v' => '1.0',
                ];

                $params['sign'] = $this->generateSignature($params);

                $response = Http::timeout($this->timeout)->asForm()->post($this->baseUrl, $params);

                if (! $response->successful()) {
                    Log::warning('TrackSolid getDeviceLocations HTTP failure', [
                        'status' => $response->status(),
                        'chunk_count' => count($chunk),
                    ]);

                    continue;
                }

                $json = $response->json();
                $code = $json['code'] ?? null;

                if ($code !== 0 && $code !== '0') {
                    Log::warning('TrackSolid getDeviceLocations API error code', [
                        'code' => $code,
                        'message' => $json['message'] ?? $json['msg'] ?? 'Unknown error',
                    ]);

                    continue;
                }

                $result = $json['result'] ?? [];

                // Handle single object vs list of objects
                if (isset($result['imei'])) {
                    $result = [$result];
                }

                if (is_array($result)) {
                    foreach ($result as $loc) {
                        if (is_array($loc) && ! empty($loc['imei'])) {
                            $locationsByImei[(string) $loc['imei']] = $loc;
                        }
                    }
                }
            } catch (Exception $e) {
                Log::error('TrackSolid getDeviceLocations exception: '.$e->getMessage());
            }
        }

        return $locationsByImei;
    }

    /**
     * Retrieve chronological historical GPS track points for a single device over a time window.
     * Uses TrackSolid Pro Open API 'jimi.device.track.list'.
     *
     * @param  string  $startTime  Format: 'Y-m-d H:i:s'
     * @param  string  $endTime  Format: 'Y-m-d H:i:s'
     * @param  string  $mapType  'GOOGLE', 'BAIDU', etc.
     * @return array{
     *     points: list<array<string, mixed>>,
     *     total_points: int,
     *     total_distance_km: float,
     *     max_speed_kmh: float,
     *     avg_speed_kmh: float,
     *     start_time: ?string,
     *     end_time: ?string,
     *     start_point: ?array<string, mixed>,
     *     end_point: ?array<string, mixed>
     * }
     */
    public function getHistoricalTrack(
        string $imei,
        string $startTime,
        string $endTime,
        string $mapType = 'GOOGLE'
    ): array {
        $emptyResult = [
            'points' => [],
            'total_points' => 0,
            'total_distance_km' => 0.0,
            'max_speed_kmh' => 0.0,
            'avg_speed_kmh' => 0.0,
            'start_time' => null,
            'end_time' => null,
            'start_point' => null,
            'end_point' => null,
        ];

        $imei = trim($imei);
        if (empty($imei)) {
            return $emptyResult;
        }

        $token = $this->getAccessToken();
        if (! $token) {
            Log::info('TrackSolid API: Cannot fetch historical track without access token.');

            return $emptyResult;
        }

        try {
            $params = [
                'access_token' => $token,
                'app_key' => $this->appKey,
                'begin_time' => $startTime,
                'end_time' => $endTime,
                'format' => 'json',
                'imei' => $imei,
                'map_type' => $mapType,
                'method' => 'jimi.device.track.list',
                'sign_method' => 'md5',
                'timestamp' => gmdate('Y-m-d H:i:s'),
                'v' => '1.0',
            ];

            $params['sign'] = $this->generateSignature($params);

            $response = Http::timeout($this->timeout)->asForm()->post($this->baseUrl, $params);

            if (! $response->successful()) {
                Log::warning('TrackSolid getHistoricalTrack HTTP failure', [
                    'status' => $response->status(),
                    'imei' => substr($imei, 0, 4).'***',
                ]);

                return $emptyResult;
            }

            $json = $response->json();
            $code = $json['code'] ?? null;

            if ($code !== 0 && $code !== '0') {
                Log::warning('TrackSolid getHistoricalTrack API error', [
                    'code' => $code,
                    'message' => $json['message'] ?? $json['msg'] ?? 'Unknown',
                ]);

                return $emptyResult;
            }

            $rawPoints = $json['result'] ?? $json['data'] ?? [];
            if (! is_array($rawPoints) || empty($rawPoints)) {
                return $emptyResult;
            }

            // Normalize and validate each track point
            $normalizedPoints = [];
            $maxSpeed = 0.0;
            $speedSum = 0.0;
            $totalDistanceKm = 0.0;
            $prevLat = null;
            $prevLng = null;

            foreach ($rawPoints as $pt) {
                if (! is_array($pt)) {
                    continue;
                }

                $lat = isset($pt['lat']) && is_numeric($pt['lat']) ? (float) $pt['lat'] : null;
                $lng = isset($pt['lng']) && is_numeric($pt['lng']) ? (float) $pt['lng'] : null;

                // Eliminate invalid / Null Island coordinates
                if ($lat === null || $lng === null || (abs($lat) < 0.0001 && abs($lng) < 0.0001)) {
                    continue;
                }

                $gpsTime = ! empty($pt['gpsTime']) ? (string) $pt['gpsTime'] : null;
                if (! $gpsTime) {
                    continue;
                }

                $speedKmh = isset($pt['gpsSpeed']) && is_numeric($pt['gpsSpeed'])
                    ? max(0.0, round((float) $pt['gpsSpeed'], 1))
                    : (isset($pt['speed']) && is_numeric($pt['speed']) ? max(0.0, round((float) $pt['speed'], 1)) : 0.0);

                // Reject impossible speed jumps (> 180 km/h) as GPS multipath noise
                if ($speedKmh > 180.0) {
                    continue;
                }

                $direction = isset($pt['direction']) && is_numeric($pt['direction']) ? (int) $pt['direction'] : 0;
                $rawAcc = strtoupper((string) ($pt['accStatus'] ?? $pt['ignition'] ?? ''));
                $isIgnitionOn = in_array($rawAcc, ['ON', '1', 'TRUE'], true);

                // Determine point operational state
                if ($speedKmh >= 3.0) {
                    $state = 'moving';
                } elseif ($isIgnitionOn) {
                    $state = 'idling';
                } else {
                    $state = 'parked';
                }

                // Compute point-to-point distance using Haversine formula
                $legDistanceKm = 0.0;
                if ($prevLat !== null && $prevLng !== null) {
                    $legDistanceKm = $this->calculateHaversineDistanceKm($prevLat, $prevLng, $lat, $lng);
                    // Filter out massive GPS telemetry jumps (> 50 km in a single sample)
                    if ($legDistanceKm > 50.0) {
                        $legDistanceKm = 0.0;
                    }
                    $totalDistanceKm += $legDistanceKm;
                }

                $prevLat = $lat;
                $prevLng = $lng;

                $maxSpeed = max($maxSpeed, $speedKmh);
                $speedSum += $speedKmh;

                $normalizedPoints[] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'gps_time' => $gpsTime,
                    'speed' => $speedKmh,
                    'direction' => $direction,
                    'acc_status' => $rawAcc,
                    'is_ignition_on' => $isIgnitionOn,
                    'state' => $state,
                    'pos_type' => $pt['posType'] ?? 'GPS',
                    'satellite' => (int) ($pt['satellite'] ?? 0),
                    'leg_distance_km' => round($legDistanceKm, 3),
                    'cumulative_distance_km' => round($totalDistanceKm, 2),
                ];
            }

            // Ensure points are strictly chronological
            usort($normalizedPoints, fn ($a, $b) => strcmp($a['gps_time'], $b['gps_time']));

            $count = count($normalizedPoints);
            $avgSpeed = $count > 0 ? round($speedSum / $count, 1) : 0.0;
            $startPt = $count > 0 ? $normalizedPoints[0] : null;
            $endPt = $count > 0 ? $normalizedPoints[$count - 1] : null;

            return [
                'points' => $normalizedPoints,
                'total_points' => $count,
                'total_distance_km' => round($totalDistanceKm, 2),
                'max_speed_kmh' => round($maxSpeed, 1),
                'avg_speed_kmh' => $avgSpeed,
                'start_time' => $startPt['gps_time'] ?? null,
                'end_time' => $endPt['gps_time'] ?? null,
                'start_point' => $startPt,
                'end_point' => $endPt,
            ];
        } catch (Exception $e) {
            Log::error('TrackSolid getHistoricalTrack exception: '.$e->getMessage());

            return $emptyResult;
        }
    }

    /**
     * Compute great-circle distance between two coordinates in kilometers using Haversine formula.
     */
    protected function calculateHaversineDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
