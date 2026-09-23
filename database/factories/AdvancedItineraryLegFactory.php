<?php

namespace Database\Factories;

use App\Models\AdvancedItinerary;
use App\Models\AdvancedItineraryLeg;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvancedItineraryLeg>
 */
class AdvancedItineraryLegFactory extends Factory
{
    protected $model = AdvancedItineraryLeg::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $d1 = fake()->randomFloat(2, 5, 50);
        $d2 = fake()->randomFloat(2, 5, 50);

        return [
            'advanced_itinerary_id' => AdvancedItinerary::factory(),
            'sort_order' => 0,
            'origin_location_id' => Location::factory(),
            'starting_point_location_id' => Location::factory(),
            'destination_location_id' => Location::factory(),
            'distance_origin_to_start' => $d1,
            'distance_start_to_dest' => $d2,
            'total_distance' => round($d1 + $d2, 2),
            'duration_origin_to_start_minutes' => null,
            'duration_start_to_dest_minutes' => null,
            'total_duration_minutes' => null,
            'routing_source' => 'osrm',
            'purpose' => fake()->words(3, true),
        ];
    }
}
