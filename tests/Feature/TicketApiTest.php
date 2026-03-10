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

test('tickets can be filtered by status', function () {
    authenticateAs();
    $openTicket = Ticket::factory()->create(['status' => 'open']);
    $completedTicket = Ticket::factory()->create(['status' => 'completed']);

    $response = $this->getJson('/api/tickets?status=open');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $openTicket->id);
});

test('tickets can be filtered by category_id', function () {
    authenticateAs();
    $category = Category::factory()->create(['is_archived' => false]);
    $ticketInCategory = Ticket::factory()->create(['category_id' => $category->id]);
    $otherTicket = Ticket::factory()->create();

    $response = $this->getJson("/api/tickets?category_id={$category->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ticketInCategory->id);
});

test('tickets can be filtered by search', function () {
    authenticateAs();
    $matchingTicket = Ticket::factory()->create(['title' => 'Unique Searchable Title']);
    Ticket::factory()->create(['title' => 'Other Ticket']);

    $response = $this->getJson('/api/tickets?search=Unique+Searchable');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingTicket->id);
});

test('tickets can be filtered by unassigned', function () {
    authenticateAs();
    $unassignedTicket = Ticket::factory()->create(['agent_id' => null]);
    $assignedTicket = Ticket::factory()->assigned()->create();

    $response = $this->getJson('/api/tickets?unassigned=1');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $unassignedTicket->id);
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

test('assigned agent can update non-workflow fields but workflow fields are filtered out', function () {
    $agent = User::factory()->agent()->create();
    $manager = User::factory()->manager()->create();
    $ticket = Ticket::factory()->create([
        'manager_id' => $manager->id,
        'agent_id' => $agent->id,
        'status' => 'in_progress',
        'title' => 'Original Title',
    ]);
    authenticateAs($agent);

    $response = $this->putJson("/api/tickets/{$ticket->id}", [
        'title' => 'Updated by Agent',
        'status' => 'completed',
        'completed_by_agent_at' => now()->format('Y-m-d H:i:s'),
        'completed_by_manager_at' => now()->format('Y-m-d H:i:s'),
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.title', 'Updated by Agent');

    $ticket->refresh();
    expect($ticket->title)->toBe('Updated by Agent')
        ->and($ticket->status)->toBe('in_progress')
        ->and($ticket->completed_by_agent_at)->toBeNull()
        ->and($ticket->completed_by_manager_at)->toBeNull();
});

test('ticket manager can update workflow fields', function () {
    $manager = User::factory()->manager()->create();
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create([
        'manager_id' => $manager->id,
        'agent_id' => $agent->id,
        'status' => 'open',
    ]);
    authenticateAs($manager);

    $response = $this->putJson("/api/tickets/{$ticket->id}", [
        'status' => 'in_progress',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'in_progress');

    expect($ticket->fresh()->status)->toBe('in_progress');
});

test('admin can update workflow fields', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);
    authenticateAs($admin);

    $response = $this->putJson("/api/tickets/{$ticket->id}", [
        'status' => 'cancelled',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'cancelled');

    expect($ticket->fresh()->status)->toBe('cancelled');
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
