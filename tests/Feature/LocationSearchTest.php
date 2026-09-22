<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_unauthenticated_user_cannot_search_places(): void
    {
        $response = $this->getJson(route('locations.searchPlaces', ['q' => 'Bulacan']));

        $response->assertUnauthorized();
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
