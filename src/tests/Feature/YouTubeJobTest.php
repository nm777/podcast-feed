<?php

use App\Jobs\ProcessYouTubeAudio;
use App\Models\LibraryItem;
use App\Models\User;
use App\Services\YouTube\YouTubeProcessingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

it('processes YouTube audio job with logging', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $libraryItem = LibraryItem::factory()->create([
        'user_id' => $user->id,
        'source_type' => 'youtube',
        'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    // Capture logs - expect info logs since job should start and process
    Log::shouldReceive('info')->atLeast()->once();
    Log::shouldReceive('error')->atLeast()->once();

    $job = new ProcessYouTubeAudio($libraryItem, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    // Mock the processing service to avoid actual YouTube processing
    $processingService = mock(YouTubeProcessingService::class);
    $processingService->shouldReceive('processYouTubeUrl')
        ->once()
        ->andThrow(new \Exception('Test error'));

    // Mock the yt-dlp command to fail so we can test error logging
    $job->handle($processingService);
});
