<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = VehicleType::inRandomOrder()->first();
        $prefix = $type?->code ?? 'VH';
        $num = str_pad($this->faker->numberBetween(1, 20), 2, '0', STR_PAD_LEFT);

        return [
            'equipment_code' => "{$prefix} {$num}",
            'vehicle_type_id' => $type?->id,
            'model' => $this->faker->randomElement([
                'CAT 320', 'HINO 700', 'KOMATSU PC200', 'VOLVO FH16', 'ISUZU GIGA', 'MITSUBISHI FUSO',
            ]),
            'plate_number' => strtoupper($this->faker->bothify('???-####')),
            'date_acquired' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'fuel_min' => $this->faker->randomFloat(0, 10, 20),
            'fuel_max' => $this->faker->randomFloat(0, 20, 35),
            'fuel_unit' => 'LIT/HR',
            'status_value' => $this->faker->randomFloat(1, 0.5, 1.0),
            'status_label' => $this->faker->randomElement(['RUNNING', 'STANDBY', 'BREAKDOWN', 'FOR REPAIR', 'DEPLOYED']),
            'location' => $this->faker->randomElement(['Project Site A', 'Project Site B', 'Main Yard', 'Depot']),
            'project_code' => 'PRJ-'.$this->faker->numerify('2026-###'),
            'operator_driver' => $this->faker->name(),
            'helper' => $this->faker->optional()->name(),
            'gps_status' => $this->faker->randomElement(Vehicle::GPS_STATUSES),
            'image' => null,
            'notes' => $this->faker->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function withGps(): static
    {
        return $this->state(['gps_status' => 'YES']);
    }

    public function withoutGps(): static
    {
        return $this->state(['gps_status' => 'NO']);
    }
}
