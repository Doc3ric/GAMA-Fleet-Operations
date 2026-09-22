<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\LocationAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocationAlias>
 */
class LocationAliasFactory extends Factory
{
    protected $model = LocationAlias::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'alias' => strtoupper($this->faker->words(2, true)),
        ];
    }
}
