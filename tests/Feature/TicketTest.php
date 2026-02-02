<?php

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create();
    $this->agent = User::factory()->agent()->create();
    $this->category = Category::factory()->create();
});

test('admin can create ticket with all fields', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/tickets', [
            'title' => 'Test Ticket',
            'description' => 'Test Description',
            'urgency' => 'high',
            'deadline' => now()->addDays(7)->format('Y-m-d'),
            'category_id' => $this->category->id,
            'manager_id' => $this->manager->id,
            'agent_id' => $this->agent->id,
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'description',
                'urgency',
                'deadline',
                'status',
                'category',
                'manager',
                'agent',
            ],
        ]);

    expect(Ticket::count())->toBe(1);
    expect(Ticket::first()->title)->toBe('Test Ticket');
});

test('manager auto-assigns themselves when creating ticket without manager_id', function () {
    $response = $this->actingAs($this->manager)
        ->postJson('/api/tickets', [
            'title' => 'Test Ticket',
            'description' => 'Test Description',
            'urgency' => 'medium',
            'deadline' => now()->addDays(5)->format('Y-m-d'),
        ]);

    $response->assertStatus(201);
    
    $ticket = Ticket::first();
    expect($ticket->manager_id)->toBe($this->manager->id);
});

test('agent cannot assign agent during ticket creation', function () {
    $response = $this->actingAs($this->agent)
        ->postJson('/api/tickets', [
            'title' => 'Test Ticket',
            'description' => 'Test Description',
            'urgency' => 'low',
            'deadline' => now()->addDays(3)->format('Y-m-d'),
            'manager_id' => $this->manager->id,
            'agent_id' => $this->agent->id,
        ]);

    $response->assertStatus(201);
    
    $ticket = Ticket::first();
    expect($ticket->agent_id)->toBeNull();
});

test('agent requires manager_id when creating ticket', function () {
    $response = $this->actingAs($this->agent)
        ->postJson('/api/tickets', [
            'title' => 'Test Ticket',
            'description' => 'Test Description',
            'urgency' => 'low',
            'deadline' => now()->addDays(3)->format('Y-m-d'),
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['manager_id']);
});

test('manager can assign agent to ticket', function () {
    $ticket = Ticket::factory()->create([
        'manager_id' => $this->manager->id,
        'status' => 'open',
    ]);

    $response = $this->actingAs($this->manager)
        ->postJson("/api/tickets/{$ticket->id}/assign", [
            'agent_id' => $this->agent->id,
        ]);

    $response->assertStatus(200);
    
    $ticket->refresh();
    expect($ticket->agent_id)->toBe($this->agent->id);
});

test('agent can complete assigned ticket', function () {
    $ticket = Ticket::factory()->create([
        'manager_id' => $this->manager->id,
        'agent_id' => $this->agent->id,
        'status' => 'in_progress',
    ]);

    $response = $this->actingAs($this->agent)
        ->postJson("/api/tickets/{$ticket->id}/complete");

    $response->assertStatus(200);
    
    $ticket->refresh();
    expect($ticket->status)->toBe('pending_review');
    expect($ticket->completed_by_agent_at)->not->toBeNull();
});

test('manager can approve completed ticket', function () {
    $ticket = Ticket::factory()->create([
        'manager_id' => $this->manager->id,
        'agent_id' => $this->agent->id,
        'status' => 'pending_review',
        'completed_by_agent_at' => now(),
    ]);

    $response = $this->actingAs($this->manager)
        ->postJson("/api/tickets/{$ticket->id}/approve");

    $response->assertStatus(200);
    
    $ticket->refresh();
    expect($ticket->status)->toBe('completed');
    expect($ticket->completed_by_manager_at)->not->toBeNull();
});

test('manager can reject completed ticket', function () {
    $ticket = Ticket::factory()->create([
        'manager_id' => $this->manager->id,
        'agent_id' => $this->agent->id,
        'status' => 'pending_review',
        'completed_by_agent_at' => now(),
    ]);

    $response = $this->actingAs($this->manager)
        ->postJson("/api/tickets/{$ticket->id}/reject", [
            'rejection_reason' => 'Needs more work',
        ]);

    $response->assertStatus(200);
    
    $ticket->refresh();
    expect($ticket->status)->toBe('in_progress');
    expect($ticket->rejected_at)->not->toBeNull();
    expect($ticket->rejection_reason)->toBe('Needs more work');
    expect($ticket->completed_by_agent_at)->toBeNull();
});

test('admin can delete ticket', function () {
    $ticket = Ticket::factory()->create();

    $response = $this->actingAs($this->admin)
        ->deleteJson("/api/tickets/{$ticket->id}");

    $response->assertStatus(200);
    expect(Ticket::count())->toBe(0);
});

test('agent cannot delete ticket', function () {
    $ticket = Ticket::factory()->create();

    $response = $this->actingAs($this->agent)
        ->deleteJson("/api/tickets/{$ticket->id}");

    $response->assertStatus(403);
    expect(Ticket::count())->toBe(1);
});

test('can list tickets', function () {
    Ticket::factory()->count(5)->create();

    $response = $this->actingAs($this->admin)
        ->getJson('/api/tickets');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'description',
                    'urgency',
                    'status',
                ],
            ],
        ]);
});

test('can view single ticket', function () {
    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'manager_id' => $this->manager->id,
        'agent_id' => $this->agent->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/tickets/{$ticket->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'description',
                'category',
                'manager',
                'agent',
            ],
        ]);
});

test('admin can update ticket', function () {
    $ticket = Ticket::factory()->create();

    $response = $this->actingAs($this->admin)
        ->putJson("/api/tickets/{$ticket->id}", [
            'title' => 'Updated Title',
            'urgency' => 'critical',
        ]);

    $response->assertStatus(200);
    
    $ticket->refresh();
    expect($ticket->title)->toBe('Updated Title');
    expect($ticket->urgency)->toBe('critical');
});
