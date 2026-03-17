<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\ShortVideo\Auth\CurrentViewerResolver;
use App\ShortVideo\Repositories\ShortVideoRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserFollowController extends Controller
{
    public function store(
        Request $request,
        User $user,
        CurrentViewerResolver $currentViewerResolver,
        ShortVideoRepository $repository
    ): JsonResponse {
        return $this->updateFollowState($request, $user, true, $currentViewerResolver, $repository);
    }

    public function destroy(
        Request $request,
        User $user,
        CurrentViewerResolver $currentViewerResolver,
        ShortVideoRepository $repository
    ): JsonResponse {
        return $this->updateFollowState($request, $user, false, $currentViewerResolver, $repository);
    }

    private function updateFollowState(
        Request $request,
        User $user,
        bool $shouldFollow,
        CurrentViewerResolver $currentViewerResolver,
        ShortVideoRepository $repository
    ): JsonResponse {
        $viewer = $currentViewerResolver->resolve($request);
        if (! $viewer) {
            return response()->json([
                'message' => 'No active viewer is available for follow actions.',
            ], 401);
        }

        if ($viewer->is($user)) {
            return response()->json([
                'message' => 'You cannot follow yourself.',
            ], 422);
        }

        if ($shouldFollow) {
            $repository->followUser($viewer->id, $user->id);
        } else {
            $repository->unfollowUser($viewer->id, $user->id);
        }

        return response()->json([
            'viewerUserId' => $viewer->id,
            'authorUserId' => $user->id,
            'following' => $shouldFollow,
        ]);
    }
}
