<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocationSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_operator_can_search_places(): void
    {
        $operator = User::factory()->operator()->create();

        $response = $this->actingAs($operator)
            ->getJson(route('locations.searchPlaces', ['q' => '14.8523, 120.8164']));

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'query',
                'results',
            ]);
    }

    public function test_search_returns_an_address_suggestion_with_selected_coordinates(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'geocode.arcgis.com/*' => Http::response(['candidates' => []]),
            'photon.komoot.io/api/*' => Http::response([
                'features' => [[
                    'geometry' => ['coordinates' => [124.65123, 8.48123]],
                    'properties' => [
                        'name' => 'Gaisano City',
                        'city' => 'Cagayan de Oro',
                        'state' => 'Northern Mindanao',
                        'country' => 'Philippines',
                    ],
                ]],
            ]),
        ]);

        $operator = User::factory()->operator()->create();

        $response = $this->actingAs($operator)->getJson(route('locations.searchPlaces', [
            'q' => 'GAMA test dispatch yard 8402',
        ]));

        $response->assertOk()
            ->assertJsonPath('results.0.title', 'Gaisano City')
            ->assertJsonPath('results.0.latitude', 8.48123)
            ->assertJsonPath('results.0.longitude', 124.65123);
    }

    public function test_unauthenticated_user_cannot_search_places(): void
    {
        $response = $this->getJson(route('locations.searchPlaces', ['q' => 'Bulacan']));

        $response->assertUnauthorized();
    }

    public function test_authenticated_operator_can_reverse_geocode_a_map_pin(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'photon.komoot.io/reverse/*' => Http::response([
                'features' => [[
                    'geometry' => ['coordinates' => [124.65123, 8.48123]],
                    'properties' => ['name' => 'Gaisano City', 'city' => 'Cagayan de Oro'],
                ]],
            ]),
        ]);

        $operator = User::factory()->operator()->create();

        $response = $this->actingAs($operator)->postJson(route('locations.reverseGeocode'), [
            'latitude' => 8.48123,
            'longitude' => 124.65123,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.title', 'Gaisano City')
            ->assertJsonPath('result.address', 'Cagayan de Oro')
            ->assertJsonPath('result.latitude', 8.48123);
    }

    public function test_reverse_geocode_rejects_coordinates_outside_earth_bounds(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson(route('locations.reverseGeocode'), [
            'latitude' => 91,
            'longitude' => 124.6,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);
    }

    public function test_resolve_coordinates_endpoint_parses_lat_lng(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('locations.resolveCoordinates'), [
                'input' => '14.852345, 120.816412',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'latitude' => 14.852345,
                'longitude' => 120.816412,
            ]);
    }

    public function test_resolve_coordinates_endpoint_rejects_invalid_text(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('locations.resolveCoordinates'), [
                'input' => 'Not a valid coordinate or URL',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }
}
