<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreatorRankingsRequest;
use App\Http\Requests\Api\FeedIndexRequest;
use App\Http\Resources\ShortVideo\CreatorRankingsResource;
use App\Http\Resources\ShortVideo\FeedPageResource;
use App\ShortVideo\Repositories\SourceRepository;
use App\ShortVideo\Services\FeedService;
use Illuminate\Http\JsonResponse;

final class FeedController extends Controller
{
    public function index(FeedIndexRequest $request, FeedService $feedService): JsonResponse
    {
        $result = $feedService->getFeedPage(
            $request->validated('cursor'),
            $request->validated('source'),
            $request->validated('limit', FeedService::DEFAULT_FEED_LIMIT),
            (string) $request->validated('mode', FeedService::MODE_EXPLORE)
        );

        if (($result['requiresAuth'] ?? false) === true) {
            return response()->json([
                'message' => 'Login required for following feed.',
            ], 401);
        }

        return (new FeedPageResource([
            'items' => $result['items'],
            'nextCursor' => $result['nextCursor'],
        ]))->response();
    }

    public function sources(SourceRepository $sources): JsonResponse
    {
        return response()->json([
            'items' => $sources->getSourcesOverview(),
        ]);
    }

    public function stats(SourceRepository $sources): JsonResponse
    {
        return response()->json($sources->getStats());
    }

    public function creators(CreatorRankingsRequest $request, FeedService $feedService): JsonResponse
    {
        return (new CreatorRankingsResource(
            $feedService->getCreatorRankingsApiPayload(
                $request->validated('limit'),
                $request->validated('window', '7d')
            )
        ))->response();
    }
}
