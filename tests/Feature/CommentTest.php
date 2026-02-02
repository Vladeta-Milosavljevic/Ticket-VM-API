<?php

use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create();
    $this->agent = User::factory()->agent()->create();
    $this->ticket = Ticket::factory()->create([
        'manager_id' => $this->manager->id,
        'agent_id' => $this->agent->id,
    ]);
});

test('manager can add comment to ticket', function () {
    $response = $this->actingAs($this->manager)
        ->postJson("/api/tickets/{$this->ticket->id}/comments", [
            'body' => 'This is a comment',
            'is_internal' => false,
        ]);

    $response->assertStatus(201);
    expect(Comment::count())->toBe(1);
    expect(Comment::first()->body)->toBe('This is a comment');
    expect(Comment::first()->is_internal)->toBeFalse();
});

test('agent can add comment to assigned ticket', function () {
    $response = $this->actingAs($this->agent)
        ->postJson("/api/tickets/{$this->ticket->id}/comments", [
            'body' => 'Agent comment',
            'is_internal' => true,
        ]);

    $response->assertStatus(201);
    expect(Comment::first()->is_internal)->toBeTrue();
});

test('admin can add comment to any ticket', function () {
    $otherTicket = Ticket::factory()->create([
        'manager_id' => $this->manager->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson("/api/tickets/{$otherTicket->id}/comments", [
            'body' => 'Admin comment',
        ]);

    $response->assertStatus(201);
});

test('unauthorized user cannot add comment', function () {
    $otherUser = User::factory()->agent()->create();
    $otherTicket = Ticket::factory()->create([
        'manager_id' => $this->manager->id,
    ]);

    $response = $this->actingAs($otherUser)
        ->postJson("/api/tickets/{$otherTicket->id}/comments", [
            'body' => 'Unauthorized comment',
        ]);

    $response->assertStatus(403);
});

test('can retrieve comments for ticket', function () {
    Comment::factory()->count(3)->create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->manager->id,
    ]);

    $response = $this->actingAs($this->manager)
        ->getJson("/api/tickets/{$this->ticket->id}/comments");

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'body',
                    'is_internal',
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                ],
            ],
        ]);
});

test('comment requires body field', function () {
    $response = $this->actingAs($this->manager)
        ->postJson("/api/tickets/{$this->ticket->id}/comments", [
            'is_internal' => false,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['body']);
});

test('comment is_internal defaults to false', function () {
    $response = $this->actingAs($this->manager)
        ->postJson("/api/tickets/{$this->ticket->id}/comments", [
            'body' => 'Public comment',
        ]);

    $response->assertStatus(201);
    expect(Comment::first()->is_internal)->toBeFalse();
});

test('manager can view comments on their ticket', function () {
    Comment::factory()->count(2)->create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->agent->id,
    ]);

    $response = $this->actingAs($this->manager)
        ->getJson("/api/tickets/{$this->ticket->id}/comments");

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('agent can view comments on assigned ticket', function () {
    Comment::factory()->count(2)->create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->manager->id,
    ]);

    $response = $this->actingAs($this->agent)
        ->getJson("/api/tickets/{$this->ticket->id}/comments");

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});
