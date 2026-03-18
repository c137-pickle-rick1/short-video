<?php

namespace App\Http\Controllers\Api;

use App\Auth\ManagedAvatarService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadAvatarRequest;
use App\ShortVideo\Auth\CurrentViewerResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ProfileAvatarController extends Controller
{
    public function __construct(private readonly ManagedAvatarService $managedAvatarService) {}

    public function store(Request $request, CurrentViewerResolver $currentViewerResolver): JsonResponse
    {
        $viewer = $currentViewerResolver->resolve($request);
        if (! $viewer) {
            return response()->json([
                'message' => '请先登录后再修改头像。',
            ], 401);
        }

        Gate::forUser($viewer)->authorize('updateAvatar', $viewer);

        $payload = $request->validate(
            UploadAvatarRequest::rulesDefinition(),
            UploadAvatarRequest::messagesDefinition()
        );
        $pendingAvatar = $this->managedAvatarService->store($viewer, $payload['avatar']);

        if ($pendingAvatar === null) {
            return response()->json([
                'message' => '头像上传失败，请稍后重试。',
            ], 500);
        }

        try {
            $viewer->forceFill([
                'avatar_url' => $pendingAvatar['avatarUrl'],
            ])->save();
        } catch (\Throwable $exception) {
            $this->managedAvatarService->delete($pendingAvatar['path']);

            throw $exception;
        }

        $this->managedAvatarService->prunePrevious($pendingAvatar['previousPath'], $pendingAvatar['path']);

        return response()->json([
            'avatarUrl' => $pendingAvatar['avatarUrl'],
        ]);
    }
}
