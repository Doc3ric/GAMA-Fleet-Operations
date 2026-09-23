<?php

namespace Tests\Unit;

use App\Models\Location;
use App\Services\RoutingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoutingServiceTest extends TestCase
{
    protected RoutingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RoutingService;
    }

    public function test_returns_km_distance_from_osrm_response(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    ['distance' => 15400.0, 'duration' => 900.0],
                ],
            ], 200),
        ]);

        $km = $this->service->getDistanceKm(8.15, 124.85, 8.10, 124.90);

        $this->assertNotNull($km);
        $this->assertEquals(15.4, $km);
    }

    public function test_returns_null_when_osrm_request_fails(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response([], 500),
        ]);

        $km = $this->service->getDistanceKm(8.15, 124.85, 8.10, 124.90);

        $this->assertNull($km);
    }

    public function test_returns_null_when_osrm_code_is_not_ok(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'NoRoute',
                'message' => 'No route found',
            ], 200),
        ]);

        $km = $this->service->getDistanceKm(8.15, 124.85, 8.10, 124.90);

        $this->assertNull($km);
    }

    public function test_calculates_both_leg_distances_and_total(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::sequence()
                ->push(['code' => 'Ok', 'routes' => [['distance' => 15400.0]]], 200)
                ->push(['code' => 'Ok', 'routes' => [['distance' => 8700.0]]], 200),
        ]);

        $origin = new Location(['latitude' => 8.15, 'longitude' => 124.85, 'official_name' => 'Origin']);
        $start = new Location(['latitude' => 8.10, 'longitude' => 124.90, 'official_name' => 'Start']);
        $dest = new Location(['latitude' => 8.05, 'longitude' => 124.95, 'official_name' => 'Dest']);

        $result = $this->service->calculateLegDistances($origin, $start, $dest);

        $this->assertEquals(15.4, $result['origin_to_start']);
        $this->assertEquals(8.7, $result['start_to_dest']);
        $this->assertEquals(24.1, $result['total']);
        $this->assertEquals(RoutingService::SOURCE_OSRM, $result['source']);
    }

    public function test_source_is_failed_when_one_leg_returns_null(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::sequence()
                ->push(['code' => 'Ok', 'routes' => [['distance' => 15400.0]]], 200)
                ->push([], 500),
        ]);

        $origin = new Location(['latitude' => 8.15, 'longitude' => 124.85, 'official_name' => 'Origin']);
        $start = new Location(['latitude' => 8.10, 'longitude' => 124.90, 'official_name' => 'Start']);
        $dest = new Location(['latitude' => 8.05, 'longitude' => 124.95, 'official_name' => 'Dest']);

        $result = $this->service->calculateLegDistances($origin, $start, $dest);

        $this->assertNull($result['total']);
        $this->assertEquals(RoutingService::SOURCE_FAILED, $result['source']);
    }
}
