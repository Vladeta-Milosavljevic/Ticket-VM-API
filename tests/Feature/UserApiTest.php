<?php

use App\Models\User;

test('unauthenticated user cannot access users index', function () {
    $response = $this->getJson('/api/users');

    $response->assertStatus(401);
});

test('unauthenticated user cannot create user', function () {
    $response = $this->postJson('/api/users', [
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'password123',
        'role' => 'agent',
    ]);

    $response->assertStatus(401);
});

test('authenticated user can list users', function () {
    authenticateAs();
    User::factory()->count(2)->create();

    $response = $this->getJson('/api/users');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
});

test('authenticated user can view a user', function () {
    authenticateAs();
    $user = User::factory()->create();

    $response = $this->getJson("/api/users/{$user->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});

test('admin can create user', function () {
    authenticateAs(User::factory()->admin()->create());

    $response = $this->postJson('/api/users', [
        'name' => 'New Agent',
        'email' => 'agent@example.com',
        'password' => 'password123',
        'role' => 'agent',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.email', 'agent@example.com')
        ->assertJsonPath('data.role', 'agent');

    $this->assertDatabaseHas('users', ['email' => 'agent@example.com']);
});

test('manager can create user', function () {
    authenticateAs(User::factory()->manager()->create());

    $response = $this->postJson('/api/users', [
        'name' => 'New Agent',
        'email' => 'newagent@example.com',
        'password' => 'password123',
        'role' => 'agent',
    ]);

    $response->assertStatus(201);
});

test('agent cannot create user', function () {
    authenticateAs(User::factory()->agent()->create());

    $response = $this->postJson('/api/users', [
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'password123',
        'role' => 'agent',
    ]);

    $response->assertStatus(403);
});

test('admin can update user', function () {
    authenticateAs(User::factory()->admin()->create());
    $user = User::factory()->create();

    $response = $this->putJson("/api/users/{$user->id}", [
        'name' => 'Updated Name',
        'email' => $user->email,
        'password' => 'newpassword123',
        'role' => $user->role,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Name');
});

test('agent cannot update user', function () {
    authenticateAs(User::factory()->agent()->create());
    $user = User::factory()->create();

    $response = $this->putJson("/api/users/{$user->id}", [
        'name' => 'Updated Name',
        'email' => $user->email,
        'password' => 'newpassword123',
        'role' => $user->role,
    ]);

    $response->assertStatus(403);
});

test('admin can delete user', function () {
    authenticateAs(User::factory()->admin()->create());
    $user = User::factory()->create();

    $response = $this->deleteJson("/api/users/{$user->id}");

    $response->assertStatus(200);
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('manager cannot delete user', function () {
    authenticateAs(User::factory()->manager()->create());
    $user = User::factory()->create();

    $response = $this->deleteJson("/api/users/{$user->id}");

    $response->assertStatus(403);
});

test('user can view their own tickets', function () {
    $user = authenticateAs(User::factory()->agent()->create());

    $response = $this->getJson("/api/users/{$user->id}/tickets");

    $response->assertStatus(200);
});

test('agent cannot view another user tickets', function () {
    $agent = authenticateAs(User::factory()->agent()->create());
    $otherUser = User::factory()->create();

    $response = $this->getJson("/api/users/{$otherUser->id}/tickets");

    $response->assertStatus(403);
});
