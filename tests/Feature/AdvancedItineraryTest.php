<?php

namespace Tests\Feature;

use App\Models\AdvancedItinerary;
use App\Models\AdvancedItineraryLeg;
use App\Models\Location;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdvancedItineraryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Admin Specialist', 'email' => 'admin@gama.com']);
        $this->operator = User::factory()->operator()->create(['name' => 'Operator User', 'email' => 'operator@gama.com']);
        $this->driver = User::factory()->driver()->create(['name' => 'Driver Juan', 'email' => 'driver@gama.com']);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $itinerary = AdvancedItinerary::factory()->create();

        $this->get(route('advanced-itineraries.index'))->assertRedirect(route('login'));
        $this->get(route('advanced-itineraries.create'))->assertRedirect(route('login'));
        $this->get(route('advanced-itineraries.show', $itinerary))->assertRedirect(route('login'));
        $this->get(route('advanced-itineraries.edit', $itinerary))->assertRedirect(route('login'));
        $this->post(route('advanced-itineraries.store'), [])->assertRedirect(route('login'));
    }

    public function test_driver_cannot_access_advanced_itineraries(): void
    {
        $itinerary = AdvancedItinerary::factory()->create();

        $this->actingAs($this->driver)->get(route('advanced-itineraries.index'))->assertForbidden();
        $this->actingAs($this->driver)->get(route('advanced-itineraries.create'))->assertForbidden();
        $this->actingAs($this->driver)->get(route('advanced-itineraries.show', $itinerary))->assertForbidden();
        $this->actingAs($this->driver)->get(route('advanced-itineraries.edit', $itinerary))->assertForbidden();
        $this->actingAs($this->driver)->post(route('advanced-itineraries.store'), [])->assertForbidden();
        $this->actingAs($this->driver)->post(route('advanced-itineraries.recalculate', $itinerary))->assertForbidden();
        $this->actingAs($this->driver)->delete(route('advanced-itineraries.destroy', $itinerary))->assertForbidden();
    }

    public function test_admin_and_operator_can_view_index(): void
    {
        AdvancedItinerary::factory()->count(3)->create();

        $this->actingAs($this->admin)->get(route('advanced-itineraries.index'))
            ->assertOk()
            ->assertSee('Advanced Itineraries');

        $this->actingAs($this->operator)->get(route('advanced-itineraries.index'))
            ->assertOk()
            ->assertSee('Advanced Itineraries');
    }

    public function test_admin_can_create_itinerary_with_multiple_legs_and_reused_locations(): void
    {
        $vehicle = Vehicle::factory()->create();
        $locA = Location::factory()->create(['official_name' => 'Central Depot', 'latitude' => 14.5995, 'longitude' => 120.9842]);
        $locB = Location::factory()->create(['official_name' => 'North Station', 'latitude' => 14.6500, 'longitude' => 120.9900]);
        $locC = Location::factory()->create(['official_name' => 'South Depot', 'latitude' => 14.5000, 'longitude' => 121.0000]);

        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    ['distance' => 12500.0, 'duration' => 600.0],
                ],
            ], 200),
        ]);

        $payload = [
            'vehicle_id' => $vehicle->id,
            'itinerary_date' => '2026-09-25',
            'title' => 'Express Multi-drop Trip',
            'notes' => 'Important shipment deliveries',
            'status' => AdvancedItinerary::STATUS_DRAFT,
            'legs' => [
                [
                    'sort_order' => 0,
                    'origin_location_id' => $locA->id,
                    'starting_point_location_id' => $locA->id,
                    'destination_location_id' => $locB->id,
                    'distance_origin_to_start' => 0.00,
                    'distance_start_to_dest' => 15.50,
                    'total_distance' => 15.50,
                    'routing_source' => 'manual',
                    'purpose' => 'First Leg Deliveries',
                ],
                [
                    'sort_order' => 1,
                    // Reusing locB as origin and starting point
                    'origin_location_id' => $locB->id,
                    'starting_point_location_id' => $locB->id,
                    'destination_location_id' => $locC->id,
                    // Missing distances to trigger automatic calculation via RoutingService
                    'distance_origin_to_start' => '',
                    'distance_start_to_dest' => '',
                    'total_distance' => '',
                    'purpose' => 'Second Leg Pickup',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post(route('advanced-itineraries.store'), $payload);

        $itinerary = AdvancedItinerary::latest('id')->first();
        $this->assertNotNull($itinerary);
        $response->assertRedirect(route('advanced-itineraries.show', $itinerary));

        $this->assertEquals($vehicle->id, $itinerary->vehicle_id);
        $this->assertEquals('2026-09-25', $itinerary->itinerary_date->format('Y-m-d'));
        $this->assertEquals('Express Multi-drop Trip', $itinerary->title);
        $this->assertEquals($this->admin->id, $itinerary->created_by);
        $this->assertEquals(2, $itinerary->legs()->count());

        $leg1 = $itinerary->legs()->where('sort_order', 0)->first();
        $this->assertNotNull($leg1);
        $this->assertEquals($locA->id, $leg1->origin_location_id);
        $this->assertEquals($locB->id, $leg1->destination_location_id);
        $this->assertEquals(15.50, $leg1->total_distance);
        $this->assertTrue($leg1->isManual());

        $leg2 = $itinerary->legs()->where('sort_order', 1)->first();
        $this->assertNotNull($leg2);
        $this->assertEquals($locB->id, $leg2->origin_location_id);
        $this->assertEquals($locC->id, $leg2->destination_location_id);
        $this->assertEquals(12.50, $leg2->distance_origin_to_start);
        $this->assertEquals(12.50, $leg2->distance_start_to_dest);
        $this->assertEquals(25.00, $leg2->total_distance);
        $this->assertTrue($leg2->isAutomatic());
    }

    public function test_total_distance_is_calculated_correctly(): void
    {
        $itinerary = AdvancedItinerary::factory()->create();

        AdvancedItineraryLeg::factory()->create([
            'advanced_itinerary_id' => $itinerary->id,
            'sort_order' => 0,
            'distance_origin_to_start' => 5.25,
            'distance_start_to_dest' => 10.75,
            'total_distance' => 16.00,
        ]);

        AdvancedItineraryLeg::factory()->create([
            'advanced_itinerary_id' => $itinerary->id,
            'sort_order' => 1,
            'distance_origin_to_start' => 2.00,
            'distance_start_to_dest' => 8.00,
            'total_distance' => 10.00,
        ]);

        $this->assertEquals(26.00, $itinerary->fresh()->total_distance);
        $this->assertEquals(26.00, $itinerary->load('legs')->total_distance);
    }

    public function test_admin_can_update_itinerary(): void
    {
        $itinerary = AdvancedItinerary::factory()->draft()->create([
            'created_by' => $this->admin->id,
        ]);

        $locA = Location::factory()->create();
        $locB = Location::factory()->create();

        AdvancedItineraryLeg::factory()->create([
            'advanced_itinerary_id' => $itinerary->id,
            'origin_location_id' => $locA->id,
            'starting_point_location_id' => $locA->id,
            'destination_location_id' => $locB->id,
        ]);

        $locC = Location::factory()->create();

        $updateData = [
            'vehicle_id' => $itinerary->vehicle_id,
            'itinerary_date' => '2026-09-30',
            'title' => 'Updated Finalized Title',
            'notes' => 'Updated notes',
            'status' => AdvancedItinerary::STATUS_FINALIZED,
            'legs' => [
                [
                    'sort_order' => 0,
                    'origin_location_id' => $locB->id,
                    'starting_point_location_id' => $locB->id,
                    'destination_location_id' => $locC->id,
                    'distance_origin_to_start' => 5.0,
                    'distance_start_to_dest' => 10.0,
                    'total_distance' => 15.0,
                    'routing_source' => 'manual',
                    'purpose' => 'Updated Leg',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->put(route('advanced-itineraries.update', $itinerary), $updateData);

        $response->assertRedirect(route('advanced-itineraries.show', $itinerary));

        $itinerary->refresh();
        $this->assertEquals('Updated Finalized Title', $itinerary->title);
        $this->assertTrue($itinerary->isFinalized());
        $this->assertEquals($this->admin->id, $itinerary->updated_by);
        $this->assertEquals(1, $itinerary->legs()->count());
        $this->assertEquals($locC->id, $itinerary->legs()->first()->destination_location_id);
    }

    public function test_admin_can_recalculate_distances(): void
    {
        $locA = Location::factory()->create(['latitude' => 14.5995, 'longitude' => 120.9842]);
        $locB = Location::factory()->create(['latitude' => 14.6500, 'longitude' => 120.9900]);

        $itinerary = AdvancedItinerary::factory()->create();
        $leg = AdvancedItineraryLeg::factory()->create([
            'advanced_itinerary_id' => $itinerary->id,
            'origin_location_id' => $locA->id,
            'starting_point_location_id' => $locA->id,
            'destination_location_id' => $locB->id,
            'distance_origin_to_start' => 0.0,
            'distance_start_to_dest' => 0.0,
            'total_distance' => 0.0,
            'routing_source' => 'manual',
        ]);

        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    ['distance' => 20000.0, 'duration' => 1200.0],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->from(route('advanced-itineraries.show', $itinerary))
            ->post(route('advanced-itineraries.recalculate', $itinerary));

        $response->assertRedirect(route('advanced-itineraries.show', $itinerary));
        $response->assertSessionHas('success');

        $leg->refresh();
        $this->assertEquals(20.0, $leg->distance_origin_to_start);
        $this->assertEquals(20.0, $leg->distance_start_to_dest);
        $this->assertEquals(40.0, $leg->total_distance);
        $this->assertEquals('osrm', $leg->routing_source);
    }

    public function test_admin_can_export_pdf(): void
    {
        $itinerary = AdvancedItinerary::factory()->create([
            'itinerary_date' => '2026-09-23',
        ]);

        AdvancedItineraryLeg::factory()->count(2)->create([
            'advanced_itinerary_id' => $itinerary->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('advanced-itineraries.exportPdf', $itinerary));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type', ''));
    }

    public function test_admin_can_delete_draft_itinerary_and_operator_cannot_delete(): void
    {
        $itinerary = AdvancedItinerary::factory()->draft()->create();
        $leg = AdvancedItineraryLeg::factory()->create([
            'advanced_itinerary_id' => $itinerary->id,
        ]);

        // Operator cannot delete
        $this->actingAs($this->operator)
            ->delete(route('advanced-itineraries.destroy', $itinerary))
            ->assertForbidden();

        $this->assertDatabaseHas('advanced_itineraries', ['id' => $itinerary->id]);

        // Admin can delete
        $response = $this->actingAs($this->admin)->delete(route('advanced-itineraries.destroy', $itinerary));
        $response->assertRedirect(route('advanced-itineraries.index'));

        $this->assertDatabaseMissing('advanced_itineraries', ['id' => $itinerary->id]);
        $this->assertDatabaseMissing('advanced_itinerary_legs', ['id' => $leg->id]);
    }

    public function test_validation_fails_on_invalid_input(): void
    {
        $response = $this->actingAs($this->admin)->post(route('advanced-itineraries.store'), [
            'itinerary_date' => '', // missing
            'status' => 'INVALID_STATUS', // invalid
            'legs' => [], // empty array
        ]);

        $response->assertSessionHasErrors(['itinerary_date', 'status', 'legs']);
    }
}
