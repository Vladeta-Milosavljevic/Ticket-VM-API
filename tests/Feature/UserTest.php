<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create();
    $this->agent = User::factory()->agent()->create();
});

test('admin can list users', function () {
    $response = $this->actingAs($this->admin)
        ->getJson('/api/users');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'role'],
            ],
        ]);
});

test('admin can create user', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role' => 'agent',
        ]);

    $response->assertStatus(201);
    expect(User::where('email', 'newuser@example.com')->exists())->toBeTrue();
});

test('manager can create user', function () {
    $response = $this->actingAs($this->manager)
        ->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role' => 'agent',
        ]);

    $response->assertStatus(201);
});

test('agent cannot create user', function () {
    $response = $this->actingAs($this->agent)
        ->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role' => 'agent',
        ]);

    $response->assertStatus(403);
});

test('admin can update user role', function () {
    $user = User::factory()->agent()->create();

    $response = $this->actingAs($this->admin)
        ->putJson("/api/users/{$user->id}", [
            'role' => 'manager',
        ]);

    $response->assertStatus(200);
    
    $user->refresh();
    expect($user->role)->toBe('manager');
});

test('admin can delete user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($this->admin)
        ->deleteJson("/api/users/{$user->id}");

    $response->assertStatus(200);
    expect(User::count())->toBe(3); // admin, manager, agent from beforeEach
});

test('manager cannot delete user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($this->manager)
        ->deleteJson("/api/users/{$user->id}");

    $response->assertStatus(403);
});

test('can view single user', function () {
    $response = $this->actingAs($this->admin)
        ->getJson("/api/users/{$this->agent->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'role',
            ],
        ]);
});

test('admin can update user details', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($this->admin)
        ->putJson("/api/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

    $response->assertStatus(200);
    
    $user->refresh();
    expect($user->name)->toBe('Updated Name');
    expect($user->email)->toBe('updated@example.com');
});

test('can get user tickets', function () {
    $ticket = \App\Models\Ticket::factory()->create([
        'agent_id' => $this->agent->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/users/{$this->agent->id}/tickets");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'status',
                ],
            ],
        ]);
});
