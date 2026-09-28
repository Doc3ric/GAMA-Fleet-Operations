<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkNote>
 */
class WorkNoteFactory extends Factory
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
            'title' => $this->faker->sentence(4),
            'content' => $this->faker->paragraph(2),
            'is_pinned' => false,
            'color' => 'slate',
        ];
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['is_pinned' => true]);
    }
}
