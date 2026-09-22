<?php

namespace Database\Factories;

use App\Models\FuelConsumptionTest;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelConsumptionTest>
 */
class FuelConsumptionTestFactory extends Factory
{
    protected $model = FuelConsumptionTest::class;

    public function definition(): array
    {
        $startOdo = fake()->numberBetween(10000, 50000);
        $distance = fake()->randomFloat(2, 15, 200);
        $endOdo = $startOdo + $distance;
        $fuel = fake()->randomFloat(3, 2, 30);
        $kmL = round($distance / $fuel, 2);

        return [
            'test_date' => fake()->date(),
            'vehicle_id' => Vehicle::factory(),
            'driver_id' => User::factory(),
            'driver_name' => fake()->name(),
            'start_odometer' => $startOdo,
            'end_odometer' => $endOdo,
            'distance_travelled' => $distance,
            'fuel_consumed_liters' => $fuel,
            'average_fuel_consumption' => $kmL,
            'test_route' => fake()->streetName().' to '.fake()->city(),
            'remarks' => fake()->optional()->sentence(),
            'attested_by' => fake()->name(),
            'requested_by' => fake()->name(),
            'start_odometer_image' => null,
            'end_odometer_image' => null,
            'fuel_receipt_image' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Set exact calibration test case values.
     */
    public function calibrationCase(): static
    {
        return $this->state([
            'start_odometer' => 38091.00,
            'end_odometer' => 38109.00,
            'distance_travelled' => 18.00,
            'fuel_consumed_liters' => 2.377,
            'average_fuel_consumption' => 7.57,
            'test_route' => 'Facility to Highway Checkpoint',
            'remarks' => 'New acquisition baseline test run',
            'attested_by' => 'Engr. J. Dela Cruz',
            'requested_by' => 'Operations Head',
        ]);
    }
}
