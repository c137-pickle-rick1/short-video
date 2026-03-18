<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShortVideo\UserFollowStateResource;
use App\Models\User;
use App\ShortVideo\Auth\CurrentViewerResolver;
use App\ShortVideo\Repositories\SocialGraphRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class UserFollowController extends Controller
{
    public function store(
        Request $request,
        User $user,
        CurrentViewerResolver $currentViewerResolver,
        SocialGraphRepository $socialGraph
    ): JsonResponse {
        return $this->updateFollowState($request, $user, true, $currentViewerResolver, $socialGraph);
    }

    public function destroy(
        Request $request,
        User $user,
        CurrentViewerResolver $currentViewerResolver,
        SocialGraphRepository $socialGraph
    ): JsonResponse {
        return $this->updateFollowState($request, $user, false, $currentViewerResolver, $socialGraph);
    }

    private function updateFollowState(
        Request $request,
        User $user,
        bool $shouldFollow,
        CurrentViewerResolver $currentViewerResolver,
        SocialGraphRepository $socialGraph
    ): JsonResponse {
        $viewer = $currentViewerResolver->resolve($request);
        if (! $viewer) {
            return response()->json([
                'message' => 'No active viewer is available for follow actions.',
            ], 401);
        }

        $authorization = Gate::forUser($viewer)->inspect('follow', $user);
        if (! $authorization->allowed()) {
            return response()->json([
                'message' => $authorization->message() ?: 'You cannot follow yourself.',
            ], 422);
        }

        if ($shouldFollow) {
            $socialGraph->followUser($viewer->id, $user->id);
        } else {
            $socialGraph->unfollowUser($viewer->id, $user->id);
        }

        return (new UserFollowStateResource([
            'viewerUserId' => $viewer->id,
            'authorUserId' => $user->id,
            'following' => $shouldFollow,
        ]))->response();
    }
}
