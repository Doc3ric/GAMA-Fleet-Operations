<?php

namespace Database\Factories;

use App\Models\DriverTrip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverTrip>
 */
class DriverTripFactory extends Factory
{
    protected $model = DriverTrip::class;

    public function definition(): array
    {
        return [
            'client_id' => fake()->uuid(),
            'driver_id' => User::factory()->driver(),
            'vehicle_id' => Vehicle::factory(),
            'driver_vehicle_assignment_id' => null,
            'trip_date' => now()->toDateString(),
            'time_in' => '08:00:00',
            'time_out' => null,
            'origin_latitude' => 14.5995123,
            'origin_longitude' => 120.9842221,
            'origin_accuracy' => 5.0,
            'origin_address' => 'Manila Port Area',
            'destination_latitude' => null,
            'destination_longitude' => null,
            'destination_accuracy' => null,
            'destination_address' => null,
            'status' => DriverTrip::STATUS_IN_PROGRESS,
            'remarks' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'destination_latitude' => 14.5547291,
            'destination_longitude' => 121.0244452,
            'destination_accuracy' => 4.5,
            'destination_address' => 'Makati Commercial Center',
            'time_out' => '09:30:00',
            'status' => DriverTrip::STATUS_COMPLETED,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => DriverTrip::STATUS_CANCELLED,
            'remarks' => 'Trip cancelled by dispatcher',
        ]);
    }
}
