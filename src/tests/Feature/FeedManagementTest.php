<?php

use App\Models\User;

test('dashboard displays user feeds', function () {
    $user = User::factory()->create();

    $feed1 = $user->feeds()->create([
        'title' => 'Test Feed 1',
        'description' => 'Description for feed 1',
        'slug' => 'test-feed-1',
        'user_guid' => 'guid-1',
        'token' => 'token-1',
        'is_public' => true,
    ]);

    $feed2 = $user->feeds()->create([
        'title' => 'Test Feed 2',
        'description' => 'Description for feed 2',
        'slug' => 'test-feed-2',
        'user_guid' => 'guid-2',
        'token' => 'token-2',
        'is_public' => false,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->has('feeds', 2)
        ->where('feeds.0.title', 'Test Feed 1')
        ->where('feeds.0.is_public', 1)
        ->where('feeds.1.title', 'Test Feed 2')
        ->where('feeds.1.is_public', 0)
    );
});

test('user can create a new feed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/feeds', [
        'title' => 'New Test Feed',
        'description' => 'A new feed for testing',
        'is_public' => true,
    ]);

    $response->assertRedirect('/dashboard');
    $response->assertSessionHas('success', 'Feed created successfully!');

    $this->assertDatabaseHas('feeds', [
        'user_id' => $user->id,
        'title' => 'New Test Feed',
        'description' => 'A new feed for testing',
        'is_public' => true,
    ]);
});

test('user can create a feed with minimal data', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/feeds', [
        'title' => 'Minimal Feed',
    ]);

    $response->assertRedirect('/dashboard');
    $response->assertSessionHas('success', 'Feed created successfully!');

    $this->assertDatabaseHas('feeds', [
        'user_id' => $user->id,
        'title' => 'Minimal Feed',
        'description' => null,
        'is_public' => false,
    ]);
});

test('feed creation validation fails with invalid data', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/feeds', [
        'title' => '',
        'is_public' => 'not-a-boolean',
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['title', 'is_public']);
});

test('user can delete their own feed', function () {
    $user = User::factory()->create();

    $feed = $user->feeds()->create([
        'title' => 'Feed to Delete',
        'slug' => 'feed-to-delete',
        'user_guid' => 'guid-delete',
        'token' => 'token-delete',
    ]);

    $response = $this->actingAs($user)->delete("/feeds/{$feed->id}");

    $response->assertRedirect('/dashboard');
    $response->assertSessionHas('success', 'Feed deleted successfully!');
    $this->assertDatabaseMissing('feeds', [
        'id' => $feed->id,
    ]);
});

test('user cannot delete another users feed', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $feed = $user1->feeds()->create([
        'title' => 'User 1 Feed',
        'slug' => 'user-1-feed',
        'user_guid' => 'guid-user1',
        'token' => 'token-user1',
    ]);

    $response = $this->actingAs($user2)->delete("/feeds/{$feed->id}");

    $response->assertStatus(403);
    $this->assertDatabaseHas('feeds', [
        'id' => $feed->id,
    ]);
});

test('unauthenticated user cannot access feed management', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');

    $response = $this->post('/feeds', [
        'title' => 'Test Feed',
    ]);
    $response->assertRedirect('/login');

    $response = $this->delete('/feeds/1');
    $response->assertRedirect('/login');
});

test('user can get list of their feeds via API', function () {
    $user = User::factory()->create();

    $feed1 = $user->feeds()->create([
        'title' => 'API Feed 1',
        'slug' => 'api-feed-1',
        'user_guid' => 'api-guid-1',
        'token' => 'api-token-1',
    ]);

    $feed2 = $user->feeds()->create([
        'title' => 'API Feed 2',
        'slug' => 'api-feed-2',
        'user_guid' => 'api-guid-2',
        'token' => 'api-token-2',
    ]);

    $response = $this->actingAs($user)->get('/feeds', [
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(200);
    $response->assertJsonCount(2);
    $response->assertJsonStructure([
        '*' => [
            'id',
            'title',
            'description',
            'is_public',
            'slug',
            'user_guid',
            'token',
            'created_at',
            'updated_at',
        ],
    ]);
});
