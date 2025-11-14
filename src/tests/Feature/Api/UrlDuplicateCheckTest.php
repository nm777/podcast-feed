<?php

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;

test('returns duplicate status for existing URL', function () {
    $user = User::factory()->create();

    // Create existing library item with media file
    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'source_url' => 'https://example.com/existing-audio.mp3',
    ]);
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
        'source_url' => 'https://example.com/existing-audio.mp3',
    ]);

    $response = $this->actingAs($user)->postJson('/api/check-url', [
        'url' => 'https://example.com/existing-audio.mp3',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'is_duplicate' => true,
        'existing_file' => [
            'id' => $mediaFile->id,
            'mime_type' => $mediaFile->mime_type,
            'filesize' => $mediaFile->filesize,
        ],
    ]);
});

test('returns not duplicate for new URL', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/check-url', [
        'url' => 'https://example.com/new-audio.mp3',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'is_duplicate' => false,
        'existing_file' => null,
    ]);
});

test('validates URL format', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/check-url', [
        'url' => 'not-a-valid-url',
    ]);

    $response->assertStatus(422);
    $response->assertJsonStructure([
        'message',
        'errors',
    ]);
    $response->assertJson([
        'message' => 'Please provide a valid URL.',
    ]);
});

test('requires authentication', function () {
    $response = $this->postJson('/api/check-url', [
        'url' => 'https://example.com/test.mp3',
    ]);

    $response->assertStatus(401);
});
