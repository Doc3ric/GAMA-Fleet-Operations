<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\TripHistoryReplay;
use App\Models\AdvancedItinerary;
use App\Models\Device;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TrackSolidApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class TripHistoryReplayTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->user = User::factory()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('trip-history.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_trip_history_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('trip-history.index'));
        $response->assertStatus(200);
        $response->assertSeeLivewire(TripHistoryReplay::class);
        $response->assertSee('Trip History & Route Replay');
    }

    public function test_presets_update_dates_correctly(): void
    {
        $vehicle = Vehicle::factory()->create(['equipment_code' => 'VH-PRESET-01']);
        Device::factory()->create(['vehicle_id' => $vehicle->id, 'imei' => '865000000000555']);

        $component = Livewire::actingAs($this->user)
            ->test(TripHistoryReplay::class)
            ->call('applyPreset', 'yesterday');

        $yesterday = Carbon::now()->subDay()->format('Y-m-d');
        $component->assertSet('startDate', $yesterday)
            ->assertSet('endDate', $yesterday);

        $component->call('applyPreset', 'past_7d');
        $pastWeek = Carbon::now()->subDays(6)->format('Y-m-d');
        $component->assertSet('startDate', $pastWeek);
    }

    public function test_trip_history_uses_tracksolid_local_date_when_server_clock_is_utc(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 20:00:00', 'UTC'));
        $vehicle = Vehicle::factory()->create();
        Device::factory()->create(['vehicle_id' => $vehicle->id, 'imei' => '865000000000556']);
        $mockApiService = Mockery::mock(TrackSolidApiService::class);
        $mockApiService->shouldReceive('getHistoricalTrack')
            ->once()
            ->with('865000000000556', '2026-11-16 00:00:00', '2026-11-16 03:59:59')
            ->andReturn(['points' => []]);
        $this->app->instance(TrackSolidApiService::class, $mockApiService);

        Livewire::actingAs($this->user)
            ->test(TripHistoryReplay::class, ['vehicleId' => $vehicle->id])
            ->assertSet('startDate', '2026-11-16')
            ->assertSet('endTime', '04:00');
    }

    public function test_track_loading_and_stop_extraction(): void
    {
        $vehicle = Vehicle::factory()->create(['equipment_code' => 'VH-REPLAY-01', 'plate_number' => 'ABC-9999']);
        $device = Device::factory()->create(['vehicle_id' => $vehicle->id, 'imei' => '865000000000888']);

        $mockApiService = Mockery::mock(TrackSolidApiService::class);
        $mockApiService->shouldReceive('getHistoricalTrack')
            ->once()
            ->andReturn([
                'total_points' => 4,
                'total_distance_km' => 15.5,
                'max_speed_kmh' => 65.0,
                'avg_speed_kmh' => 42.0,
                'start_time' => '2026-10-23 08:00:00',
                'end_time' => '2026-10-23 08:45:00',
                'points' => [
                    // Moving point 1
                    ['lat' => 14.10, 'lng' => 121.00, 'gps_time' => '2026-10-23 08:00:00', 'speed' => 50.0, 'direction' => 90, 'acc_status' => 'ON', 'is_ignition_on' => true, 'state' => 'moving', 'cumulative_distance_km' => 0.0],
                    // Moving point 2
                    ['lat' => 14.15, 'lng' => 121.05, 'gps_time' => '2026-10-23 08:15:00', 'speed' => 60.0, 'direction' => 95, 'acc_status' => 'ON', 'is_ignition_on' => true, 'state' => 'moving', 'cumulative_distance_km' => 8.2],
                    // Idling Stop start
                    ['lat' => 14.20, 'lng' => 121.10, 'gps_time' => '2026-10-23 08:30:00', 'speed' => 0.0, 'direction' => 0, 'acc_status' => 'ON', 'is_ignition_on' => true, 'state' => 'idling', 'cumulative_distance_km' => 15.5],
                    // Idling Stop end
                    ['lat' => 14.20, 'lng' => 121.10, 'gps_time' => '2026-10-23 08:45:00', 'speed' => 0.0, 'direction' => 0, 'acc_status' => 'ON', 'is_ignition_on' => true, 'state' => 'idling', 'cumulative_distance_km' => 15.5],
                ],
            ]);
        $this->app->instance(TrackSolidApiService::class, $mockApiService);

        $test = Livewire::actingAs($this->user)
            ->test(TripHistoryReplay::class, ['vehicleId' => $vehicle->id])
            ->assertSet('selectedTarget', "v_{$vehicle->id}")
            ->assertDispatched('track-data-loaded')
            ->assertSee('15.5') // Total distance
            ->assertSee('GPS Route Distance')
            ->assertSee('65')   // Max speed
            ->assertSee('VH-REPLAY-01');

        $this->assertCount(1, $test->get('stops'));
        $this->assertEquals('idling', $test->get('stops')[0]['type']);
        $this->assertEquals(900, $test->get('stops')[0]['duration_seconds']);
    }

    public function test_offline_gap_does_not_count_as_idling_or_parked_time(): void
    {
        $vehicle = Vehicle::factory()->create();
        Device::factory()->create(['vehicle_id' => $vehicle->id, 'imei' => '865000000000889']);
        $mockApiService = Mockery::mock(TrackSolidApiService::class);
        $mockApiService->shouldReceive('getHistoricalTrack')->once()->andReturn([
            'total_points' => 4,
            'total_distance_km' => 0.0,
            'max_speed_kmh' => 0.0,
            'avg_speed_kmh' => 0.0,
            'start_time' => '2026-10-23 08:00:00',
            'end_time' => '2026-10-23 12:05:00',
            'points' => [
                ['lat' => 14.1, 'lng' => 121.0, 'gps_time' => '2026-10-23 08:00:00', 'speed' => 0, 'state' => 'idling'],
                ['lat' => 14.1, 'lng' => 121.0, 'gps_time' => '2026-10-23 08:05:00', 'speed' => 0, 'state' => 'idling'],
                ['lat' => 14.1, 'lng' => 121.0, 'gps_time' => '2026-10-23 12:00:00', 'speed' => 0, 'state' => 'parked'],
                ['lat' => 14.1, 'lng' => 121.0, 'gps_time' => '2026-10-23 12:05:00', 'speed' => 0, 'state' => 'parked'],
            ],
        ]);
        $this->app->instance(TrackSolidApiService::class, $mockApiService);

        $component = Livewire::actingAs($this->user)->test(TripHistoryReplay::class, ['vehicleId' => $vehicle->id]);

        $this->assertSame(300, $component->get('trackData')['idling_duration_seconds']);
        $this->assertSame(300, $component->get('trackData')['parking_duration_seconds']);
        $this->assertSame(['idling', 'parked'], array_column($component->get('stops'), 'type'));
        $component->assertSee('Idling Stop')->assertSee('Parking Stop');
    }

    public function test_planned_itinerary_comparison(): void
    {
        $vehicle = Vehicle::factory()->create(['equipment_code' => 'VH-ITIN-01']);
        $device = Device::factory()->create(['vehicle_id' => $vehicle->id, 'imei' => '865000000000777']);

        $today = Carbon::now()->format('Y-m-d');

        // Create planned itinerary with planned 50.0 km
        AdvancedItinerary::factory()->create([
            'vehicle_id' => $vehicle->id,
            'itinerary_date' => $today,
            'title' => 'Deliveries Route A',
            'destination' => 'Laguna Distribution Center',
            'driver_name' => 'Juan Dela Cruz',
            'total_distance' => 50.0,
            'status' => AdvancedItinerary::STATUS_FINALIZED,
        ]);

        $mockApiService = Mockery::mock(TrackSolidApiService::class);
        $mockApiService->shouldReceive('getHistoricalTrack')
            ->andReturn([
                'total_points' => 2,
                'total_distance_km' => 55.4,
                'max_speed_kmh' => 70.0,
                'avg_speed_kmh' => 45.0,
                'start_time' => "{$today} 08:00:00",
                'end_time' => "{$today} 10:00:00",
                'points' => [
                    ['lat' => 14.10, 'lng' => 121.00, 'gps_time' => "{$today} 08:00:00", 'speed' => 40.0, 'direction' => 0, 'acc_status' => 'ON', 'is_ignition_on' => true, 'state' => 'moving', 'cumulative_distance_km' => 0.0],
                    ['lat' => 14.30, 'lng' => 121.20, 'gps_time' => "{$today} 10:00:00", 'speed' => 0.0, 'direction' => 0, 'acc_status' => 'OFF', 'is_ignition_on' => false, 'state' => 'parked', 'cumulative_distance_km' => 55.4],
                ],
            ]);
        $this->app->instance(TrackSolidApiService::class, $mockApiService);

        Livewire::actingAs($this->user)
            ->test(TripHistoryReplay::class, ['vehicleId' => $vehicle->id])
            ->assertSee('Scheduled Itinerary Linked')
            ->assertSee('Deliveries Route A')
            ->assertSee('Laguna Distribution Center')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('50.0 km')
            ->assertSee('+5.4 km');
    }

    public function test_date_validation_rejects_inverted_dates(): void
    {
        $vehicle = Vehicle::factory()->create();
        Device::factory()->create(['vehicle_id' => $vehicle->id, 'imei' => '865000000000111']);

        Livewire::actingAs($this->user)
            ->test(TripHistoryReplay::class, ['vehicleId' => $vehicle->id])
            ->set('startDate', '2026-10-25')
            ->set('endDate', '2026-10-20')
            ->call('loadTrack')
            ->assertSee('End date & time cannot be earlier than start date & time.');
    }

    public function test_date_validation_rejects_ranges_over_tracksolid_seven_day_limit(): void
    {
        $vehicle = Vehicle::factory()->create();
        Device::factory()->create(['vehicle_id' => $vehicle->id, 'imei' => '865000000000112']);

        Livewire::actingAs($this->user)
            ->test(TripHistoryReplay::class, ['vehicleId' => $vehicle->id])
            ->set('startDate', Carbon::now('Asia/Manila')->subDays(8)->format('Y-m-d'))
            ->set('endDate', Carbon::now('Asia/Manila')->format('Y-m-d'))
            ->call('loadTrack')
            ->assertSee('TrackSolid history is limited to seven days per query');
    }

    public function test_date_validation_rejects_future_trip_time(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 04:00:00', 'Asia/Manila'));
        $vehicle = Vehicle::factory()->create();
        Device::factory()->create(['vehicle_id' => $vehicle->id, 'imei' => '865000000000113']);

        Livewire::actingAs($this->user)
            ->test(TripHistoryReplay::class, ['vehicleId' => $vehicle->id])
            ->set('endTime', '04:02')
            ->call('loadTrack')
            ->assertSee('Trip history cannot be requested for a future time.');
    }
}

// NOTE: Regression tests added below to prevent key-format bugs in the points endpoint.
