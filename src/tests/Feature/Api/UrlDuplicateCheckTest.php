<?php

use App\Models\MediaFile;
use App\Models\User;

test('returns duplicate status for existing URL', function () {
    $user = User::factory()->create();

    // Create existing media file with specific URL
    $mediaFile = MediaFile::factory()->create([
        'source_url' => 'https://example.com/existing-audio.mp3',
    ]);

    $response = $this->actingAs($user)->postJson('/check-url-duplicate', [
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

    $response = $this->actingAs($user)->postJson('/check-url-duplicate', [
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

    $response = $this->actingAs($user)->postJson('/check-url-duplicate', [
        'url' => 'not-a-valid-url',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'error' => 'Invalid URL',
    ]);
});

test('requires authentication', function () {
    $response = $this->postJson('/check-url-duplicate', [
        'url' => 'https://example.com/test.mp3',
    ]);

    $response->assertStatus(401);
});
