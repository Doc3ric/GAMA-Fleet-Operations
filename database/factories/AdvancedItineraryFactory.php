<?php

namespace Database\Factories;

use App\Models\AdvancedItinerary;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvancedItinerary>
 */
class AdvancedItineraryFactory extends Factory
{
    protected $model = AdvancedItinerary::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'itinerary_date' => fake()->date(),
            'title' => 'Itinerary '.fake()->words(3, true),
            'notes' => fake()->optional()->sentence(),
            'status' => AdvancedItinerary::STATUS_DRAFT,
            'total_duration_minutes' => null,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => AdvancedItinerary::STATUS_DRAFT,
        ]);
    }

    public function finalized(): static
    {
        return $this->state(fn () => [
            'status' => AdvancedItinerary::STATUS_FINALIZED,
        ]);
    }
}
