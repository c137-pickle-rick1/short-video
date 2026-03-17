<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\ShortVideo\Auth\CurrentViewerResolver;
use App\ShortVideo\Repositories\ShortVideoRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VideoLikeController extends Controller
{
    public function store(
        Request $request,
        Video $video,
        CurrentViewerResolver $currentViewerResolver,
        ShortVideoRepository $repository
    ): JsonResponse {
        return $this->updateState($request, $video, true, $currentViewerResolver, $repository);
    }

    public function destroy(
        Request $request,
        Video $video,
        CurrentViewerResolver $currentViewerResolver,
        ShortVideoRepository $repository
    ): JsonResponse {
        return $this->updateState($request, $video, false, $currentViewerResolver, $repository);
    }

    private function updateState(
        Request $request,
        Video $video,
        bool $liked,
        CurrentViewerResolver $currentViewerResolver,
        ShortVideoRepository $repository
    ): JsonResponse {
        $viewer = $currentViewerResolver->resolve($request);
        if (! $viewer) {
            return response()->json([
                'message' => 'Login required for like actions.',
            ], 401);
        }

        return response()->json([
            'videoId' => $video->id,
            'engagement' => $repository->setVideoLikeState($video->id, $viewer->id, $liked),
        ]);
    }
}
