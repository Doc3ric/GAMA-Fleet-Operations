<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkTask>
 */
class WorkTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'category' => fake()->optional()->randomElement([
                'GPS Monitoring', 'Vehicle Monitoring', 'Fuel Monitoring',
                'Driver Monitoring', 'Reports', 'Fleet Management',
            ]),
            'status' => WorkTask::STATUS_PENDING,
            'priority' => WorkTask::PRIORITY_NORMAL,
            'due_date' => fake()->optional()->dateTimeBetween('today', '+30 days'),
            'completed_at' => null,
            'next_action' => fake()->optional()->sentence(),
            'notes' => fake()->optional()->paragraph(),
            'sort_order' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkTask::STATUS_PENDING,
            'completed_at' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkTask::STATUS_IN_PROGRESS,
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkTask::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkTask::STATUS_PENDING,
            'due_date' => now()->subDays(3)->toDateString(),
            'completed_at' => null,
        ]);
    }

    public function dueToday(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkTask::STATUS_PENDING,
            'due_date' => now()->toDateString(),
            'completed_at' => null,
        ]);
    }

    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => WorkTask::PRIORITY_URGENT,
        ]);
    }
}
