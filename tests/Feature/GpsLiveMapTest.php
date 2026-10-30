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
                        'hbTime' => now()->format('Y-m-d H:i:s'),
                        'gpsTime' => now()->format('Y-m-d H:i:s'),
                    ],
                    [
                        'imei' => '865100000000002',
                        'lat' => 8.480000,
                        'lng' => 124.720000,
                        'speed' => 0.0,
                        'direction' => 0,
                        'status' => 1,
                        'accStatus' => 1,
                        'hbTime' => now()->format('Y-m-d H:i:s'),
                        'gpsTime' => now()->format('Y-m-d H:i:s'),
                    ],
                    [
                        'imei' => '865100000000003',
                        'lat' => 8.485000,
                        'lng' => 124.725000,
                        'speed' => 0.0,
                        'direction' => 0,
                        'status' => 1,
                        'accStatus' => 0,
                        'hbTime' => now()->format('Y-m-d H:i:s'),
                        'gpsTime' => now()->format('Y-m-d H:i:s'),
                    ],
                    [
                        'imei' => '865100000000004',
                        'lat' => 8.490000,
                        'lng' => 124.730000,
                        'speed' => 0.0,
                        'direction' => 0,
                        'status' => 0,
                        'accStatus' => 0,
                        'hbTime' => now()->subDays(2)->format('Y-m-d H:i:s'),
                        'gpsTime' => now()->subDays(2)->format('Y-m-d H:i:s'),
                    ],
                    [
                        'imei' => '865100000000099',
                        'lat' => 8.500000,
                        'lng' => 124.750000,
                        'speed' => 0.0,
                        'direction' => 0,
                        'status' => 1,
                        'accStatus' => 0,
                        'hbTime' => now()->format('Y-m-d H:i:s'),
                        'gpsTime' => now()->format('Y-m-d H:i:s'),
                    ],
                ],
            ], 200),
        ]);

        $component = Livewire::actingAs($this->user)->test(GpsLiveMap::class);

        $fleetData = $component->instance()->getNormalizedFleetData();

        $this->assertEquals(4, $fleetData['summary']['total']);
        $this->assertEquals(1, $fleetData['summary']['moving']);
        $this->assertEquals(1, $fleetData['summary']['idling']);
        $this->assertEquals(1, $fleetData['summary']['parked']);
        $this->assertEquals(1, $fleetData['summary']['offline']);
        $this->assertEquals(1, $fleetData['summary']['unmapped']);

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
                        'hbTime' => now()->format('Y-m-d H:i:s'),
                        'gpsTime' => now()->format('Y-m-d H:i:s'),
                    ],
                ],
            ], 200),
        ]);

        $component = Livewire::actingAs($this->user)->test(GpsLiveMap::class);
        $fleet = $component->instance()->getNormalizedFleetData();

        $this->assertEquals('parked', $fleet['vehicles'][0]['state']);
        $this->assertNotEquals('offline', $fleet['vehicles'][0]['state']);
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
                        'hbTime' => now()->subHours(5)->format('Y-m-d H:i:s'),
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
}
