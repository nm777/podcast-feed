<?php

use App\Http\Requests\Api\LibraryItemRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('API Library Controller Validation', function () {
    beforeEach(function () {
        $this->user = \App\Models\User::factory()->create();
    });

    it('validates library item creation with proper rules', function () {
        $this->actingAs($this->user);

        // Test missing required fields
        $response = $this->postJson('/api/library', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'source_type']);
    });

    it('validates file upload requirements', function () {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/library', [
            'title' => 'Test Upload',
            'source_type' => 'upload',
            // Missing file
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    });

    it('validates URL requirements for url source type', function () {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/library', [
            'title' => 'Test URL',
            'source_type' => 'url',
            // Missing source_url
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['source_url']);
    });

    it('validates YouTube URL requirements for youtube source type', function () {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/library', [
            'title' => 'Test YouTube',
            'source_type' => 'youtube',
            // Missing source_url
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['source_url']);
    });

    it('validates invalid YouTube URL', function () {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/library', [
            'title' => 'Test YouTube',
            'source_type' => 'youtube',
            'source_url' => 'invalid-youtube-url',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['source_url']);
    });

    it('accepts valid library item data', function () {
        $this->actingAs($this->user);

        // Fake the queue to avoid actual processing
        \Illuminate\Support\Facades\Queue::fake();

        $response = $this->postJson('/api/library', [
            'title' => 'Valid Library Item',
            'description' => 'A valid description',
            'source_type' => 'url',
            'source_url' => 'https://example.com/audio.mp3',
        ]);

        $response->assertStatus(201);

        // Assert job was dispatched
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ProcessMediaFile::class);

        // Assert response contains expected data
        $response->assertJsonPath('data.title', 'Valid Library Item');
        $response->assertJsonPath('data.source_type', 'url');
        $response->assertJsonPath('data.source_url', 'https://example.com/audio.mp3');
    });

    it('uses consolidated validation rules from form request', function () {
        $request = new LibraryItemRequest;

        $rules = $request->rules();

        expect($rules)->toHaveKey('title');
        expect($rules)->toHaveKey('source_type');
        expect($rules)->toHaveKey('file');
        expect($rules)->toHaveKey('source_url');
        expect($rules)->toHaveKey('description');

        expect($rules['title'])->toContain('required');
        expect($rules['source_type'])->toContain('required');
        expect($rules['source_type'])->toContain('in:upload,url,youtube');
    });
});
