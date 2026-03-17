<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\ShortVideo\Auth\CurrentViewerResolver;
use App\ShortVideo\Repositories\ShortVideoRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VideoCommentController extends Controller
{
    public function index(Video $video, ShortVideoRepository $repository): JsonResponse
    {
        $comments = $repository->listVideoComments($video->id);

        return response()->json([
            'videoId' => $video->id,
            'items' => $comments,
            'totalCount' => count($comments),
        ]);
    }

    public function store(
        Request $request,
        Video $video,
        CurrentViewerResolver $currentViewerResolver,
        ShortVideoRepository $repository
    ): JsonResponse {
        $viewer = $currentViewerResolver->resolve($request);
        if (! $viewer) {
            return response()->json([
                'message' => 'Login required for comment actions.',
            ], 401);
        }

        $payload = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:500'],
        ], [
            'body.required' => '评论内容不能为空。',
            'body.min' => '评论内容不能为空。',
            'body.max' => '评论内容不能超过 500 个字符。',
        ]);

        $body = trim((string) $payload['body']);
        if ($body === '') {
            return response()->json([
                'message' => '评论内容不能为空。',
            ], 422);
        }

        return response()->json([
            'videoId' => $video->id,
            'item' => $repository->createVideoComment($video->id, $viewer->id, $body),
            'engagement' => $repository->getVideoEngagementSnapshot($video->id, $viewer->id),
        ], 201);
    }
}
