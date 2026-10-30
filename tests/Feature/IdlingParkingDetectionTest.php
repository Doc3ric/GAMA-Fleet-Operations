<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\IdlingParkingMonitor;
use App\Models\Device;
use App\Models\TelemetryEvent;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\IdlingParkingDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IdlingParkingDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('idling-parking.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_idling_parking_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('idling-parking.index'));
        $response->assertStatus(200);
        $response->assertSeeLivewire(IdlingParkingMonitor::class);
        $response->assertSee('Idling & Parking Detection');
    }

    public function test_service_classifies_operational_states_correctly(): void
    {
        $service = app(IdlingParkingDetectionService::class);

        // 1. Moving: speed >= 3 km/h
        $stateMoving = $service->classifyState(isOnline: true, isIgnitionOn: true, speedKmh: 45.0);
        $this->assertEquals(TelemetryEvent::TYPE_MOVING, $stateMoving);

        // 2. Idling: speed < 3 km/h, ignition ON
        $stateIdling = $service->classifyState(isOnline: true, isIgnitionOn: true, speedKmh: 0.0);
        $this->assertEquals(TelemetryEvent::TYPE_IDLING, $stateIdling);

        // 3. Parked: speed < 3 km/h, ignition OFF
        $stateParked = $service->classifyState(isOnline: true, isIgnitionOn: false, speedKmh: 0.0);
        $this->assertEquals(TelemetryEvent::TYPE_PARKING, $stateParked);

        // 4. Offline: disconnected
        $stateOffline = $service->classifyState(isOnline: false, isIgnitionOn: false, speedKmh: 0.0);
        $this->assertEquals(TelemetryEvent::TYPE_OFFLINE, $stateOffline);

        // 5. Zero speed does not mean offline if connected
        $stateConnectedZeroSpeed = $service->classifyState(isOnline: true, isIgnitionOn: false, speedKmh: 0.0);
        $this->assertNotEquals(TelemetryEvent::TYPE_OFFLINE, $stateConnectedZeroSpeed);
        $this->assertEquals(TelemetryEvent::TYPE_PARKING, $stateConnectedZeroSpeed);
    }

    public function test_service_tracks_and_transitions_telemetry_events(): void
    {
        $service = app(IdlingParkingDetectionService::class);

        $vehicle = Vehicle::factory()->create([
            'equipment_code' => 'TEST-VH-01',
            'plate_number' => 'ABC-1234',
        ]);

        $device = Device::factory()->create([
            'vehicle_id' => $vehicle->id,
            'imei' => '865000000000001',
            'device_name' => 'TRACK-01',
        ]);

        // Snapshot 1: Vehicle starts idling (speed 0, ACC ON)
        $locations1 = [
            $device->imei => [
                'lat' => 14.5995,
                'lng' => 120.9842,
                'speed' => 0.0,
                'accStatus' => 1,
                'status' => 1,
            ],
        ];

        $res1 = $service->processTelemetrySnapshot($locations1);
        $this->assertEquals(1, $res1['transitioned']);

        $event1 = TelemetryEvent::where('imei', $device->imei)->where('is_ongoing', true)->first();
        $this->assertNotNull($event1);
        $this->assertEquals(TelemetryEvent::TYPE_IDLING, $event1->event_type);
        $this->assertTrue($event1->is_ongoing);

        // Snapshot 2: Same state (ongoing continues)
        $locations2 = [
            $device->imei => [
                'lat' => 14.5995,
                'lng' => 120.9842,
                'speed' => 0.0,
                'accStatus' => 1,
                'status' => 1,
            ],
        ];

        $res2 = $service->processTelemetrySnapshot($locations2);
        $this->assertEquals(1, $res2['updated']);

        // Snapshot 3: Vehicle begins moving at 40 km/h (closes idling event and creates moving event)
        $locations3 = [
            $device->imei => [
                'lat' => 14.6050,
                'lng' => 120.9900,
                'speed' => 40.0,
                'accStatus' => 1,
                'status' => 1,
            ],
        ];

        $res3 = $service->processTelemetrySnapshot($locations3);
        $this->assertEquals(1, $res3['transitioned']);

        // Check that the previous idling event was closed
        $event1Fresh = TelemetryEvent::find($event1->id);
        $this->assertFalse($event1Fresh->is_ongoing);

        // Check that a new moving event is ongoing
        $movingEvent = TelemetryEvent::where('imei', $device->imei)->where('is_ongoing', true)->first();
        $this->assertNotNull($movingEvent);
        $this->assertEquals(TelemetryEvent::TYPE_MOVING, $movingEvent->event_type);
    }

    public function test_reconstruction_from_historical_track_points(): void
    {
        $service = app(IdlingParkingDetectionService::class);

        $vehicle = Vehicle::factory()->create();
        $device = Device::factory()->create(['vehicle_id' => $vehicle->id, 'imei' => '865000000000099']);

        $trackPoints = [
            // Moving leg 1 (speed 30-35 km/h)
            ['lat' => 14.1, 'lng' => 121.1, 'gps_time' => '2026-10-23 09:00:00', 'speed' => 30.0, 'acc_status' => 'ON'],
            ['lat' => 14.2, 'lng' => 121.2, 'gps_time' => '2026-10-23 09:10:00', 'speed' => 35.0, 'acc_status' => 'ON'],

            // Idling cluster (speed 0, ACC ON) for 10 minutes
            ['lat' => 14.25, 'lng' => 121.25, 'gps_time' => '2026-10-23 09:15:00', 'speed' => 0.0, 'acc_status' => 'ON'],
            ['lat' => 14.25, 'lng' => 121.25, 'gps_time' => '2026-10-23 09:20:00', 'speed' => 0.0, 'acc_status' => 'ON'],
            ['lat' => 14.25, 'lng' => 121.25, 'gps_time' => '2026-10-23 09:25:00', 'speed' => 0.0, 'acc_status' => 'ON'],

            // Parking cluster (speed 0, ACC OFF) for 20 minutes
            ['lat' => 14.25, 'lng' => 121.25, 'gps_time' => '2026-10-23 09:30:00', 'speed' => 0.0, 'acc_status' => 'OFF'],
            ['lat' => 14.25, 'lng' => 121.25, 'gps_time' => '2026-10-23 09:50:00', 'speed' => 0.0, 'acc_status' => 'OFF'],
        ];

        $result = $service->reconstructFromHistoricalTrack(
            imei: $device->imei,
            trackPoints: $trackPoints,
            vehicleId: $vehicle->id,
            deviceId: $device->id
        );

        $this->assertGreaterThanOrEqual(2, $result['created_count']);

        // Check that idling event was recorded
        $hasIdling = TelemetryEvent::where('vehicle_id', $vehicle->id)
            ->where('event_type', TelemetryEvent::TYPE_IDLING)
            ->where('duration_seconds', '>=', 600)
            ->exists();
        $this->assertTrue($hasIdling);

        // Check that parking event was recorded
        $hasParking = TelemetryEvent::where('vehicle_id', $vehicle->id)
            ->where('event_type', TelemetryEvent::TYPE_PARKING)
            ->where('duration_seconds', '>=', 1200)
            ->exists();
        $this->assertTrue($hasParking);
    }

    public function test_livewire_component_filters_and_resolves_events(): void
    {
        $vehicle = Vehicle::factory()->create([
            'equipment_code' => 'VH-MONITOR-01',
            'plate_number' => 'XYZ-9988',
        ]);

        $ongoingIdling = TelemetryEvent::create([
            'vehicle_id' => $vehicle->id,
            'imei' => '865000000000077',
            'event_type' => TelemetryEvent::TYPE_IDLING,
            'start_time' => now()->subMinutes(25),
            'end_time' => now(),
            'duration_seconds' => 1500,
            'is_ongoing' => true,
            'latitude' => 14.3,
            'longitude' => 121.1,
            'source' => TelemetryEvent::SOURCE_LIVE_SYNC,
        ]);

        $completedParking = TelemetryEvent::create([
            'vehicle_id' => $vehicle->id,
            'imei' => '865000000000077',
            'event_type' => TelemetryEvent::TYPE_PARKING,
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
            'duration_seconds' => 3600,
            'is_ongoing' => false,
            'latitude' => 14.3,
            'longitude' => 121.1,
            'source' => TelemetryEvent::SOURCE_TRACK_RECONSTRUCTION,
        ]);

        Livewire::actingAs($this->user)
            ->test(IdlingParkingMonitor::class)
            ->assertSee('VH-MONITOR-01')
            ->assertSee('XYZ-9988')
            ->set('eventType', 'idling')
            ->assertSee('Idling')
            ->set('statusMode', 'ongoing')
            ->assertSee('Ongoing')
            ->call('closeOngoingEvent', $ongoingIdling->id);

        $this->assertFalse($ongoingIdling->fresh()->is_ongoing);
    }
}
