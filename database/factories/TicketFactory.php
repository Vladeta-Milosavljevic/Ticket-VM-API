<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $urgency = \fake()->randomElement(['low', 'medium', 'high', 'critical']);
        $status = \fake()->randomElement(['open', 'in_progress', 'pending_review', 'completed', 'rejected', 'cancelled']);

        // Deadlines: mix of past, near future, and far future
        $deadline = \fake()->dateTimeBetween('-1 week', '+2 months');

        return [
            'title' => \fake()->sentence(4),
            'description' => \fake()->paragraph(3),
            'urgency' => $urgency,
            'deadline' => $deadline,
            'status' => $status,
            'category_id' => Category::factory(),
            'manager_id' => User::factory(),
            'agent_id' => null,
            'completed_by_agent_at' => null,
            'completed_by_manager_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ];
    }

    /**
     * Set ticket as assigned to an agent.
     */
    public function assigned(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'agent_id' => User::factory()->agent(),
                'status' => \fake()->randomElement(['open', 'in_progress']),
            ];
        });
    }

    /**
     * Set ticket as completed by agent (pending review).
     */
    public function pendingReview(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'agent_id' => User::factory()->agent(),
                'status' => 'pending_review',
                'completed_by_agent_at' => \fake()->dateTimeBetween('-3 days', 'now'),
            ];
        });
    }

    /**
     * Set ticket as completed.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $completedByAgentAt = \fake()->dateTimeBetween('-1 week', '-2 days');

            return [
                'agent_id' => User::factory()->agent(),
                'status' => 'completed',
                'completed_by_agent_at' => $completedByAgentAt,
                'completed_by_manager_at' => \fake()->dateTimeBetween($completedByAgentAt, 'now'),
            ];
        });
    }

    /**
     * Set ticket as rejected.
     */
    public function rejected(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'agent_id' => User::factory()->agent(),
                'status' => 'rejected',
                'completed_by_agent_at' => \fake()->dateTimeBetween('-1 week', '-1 day'),
                'rejected_at' => \fake()->dateTimeBetween('-1 day', 'now'),
                'rejection_reason' => \fake()->sentence(),
            ];
        });
    }

    /**
     * Set ticket as cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'cancelled',
            ];
        });
    }
}
