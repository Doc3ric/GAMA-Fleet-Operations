<?php

namespace Database\Factories;

use App\Models\FleetDispatch;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FleetDispatch>
 */
class FleetDispatchFactory extends Factory
{
    protected $model = FleetDispatch::class;

    public function definition(): array
    {
        $plannedTime = Carbon::today()->setHour(8)->setMinute(0)->setSecond(0);

        return [
            'trip_number' => 'TRP-'.fake()->unique()->numerify('2026####'),
            'advanced_itinerary_id' => null,
            'vehicle_id' => Vehicle::factory(),
            'driver_name' => fake()->name(),
            'trip_date' => Carbon::today(),
            'starting_point' => 'Main Yard, Manila',
            'destination' => fake()->city(),
            'planned_route' => 'Direct via Expressway',
            'planned_distance_km' => 120.0,
            'planned_departure_time' => $plannedTime,
            'actual_departure_time' => $plannedTime->copy()->addMinutes(5),
            'departure_source' => FleetDispatch::SOURCE_AUTHORIZED_DISPATCH,
            'dispatch_status' => FleetDispatch::STATUS_ON_TIME,
            'delay_minutes' => 5,
            'grace_period_minutes' => 15,
            'notes' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function onTime(): static
    {
        return $this->state(fn (array $attrs) => [
            'dispatch_status' => FleetDispatch::STATUS_ON_TIME,
            'actual_departure_time' => Carbon::parse($attrs['planned_departure_time'])->addMinutes(5),
            'delay_minutes' => 5,
        ]);
    }

    public function delayed(int $delayMinutes = 45): static
    {
        return $this->state(fn (array $attrs) => [
            'dispatch_status' => FleetDispatch::STATUS_DELAYED,
            'actual_departure_time' => Carbon::parse($attrs['planned_departure_time'])->addMinutes($delayMinutes),
            'delay_minutes' => $delayMinutes,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attrs) => [
            'dispatch_status' => FleetDispatch::STATUS_PENDING,
            'planned_departure_time' => Carbon::now()->addHours(2),
            'actual_departure_time' => null,
            'delay_minutes' => 0,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'dispatch_status' => FleetDispatch::STATUS_CANCELLED,
            'actual_departure_time' => null,
        ]);
    }
}
