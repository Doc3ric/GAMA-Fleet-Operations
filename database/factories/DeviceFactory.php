<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'device_name' => fake()->bothify('???-####'),
            'imei' => fake()->unique()->numerify('8651350#########'),
            'model' => fake()->randomElement(['X3', 'VG01U', 'GT06N']),
            'activated_date' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'sales_time' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'sim' => fake()->numerify('9#########'),
            'expiration_date' => fake()->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            'raw_expiration' => null,
            'group_name' => fake()->randomElement(['Default Group', 'Cebu', 'LIVE OPERATION UNIT', 'Chick Van', 'Egg Van']),
            'iccid' => fake()->numerify('89630324227#########'),
            'imsi' => fake()->numerify('5150392325#####'),
            'mileage' => fake()->randomFloat(2, 500, 80000),
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * State for a device expiring within 30 days.
     */
    public function expiringSoon(int $days = 20): static
    {
        return $this->state(fn () => [
            'expiration_date' => now()->addDays($days)->format('Y-m-d'),
            'raw_expiration' => now()->addDays($days)->format('Y-m-d')."(Expires in {$days} days)",
        ]);
    }

    /**
     * State for an already expired device.
     */
    public function expired(int $daysAgo = 5): static
    {
        return $this->state(fn () => [
            'expiration_date' => now()->subDays($daysAgo)->format('Y-m-d'),
            'raw_expiration' => 'Expired',
        ]);
    }
}
