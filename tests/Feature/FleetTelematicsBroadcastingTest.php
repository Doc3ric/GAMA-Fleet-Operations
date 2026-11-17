<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\VehicleTelemetryStreamed;
use App\Livewire\GpsLiveMap;
use App\Models\Device;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\FleetTelematicsBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class FleetTelematicsBroadcastingTest extends TestCase
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

    public function test_vehicle_telemetry_streamed_event_has_correct_channel_and_name(): void
    {
        $sampleVehicles = [
            [
                'id' => 1,
                'equipment_code' => 'TEST-01',
                'lat' => 8.47645,
                'lng' => 124.71772,
                'speed' => 45.0,
                'state' => 'moving',
            ],
        ];
        $summary = ['total' => 1, 'moving' => 1];

        $event = new VehicleTelemetryStreamed($sampleVehicles, $summary, '10:00:00 AM');

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertSame('private-fleet-telematics', $channels[0]->name);
        $this->assertSame('telemetry.updated', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame($sampleVehicles, $payload['vehicles']);
        $this->assertSame($summary, $payload['summary']);
        $this->assertSame('10:00:00 AM', $payload['timestamp']);
    }

    public function test_fleet_telematics_broadcaster_dispatches_event(): void
    {
        Event::fake([VehicleTelemetryStreamed::class]);

        $vehicle = Vehicle::factory()->create(['equipment_code' => 'STREAM-UNIT-1']);
        Device::factory()->create([
            'vehicle_id' => $vehicle->id,
            'imei' => '865100000012345',
        ]);

        Cache::put('tracksolid_live_device_locations', [
            'locations' => [
                '865100000012345' => [
                    'imei' => '865100000012345',
                    'lat' => 8.47645,
                    'lng' => 124.71772,
                    'speed' => 25.0,
                    'status' => 1,
                    'accStatus' => 1,
                    'direction' => 180,
                    'gpsTime' => now()->format('Y-m-d H:i:s'),
                    'hbTime' => now()->format('Y-m-d H:i:s'),
                ],
            ],
            'status' => 'success',
        ], 25);

        /** @var FleetTelematicsBroadcaster $broadcaster */
        $broadcaster = app(FleetTelematicsBroadcaster::class);
        $result = $broadcaster->broadcast();

        $this->assertTrue($result['broadcasted']);
        $this->assertSame(1, $result['summary']['total']);
        $this->assertSame(1, $result['summary']['moving']);

        Event::assertDispatched(VehicleTelemetryStreamed::class, function (VehicleTelemetryStreamed $event) {
            return count($event->vehicles) >= 1 && $event->vehicles[0]['equipment_code'] === 'STREAM-UNIT-1';
        });
    }

    public function test_broadcast_artisan_command_runs_successfully(): void
    {
        Event::fake([VehicleTelemetryStreamed::class]);

        $this->artisan('fleet:broadcast-telematics', ['--interval' => 5])
            ->assertSuccessful();

        Event::assertDispatched(VehicleTelemetryStreamed::class);
    }

    public function test_gps_live_map_refreshes_and_broadcasts_telemetry(): void
    {
        Event::fake([VehicleTelemetryStreamed::class]);

        $vehicle = Vehicle::factory()->create(['equipment_code' => 'MAP-UNIT-1']);
        Device::factory()->create([
            'vehicle_id' => $vehicle->id,
            'imei' => '865100000099887',
        ]);

        Cache::put('tracksolid_live_device_locations', [
            'locations' => [
                '865100000099887' => [
                    'imei' => '865100000099887',
                    'lat' => 8.48,
                    'lng' => 124.72,
                    'speed' => 0,
                    'status' => 1,
                    'accStatus' => 0,
                ],
            ],
            'status' => 'success',
        ], 25);

        Livewire::actingAs($this->user)
            ->test(GpsLiveMap::class)
            ->call('refreshLocations', true)
            ->assertDispatched('gps-locations-refreshed');

        Event::assertDispatched(VehicleTelemetryStreamed::class);
    }

    public function test_fleet_telematics_channel_authorization_rule(): void
    {
        $channels = Broadcast::getChannels();
        $this->assertArrayHasKey('fleet-telematics', $channels);

        $authCallback = $channels['fleet-telematics'];
        $this->assertTrue((bool) $authCallback($this->user));
        $this->assertFalse((bool) $authCallback(null));
    }
}
