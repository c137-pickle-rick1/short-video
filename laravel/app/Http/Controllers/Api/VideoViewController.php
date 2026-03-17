<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\ShortVideo\Auth\CurrentViewerResolver;
use App\ShortVideo\Repositories\ShortVideoRepository;
use App\ShortVideo\Support\ShortVideoData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VideoViewController extends Controller
{
    public function store(
        Request $request,
        Video $video,
        CurrentViewerResolver $currentViewerResolver,
        ShortVideoRepository $repository
    ): JsonResponse {
        $payload = $request->validate([
            'sessionId' => ['required', 'string', 'min:8', 'max:120'],
        ]);

        $sessionId = ShortVideoData::normalizeSessionId((string) $payload['sessionId']);
        if ($sessionId === '') {
            return response()->json([
                'message' => 'A valid sessionId is required.',
            ], 422);
        }

        $viewer = $currentViewerResolver->resolve($request);
        $recorded = $repository->recordVideoView($video->id, $viewer?->id, $sessionId);

        return response()->json([
            'videoId' => $video->id,
            'recorded' => $recorded,
            'engagement' => $repository->getVideoEngagementSnapshot($video->id, $viewer?->id),
        ]);
    }
}
