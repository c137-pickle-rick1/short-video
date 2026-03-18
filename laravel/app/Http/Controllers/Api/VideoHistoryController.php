<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\ShortVideo\Auth\CurrentViewerResolver;
use App\ShortVideo\Repositories\EngagementRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VideoHistoryController extends Controller
{
    public function clear(
        Request $request,
        CurrentViewerResolver $currentViewerResolver,
        EngagementRepository $engagement
    ): JsonResponse {
        $viewer = $currentViewerResolver->resolve($request);
        if (! $viewer) {
            return response()->json([
                'message' => 'Login required for history actions.',
            ], 401);
        }

        return response()->json([
            'removedCount' => $engagement->clearViewerHistory($viewer->id),
        ]);
    }

    public function destroy(
        Request $request,
        Video $video,
        CurrentViewerResolver $currentViewerResolver,
        EngagementRepository $engagement
    ): JsonResponse {
        $viewer = $currentViewerResolver->resolve($request);
        if (! $viewer) {
            return response()->json([
                'message' => 'Login required for history actions.',
            ], 401);
        }

        return response()->json([
            'videoId' => $video->id,
            'removed' => $engagement->deleteViewerHistory($video->id, $viewer->id) > 0,
        ]);
    }
}
