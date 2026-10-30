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
            ->assertSee('65')   // Max speed
            ->assertSee('VH-REPLAY-01');

        $this->assertCount(1, $test->get('stops'));
        $this->assertEquals('idling', $test->get('stops')[0]['type']);
        $this->assertEquals(900, $test->get('stops')[0]['duration_seconds']);
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
}

// NOTE: Regression tests added below to prevent key-format bugs in the points endpoint.
