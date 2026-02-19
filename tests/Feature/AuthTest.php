<?php

use App\Models\User;

test('login returns token and user on valid credentials', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'token',
            'user' => [
                'id',
                'name',
                'email',
                'role',
            ],
        ])
        ->assertJson([
            'message' => 'Login successful',
            'user' => [
                'email' => 'test@example.com',
            ],
        ]);
});

test('login returns 401 on invalid credentials', function () {
    User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Invalid credentials',
        ]);
});

test('login validates required fields', function () {
    $response = $this->postJson('/api/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

test('logout succeeds when authenticated', function () {
    $user = authenticateAs();

    $response = $this->postJson('/api/logout');

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Logout successful',
        ]);
});

test('logout returns 401 when unauthenticated', function () {
    $response = $this->postJson('/api/logout');

    $response->assertStatus(401);
});
