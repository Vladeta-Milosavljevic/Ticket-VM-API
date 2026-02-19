<?php

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;

test('unauthenticated user cannot access tickets index', function () {
    $response = $this->getJson('/api/tickets');

    $response->assertStatus(401);
});

test('unauthenticated user cannot create ticket', function () {
    $category = Category::factory()->create(['is_archived' => false]);
    $manager = User::factory()->manager()->create();

    $response = $this->postJson('/api/tickets', [
        'title' => 'Test Ticket',
        'description' => 'Test description',
        'urgency' => 'medium',
        'deadline' => Carbon::tomorrow()->format('Y-m-d H:i:s'),
        'category_id' => $category->id,
        'manager_id' => $manager->id,
    ]);

    $response->assertStatus(401);
});

test('authenticated user can list tickets', function () {
    authenticateAs();
    Ticket::factory()->count(2)->create();

    $response = $this->getJson('/api/tickets');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
});

test('authenticated user can view a ticket', function () {
    authenticateAs();
    $ticket = Ticket::factory()->create();

    $response = $this->getJson("/api/tickets/{$ticket->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $ticket->id)
        ->assertJsonPath('data.title', $ticket->title);
});

test('authenticated user can create ticket with manager_id', function () {
    $category = Category::factory()->create(['is_archived' => false]);
    authenticateAs(User::factory()->agent()->create());
    $manager = User::factory()->manager()->create();

    $response = $this->postJson('/api/tickets', [
        'title' => 'New Ticket',
        'description' => 'Ticket description',
        'urgency' => 'high',
        'deadline' => Carbon::tomorrow()->addDay()->format('Y-m-d H:i:s'),
        'category_id' => $category->id,
        'manager_id' => $manager->id,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.title', 'New Ticket');

    $this->assertDatabaseHas('tickets', ['title' => 'New Ticket']);
});

test('manager can create ticket without manager_id (auto-assigned)', function () {
    $category = Category::factory()->create(['is_archived' => false]);
    $manager = authenticateAs(User::factory()->manager()->create());

    $response = $this->postJson('/api/tickets', [
        'title' => 'Manager Ticket',
        'description' => 'Auto-assigned to manager',
        'urgency' => 'low',
        'deadline' => Carbon::tomorrow()->format('Y-m-d H:i:s'),
        'category_id' => $category->id,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.manager.id', $manager->id);
});

test('ticket manager can update ticket', function () {
    $manager = User::factory()->manager()->create();
    $ticket = Ticket::factory()->create(['manager_id' => $manager->id]);
    authenticateAs($manager);

    $response = $this->putJson("/api/tickets/{$ticket->id}", [
        'title' => 'Updated Title',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.title', 'Updated Title');
});

test('agent cannot update ticket they are not assigned to', function () {
    $ticket = Ticket::factory()->create(['agent_id' => null]);
    authenticateAs(User::factory()->agent()->create());

    $response = $this->putJson("/api/tickets/{$ticket->id}", [
        'title' => 'Updated Title',
        'description' => $ticket->description,
        'urgency' => $ticket->urgency,
        'deadline' => $ticket->deadline->format('Y-m-d H:i:s'),
        'status' => $ticket->status,
    ]);

    $response->assertStatus(403);
});

test('admin can delete ticket', function () {
    authenticateAs(User::factory()->admin()->create());
    $ticket = Ticket::factory()->create();

    $response = $this->deleteJson("/api/tickets/{$ticket->id}");

    $response->assertStatus(200);
    $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
});

test('agent cannot delete ticket', function () {
    authenticateAs(User::factory()->agent()->create());
    $ticket = Ticket::factory()->create();

    $response = $this->deleteJson("/api/tickets/{$ticket->id}");

    $response->assertStatus(403);
});

test('ticket manager can assign agent', function () {
    $manager = User::factory()->manager()->create();
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create(['manager_id' => $manager->id, 'agent_id' => null]);
    authenticateAs($manager);

    $response = $this->postJson("/api/tickets/{$ticket->id}/assign", [
        'agent_id' => $agent->id,
    ]);

    $response->assertStatus(200);
    expect($ticket->fresh()->agent_id)->toBe($agent->id);
});

test('agent cannot assign to ticket they do not manage', function () {
    $ticket = Ticket::factory()->create(['agent_id' => null]);
    $agent = User::factory()->agent()->create();
    authenticateAs($agent);

    $response = $this->postJson("/api/tickets/{$ticket->id}/assign", [
        'agent_id' => $agent->id,
    ]);

    $response->assertStatus(403);
});

test('assigned agent can complete ticket', function () {
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create(['agent_id' => $agent->id, 'status' => 'in_progress']);
    authenticateAs($agent);

    $response = $this->postJson("/api/tickets/{$ticket->id}/complete");

    $response->assertStatus(200);
    expect($ticket->fresh()->status)->toBe('pending_review');
});

test('ticket manager can approve completion', function () {
    $manager = User::factory()->manager()->create();
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->pendingReview()->create([
        'manager_id' => $manager->id,
        'agent_id' => $agent->id,
    ]);
    authenticateAs($manager);

    $response = $this->postJson("/api/tickets/{$ticket->id}/approve");

    $response->assertStatus(200);
    expect($ticket->fresh()->status)->toBe('completed');
});

test('assigned agent can add comment', function () {
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->assigned()->create(['agent_id' => $agent->id]);
    authenticateAs($agent);

    $response = $this->postJson("/api/tickets/{$ticket->id}/comments", [
        'body' => 'Test comment from agent',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('comments', [
        'ticket_id' => $ticket->id,
        'body' => 'Test comment from agent',
    ]);
});
