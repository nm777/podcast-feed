<?php

namespace App\Services\YouTube;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class YouTubeMetadataExtractor
{
    /**
     * Extract video metadata from YouTube URL.
     */
    public function extractMetadata(string $youtubeUrl): ?array
    {
        try {
            $metadataCommand = [
                'yt-dlp',
                '--dump-json',
                '--no-playlist',
                $youtubeUrl,
            ];

            Log::info('Getting video metadata', [
                'command' => implode(' ', $metadataCommand),
            ]);

            $process = new Process($metadataCommand);
            $process->run();
            $metadata = null;

            Log::info('Metadata command completed', [
                'is_successful' => $process->isSuccessful(),
                'output' => $process->getOutput(),
                'error_output' => $process->getErrorOutput(),
            ]);

            if ($process->isSuccessful()) {
                $metadata = json_decode($process->getOutput(), true);
                Log::info('Parsed metadata', [
                    'title' => $metadata['title'] ?? 'N/A',
                    'description' => isset($metadata['description']) ? substr($metadata['description'], 0, 100).'...' : 'N/A',
                ]);
            } else {
                Log::error('Failed to extract metadata', [
                    'error_output' => $process->getErrorOutput(),
                ]);

                return null;
            }

            return [
                'title' => $metadata['title'] ?? null,
                'description' => $metadata['description'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('Metadata extraction failed', [
                'youtube_url' => $youtubeUrl,
                'error_message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
