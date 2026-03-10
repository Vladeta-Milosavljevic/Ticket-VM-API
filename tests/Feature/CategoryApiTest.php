<?php

use App\Models\Category;
use App\Models\User;

test('unauthenticated user cannot access categories index', function () {
    $response = $this->getJson('/api/categories');

    $response->assertStatus(401);
});

test('unauthenticated user cannot access category show', function () {
    $category = Category::factory()->create();

    $response = $this->getJson("/api/categories/{$category->id}");

    $response->assertStatus(401);
});

test('unauthenticated user cannot create category', function () {
    $response = $this->postJson('/api/categories', [
        'name' => 'Test Category',
        'description' => 'Test description',
    ]);

    $response->assertStatus(401);
});

test('authenticated user can list categories', function () {
    authenticateAs();
    Category::factory()->count(3)->create();

    $response = $this->getJson('/api/categories');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

test('authenticated user can filter categories by active_only', function () {
    authenticateAs();
    Category::factory()->create(['is_archived' => false, 'name' => 'Active A']);
    Category::factory()->create(['is_archived' => false, 'name' => 'Active B']);
    Category::factory()->create(['is_archived' => true, 'name' => 'Archived']);

    $response = $this->getJson('/api/categories?active_only=1');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('authenticated user can filter categories by archived', function () {
    authenticateAs();
    Category::factory()->create(['is_archived' => false, 'name' => 'Active']);
    Category::factory()->create(['is_archived' => true, 'name' => 'Archived A']);
    Category::factory()->create(['is_archived' => true, 'name' => 'Archived B']);

    $response = $this->getJson('/api/categories?archived=1');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('index rejects invalid active_only value', function () {
    authenticateAs();

    $response = $this->getJson('/api/categories?active_only=invalid');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['active_only']);
});

test('index rejects invalid archived value', function () {
    authenticateAs();

    $response = $this->getJson('/api/categories?archived=invalid');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['archived']);
});

test('authenticated user can view a category', function () {
    authenticateAs();
    $category = Category::factory()->create();

    $response = $this->getJson("/api/categories/{$category->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $category->id)
        ->assertJsonPath('data.name', $category->name);
});

test('admin can create category', function () {
    authenticateAs(User::factory()->admin()->create());

    $response = $this->postJson('/api/categories', [
        'name' => 'New Category',
        'description' => 'Category description',
        'color' => '#ff0000',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'New Category');

    $this->assertDatabaseHas('categories', ['name' => 'New Category']);
});

test('non-admin cannot create category', function () {
    authenticateAs(User::factory()->agent()->create());

    $response = $this->postJson('/api/categories', [
        'name' => 'New Category',
        'description' => 'Category description',
    ]);

    $response->assertStatus(403);
});

test('admin can update category', function () {
    authenticateAs(User::factory()->admin()->create());
    $category = Category::factory()->create();

    $response = $this->putJson("/api/categories/{$category->id}", [
        'name' => 'Updated Category',
        'description' => 'Updated description',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Category');

    $this->assertDatabaseHas('categories', ['name' => 'Updated Category']);
});

test('non-admin cannot update category', function () {
    authenticateAs(User::factory()->manager()->create());
    $category = Category::factory()->create();

    $response = $this->putJson("/api/categories/{$category->id}", [
        'name' => 'Updated Category',
        'description' => 'Updated description',
    ]);

    $response->assertStatus(403);
});

test('admin can archive category', function () {
    authenticateAs(User::factory()->admin()->create());
    $category = Category::factory()->create(['is_archived' => false]);

    $response = $this->postJson("/api/categories/{$category->id}/archive");

    $response->assertStatus(200);
    expect($category->fresh()->is_archived)->toBeTrue();
});

test('admin can reactivate category', function () {
    authenticateAs(User::factory()->admin()->create());
    $category = Category::factory()->create(['is_archived' => true]);

    $response = $this->postJson("/api/categories/{$category->id}/reactivate");

    $response->assertStatus(200);
    expect($category->fresh()->is_archived)->toBeFalse();
});
