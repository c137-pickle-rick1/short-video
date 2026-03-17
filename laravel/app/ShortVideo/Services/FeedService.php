<?php

namespace App\ShortVideo\Services;

use App\ShortVideo\Repositories\ShortVideoRepository;
use App\ShortVideo\Support\ShortVideoData;

final class FeedService
{
    public const DEFAULT_FEED_LIMIT = 12;
    public const MAX_FEED_LIMIT = 24;
    public const HOME_PAGE_FEED_LIMIT = 8;

    public function __construct(private readonly ShortVideoRepository $repository) {}

    /**
     * @return array<string, mixed>
     */
    public function getFeedPage(?string $cursor = null, ?string $sourceHandle = '', int|string|null $limit = null): array
    {
        $normalizedSourceHandle = ShortVideoData::normalizeHandle($sourceHandle);
        $normalizedLimit = $this->normalizeLimit($limit, self::DEFAULT_FEED_LIMIT);
        $feed = $this->repository->getFeed(
            $cursor,
            $normalizedSourceHandle !== '' ? $normalizedSourceHandle : null,
            $normalizedLimit
        );

        return [
            'items' => array_map([$this, 'mapFeedItemForPresentation'], $feed['items']),
            'nextCursor' => $feed['nextCursor'],
            'sourceHandle' => $normalizedSourceHandle,
            'limit' => $normalizedLimit,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getHomePageViewModel(?string $sourceHandle = '', int|string|null $limit = null): array
    {
        $feed = $this->getFeedPage(null, $sourceHandle, $limit ?? self::HOME_PAGE_FEED_LIMIT);
        $renderedCount = count($feed['items']);
        $done = empty($feed['nextCursor']);

        return [
            'pageTitle' => $feed['sourceHandle'] !== ''
                ? '@'.$feed['sourceHandle'].' · Lagos Explore Feed'
                : 'Lagos Explore Feed',
            'activeSourceHandle' => $feed['sourceHandle'],
            'feed' => [
                'items' => $feed['items'],
                'nextCursor' => $feed['nextCursor'],
                'limit' => $feed['limit'],
                'renderedCount' => $renderedCount,
                'done' => $done,
                'isEmpty' => $renderedCount === 0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapFeedItemForPresentation(array $item): array
    {
        $item['hlsUrl'] = ! empty($item['hlsUrl']) ? (string) $item['hlsUrl'] : null;
        $item['videoUrl'] = ! empty($item['videoUrl']) ? '/api/media/'.$item['tweetId'] : null;

        return $item;
    }

    private function normalizeLimit(int|string|null $limit, int $fallback): int
    {
        $numericLimit = is_numeric((string) $limit) ? (int) $limit : $fallback;

        return max(1, min(self::MAX_FEED_LIMIT, $numericLimit));
    }
}
