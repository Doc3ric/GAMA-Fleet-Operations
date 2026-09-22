<?php

namespace Database\Factories;

use App\Models\DriverVehicleAssignment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverVehicleAssignment>
 */
class DriverVehicleAssignmentFactory extends Factory
{
    protected $model = DriverVehicleAssignment::class;

    public function definition(): array
    {
        return [
            'driver_id' => User::factory()->driver(),
            'vehicle_id' => Vehicle::factory(),
            'assigned_from' => now()->toDateString(),
            'assigned_until' => null,
            'notes' => $this->faker->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'assigned_from' => now()->subDays(30)->toDateString(),
            'assigned_until' => now()->subDays(1)->toDateString(),
        ]);
    }

    public function future(): static
    {
        return $this->state([
            'assigned_from' => now()->addDays(2)->toDateString(),
            'assigned_until' => now()->addDays(10)->toDateString(),
        ]);
    }
}
