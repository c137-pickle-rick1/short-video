<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShortVideo\VideoEngagementStateResource;
use App\Models\Video;
use App\ShortVideo\Auth\CurrentViewerResolver;
use App\ShortVideo\Repositories\EngagementRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class VideoLikeController extends Controller
{
    public function store(
        Request $request,
        Video $video,
        CurrentViewerResolver $currentViewerResolver,
        EngagementRepository $engagement
    ): JsonResponse {
        return $this->updateState($request, $video, true, $currentViewerResolver, $engagement);
    }

    public function destroy(
        Request $request,
        Video $video,
        CurrentViewerResolver $currentViewerResolver,
        EngagementRepository $engagement
    ): JsonResponse {
        return $this->updateState($request, $video, false, $currentViewerResolver, $engagement);
    }

    private function updateState(
        Request $request,
        Video $video,
        bool $liked,
        CurrentViewerResolver $currentViewerResolver,
        EngagementRepository $engagement
    ): JsonResponse {
        $viewer = $currentViewerResolver->resolve($request);
        if (! $viewer) {
            return response()->json([
                'message' => 'Login required for like actions.',
            ], 401);
        }

        Gate::forUser($viewer)->authorize('like', $video);

        return (new VideoEngagementStateResource([
            'videoId' => $video->id,
            'engagement' => $engagement->setVideoLikeState($video->id, $viewer->id, $liked),
        ]))->response();
    }
}
