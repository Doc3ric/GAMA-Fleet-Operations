<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('???')).$this->faker->randomNumber(2, true),
            'official_name' => 'GAMA '.strtoupper($this->faker->words(2, true)),
            'type' => $this->faker->randomElement(Location::TYPES),
            'latitude' => $this->faker->latitude(14.0, 15.5),
            'longitude' => $this->faker->longitude(120.5, 121.5),
            'address' => $this->faker->streetAddress().', '.$this->faker->city(),
            'barangay' => 'Brgy. '.$this->faker->streetName(),
            'municipality' => $this->faker->city(),
            'province' => 'Bulacan',
            'status' => Location::STATUS_ACTIVE,
            'image_path' => null,
            'notes' => $this->faker->sentence(),
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => Location::STATUS_ACTIVE,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => Location::STATUS_INACTIVE,
        ]);
    }
}
