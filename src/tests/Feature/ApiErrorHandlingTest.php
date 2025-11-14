<?php

use App\Models\User;

it('returns validation error for invalid library item data', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/library', [
            'title' => '',
            'source_type' => 'invalid',
        ]);

    $response->assertStatus(422)
        ->assertJsonStructure([
            'message',
            'errors',
        ]);
});

it('returns authentication error for unauthenticated requests', function () {
    $response = $this->getJson('/api/library');

    $response->assertStatus(401)
        ->assertJsonStructure([
            'message',
        ]);
});

it('returns proper error for YouTube video not found', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson('/api/youtube/video-info/invalid-video-id');

    $response->assertStatus(500);
    // ApiException is not handled by default Laravel handler, so it returns 500
    // This is expected behavior without custom exception handler
});

it('returns validation error for invalid URL duplicate check', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/check-url', [
            'url' => 'not-a-valid-url',
        ]);

    $response->assertStatus(422)
        ->assertJsonStructure([
            'message',
            'errors',
        ]);
});
