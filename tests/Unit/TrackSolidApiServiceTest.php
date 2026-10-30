<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Models\FleetDispatch;
use App\Models\Vehicle;
use App\Services\TrackSolidApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrackSolidApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('fleet_reconciliation.tracksolid.base_url', 'https://api.tracksolidpro.test/route/rest');
        Config::set('fleet_reconciliation.tracksolid.app_key', 'test_app_key');
        Config::set('fleet_reconciliation.tracksolid.app_secret', 'test_secret_123');
        Config::set('fleet_reconciliation.tracksolid.user_id', 'test_user');
    }

    public function test_authenticate_retrieves_and_caches_token(): void
    {
        Cache::flush();

        Http::fake([
            'https://api.tracksolidpro.test/route/rest*' => Http::response([
                'code' => 0,
                'message' => 'success',
                'result' => [
                    'accessToken' => 'fake_access_token_xyz',
                    'expiresIn' => 7200,
                ],
            ], 200),
        ]);

        $service = new TrackSolidApiService;
        $token = $service->getAccessToken();

        $this->assertEquals('fake_access_token_xyz', $token);
        $this->assertTrue(Cache::has('tracksolid_api_access_token_'.md5('test_app_key')));
    }

    public function test_handles_api_error_response_gracefully(): void
    {
        Cache::flush();

        Http::fake([
            'https://api.tracksolidpro.test/route/rest*' => Http::response([
                'code' => 10001,
                'message' => 'Signature verification failed',
            ], 200),
        ]);

        $service = new TrackSolidApiService;
        $token = $service->getAccessToken();

        $this->assertNull($token);
    }

    public function test_get_mileage_for_device(): void
    {
        Cache::put('tracksolid_api_access_token_'.md5('test_app_key'), 'valid_token', 3600);

        Http::fake(function (Request $request) {
            $uri = (string) $request->toPsrRequest()->getUri();
            if (str_contains($uri, 'jimi.oauth.token.get')) {
                return Http::response([
                    'code' => 0,
                    'result' => ['accessToken' => 'valid_token', 'expiresIn' => 7200],
                ], 200);
            }

            if (str_contains($uri, 'jimi.device.mileage.get')) {
                return Http::response([
                    'code' => 0,
                    'result' => [
                        'run_distance' => 145.8,
                        'start_odometer' => 50000.0,
                        'end_odometer' => 50145.8,
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $service = new TrackSolidApiService;
        $mileage = $service->getMileageForDevice('868210041234567', '2026-10-16 08:00:00', '2026-10-16 23:59:59');

        $this->assertNotNull($mileage);
        $this->assertEquals(145.8, $mileage['run_distance_km']);
        $this->assertEquals(50000.0, $mileage['start_odometer']);
        $this->assertEquals(50145.8, $mileage['end_odometer']);
    }

    public function test_sync_dispatch_mileage(): void
    {
        Cache::put('tracksolid_api_access_token_'.md5('test_app_key'), 'valid_token', 3600);

        $vehicle = Vehicle::factory()->create(['gps_status' => 'YES']);
        Device::factory()->create([
            'vehicle_id' => $vehicle->id,
            'imei' => '868210049876543',
        ]);

        $dispatch = FleetDispatch::factory()->create([
            'vehicle_id' => $vehicle->id,
            'planned_departure_time' => Carbon::today()->setHour(8),
            'actual_departure_time' => Carbon::today()->setHour(8)->addMinutes(10),
        ]);

        Http::fake(function (Request $request) {
            $uri = (string) $request->toPsrRequest()->getUri();
            if (str_contains($uri, 'jimi.oauth.token.get')) {
                return Http::response([
                    'code' => 0,
                    'result' => ['accessToken' => 'valid_token', 'expiresIn' => 7200],
                ], 200);
            }

            if (str_contains($uri, 'jimi.device.mileage.get')) {
                return Http::response([
                    'code' => 0,
                    'result' => [
                        'run_distance' => 112.5,
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $service = new TrackSolidApiService;
        $syncedDistance = $service->syncDispatchMileage($dispatch);

        $this->assertEquals(112.5, $syncedDistance);
    }

    public function test_generate_signature_matches_jimi_open_api_spec(): void
    {
        $service = new TrackSolidApiService;
        $params = [
            'method' => 'jimi.oauth.token.get',
            'timestamp' => '2026-10-16 12:00:00',
            'app_key' => 'test_app_key',
            'v' => '1.0',
            'sign_method' => 'md5',
            'format' => 'json',
            'user_id' => 'test_user',
        ];

        $expected = strtoupper(md5('test_secret_123'.'app_keytest_app_keyformatjsonmethodjimi.oauth.token.getsign_methodmd5timestamp2026-10-16 12:00:00user_idtest_userv1.0'.'test_secret_123'));

        $this->assertEquals($expected, $service->generateSignature($params));
    }

    public function test_get_device_locations_retrieves_and_keys_by_imei(): void
    {
        Cache::put('tracksolid_api_access_token_'.md5('test_app_key'), 'valid_token', 3600);

        Http::fake(function (Request $request) {
            $uri = (string) $request->toPsrRequest()->getUri();
            $body = $request->body();

            if (str_contains($uri, 'jimi.oauth.token.get') || str_contains($body, 'jimi.oauth.token.get')) {
                return Http::response([
                    'code' => 0,
                    'result' => ['accessToken' => 'valid_token', 'expiresIn' => 7200],
                ], 200);
            }

            if (str_contains($body, 'jimi.device.location.get') || str_contains($uri, 'jimi.device.location.get')) {
                return Http::response([
                    'code' => 0,
                    'result' => [
                        [
                            'imei' => '865100000000001',
                            'deviceName' => 'KBJ-9041',
                            'lat' => 8.47645,
                            'lng' => 124.717724,
                            'speed' => 25.5,
                            'direction' => 90,
                            'status' => 1,
                            'accStatus' => 1,
                            'hbTime' => '2026-10-21 09:50:00',
                            'gpsTime' => '2026-10-21 09:50:00',
                        ],
                        [
                            'imei' => '865100000000002',
                            'deviceName' => 'EV16',
                            'lat' => 8.290366,
                            'lng' => 124.969467,
                            'speed' => 0,
                            'direction' => 0,
                            'status' => 0,
                            'accStatus' => 0,
                            'hbTime' => '2026-10-20 18:00:00',
                            'gpsTime' => '2026-10-20 18:00:00',
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $service = new TrackSolidApiService;
        $locations = $service->getDeviceLocations(['865100000000001', '865100000000002']);

        $this->assertCount(2, $locations);
        $this->assertArrayHasKey('865100000000001', $locations);
        $this->assertArrayHasKey('865100000000002', $locations);
        $this->assertEquals(8.47645, $locations['865100000000001']['lat']);
        $this->assertEquals(25.5, $locations['865100000000001']['speed']);
        $this->assertEquals(1, $locations['865100000000001']['status']);
    }

    public function test_get_device_locations_handles_api_failure_gracefully(): void
    {
        Cache::put('tracksolid_api_access_token_'.md5('test_app_key'), 'valid_token', 3600);

        Http::fake([
            'https://api.tracksolidpro.test/route/rest*' => Http::response([
                'code' => 10002,
                'message' => 'Rate limit exceeded',
            ], 200),
        ]);

        $service = new TrackSolidApiService;
        $locations = $service->getDeviceLocations(['865100000000001']);

        $this->assertIsArray($locations);
        $this->assertEmpty($locations);
    }

    public function test_get_historical_track_parses_and_normalizes_points(): void
    {
        Cache::put('tracksolid_api_access_token_'.md5('test_app_key'), 'valid_token', 3600);

        Http::fake(function (Request $request) {
            $body = $request->body();
            if (str_contains($body, 'jimi.device.track.list')) {
                return Http::response([
                    'code' => 0,
                    'result' => [
                        [
                            'lat' => 8.4735,
                            'lng' => 124.7233,
                            'gpsTime' => '2026-10-23 08:00:00',
                            'direction' => 340,
                            'gpsSpeed' => 0,
                            'accStatus' => 'OFF',
                            'ignition' => 'OFF',
                        ],
                        [
                            'lat' => 8.4740,
                            'lng' => 124.7240,
                            'gpsTime' => '2026-10-23 08:05:00',
                            'direction' => 45,
                            'gpsSpeed' => 25.0,
                            'accStatus' => 'ON',
                            'ignition' => 'ON',
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $service = new TrackSolidApiService;
        $track = $service->getHistoricalTrack('865100000000001', '2026-10-23 00:00:00', '2026-10-23 23:59:59');

        $this->assertEquals(2, $track['total_points']);
        $this->assertEquals('2026-10-23 08:00:00', $track['start_time']);
        $this->assertEquals('2026-10-23 08:05:00', $track['end_time']);
        $this->assertEquals(25.0, $track['max_speed_kmh']);
        $this->assertEquals('parked', $track['points'][0]['state']);
        $this->assertEquals('moving', $track['points'][1]['state']);
        $this->assertGreaterThan(0.0, $track['total_distance_km']);
    }

    public function test_get_historical_track_filters_null_island_and_speed_jumps(): void
    {
        Cache::put('tracksolid_api_access_token_'.md5('test_app_key'), 'valid_token', 3600);

        Http::fake([
            'https://api.tracksolidpro.test/route/rest*' => Http::response([
                'code' => 0,
                'result' => [
                    [
                        'lat' => 0.0,
                        'lng' => 0.0, // Null Island
                        'gpsTime' => '2026-10-23 08:00:00',
                        'gpsSpeed' => 0,
                    ],
                    [
                        'lat' => 8.4735,
                        'lng' => 124.7233, // Valid
                        'gpsTime' => '2026-10-23 08:01:00',
                        'gpsSpeed' => 20,
                        'accStatus' => 'ON',
                    ],
                    [
                        'lat' => 8.5000,
                        'lng' => 124.7500, // Invalid speed jump
                        'gpsTime' => '2026-10-23 08:02:00',
                        'gpsSpeed' => 350.0,
                    ],
                ],
            ], 200),
        ]);

        $service = new TrackSolidApiService;
        $track = $service->getHistoricalTrack('865100000000001', '2026-10-23 00:00:00', '2026-10-23 23:59:59');

        $this->assertEquals(1, $track['total_points']);
        $this->assertEquals(8.4735, $track['points'][0]['lat']);
    }
}
