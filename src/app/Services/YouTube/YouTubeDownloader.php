<?php

namespace App\Services\YouTube;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class YouTubeDownloader
{
    /**
     * Download audio from YouTube URL using yt-dlp.
     */
    public function downloadAudio(string $youtubeUrl, string $tempDir): ?string
    {
        $tempPath = $tempDir.'/audio.%(ext)s';

        Log::info('Setting up YouTube download', [
            'youtube_url' => $youtubeUrl,
            'temp_dir' => $tempDir,
            'temp_path' => $tempPath,
        ]);

        try {
            // Create temp directory
            Storage::disk('public')->makeDirectory($tempDir);
            Log::info('Created temp directory', ['temp_dir' => $tempDir]);

            // Download audio using yt-dlp
            $command = [
                'yt-dlp',
                '--extract-audio',
                '--audio-format',
                'mp3',
                '--audio-quality',
                '0', // best quality
                '--no-playlist',
                '--output',
                Storage::disk('public')->path($tempPath),
                $youtubeUrl,
            ];

            Log::info('Running yt-dlp command', [
                'command' => implode(' ', $command),
            ]);

            $process = new Process($command);
            $process->setTimeout(300); // 5 minutes timeout
            $process->run();

            Log::info('yt-dlp command completed', [
                'is_successful' => $process->isSuccessful(),
                'exit_code' => $process->getExitCode(),
                'output' => $process->getOutput(),
                'error_output' => $process->getErrorOutput(),
            ]);

            if (! $process->isSuccessful()) {
                Log::error('yt-dlp command failed', [
                    'exit_code' => $process->getExitCode(),
                    'output' => $process->getOutput(),
                    'error_output' => $process->getErrorOutput(),
                ]);
                throw new ProcessFailedException($process);
            }

            // Find downloaded file (yt-dlp might create different extensions)
            $downloadedFile = $this->findDownloadedFile($tempDir);

            Log::info('Looking for downloaded files', [
                'temp_dir' => $tempDir,
                'files_found' => Storage::disk('public')->allFiles($tempDir),
                'downloaded_file' => $downloadedFile,
            ]);

            if (! $downloadedFile || ! Storage::disk('public')->exists($downloadedFile)) {
                Log::error('No downloaded file found', [
                    'temp_dir' => $tempDir,
                    'files_found' => Storage::disk('public')->allFiles($tempDir),
                    'downloaded_file' => $downloadedFile,
                    'file_exists' => $downloadedFile ? Storage::disk('public')->exists($downloadedFile) : false,
                ]);

                return null;
            }

            Log::info('Found downloaded file', [
                'downloaded_file' => $downloadedFile,
                'file_size' => Storage::disk('public')->size($downloadedFile),
            ]);

            return $downloadedFile;

        } catch (\Exception $e) {
            Log::error('YouTube download failed', [
                'youtube_url' => $youtubeUrl,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Find the downloaded audio file in temp directory.
     */
    private function findDownloadedFile(string $tempDir): ?string
    {
        $files = Storage::disk('public')->allFiles($tempDir);

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_FILENAME) === 'audio') {
                return $file;
            }
        }

        return null;
    }

    /**
     * Clean up temporary directory.
     */
    public function cleanupTempDirectory(string $tempDir): void
    {
        if (Storage::disk('public')->exists($tempDir)) {
            Storage::disk('public')->deleteDirectory($tempDir);
            Log::info('Cleaned up temp directory', ['temp_dir' => $tempDir]);
        }
    }
}
