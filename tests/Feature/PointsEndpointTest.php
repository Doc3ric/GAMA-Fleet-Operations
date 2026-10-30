<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\TripHistoryReplay;
use App\Models\Device;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TrackSolidApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * Regression tests for the /trip-history/points/{key} endpoint.
 *
 * Ensures the cache key is always a raw 32-char MD5 hash — never the
 * "trip_points_" prefixed form — so the route lookup never double-prefixes.
 */
class PointsEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->user = User::factory()->create();
    }

    public function test_points_endpoint_returns_cached_points_for_valid_hash(): void
    {
        $hash = md5('test_endpoint_valid_hash');
        $points = [
            ['lat' => 14.10, 'lng' => 121.05, 'gps_time' => '2026-10-23 08:00:00', 'speed' => 50.5],
        ];
        Cache::put("trip_points_{$hash}", $points, 120);

        $response = $this->actingAs($this->user)->getJson("/trip-history/points/{$hash}");

        $response->assertStatus(200);
        $response->assertJsonPath('points.0.lat', 14.10);
        $this->assertEquals(121.05, $response->json('points.0.lng'));
        $this->assertEquals(50.5, $response->json('points.0.speed'));
    }

    public function test_points_endpoint_rejects_prefixed_key_format(): void
    {
        // Sending the full "trip_points_abc..." string as the URL segment must return 400.
        // This prevents the double-prefix bug where Cache::get("trip_points_trip_points_...") fails.
        $fullKey = 'trip_points_'.md5('some_data');

        $response = $this->actingAs($this->user)->get("/trip-history/points/{$fullKey}");

        $response->assertStatus(400);
    }

    public function test_points_endpoint_returns_empty_array_for_expired_cache(): void
    {
        $hash = md5('nonexistent_cache_key_xyz');

        $response = $this->actingAs($this->user)->getJson("/trip-history/points/{$hash}");

        $response->assertStatus(200);
        $response->assertJson(['points' => []]);
    }

    public function test_points_endpoint_requires_authentication(): void
    {
        $hash = md5('some_auth_test_key');

        $response = $this->getJson("/trip-history/points/{$hash}");

        $response->assertStatus(401);
    }

    public function test_livewire_component_stores_raw_hash_as_points_cache_key(): void
    {
        $vehicle = Vehicle::factory()->create(['equipment_code' => 'VH-HASHKEY-01']);
        Device::factory()->create(['vehicle_id' => $vehicle->id, 'imei' => '865000000001234']);

        $mockApiService = Mockery::mock(TrackSolidApiService::class);
        $mockApiService->shouldReceive('getHistoricalTrack')
            ->once()
            ->andReturn([
                'total_points' => 2,
                'total_distance_km' => 5.0,
                'max_speed_kmh' => 40.0,
                'avg_speed_kmh' => 30.0,
                'start_time' => '2026-10-23 08:00:00',
                'end_time' => '2026-10-23 08:10:00',
                'points' => [
                    ['lat' => 14.10, 'lng' => 121.00, 'gps_time' => '2026-10-23 08:00:00', 'speed' => 30.0, 'direction' => 0, 'acc_status' => 'ON', 'is_ignition_on' => true, 'state' => 'moving', 'cumulative_distance_km' => 0.0],
                    ['lat' => 14.12, 'lng' => 121.02, 'gps_time' => '2026-10-23 08:10:00', 'speed' => 0.0, 'direction' => 0, 'acc_status' => 'OFF', 'is_ignition_on' => false, 'state' => 'parked', 'cumulative_distance_km' => 5.0],
                ],
            ]);
        $this->app->instance(TrackSolidApiService::class, $mockApiService);

        $component = Livewire::actingAs($this->user)
            ->test(TripHistoryReplay::class, ['vehicleId' => $vehicle->id]);

        $hash = $component->get('pointsCacheKey');

        // Must be a raw 32-char MD5 hex hash with no prefix
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{32}$/',
            $hash,
            'pointsCacheKey must be a raw MD5 hash, not prefixed with "trip_points_"'
        );

        // Points must be stored under the correctly-prefixed cache key
        $cached = Cache::get("trip_points_{$hash}");
        $this->assertNotNull($cached, 'Points must exist in cache under "trip_points_{hash}"');
        $this->assertCount(2, $cached);

        // The route must be able to serve them correctly
        $response = $this->actingAs($this->user)->getJson("/trip-history/points/{$hash}");
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('points'));
    }
}
