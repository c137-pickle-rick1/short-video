<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadVideoRequest;
use App\ShortVideo\Auth\CurrentViewerResolver;
use App\ShortVideo\Services\VideoUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class VideoUploadController extends Controller
{
    public function store(
        Request $request,
        CurrentViewerResolver $currentViewerResolver,
        VideoUploadService $videoUploadService
    ): JsonResponse {
        $viewer = $currentViewerResolver->resolve($request);

        if (! $viewer) {
            return response()->json([
                'message' => '请先登录后再上传视频。',
            ], 401);
        }

        Gate::forUser($viewer)->authorize('uploadVideo', $viewer);

        $request->merge(UploadVideoRequest::normalizedInput($request));
        $payload = $request->validate(
            UploadVideoRequest::rulesDefinition(),
            UploadVideoRequest::messagesDefinition()
        );
        $uploadedVideo = $request->file('video');

        if (! $uploadedVideo) {
            return response()->json([
                'message' => '请选择要上传的视频文件。',
            ], 422);
        }

        $video = $videoUploadService->store(
            viewer: $viewer,
            uploadedVideo: $uploadedVideo,
            title: (string) $payload['title'],
            tags: isset($payload['tags']) ? (string) $payload['tags'] : null
        );

        if (! $video) {
            return response()->json([
                'message' => '视频上传失败，请稍后重试。',
            ], 500);
        }

        return response()->json([
            'id' => $video->id,
            'title' => (string) ($video->title ?? ''),
            'tags' => is_string($video->description) && trim($video->description) !== ''
                ? $video->description
                : null,
            'status' => (string) $video->status,
            'statusLabel' => '上传中',
            'redirectUrl' => route('profile.show', [
                'username' => $viewer->username,
                'tab' => 'uploading',
            ]),
        ]);
    }
}
