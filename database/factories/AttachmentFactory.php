<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ext = fake()->randomElement(['pdf', 'doc', 'docx', 'txt', 'png', 'jpeg', 'gif']);
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'png' => 'image/png',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
        ];
        $originalName = fake()->word().'.'.$ext;

        return [
            'attachable_type' => Ticket::class,
            'attachable_id' => Ticket::factory(),
            'filename' => fake()->slug().'.'.$ext,
            'original_name' => $originalName,
            'mime_type' => $mimeTypes[$ext],
            'size' => fake()->numberBetween(1024, 5 * 1024 * 1024),
            'disk' => 'public',
            'path' => 'attachments/tickets/1/'.fake()->uuid().'_'.$originalName,
            'user_id' => User::factory(),
        ];
    }

    /**
     * Attach to a ticket.
     */
    public function forTicket(Ticket $ticket): static
    {
        return $this->state(fn (array $attributes) => [
            'attachable_type' => Ticket::class,
            'attachable_id' => $ticket->id,
            'path' => 'attachments/tickets/'.$ticket->id.'/'.fake()->uuid().'_'.($attributes['original_name'] ?? 'file.pdf'),
        ]);
    }

    /**
     * Attach to a comment.
     */
    public function forComment(Comment $comment): static
    {
        return $this->state(fn (array $attributes) => [
            'attachable_type' => Comment::class,
            'attachable_id' => $comment->id,
            'path' => 'attachments/comments/'.$comment->id.'/'.fake()->uuid().'_'.($attributes['original_name'] ?? 'file.pdf'),
        ]);
    }
}
