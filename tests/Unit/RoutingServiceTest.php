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
        $this->assertNull($result['duration_origin_to_start_minutes']);
        $this->assertNull($result['duration_start_to_dest_minutes']);
        $this->assertNull($result['total_duration_minutes']);
        $this->assertEquals(RoutingService::SOURCE_FAILED, $result['source']);
    }

    public function test_extracts_duration_in_minutes_from_osrm_response(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    ['distance' => 15400.0, 'duration' => 1500.0], // 1500s = 25m
                ],
            ], 200),
        ]);

        $segment = $this->service->getRouteSegment(8.15, 124.85, 8.10, 124.90);

        $this->assertNotNull($segment);
        $this->assertEquals(15.4, $segment['distance_km']);
        $this->assertEquals(25, $segment['duration_minutes']);
    }

    public function test_calculates_both_leg_durations_and_total_duration(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::sequence()
                ->push(['code' => 'Ok', 'routes' => [['distance' => 15400.0, 'duration' => 1800.0]]], 200) // 30m
                ->push(['code' => 'Ok', 'routes' => [['distance' => 8700.0, 'duration' => 1200.0]]], 200), // 20m
        ]);

        $origin = new Location(['latitude' => 8.15, 'longitude' => 124.85, 'official_name' => 'Origin']);
        $start = new Location(['latitude' => 8.10, 'longitude' => 124.90, 'official_name' => 'Start']);
        $dest = new Location(['latitude' => 8.05, 'longitude' => 124.95, 'official_name' => 'Dest']);

        $result = $this->service->calculateLegDistances($origin, $start, $dest);

        $this->assertEquals(15.4, $result['origin_to_start']);
        $this->assertEquals(8.7, $result['start_to_dest']);
        $this->assertEquals(24.1, $result['total']);
        $this->assertEquals(30, $result['duration_origin_to_start_minutes']);
        $this->assertEquals(20, $result['duration_start_to_dest_minutes']);
        $this->assertEquals(50, $result['total_duration_minutes']);
        $this->assertEquals(RoutingService::SOURCE_OSRM, $result['source']);
    }

    public function test_formats_duration_properly(): void
    {
        $this->assertEquals('45m', RoutingService::formatDuration(45));
        $this->assertEquals('1h 15m', RoutingService::formatDuration(75));
        $this->assertEquals('2h', RoutingService::formatDuration(120));
        $this->assertEquals('2h 45m', RoutingService::formatDuration(165));
        $this->assertEquals('—', RoutingService::formatDuration(null));
    }

    public function test_calculates_haversine_distance(): void
    {
        // Distance between Cagayan de Oro (8.4822, 124.6472) and Tagoloan (8.5414, 124.7547) is ~13.5 km
        $dist = RoutingService::calculateHaversineDistanceKm(8.4822, 124.6472, 8.5414, 124.7547);
        $this->assertGreaterThan(12.0, $dist);
        $this->assertLessThan(15.0, $dist);
    }

    public function test_calculates_multipoint_route_with_osrm_and_fallback(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    [
                        'distance' => 20000.0,
                        'duration' => 1200.0,
                        'geometry' => [
                            'coordinates' => [
                                [124.6472, 8.4822],
                                [124.7000, 8.5100],
                                [124.7547, 8.5414],
                            ],
                        ],
                        'legs' => [
                            ['distance' => 10000.0, 'duration' => 600.0],
                            ['distance' => 10000.0, 'duration' => 600.0],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $points = [
            ['name' => 'Origin Yard', 'lat' => 8.4822, 'lng' => 124.6472],
            ['name' => 'Depot 1', 'lat' => 8.5100, 'lng' => 124.7000],
            ['name' => 'Final Customer', 'lat' => 8.5414, 'lng' => 124.7547],
        ];

        $route = $this->service->calculateMultiPointRoute($points);

        $this->assertTrue($route['success']);
        $this->assertCount(2, $route['legs']);
        $this->assertEquals(20.0, $route['total_distance_km']); // 2 legs of 10.0km each
        $this->assertNotEmpty($route['geometry']);
    }

    public function test_uses_one_haversine_fallback_when_osrm_is_unavailable(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'router.project-osrm.org/route/v1/driving/*' => Http::response([], 503),
        ]);

        $route = $this->service->calculateMultiPointRoute([
            ['name' => 'Fallback Origin', 'lat' => 7.1111, 'lng' => 124.1111],
            ['name' => 'Fallback Destination', 'lat' => 7.2222, 'lng' => 124.2222],
        ]);

        $this->assertSame('haversine_estimate', $route['source']);
        $this->assertSame('haversine_estimate', $route['alternatives'][0]['source']);
        $this->assertSame(1, count($route['legs']));
        $this->assertSame('haversine_estimate', $route['legs'][0]['source']);
        Http::assertSentCount(1);
    }

    public function test_returns_osrm_route_alternatives_with_ordered_leg_details(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'router.project-osrm.org/route/v1/driving/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    [
                        'distance' => 24000,
                        'duration' => 1800,
                        'geometry' => ['coordinates' => [[124.1, 8.1], [124.15, 8.15], [124.2, 8.2]]],
                        'legs' => [
                            ['distance' => 10000, 'duration' => 600],
                            ['distance' => 14000, 'duration' => 1200],
                        ],
                    ],
                    [
                        'distance' => 27000,
                        'duration' => 2100,
                        'geometry' => ['coordinates' => [[124.1, 8.1], [124.12, 8.18], [124.2, 8.2]]],
                        'legs' => [
                            ['distance' => 12000, 'duration' => 900],
                            ['distance' => 15000, 'duration' => 1200],
                        ],
                    ],
                ],
            ]),
        ]);

        $route = $this->service->calculateMultiPointRoute([
            ['name' => 'Origin', 'lat' => 8.1, 'lng' => 124.1],
            ['name' => 'Warehouse', 'lat' => 8.15, 'lng' => 124.15],
            ['name' => 'Destination', 'lat' => 8.2, 'lng' => 124.2],
        ]);

        $this->assertTrue($route['success']);
        $this->assertSame(2, count($route['alternatives']));
        $this->assertSame(24.0, $route['total_distance_km']);
        $this->assertSame('Origin', $route['legs'][0]['from_name']);
        $this->assertSame('Warehouse', $route['legs'][0]['to_name']);
        $this->assertSame([124.1, 8.1], $route['geojson']['coordinates'][0]);
        $this->assertSame('osrm', $route['source']);
    }
}
