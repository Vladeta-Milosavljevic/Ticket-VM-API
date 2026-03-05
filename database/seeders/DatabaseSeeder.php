<?php

namespace Database\Seeders;

use App\Models\Attachment;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create users with specific roles
        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $managers = User::factory()->manager()->count(3)->create();
        $agents = User::factory()->agent()->count(6)->create();

        // Create categories
        $categories = Category::factory()->count(7)->create();

        // Create tickets with varied statuses (50 total)
        // Open tickets (15)
        Ticket::factory()
            ->count(15)
            ->state(function () use ($managers, $categories) {
                return [
                    'manager_id' => $managers->random()->id,
                    'category_id' => $categories->random()->id,
                    'status' => 'open',
                    'agent_id' => null,
                ];
            })
            ->create();

        // Assigned but in progress (10)
        Ticket::factory()
            ->assigned()
            ->count(10)
            ->state(function () use ($managers, $agents, $categories) {
                return [
                    'manager_id' => $managers->random()->id,
                    'agent_id' => $agents->random()->id,
                    'category_id' => $categories->random()->id,
                    'status' => 'in_progress',
                ];
            })
            ->create();

        // Pending review (8)
        Ticket::factory()
            ->pendingReview()
            ->count(8)
            ->state(function () use ($managers, $agents, $categories) {
                return [
                    'manager_id' => $managers->random()->id,
                    'agent_id' => $agents->random()->id,
                    'category_id' => $categories->random()->id,
                ];
            })
            ->create();

        // Completed (10)
        Ticket::factory()
            ->completed()
            ->count(10)
            ->state(function () use ($managers, $agents, $categories) {
                return [
                    'manager_id' => $managers->random()->id,
                    'agent_id' => $agents->random()->id,
                    'category_id' => $categories->random()->id,
                ];
            })
            ->create();

        // Rejected (5)
        Ticket::factory()
            ->rejected()
            ->count(5)
            ->state(function () use ($managers, $agents, $categories) {
                return [
                    'manager_id' => $managers->random()->id,
                    'agent_id' => $agents->random()->id,
                    'category_id' => $categories->random()->id,
                ];
            })
            ->create();

        // Cancelled (2)
        Ticket::factory()
            ->cancelled()
            ->count(2)
            ->state(function () use ($managers, $categories) {
                return [
                    'manager_id' => $managers->random()->id,
                    'category_id' => $categories->random()->id,
                    'agent_id' => null,
                ];
            })
            ->create();

        // Get all tickets for comments
        $tickets = Ticket::all();

        // Add attachments to some tickets (first 10 tickets get 1-2 attachments each)
        foreach ($tickets->take(10) as $ticket) {
            $count = rand(1, 2);
            Attachment::factory()
                ->forTicket($ticket)
                ->count($count)
                ->create(['user_id' => $ticket->manager_id]);
        }

        // Create 5 comments per ticket
        foreach ($tickets as $ticket) {
            // Mix of manager and agent comments
            for ($i = 0; $i < 5; $i++) {
                // Alternate between manager and agent (if ticket has an agent)
                if ($ticket->agent_id && $i % 2 === 0) {
                    $commenter = $ticket->agent;
                } else {
                    $commenter = $ticket->manager;
                }

                $comment = Comment::factory()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $commenter->id,
                ]);

                // Add attachments to ~20% of comments
                if (rand(1, 5) === 1) {
                    Attachment::factory()
                        ->forComment($comment)
                        ->count(1)
                        ->create(['user_id' => $commenter->id]);
                }
            }
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Users created: 1 admin, 3 managers, 6 agents');
        $this->command->info('Categories created: 7');
        $this->command->info('Tickets created: 50');
        $this->command->info('Comments created: 250 (5 per ticket)');
        $this->command->info('Attachments created: demo attachments on tickets and comments');
    }
}
