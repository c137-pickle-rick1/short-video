<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreVideoCommentRequest;
use App\Http\Resources\ShortVideo\VideoCommentCreatedResource;
use App\Http\Resources\ShortVideo\VideoCommentsResource;
use App\Models\Video;
use App\Models\VideoComment;
use App\ShortVideo\Auth\CurrentViewerResolver;
use App\ShortVideo\Repositories\EngagementRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class VideoCommentController extends Controller
{
    public function index(Video $video, EngagementRepository $engagement): JsonResponse
    {
        $comments = $engagement->listVideoComments($video->id);

        return (new VideoCommentsResource([
            'videoId' => $video->id,
            'items' => $comments,
            'totalCount' => count($comments),
        ]))->response();
    }

    public function store(
        StoreVideoCommentRequest $request,
        Video $video,
        CurrentViewerResolver $currentViewerResolver,
        EngagementRepository $engagement
    ): JsonResponse {
        $viewer = $currentViewerResolver->resolve($request);
        if (! $viewer) {
            return response()->json([
                'message' => 'Login required for comment actions.',
            ], 401);
        }

        Gate::forUser($viewer)->authorize('comment', $video);

        $body = (string) $request->validated('body');

        return (new VideoCommentCreatedResource([
            'videoId' => $video->id,
            'item' => $engagement->createVideoComment($video->id, $viewer->id, $body),
            'engagement' => $engagement->getVideoEngagementSnapshot($video->id, $viewer->id),
        ]))->response()->setStatusCode(201);
    }

    public function destroy(
        Request $request,
        Video $video,
        VideoComment $comment,
        CurrentViewerResolver $currentViewerResolver,
        EngagementRepository $engagement
    ): JsonResponse {
        $viewer = $currentViewerResolver->resolve($request);
        if (! $viewer) {
            return response()->json([
                'message' => 'Login required for comment actions.',
            ], 401);
        }

        abort_if($comment->video_id !== $video->id, 404);

        Gate::forUser($viewer)->authorize('delete', $comment);

        return response()->json([
            'videoId' => $video->id,
            'commentId' => $comment->id,
            'removed' => $engagement->deleteViewerComment($comment->id, $video->id, $viewer->id) > 0,
            'engagement' => $engagement->getVideoEngagementSnapshot($video->id, $viewer->id),
        ]);
    }
}
