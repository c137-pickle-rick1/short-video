<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\ShortVideo\Repositories\ShortVideoRepository;
use App\ShortVideo\Services\FeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FeedController extends Controller
{
    public function index(Request $request, FeedService $feedService): JsonResponse
    {
        $result = $feedService->getFeedPage(
            $request->query('cursor'),
            $request->query('source'),
            $request->query('limit', FeedService::DEFAULT_FEED_LIMIT)
        );

        return response()->json([
            'items' => $result['items'],
            'nextCursor' => $result['nextCursor'],
        ]);
    }

    public function sources(ShortVideoRepository $repository): JsonResponse
    {
        return response()->json([
            'items' => $repository->getSourcesOverview(),
        ]);
    }

    public function stats(ShortVideoRepository $repository): JsonResponse
    {
        return response()->json($repository->getStats());
    }
}
