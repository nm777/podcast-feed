<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\YouTubeVideoInfoService;
use Illuminate\Http\JsonResponse;

class YouTubeController extends Controller
{
    public function __construct(
        private YouTubeVideoInfoService $youTubeVideoInfoService
    ) {}

    /**
     * Get YouTube video information.
     */
    public function getVideoInfo(string $videoId): JsonResponse
    {
        $videoInfo = $this->youTubeVideoInfoService->getVideoInfo($videoId);

        if (! $videoInfo) {
            throw new ApiException('Video not found', 'VIDEO_NOT_FOUND', 404);
        }

        return response()->json($videoInfo);
    }
}
