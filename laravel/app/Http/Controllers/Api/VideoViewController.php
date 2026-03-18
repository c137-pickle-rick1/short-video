<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RecordVideoViewRequest;
use App\Http\Resources\ShortVideo\VideoViewStateResource;
use App\Models\Video;
use App\ShortVideo\Auth\CurrentViewerResolver;
use App\ShortVideo\Repositories\EngagementRepository;
use App\ShortVideo\Support\ShortVideoData;
use Illuminate\Http\JsonResponse;

final class VideoViewController extends Controller
{
    public function store(
        RecordVideoViewRequest $request,
        Video $video,
        CurrentViewerResolver $currentViewerResolver,
        EngagementRepository $engagement
    ): JsonResponse {
        $sessionId = ShortVideoData::normalizeSessionId((string) $request->validated('sessionId'));
        if ($sessionId === '') {
            return response()->json([
                'message' => 'A valid sessionId is required.',
            ], 422);
        }

        $viewer = $currentViewerResolver->resolve($request);
        $recorded = $engagement->recordVideoView($video->id, $viewer?->id, $sessionId);

        return (new VideoViewStateResource([
            'videoId' => $video->id,
            'recorded' => $recorded,
            'engagement' => $engagement->getVideoEngagementSnapshot($video->id, $viewer?->id),
        ]))->response();
    }
}
