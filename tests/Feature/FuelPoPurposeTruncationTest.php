<?php

namespace Tests\Feature;

use App\Models\AdvancedItinerary;
use App\Models\AdvancedItineraryLeg;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelPoPurposeTruncationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->vehicle = Vehicle::factory()->create([
            'average_fuel_consumption' => 2.0,
        ]);
    }

    public function test_store_silently_truncates_destination_purpose_exceeding_255_characters(): void
    {
        $overLengthPurpose = str_repeat('A', 300);

        $response = $this->actingAs($this->admin)->post(route('fuel-po.store'), [
            'itinerary_date' => now()->toDateString(),
            'vehicle_id' => $this->vehicle->id,
            'destinations' => [
                ['name' => 'Depot', 'distance' => 10.0, 'purpose' => 'Delivery'],
                ['name' => 'Warehouse', 'distance' => 20.0, 'purpose' => $overLengthPurpose],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('fuel-po.index'));

        $leg = AdvancedItineraryLeg::where('sort_order', 1)->latest('id')->first();
        $this->assertNotNull($leg);
        $this->assertLessThanOrEqual(255, mb_strlen((string) $leg->purpose));
    }

    public function test_update_silently_truncates_destination_purpose_exceeding_255_characters(): void
    {
        // Create an itinerary first
        $storeResponse = $this->actingAs($this->admin)->post(route('fuel-po.store'), [
            'itinerary_date' => now()->toDateString(),
            'vehicle_id' => $this->vehicle->id,
            'status' => 'FINALIZED',
            'destinations' => [
                ['name' => 'Depot', 'distance' => 10.0, 'purpose' => 'Initial'],
            ],
        ]);
        $storeResponse->assertRedirect(route('fuel-po.index'));

        $itinerary = AdvancedItinerary::latest('id')->first();
        $this->assertNotNull($itinerary);

        $overLengthPurpose = str_repeat('B', 300);

        $updateResponse = $this->actingAs($this->admin)->put(route('fuel-po.update', $itinerary), [
            'itinerary_date' => now()->toDateString(),
            'vehicle_id' => $this->vehicle->id,
            'status' => 'FINALIZED',
            'destinations' => [
                ['name' => 'Site A', 'distance' => 15.0, 'purpose' => 'Hauling'],
                ['name' => 'Site B', 'distance' => 25.0, 'purpose' => $overLengthPurpose],
            ],
        ]);

        $updateResponse->assertSessionHasNoErrors();
        $updateResponse->assertRedirect(route('fuel-po.show', $itinerary));

        $legs = AdvancedItineraryLeg::where('advanced_itinerary_id', $itinerary->id)->get();
        $this->assertCount(2, $legs);
        foreach ($legs as $leg) {
            $this->assertLessThanOrEqual(255, mb_strlen((string) $leg->purpose));
        }
    }
}
