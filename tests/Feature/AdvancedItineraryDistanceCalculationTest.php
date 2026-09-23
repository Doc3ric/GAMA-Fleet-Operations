<?php

namespace Tests\Feature;

use App\Models\AdvancedItinerary;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdvancedItineraryDistanceCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected User $driver;

    protected Location $origin;

    protected Location $start;

    protected Location $destination;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['email' => 'admin@gama.com']);
        $this->operator = User::factory()->operator()->create(['email' => 'operator@gama.com']);
        $this->driver = User::factory()->driver()->create(['email' => 'driver@gama.com']);

        $this->origin = Location::factory()->create([
            'code' => 'GMO',
            'official_name' => 'Gama Main Office',
            'latitude' => 8.1500000,
            'longitude' => 124.8500000,
        ]);

        $this->start = Location::factory()->create([
            'code' => 'ANA',
            'official_name' => 'Anakciamo Depot',
            'latitude' => 8.1000000,
            'longitude' => 124.9000000,
        ]);

        $this->destination = Location::factory()->create([
            'code' => 'KIB',
            'official_name' => 'Kibawe Facility',
            'latitude' => 7.5600000,
            'longitude' => 124.9900000,
        ]);
    }

    public function test_guests_cannot_calculate_distance(): void
    {
        $response = $this->postJson(route('locations.calculateDistance'), [
            'origin_id' => $this->origin->id,
            'waypoint_id' => $this->start->id,
            'destination_id' => $this->destination->id,
        ]);

        $response->assertUnauthorized();
    }

    public function test_driver_cannot_calculate_distance(): void
    {
        $response = $this->actingAs($this->driver)->postJson(route('locations.calculateDistance'), [
            'origin_id' => $this->origin->id,
            'waypoint_id' => $this->start->id,
            'destination_id' => $this->destination->id,
        ]);

        $response->assertForbidden();
    }

    public function test_distance_calculation_endpoint_returns_road_distance_for_valid_locations(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::sequence()
                ->push(['code' => 'Ok', 'routes' => [['distance' => 15400.0]]], 200) // 15.4 km
                ->push(['code' => 'Ok', 'routes' => [['distance' => 8700.0]]], 200),  // 8.7 km
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('locations.calculateDistance'), [
            'origin_id' => $this->origin->id,
            'waypoint_id' => $this->start->id,
            'destination_id' => $this->destination->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'origin_to_start' => 15.4,
                'start_to_dest' => 8.7,
                'total' => 24.1,
                'source' => 'osrm',
                'origin' => ['name' => 'Gama Main Office'],
                'waypoint' => ['name' => 'Anakciamo Depot'],
                'destination' => ['name' => 'Kibawe Facility'],
            ]);
    }

    public function test_distance_calculation_fails_gracefully_when_location_missing_coordinates(): void
    {
        $incompleteLocation = Location::factory()->create([
            'official_name' => 'Unmapped Field Site',
            'latitude' => 0.0,
            'longitude' => 0.0,
        ]);

        $response = $this->actingAs($this->operator)->postJson(route('locations.calculateDistance'), [
            'origin_id' => $this->origin->id,
            'waypoint_id' => $incompleteLocation->id,
            'destination_id' => $this->destination->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString('does not have coordinates stored', $response->json('message'));
    }

    public function test_distance_calculation_handles_osrm_service_failure(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response([], 500),
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('locations.calculateDistance'), [
            'origin_id' => $this->origin->id,
            'waypoint_id' => $this->start->id,
            'destination_id' => $this->destination->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'origin_to_start' => null,
                'start_to_dest' => null,
                'total' => null,
                'source' => 'failed',
            ]);
    }

    public function test_itinerary_store_auto_calculates_distances_when_omitted_in_request(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::sequence()
                ->push(['code' => 'Ok', 'routes' => [['distance' => 15400.0]]], 200)
                ->push(['code' => 'Ok', 'routes' => [['distance' => 8700.0]]], 200),
        ]);

        $payload = [
            'itinerary_date' => '2026-09-23',
            'status' => AdvancedItinerary::STATUS_DRAFT,
            'legs' => [
                [
                    'sort_order' => 0,
                    'origin_location_id' => $this->origin->id,
                    'starting_point_location_id' => $this->start->id,
                    'destination_location_id' => $this->destination->id,
                    // Distances intentionally omitted/empty
                    'distance_origin_to_start' => null,
                    'distance_start_to_dest' => null,
                    'total_distance' => null,
                    'purpose' => 'Automated Calculation Test',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post(route('advanced-itineraries.store'), $payload);

        $itinerary = AdvancedItinerary::latest('id')->first();
        $this->assertNotNull($itinerary);
        $response->assertRedirect(route('advanced-itineraries.show', $itinerary));

        $leg = $itinerary->legs->first();
        $this->assertNotNull($leg);
        $this->assertEquals(15.4, $leg->distance_origin_to_start);
        $this->assertEquals(8.7, $leg->distance_start_to_dest);
        $this->assertEquals(24.1, $leg->total_distance);
        $this->assertEquals('osrm', $leg->routing_source);
        $this->assertEquals(24.1, $itinerary->total_distance);
    }
}
