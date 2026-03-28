<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'body' => \fake()->paragraph(2),
            'is_internal' => \fake()->boolean(20), // 20% chance of being internal
        ];
    }

    /**
     * Set comment as internal.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => true,
        ]);
    }

    /**
     * Set comment as public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => false,
        ]);
    }
}
