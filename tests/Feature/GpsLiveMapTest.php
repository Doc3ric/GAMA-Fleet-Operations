<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\GpsLiveMap;
use App\Models\Device;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class GpsLiveMapTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        Config::set('fleet_reconciliation.tracksolid.base_url', 'https://api.tracksolidpro.test/route/rest');
        Config::set('fleet_reconciliation.tracksolid.app_key', 'test_key');
        Config::set('fleet_reconciliation.tracksolid.app_secret', 'test_secret');
        Config::set('fleet_reconciliation.tracksolid.user_id', 'test_user');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('gps-map.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_gps_live_map(): void
    {
        $response = $this->actingAs($this->user)->get(route('gps-map.index'));
        $response->assertStatus(200);
        $response->assertSeeLivewire(GpsLiveMap::class);
        $response->assertSee('Live GPS Fleet Map');
    }

    public function test_vehicle_rows_include_the_reverse_geocoded_current_location_display(): void
    {
        $vehicle = Vehicle::factory()->create(['equipment_code' => 'LOCATION-UNIT']);
        Device::factory()->create(['vehicle_id' => $vehicle->id, 'imei' => '865100000099999']);

        Cache::put('tracksolid_live_device_locations', [
            'locations' => [
                '865100000099999' => [
                    'imei' => '865100000099999',
                    'lat' => 8.47645,
                    'lng' => 124.71772,
                    'speed' => 0,
                    'status' => 1,
                    'accStatus' => 0,
                ],
            ],
            'status' => 'success',
        ], 25);

        Livewire::actingAs($this->user)
            ->test(GpsLiveMap::class)
            ->assertSee('LOCATION-UNIT')
            ->assertSee('Resolving nearby address...');
    }

    public function test_gps_data_normalization_and_state_classification(): void
    {
        Cache::flush();

        // Moving vehicle: online, speed = 35.5, ACC = 1
        $vMoving = Vehicle::factory()->create([
            'equipment_code' => 'TRK-01',
            'plate_number' => 'ABC-1001',
            'gps_status' => 'YES',
        ]);
        Device::factory()->create([
            'vehicle_id' => $vMoving->id,
            'imei' => '865100000000001',
            'device_name' => 'GPS-TRK01',
        ]);

        // Idling vehicle: online, speed = 0, ACC = 1
        $vIdling = Vehicle::factory()->create([
            'equipment_code' => 'TRK-02',
            'plate_number' => 'ABC-1002',
            'gps_status' => 'YES',
        ]);
        Device::factory()->create([
            'vehicle_id' => $vIdling->id,
            'imei' => '865100000000002',
            'device_name' => 'GPS-TRK02',
        ]);

        // Parked vehicle: online, speed = 0, ACC = 0
        $vParked = Vehicle::factory()->create([
            'equipment_code' => 'TRK-03',
            'plate_number' => 'ABC-1003',
            'gps_status' => 'YES',
        ]);
        Device::factory()->create([
            'vehicle_id' => $vParked->id,
            'imei' => '865100000000003',
            'device_name' => 'GPS-TRK03',
        ]);

        // Offline vehicle: status = 0
        $vOffline = Vehicle::factory()->create([
            'equipment_code' => 'TRK-04',
            'plate_number' => 'ABC-1004',
            'gps_status' => 'YES',
        ]);
        Device::factory()->create([
            'vehicle_id' => $vOffline->id,
            'imei' => '865100000000004',
            'device_name' => 'GPS-TRK04',
        ]);

        // Unmapped device: vehicle_id = null
        Device::factory()->create([
            'vehicle_id' => null,
            'imei' => '865100000000099',
            'device_name' => 'SPARE-GPS-99',
        ]);

        // Mock TrackSolid API response
        Cache::put('tracksolid_api_access_token_'.md5('test_key'), 'valid_token', 3600);

        Http::fake([
            'https://api.tracksolidpro.test/route/rest*' => Http::response([
                'code' => 0,
                'result' => [
                    [
                        'imei' => '865100000000001',
                        'lat' => 8.476450,
                        'lng' => 124.717720,
                        'speed' => 35.5,
                        'direction' => 90,
                        'status' => 1,
                        'accStatus' => 1,
                        'hbTime' => now('Asia/Manila')->format('Y-m-d H:i:s'),
                        'gpsTime' => now('Asia/Manila')->format('Y-m-d H:i:s'),
                    ],
                    [
                        'imei' => '865100000000002',
                        'lat' => 8.480000,
                        'lng' => 124.720000,
                        'speed' => 0.0,
                        'direction' => 0,
                        'status' => 1,
                        'accStatus' => 1,
                        'hbTime' => now('Asia/Manila')->format('Y-m-d H:i:s'),
                        'gpsTime' => now('Asia/Manila')->format('Y-m-d H:i:s'),
                    ],
                    [
                        'imei' => '865100000000003',
                        'lat' => 8.485000,
                        'lng' => 124.725000,
                        'speed' => 0.0,
                        'direction' => 0,
                        'status' => 1,
                        'accStatus' => 0,
                        'hbTime' => now('Asia/Manila')->format('Y-m-d H:i:s'),
                        'gpsTime' => now('Asia/Manila')->format('Y-m-d H:i:s'),
                    ],
                    [
                        'imei' => '865100000000004',
                        'lat' => 8.490000,
                        'lng' => 124.730000,
                        'speed' => 0.0,
                        'direction' => 0,
                        'status' => 0,
                        'accStatus' => 0,
                        'hbTime' => now('Asia/Manila')->subDays(2)->format('Y-m-d H:i:s'),
                        'gpsTime' => now('Asia/Manila')->subDays(2)->format('Y-m-d H:i:s'),
                    ],
                    [
                        'imei' => '865100000000099',
                        'lat' => 8.500000,
                        'lng' => 124.750000,
                        'speed' => 0.0,
                        'direction' => 0,
                        'status' => 1,
                        'accStatus' => 0,
                        'hbTime' => now('Asia/Manila')->format('Y-m-d H:i:s'),
                        'gpsTime' => now('Asia/Manila')->format('Y-m-d H:i:s'),
                    ],
                ],
            ], 200),
        ]);

        $component = Livewire::actingAs($this->user)->test(GpsLiveMap::class);

        $fleetData = $component->instance()->getNormalizedFleetData();

        $this->assertEquals(5, $fleetData['summary']['total']);
        $this->assertEquals(1, $fleetData['summary']['moving']);
        $this->assertEquals(1, $fleetData['summary']['idling']);
        $this->assertEquals(2, $fleetData['summary']['parked']);
        $this->assertEquals(1, $fleetData['summary']['offline']);
        $this->assertEquals(1, $fleetData['summary']['unmapped']);
        $this->assertEquals(5, $fleetData['summary']['moving'] + $fleetData['summary']['idling'] + $fleetData['summary']['parked'] + $fleetData['summary']['offline']);

        // Check individual vehicle states
        $vehiclesByKey = collect($fleetData['vehicles'])->keyBy('equipment_code');

        $this->assertEquals('moving', $vehiclesByKey['TRK-01']['state']);
        $this->assertEquals(35.5, $vehiclesByKey['TRK-01']['speed']);
        $this->assertTrue($vehiclesByKey['TRK-01']['is_ignition_on']);
        $this->assertTrue($vehiclesByKey['TRK-01']['is_online']);

        // Speed 0 with status 1 and ACC 1 must be IDLING, not offline
        $this->assertEquals('idling', $vehiclesByKey['TRK-02']['state']);
        $this->assertTrue($vehiclesByKey['TRK-02']['is_ignition_on']);
        $this->assertTrue($vehiclesByKey['TRK-02']['is_online']);

        // Speed 0 with status 1 and ACC 0 must be PARKED, not offline
        $this->assertEquals('parked', $vehiclesByKey['TRK-03']['state']);
        $this->assertFalse($vehiclesByKey['TRK-03']['is_ignition_on']);
        $this->assertTrue($vehiclesByKey['TRK-03']['is_online']);

        // Status 0 must be OFFLINE
        $this->assertEquals('offline', $vehiclesByKey['TRK-04']['state']);
        $this->assertFalse($vehiclesByKey['TRK-04']['is_online']);

        // Unmapped device check
        $this->assertCount(1, $fleetData['unmapped']);
        $this->assertTrue($fleetData['unmapped'][0]['is_unmapped']);
        $this->assertEquals('865100000000099', $fleetData['unmapped'][0]['imei']);
    }

    public function test_speed_zero_does_not_classify_vehicle_as_offline(): void
    {
        Cache::flush();

        $vehicle = Vehicle::factory()->create(['equipment_code' => 'STATIONARY-01']);
        Device::factory()->create([
            'vehicle_id' => $vehicle->id,
            'imei' => '865100000000055',
        ]);

        Cache::put('tracksolid_api_access_token_'.md5('test_key'), 'valid_token', 3600);

        Http::fake([
            'https://api.tracksolidpro.test/route/rest*' => Http::response([
                'code' => 0,
                'result' => [
                    [
                        'imei' => '865100000000055',
                        'lat' => 8.476450,
                        'lng' => 124.717720,
                        'speed' => 0,
                        'status' => 1,
                        'accStatus' => 0,
                        'hbTime' => now('Asia/Manila')->format('Y-m-d H:i:s'),
                        'gpsTime' => now('Asia/Manila')->format('Y-m-d H:i:s'),
                    ],
                ],
            ], 200),
        ]);

        $component = Livewire::actingAs($this->user)->test(GpsLiveMap::class);
        $fleet = $component->instance()->getNormalizedFleetData();

        $this->assertEquals('parked', $fleet['vehicles'][0]['state']);
        $this->assertNotEquals('offline', $fleet['vehicles'][0]['state']);
    }

    public function test_gps_reliability_distinguishes_bad_timestamps_coordinates_unknown_and_sync_failures(): void
    {
        Cache::flush();
        $cases = [
            ['FUTURE', '865100000000201', 1, 8.4, 124.7, now('Asia/Manila')->addMinutes(10)->format('Y-m-d H:i:s'), now('Asia/Manila')->format('Y-m-d H:i:s')],
            ['INVALID-COORDS', '865100000000202', 1, 95.0, 124.7, now('Asia/Manila')->format('Y-m-d H:i:s'), now('Asia/Manila')->format('Y-m-d H:i:s')],
            ['UNKNOWN', '865100000000203', 'MYSTERY', 8.4, 124.7, now('Asia/Manila')->format('Y-m-d H:i:s'), now('Asia/Manila')->format('Y-m-d H:i:s')],
            ['STALE-HB', '865100000000204', 1, 8.4, 124.7, now('Asia/Manila')->format('Y-m-d H:i:s'), now('Asia/Manila')->subHours(3)->format('Y-m-d H:i:s')],
            ['SYNC-FAIL', '865100000000205', 1, 8.4, 124.7, now('Asia/Manila')->format('Y-m-d H:i:s'), now('Asia/Manila')->format('Y-m-d H:i:s')],
        ];
        $locations = [];
        foreach ($cases as [$code, $imei, $status, $lat, $lng, $gpsTime, $hbTime]) {
            $vehicle = Vehicle::factory()->create(['equipment_code' => $code]);
            Device::factory()->create(['vehicle_id' => $vehicle->id, 'imei' => $imei]);
            $locations[$imei] = compact('imei', 'status', 'lat', 'lng', 'gpsTime', 'hbTime');
        }

        Cache::put('tracksolid_live_device_locations', [
            'locations' => $locations,
            'failed_imeis' => ['865100000000205'],
            'status' => 'partial_failure',
        ], 25);

        $fleet = Livewire::actingAs($this->user)->test(GpsLiveMap::class)->instance()->getNormalizedFleetData();
        $vehicles = collect($fleet['vehicles'])->keyBy('equipment_code');

        $this->assertSame('invalid_timestamp', $vehicles['FUTURE']['connection_status']);
        $this->assertSame('invalid_coordinates', $vehicles['INVALID-COORDS']['connection_status']);
        $this->assertSame('unknown', $vehicles['UNKNOWN']['connection_status']);
        $this->assertSame('stale_heartbeat', $vehicles['STALE-HB']['connection_status']);
        $this->assertSame('sync_failure', $vehicles['SYNC-FAIL']['connection_status']);
        $this->assertFalse($vehicles['INVALID-COORDS']['has_coords']);
    }

    public function test_missing_coordinates_handled_safely(): void
    {
        Cache::flush();

        $vehicle = Vehicle::factory()->create(['equipment_code' => 'NO-GPS-FIX']);
        Device::factory()->create([
            'vehicle_id' => $vehicle->id,
            'imei' => '865100000000077',
        ]);

        Cache::put('tracksolid_api_access_token_'.md5('test_key'), 'valid_token', 3600);

        Http::fake([
            'https://api.tracksolidpro.test/route/rest*' => Http::response([
                'code' => 0,
                'result' => [
                    [
                        'imei' => '865100000000077',
                        'lat' => null,
                        'lng' => null,
                        'speed' => 0,
                        'status' => 1,
                        'accStatus' => 0,
                    ],
                ],
            ], 200),
        ]);

        $component = Livewire::actingAs($this->user)->test(GpsLiveMap::class);
        $fleet = $component->instance()->getNormalizedFleetData();

        $this->assertFalse($fleet['vehicles'][0]['has_coords']);
        $this->assertNull($fleet['vehicles'][0]['lat']);
        $this->assertNull($fleet['vehicles'][0]['lng']);
    }

    public function test_null_island_coordinates_treated_as_no_fix(): void
    {
        Cache::flush();

        $vehicle = Vehicle::factory()->create(['equipment_code' => 'NULL-ISLAND']);
        Device::factory()->create([
            'vehicle_id' => $vehicle->id,
            'imei' => '865100000000088',
        ]);

        Cache::put('tracksolid_api_access_token_'.md5('test_key'), 'valid_token', 3600);

        Http::fake([
            'https://api.tracksolidpro.test/route/rest*' => Http::response([
                'code' => 0,
                'result' => [
                    [
                        'imei' => '865100000000088',
                        'lat' => 0.0,
                        'lng' => 0.0,
                        'speed' => 0,
                        'status' => 1,
                        'accStatus' => 0,
                    ],
                ],
            ], 200),
        ]);

        $component = Livewire::actingAs($this->user)->test(GpsLiveMap::class);
        $fleet = $component->instance()->getNormalizedFleetData();

        $this->assertFalse($fleet['vehicles'][0]['has_coords']);
        $this->assertNull($fleet['vehicles'][0]['lat']);
        $this->assertNull($fleet['vehicles'][0]['lng']);
    }

    public function test_stale_data_detection(): void
    {
        Cache::flush();

        $vehicle = Vehicle::factory()->create(['equipment_code' => 'STALE-UNIT']);
        Device::factory()->create([
            'vehicle_id' => $vehicle->id,
            'imei' => '865100000000044',
        ]);

        Cache::put('tracksolid_api_access_token_'.md5('test_key'), 'valid_token', 3600);

        Http::fake([
            'https://api.tracksolidpro.test/route/rest*' => Http::response([
                'code' => 0,
                'result' => [
                    [
                        'imei' => '865100000000044',
                        'lat' => 8.476,
                        'lng' => 124.717,
                        'status' => 1,
                        'hbTime' => now('Asia/Manila')->subHours(5)->format('Y-m-d H:i:s'),
                    ],
                ],
            ], 200),
        ]);

        $component = Livewire::actingAs($this->user)->test(GpsLiveMap::class);
        $fleet = $component->instance()->getNormalizedFleetData();

        $this->assertTrue($fleet['vehicles'][0]['is_stale']);
    }

    public function test_search_filter_filters_vehicles(): void
    {
        Cache::flush();

        Vehicle::factory()->create(['equipment_code' => 'ALPHA-01', 'plate_number' => 'XYZ-1111']);
        Vehicle::factory()->create(['equipment_code' => 'BETA-02', 'plate_number' => 'XYZ-2222']);

        $component = Livewire::actingAs($this->user)
            ->test(GpsLiveMap::class)
            ->set('search', 'ALPHA');

        $filtered = $component->instance()->getFilteredVehicles();
        $this->assertCount(1, $filtered);
        $this->assertEquals('ALPHA-01', $filtered[0]['equipment_code']);
    }

    public function test_status_filter_filters_correctly(): void
    {
        Cache::flush();

        $v1 = Vehicle::factory()->create(['equipment_code' => 'MOVING-1']);
        Device::factory()->create(['vehicle_id' => $v1->id, 'imei' => '1111']);

        $v2 = Vehicle::factory()->create(['equipment_code' => 'PARKED-1']);
        Device::factory()->create(['vehicle_id' => $v2->id, 'imei' => '2222']);

        Cache::put('tracksolid_api_access_token_'.md5('test_key'), 'valid_token', 3600);

        Http::fake([
            'https://api.tracksolidpro.test/route/rest*' => Http::response([
                'code' => 0,
                'result' => [
                    ['imei' => '1111', 'lat' => 8.1, 'lng' => 124.1, 'speed' => 20, 'status' => 1, 'accStatus' => 1],
                    ['imei' => '2222', 'lat' => 8.2, 'lng' => 124.2, 'speed' => 0, 'status' => 1, 'accStatus' => 0],
                ],
            ], 200),
        ]);

        $component = Livewire::actingAs($this->user)->test(GpsLiveMap::class);

        $component->call('setStatusFilter', 'moving');
        $filtered = $component->instance()->getFilteredVehicles();
        $this->assertCount(1, $filtered);
        $this->assertEquals('MOVING-1', $filtered[0]['equipment_code']);

        $component->call('setStatusFilter', 'parked');
        $filtered = $component->instance()->getFilteredVehicles();
        $this->assertCount(1, $filtered);
        $this->assertEquals('PARKED-1', $filtered[0]['equipment_code']);
    }

    public function test_unmapped_filter_enables_unmapped_devices(): void
    {
        Cache::flush();

        Device::factory()->create([
            'vehicle_id' => null,
            'imei' => '865100000000123',
            'device_name' => 'UNMAPPED-123',
        ]);

        Cache::put('tracksolid_api_access_token_'.md5('test_key'), 'valid_token', 3600);
        Http::fake([
            'https://api.tracksolidpro.test/route/rest*' => Http::response([
                'code' => 0,
                'result' => [],
            ], 200),
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(GpsLiveMap::class)
            ->set('showUnmapped', false)
            ->call('setStatusFilter', 'unmapped');

        $component->assertSet('showUnmapped', true)
            ->assertSee('UNMAPPED-123');
    }

    public function test_api_failure_does_not_crash_component(): void
    {
        Cache::flush();

        Vehicle::factory()->create(['equipment_code' => 'SAFE-01']);
        Device::factory()->create(['imei' => '9999']);

        Http::fake([
            'https://api.tracksolidpro.test/route/rest*' => Http::response([], 500),
        ]);

        $component = Livewire::actingAs($this->user)->test(GpsLiveMap::class);

        $component->assertStatus(200);
        $this->assertContains($component->get('apiStatus'), ['warning', 'error', 'connected']);
    }

    public function test_separate_gps_time_and_hb_time_timestamps_are_exposed(): void
    {
        Cache::flush();

        $vehicle = Vehicle::factory()->create(['equipment_code' => 'TIMESTAMPS-01']);
        Device::factory()->create([
            'vehicle_id' => $vehicle->id,
            'imei' => '865100000099901',
        ]);

        $gpsFixTime = '2026-11-10 14:15:00';
        $heartbeatTime = '2026-11-10 14:16:30';

        Cache::put('tracksolid_api_access_token_'.md5('test_key'), 'valid_token', 3600);
        Http::fake([
            'https://api.tracksolidpro.test/route/rest*' => Http::response([
                'code' => 0,
                'result' => [
                    [
                        'imei' => '865100000099901',
                        'lat' => 8.48,
                        'lng' => 124.65,
                        'speed' => 25.0,
                        'direction' => 145,
                        'status' => 1,
                        'accStatus' => 1,
                        'gpsTime' => $gpsFixTime,
                        'hbTime' => $heartbeatTime,
                    ],
                ],
            ], 200),
        ]);

        $component = Livewire::actingAs($this->user)->test(GpsLiveMap::class);
        $vehicles = $component->instance()->getNormalizedFleetData()['vehicles'];

        $this->assertCount(1, $vehicles);
        $this->assertEquals($gpsFixTime, $vehicles[0]['gps_time']);
        $this->assertEquals($heartbeatTime, $vehicles[0]['hb_time']);
        $this->assertNotEmpty($vehicles[0]['gps_time_human']);
        $this->assertNotEmpty($vehicles[0]['hb_time_human']);
        $this->assertEquals(145, $vehicles[0]['direction']);
    }

    public function test_online_device_with_stale_gps_is_distinguished_from_offline_device(): void
    {
        Cache::flush();

        // Unit A: Connected cellular heartbeat (now), but GPS fix is 5 hours old
        $vStaleGps = Vehicle::factory()->create(['equipment_code' => 'STALE-GPS-ONLINE']);
        Device::factory()->create([
            'vehicle_id' => $vStaleGps->id,
            'imei' => '865100000099902',
        ]);

        // Unit B: Truly offline (no heartbeat for 3 days)
        $vTrulyOffline = Vehicle::factory()->create(['equipment_code' => 'TRULY-OFFLINE']);
        Device::factory()->create([
            'vehicle_id' => $vTrulyOffline->id,
            'imei' => '865100000099903',
        ]);

        Cache::put('tracksolid_api_access_token_'.md5('test_key'), 'valid_token', 3600);
        Http::fake([
            'https://api.tracksolidpro.test/route/rest*' => Http::response([
                'code' => 0,
                'result' => [
                    [
                        'imei' => '865100000099902',
                        'lat' => 8.48,
                        'lng' => 124.65,
                        'speed' => 0,
                        'status' => 1, // Online!
                        'accStatus' => 0,
                        'hbTime' => now('Asia/Manila')->format('Y-m-d H:i:s'), // Heartbeat fresh!
                        'gpsTime' => now('Asia/Manila')->subHours(5)->format('Y-m-d H:i:s'), // Satellite fix stale!
                    ],
                    [
                        'imei' => '865100000099903',
                        'lat' => 8.49,
                        'lng' => 124.66,
                        'speed' => 0,
                        'status' => 0, // Offline
                        'accStatus' => 0,
                        'hbTime' => now('Asia/Manila')->subDays(3)->format('Y-m-d H:i:s'),
                        'gpsTime' => now('Asia/Manila')->subDays(3)->format('Y-m-d H:i:s'),
                    ],
                ],
            ], 200),
        ]);

        $component = Livewire::actingAs($this->user)->test(GpsLiveMap::class);
        $vehicles = collect($component->instance()->getNormalizedFleetData()['vehicles'])->keyBy('equipment_code');

        // Unit A is ONLINE, but has stale GPS
        $this->assertTrue($vehicles['STALE-GPS-ONLINE']['is_online']);
        $this->assertTrue($vehicles['STALE-GPS-ONLINE']['is_gps_stale']);
        $this->assertFalse($vehicles['STALE-GPS-ONLINE']['is_heartbeat_stale']);
        $this->assertEquals('online_stale_gps', $vehicles['STALE-GPS-ONLINE']['connection_status']);
        $this->assertEquals('gps_stale', $vehicles['STALE-GPS-ONLINE']['movement_state']);

        // Unit B is OFFLINE
        $this->assertFalse($vehicles['TRULY-OFFLINE']['is_online']);
        $this->assertTrue($vehicles['TRULY-OFFLINE']['is_heartbeat_stale']);
        $this->assertEquals('offline', $vehicles['TRULY-OFFLINE']['connection_status']);
        $this->assertEquals('offline', $vehicles['TRULY-OFFLINE']['movement_state']);
    }

    public function test_stationary_filter_matches_both_idling_and_parked(): void
    {
        Cache::flush();

        $vIdling = Vehicle::factory()->create(['equipment_code' => 'IDLE-1']);
        Device::factory()->create(['vehicle_id' => $vIdling->id, 'imei' => '3331']);

        $vParked = Vehicle::factory()->create(['equipment_code' => 'PARK-1']);
        Device::factory()->create(['vehicle_id' => $vParked->id, 'imei' => '3332']);

        $vMoving = Vehicle::factory()->create(['equipment_code' => 'MOVE-1']);
        Device::factory()->create(['vehicle_id' => $vMoving->id, 'imei' => '3333']);

        Cache::put('tracksolid_api_access_token_'.md5('test_key'), 'valid_token', 3600);
        Http::fake([
            'https://api.tracksolidpro.test/route/rest*' => Http::response([
                'code' => 0,
                'result' => [
                    ['imei' => '3331', 'lat' => 8.1, 'lng' => 124.1, 'speed' => 0, 'status' => 1, 'accStatus' => 1, 'hbTime' => now('Asia/Manila')->format('Y-m-d H:i:s'), 'gpsTime' => now('Asia/Manila')->format('Y-m-d H:i:s')],
                    ['imei' => '3332', 'lat' => 8.2, 'lng' => 124.2, 'speed' => 0, 'status' => 1, 'accStatus' => 0, 'hbTime' => now('Asia/Manila')->format('Y-m-d H:i:s'), 'gpsTime' => now('Asia/Manila')->format('Y-m-d H:i:s')],
                    ['imei' => '3333', 'lat' => 8.3, 'lng' => 124.3, 'speed' => 45, 'status' => 1, 'accStatus' => 1, 'hbTime' => now('Asia/Manila')->format('Y-m-d H:i:s'), 'gpsTime' => now('Asia/Manila')->format('Y-m-d H:i:s')],
                ],
            ], 200),
        ]);

        $component = Livewire::actingAs($this->user)->test(GpsLiveMap::class);

        $component->call('setStatusFilter', 'stationary');
        $filtered = $component->instance()->getFilteredVehicles();

        $this->assertCount(2, $filtered);
        $codes = collect($filtered)->pluck('equipment_code')->all();
        $this->assertContains('IDLE-1', $codes);
        $this->assertContains('PARK-1', $codes);
        $this->assertNotContains('MOVE-1', $codes);
    }
}
