<?php

use App\Models\LibraryItem;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

it('allows users to only access their own media files', function () {
    Storage::fake('public');

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Create a media file for user1 with actual file in storage
    Storage::disk('public')->put('media/test-file.mp3', 'fake audio content');
    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user1->id,
        'file_path' => 'media/test-file.mp3',
        'file_hash' => 'test-hash-123',
    ]);

    // Create library item for user1
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user1->id,
        'media_file_id' => $mediaFile->id,
    ]);

    // User1 should be able to access their own media file
    actingAs($user1)
        ->get("/files/{$mediaFile->file_path}")
        ->assertStatus(200);

    // User2 should not be able to access user1's media file
    actingAs($user2)
        ->get("/files/{$mediaFile->file_path}")
        ->assertStatus(403);
});

it('allows duplicate files for different users but links to existing media without duplicate flag', function () {
    Storage::fake('public');

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Create a test file with specific content
    $fileContent = 'fake audio content for testing';
    $fileHash = hash('sha256', $fileContent);

    // Create uploaded file with the same content
    $uploadedFile = UploadedFile::fake()->createWithContent('test.mp3', $fileContent);

    // User1 uploads the file first
    actingAs($user1)
        ->post('/library', [
            'title' => 'Test Audio 1',
            'source_type' => 'upload',
            'file' => $uploadedFile,
        ]);

    // Check that media file was created for user1
    $mediaFile1 = MediaFile::where('file_hash', $fileHash)->first();
    expect($mediaFile1)->not->toBeNull();
    expect($mediaFile1->user_id)->toBe($user1->id);

    // User2 uploads the same file content
    $uploadedFile2 = UploadedFile::fake()->createWithContent('test.mp3', $fileContent);
    actingAs($user2)
        ->post('/library', [
            'title' => 'Test Audio 2',
            'source_type' => 'upload',
            'file' => $uploadedFile2,
        ]);

    // Check that user2's library item links to the same media file
    $libraryItem2 = LibraryItem::where('user_id', $user2->id)
        ->where('title', 'Test Audio 2')
        ->first();

    expect($libraryItem2)->not->toBeNull();
    expect($libraryItem2->media_file_id)->toBe($mediaFile1->id);
    expect($libraryItem2->is_duplicate)->toBeFalse(); // Cross-user links are not duplicates
});

it('prevents users from accessing other users library items', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Create library item for user1
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user1->id,
    ]);

    // User2 should not be able to delete user1's library item
    actingAs($user2)
        ->delete("/library/{$libraryItem->id}")
        ->assertStatus(403);

    // User1 should be able to delete their own library item
    actingAs($user1)
        ->delete("/library/{$libraryItem->id}")
        ->assertRedirect('/library');
});

it('only shows user-specific duplicates in URL check API', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Create a media file for user1
    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user1->id,
        'source_url' => 'https://example.com/audio.mp3',
    ]);

    // User1 should see the duplicate
    actingAs($user1)
        ->post('/api/check-url', ['url' => 'https://example.com/audio.mp3'])
        ->assertJson([
            'is_duplicate' => true,
            'existing_file' => [
                'id' => $mediaFile->id,
            ],
        ]);

    // User2 should not see the duplicate
    actingAs($user2)
        ->post('/api/check-url', ['url' => 'https://example.com/audio.mp3'])
        ->assertJson([
            'is_duplicate' => false,
            'existing_file' => null,
        ]);
});

it('creates new media file when user uploads file with different hash', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    // Create a test file
    $uploadedFile = UploadedFile::fake()->create('unique-audio.mp3', 1000);

    // User uploads the file
    actingAs($user)
        ->post('/library', [
            'title' => 'Unique Audio',
            'source_type' => 'upload',
            'file' => $uploadedFile,
        ]);

    // Check that media file was created for the user
    $libraryItem = LibraryItem::where('user_id', $user->id)
        ->where('title', 'Unique Audio')
        ->first();

    expect($libraryItem)->not->toBeNull();
    expect($libraryItem->media_file_id)->not->toBeNull();

    $mediaFile = MediaFile::find($libraryItem->media_file_id);
    expect($mediaFile->user_id)->toBe($user->id);
    expect($libraryItem->is_duplicate)->toBeFalse();
});

it('correctly sets is_duplicate flag for true duplicate file uploads', function () {
    Storage::fake('public');
    Queue::fake(); // Prevent jobs from actually running

    $user = User::factory()->create();

    // Create a media file first to simulate a previous upload
    $fileContent = 'fake audio content for testing';
    $fileHash = hash('sha256', $fileContent);

    // Store the file in storage to match the factory
    Storage::disk('public')->put('media/'.$fileHash.'.mp3', $fileContent);

    $existingMediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'file_hash' => $fileHash,
        'file_path' => 'media/'.$fileHash.'.mp3',
    ]);

    // Create a library item that references this media file
    LibraryItem::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $existingMediaFile->id,
        'title' => 'Original File',
        'source_type' => 'upload',
        'processing_status' => \App\ProcessingStatusType::COMPLETED,
    ]);

    // Now upload the same file content again
    $uploadedFile = UploadedFile::fake()->createWithContent('test.mp3', $fileContent);

    $response = actingAs($user)
        ->post('/library', [
            'title' => 'Duplicate Test',
            'source_type' => 'upload',
            'file' => $uploadedFile,
        ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success', 'Duplicate file detected. This file already exists in your library and will be removed automatically in 5 minutes.');

    // Check that library item was created with is_duplicate flag
    $libraryItem = LibraryItem::where('user_id', $user->id)
        ->where('title', 'Duplicate Test')
        ->first();
    expect($libraryItem)->not->toBeNull();
    expect($libraryItem->is_duplicate)->toBeTrue();
    expect($libraryItem->media_file_id)->toBe($existingMediaFile->id);
});

it('detects duplicate YouTube URLs correctly', function () {
    Storage::fake('public');

    $user1 = User::factory()->create();

    // Create a media file for user1 with YouTube URL
    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user1->id,
        'source_url' => 'https://www.youtube.com/watch?v=test123',
        'file_hash' => 'youtube-hash-123',
    ]);

    // User1 tries to add same YouTube URL (should show duplicate warning)
    $response = actingAs($user1)
        ->post('/library', [
            'title' => 'Duplicate YouTube',
            'source_type' => 'youtube',
            'source_url' => 'https://www.youtube.com/watch?v=test123',
        ]);

    $response->assertRedirect('/library');
    $response->assertSessionHas('success', 'Duplicate URL detected. This file already exists in your library and will be removed automatically in 5 minutes.');
});
