<?php

use App\Models\Attachment;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('authenticated user can create ticket with attachments', function () {
    $category = Category::factory()->create(['is_archived' => false]);
    $manager = User::factory()->manager()->create();
    authenticateAs(User::factory()->agent()->create());

    $file1 = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');
    $file2 = UploadedFile::fake()->create('screenshot.png', 1024, 'image/png');

    $response = $this->post('/api/tickets', [
        'title' => 'Ticket with attachments',
        'description' => 'Description',
        'urgency' => 'medium',
        'deadline' => Carbon::tomorrow()->format('Y-m-d H:i:s'),
        'category_id' => $category->id,
        'manager_id' => $manager->id,
        'attachments' => [$file1, $file2],
    ], ['Accept' => 'application/json']);

    $response->assertStatus(201)
        ->assertJsonPath('data.title', 'Ticket with attachments')
        ->assertJsonStructure(['data' => ['attachments']]);

    $ticket = Ticket::find($response->json('data.id'));
    expect($ticket->attachments)->toHaveCount(2);

    $paths = $ticket->attachments->pluck('path')->toArray();
    foreach ($paths as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

test('authenticated user can create ticket without attachments', function () {
    $category = Category::factory()->create(['is_archived' => false]);
    $manager = User::factory()->manager()->create();
    authenticateAs(User::factory()->agent()->create());

    $response = $this->postJson('/api/tickets', [
        'title' => 'Ticket without attachments',
        'description' => 'Description',
        'urgency' => 'medium',
        'deadline' => Carbon::tomorrow()->format('Y-m-d H:i:s'),
        'category_id' => $category->id,
        'manager_id' => $manager->id,
    ]);

    $response->assertStatus(201);
    $ticket = Ticket::find($response->json('data.id'));
    expect($ticket->attachments)->toHaveCount(0);
});

test('assigned agent can create comment with attachments', function () {
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->assigned()->create(['agent_id' => $agent->id]);
    authenticateAs($agent);

    $file = UploadedFile::fake()->create('notes.txt', 512, 'text/plain');

    $response = $this->post("/api/tickets/{$ticket->id}/comments", [
        'body' => 'Comment with attachment',
        'attachments' => [$file],
    ], ['Accept' => 'application/json']);

    $response->assertStatus(201);
    $comment = Comment::latest()->first();
    expect($comment->attachments)->toHaveCount(1);
    expect($comment->attachments->first()->original_name)->toBe('notes.txt');
});

test('unauthorized user cannot add attachments to ticket', function () {
    $ticket = Ticket::factory()->create([
        'manager_id' => User::factory()->manager()->create()->id,
        'agent_id' => User::factory()->agent()->create()->id,
    ]);
    $otherUser = User::factory()->agent()->create();
    authenticateAs($otherUser);

    $file = UploadedFile::fake()->create('doc.pdf', 1024, 'application/pdf');

    $response = $this->put("/api/tickets/{$ticket->id}", [
        'title' => $ticket->title,
        'attachments' => [$file],
    ], ['Accept' => 'application/json']);

    $response->assertStatus(403);
});

test('file type rejection for invalid mime', function () {
    $category = Category::factory()->create(['is_archived' => false]);
    $manager = User::factory()->manager()->create();
    authenticateAs(User::factory()->agent()->create());

    $file = UploadedFile::fake()->create('virus.exe', 1024, 'application/x-msdownload');

    $response = $this->post('/api/tickets', [
        'title' => 'Ticket',
        'description' => 'Description',
        'urgency' => 'medium',
        'deadline' => Carbon::tomorrow()->format('Y-m-d H:i:s'),
        'category_id' => $category->id,
        'manager_id' => $manager->id,
        'attachments' => [$file],
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['attachments.0']);
});

test('file size rejection when exceeding 10MB', function () {
    $category = Category::factory()->create(['is_archived' => false]);
    $manager = User::factory()->manager()->create();
    authenticateAs(User::factory()->agent()->create());

    $file = UploadedFile::fake()->create('large.pdf', 10241); // 10MB + 1KB

    $response = $this->post('/api/tickets', [
        'title' => 'Ticket',
        'description' => 'Description',
        'urgency' => 'medium',
        'deadline' => Carbon::tomorrow()->format('Y-m-d H:i:s'),
        'category_id' => $category->id,
        'manager_id' => $manager->id,
        'attachments' => [$file],
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['attachments.0']);
});

test('max 5 files per request validation', function () {
    $category = Category::factory()->create(['is_archived' => false]);
    $manager = User::factory()->manager()->create();
    authenticateAs(User::factory()->agent()->create());

    $files = collect(range(1, 6))->map(fn () => UploadedFile::fake()->create('file.pdf', 1024, 'application/pdf'))->toArray();

    $response = $this->post('/api/tickets', [
        'title' => 'Ticket',
        'description' => 'Description',
        'urgency' => 'medium',
        'deadline' => Carbon::tomorrow()->format('Y-m-d H:i:s'),
        'category_id' => $category->id,
        'manager_id' => $manager->id,
        'attachments' => $files,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['attachments']);
});

test('authorized user can delete attachment', function () {
    $manager = User::factory()->manager()->create();
    $ticket = Ticket::factory()->create(['manager_id' => $manager->id]);
    $attachment = Attachment::factory()->forTicket($ticket)->create([
        'user_id' => $manager->id,
        'path' => 'attachments/tickets/'.$ticket->id.'/test.pdf',
    ]);
    Storage::disk('public')->put($attachment->path, 'fake content');
    authenticateAs($manager);

    $response = $this->deleteJson("/api/attachments/{$attachment->id}");

    $response->assertStatus(200);
    $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    Storage::disk('public')->assertMissing($attachment->path);
});

test('unauthorized user receives 403 on attachment delete', function () {
    $ticket = Ticket::factory()->create([
        'manager_id' => User::factory()->manager()->create()->id,
        'agent_id' => User::factory()->agent()->create()->id,
    ]);
    $attachment = Attachment::factory()->forTicket($ticket)->create();
    $otherUser = User::factory()->agent()->create();
    authenticateAs($otherUser);

    $response = $this->deleteJson("/api/attachments/{$attachment->id}");

    $response->assertStatus(403);
    $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
});

test('authorized user can delete comment attachment', function () {
    $manager = User::factory()->manager()->create();
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create([
        'manager_id' => $manager->id,
        'agent_id' => $agent->id,
    ]);
    $comment = Comment::factory()->for($ticket)->create(['user_id' => $agent->id]);
    $attachment = Attachment::factory()->forComment($comment)->create([
        'user_id' => $agent->id,
        'path' => 'attachments/comments/'.$comment->id.'/test.pdf',
    ]);
    Storage::disk('public')->put($attachment->path, 'fake content');
    authenticateAs($manager);

    $response = $this->deleteJson("/api/attachments/{$attachment->id}");

    $response->assertStatus(200);
    $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    Storage::disk('public')->assertMissing($attachment->path);
});
