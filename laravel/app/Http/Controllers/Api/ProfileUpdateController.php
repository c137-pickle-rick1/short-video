<?php

namespace App\Http\Controllers\Api;

use App\Auth\ManagedAvatarService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\ShortVideo\Auth\CurrentViewerResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ProfileUpdateController extends Controller
{
    public function __construct(private readonly ManagedAvatarService $managedAvatarService) {}

    public function store(Request $request, CurrentViewerResolver $currentViewerResolver): JsonResponse
    {
        $viewer = $currentViewerResolver->resolve($request);
        if (! $viewer) {
            return response()->json([
                'message' => '请先登录后再保存资料。',
            ], 401);
        }

        Gate::forUser($viewer)->authorize('updateProfile', $viewer);

        $request->merge(UpdateProfileRequest::normalizedInput($request));

        $payload = $request->validate(
            UpdateProfileRequest::rulesDefinition(),
            UpdateProfileRequest::messagesDefinition()
        );

        $pendingAvatar = null;

        if ($request->hasFile('avatar')) {
            $pendingAvatar = $this->managedAvatarService->store($viewer, $payload['avatar']);

            if ($pendingAvatar === null) {
                return response()->json([
                    'message' => '资料保存失败，请稍后重试。',
                ], 500);
            }
        }

        try {
            $viewer->forceFill([
                'name' => (string) $payload['name'],
                'bio' => $payload['bio'] ?? null,
                'avatar_url' => $pendingAvatar['avatarUrl'] ?? $viewer->avatar_url,
            ])->save();
        } catch (\Throwable $exception) {
            if ($pendingAvatar !== null) {
                $this->managedAvatarService->delete($pendingAvatar['path']);
            }

            throw $exception;
        }

        if ($pendingAvatar !== null) {
            $this->managedAvatarService->prunePrevious($pendingAvatar['previousPath'], $pendingAvatar['path']);
        }

        return response()->json([
            'name' => (string) $viewer->name,
            'bio' => is_string($viewer->bio) && trim($viewer->bio) !== '' ? $viewer->bio : null,
            'avatarUrl' => is_string($viewer->avatar_url) && trim($viewer->avatar_url) !== '' ? $viewer->avatar_url : null,
        ]);
    }
}
