<?php

namespace App\ShortVideo\Services;

use App\Models\User;
use App\ShortVideo\Auth\CurrentViewerResolver;
use App\ShortVideo\Repositories\FeedRepository;
use App\ShortVideo\Repositories\SocialGraphRepository;
use App\ShortVideo\Support\FeedConfig;
use App\ShortVideo\Support\FeedCursor;
use App\ShortVideo\Support\ShortVideoData;

final class FeedQueryService
{
    public function __construct(
        private readonly FeedRepository $feeds,
        private readonly SocialGraphRepository $socialGraph,
        private readonly CurrentViewerResolver $currentViewerResolver
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getFeedPage(
        ?string $cursor = null,
        ?string $sourceHandle = '',
        int|string|null $limit = null,
        string $mode = FeedConfig::MODE_EXPLORE
    ): array {
        $normalizedMode = $this->normalizeMode($mode);
        $normalizedSourceHandle = $normalizedMode === FeedConfig::MODE_EXPLORE
            ? ShortVideoData::normalizeHandle($sourceHandle)
            : '';
        $normalizedLimit = $this->normalizeLimit($limit, FeedConfig::DEFAULT_FEED_LIMIT);
        $viewer = $this->currentViewerResolver->resolve();

        if ($normalizedMode === FeedConfig::MODE_FOLLOWING && ! $viewer) {
            return [
                'items' => [],
                'nextCursor' => null,
                'sourceHandle' => '',
                'limit' => $normalizedLimit,
                'viewer' => null,
                'headerViewer' => null,
                'mode' => $normalizedMode,
                'requiresAuth' => true,
            ];
        }

        $feed = $normalizedMode === FeedConfig::MODE_FEATURED
            ? $this->getFeaturedFeedPayload($cursor, $normalizedLimit, $viewer?->id)
            : $this->feeds->getFeed(
                $cursor,
                $normalizedSourceHandle !== '' ? $normalizedSourceHandle : null,
                $normalizedLimit,
                $normalizedMode,
                $viewer?->id
            );

        $followedAuthorIds = $viewer
            ? $this->socialGraph->getFollowedUserIds(
                $viewer->id,
                array_map(
                    static fn (array $item): mixed => $item['authorUserId'] ?? null,
                    $feed['items']
                )
            )
            : [];

        return [
            'items' => array_map(
                fn (array $item) => $this->mapFeedItemForPresentation($item, $viewer?->id, $followedAuthorIds),
                $feed['items']
            ),
            'nextCursor' => $feed['nextCursor'],
            'sourceHandle' => $normalizedSourceHandle,
            'limit' => $normalizedLimit,
            'viewer' => $viewer ? [
                'id' => $viewer->id,
                'username' => $viewer->username,
            ] : null,
            'headerViewer' => $this->mapViewerSummary($viewer),
            'mode' => $normalizedMode,
            'requiresAuth' => false,
        ];
    }

    public function formatFeedSummary(string $mode, string $sourceHandle, int $renderedCount, bool $done): string
    {
        $normalizedMode = $this->normalizeMode($mode);
        $sourceLabel = match ($normalizedMode) {
            FeedConfig::MODE_FEATURED => '精选',
            FeedConfig::MODE_FOLLOWING => '订阅更新',
            default => $sourceHandle !== '' ? '@'.$sourceHandle : '全部来源',
        };

        if ($renderedCount === 0 && $done) {
            return "{$sourceLabel} 暂无内容";
        }

        if ($renderedCount === 0) {
            return match ($normalizedMode) {
                FeedConfig::MODE_FEATURED, FeedConfig::MODE_FOLLOWING => "{$sourceLabel} 正在加载…",
                default => "{$sourceLabel} 正在加载探索内容…",
            };
        }

        return $sourceLabel.' · 已展示 '.$renderedCount.' 条 · '.($done ? '已加载完毕' : '向下滚动继续加载');
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<int>  $followedAuthorIds
     * @return array<string, mixed>
     */
    private function mapFeedItemForPresentation(array $item, ?int $viewerUserId, array $followedAuthorIds): array
    {
        $authorUserId = is_numeric((string) ($item['authorUserId'] ?? null)) ? (int) $item['authorUserId'] : null;
        $item['hlsUrl'] = ! empty($item['hlsUrl']) ? (string) $item['hlsUrl'] : null;
        $item['videoUrl'] = ! empty($item['tweetId'])
            ? '/api/media/'.$item['tweetId']
            : (! empty($item['videoUrl']) ? (string) $item['videoUrl'] : null);
        $item['authorUserId'] = $authorUserId;
        $item['viewerUserId'] = $viewerUserId;
        $item['canFollowAuthor'] = $viewerUserId !== null && $authorUserId !== null && $viewerUserId !== $authorUserId;
        $item['authorFollowedByViewer'] = $item['canFollowAuthor'] && in_array($authorUserId, $followedAuthorIds, true);
        $item['engagement'] = [
            'likeCount' => (int) ($item['engagement']['likeCount'] ?? 0),
            'bookmarkCount' => (int) ($item['engagement']['bookmarkCount'] ?? 0),
            'commentCount' => (int) ($item['engagement']['commentCount'] ?? 0),
            'viewCount' => (int) ($item['engagement']['viewCount'] ?? 0),
            'likedByViewer' => ($item['engagement']['likedByViewer'] ?? false) === true,
            'bookmarkedByViewer' => ($item['engagement']['bookmarkedByViewer'] ?? false) === true,
        ];
        unset($item['sortValue'], $item['secondarySortValue'], $item['cursorTweetId']);

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function getFeaturedFeedPayload(?string $cursor, int $limit, ?int $viewerUserId): array
    {
        $candidates = array_map(
            fn (array $item): array => $this->scoreFeaturedCandidate($item),
            $this->feeds->getLatestPublicFeedCandidates(FeedConfig::FEATURED_CANDIDATE_LIMIT, $viewerUserId)
        );

        usort($candidates, fn (array $left, array $right): int => $this->compareFeaturedItems($left, $right));

        ['cursorSort' => $cursorScore, 'cursorSecondarySort' => $cursorPublishedAt, 'cursorTweetId' => $cursorTweetId] = FeedCursor::decode($cursor);
        if ($cursorScore !== null || $cursorPublishedAt !== null || $cursorTweetId !== null) {
            $candidates = array_values(array_filter(
                $candidates,
                fn (array $item): bool => $this->isFeaturedItemAfterCursor(
                    $item,
                    $cursorScore,
                    $cursorPublishedAt,
                    $cursorTweetId
                )
            ));
        }

        $pageItems = array_slice($candidates, 0, $limit);
        $hasMore = count($candidates) > $limit;

        return [
            'items' => $pageItems,
            'nextCursor' => $hasMore ? FeedCursor::encode($pageItems) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function scoreFeaturedCandidate(array $item): array
    {
        $engagement = is_array($item['engagement'] ?? null) ? $item['engagement'] : [];
        $score = ShortVideoData::calculateFeaturedScore(
            (int) ($engagement['likeCount'] ?? 0),
            (int) ($engagement['bookmarkCount'] ?? 0),
            (int) ($engagement['commentCount'] ?? 0),
            (int) ($engagement['viewCount'] ?? 0),
            isset($item['postedAt']) ? (string) $item['postedAt'] : null
        );

        $item['sortValue'] = number_format($score, 8, '.', '');
        $item['secondarySortValue'] = isset($item['postedAt']) ? (string) $item['postedAt'] : null;
        $item['cursorTweetId'] = isset($item['cursorTweetId']) ? (string) $item['cursorTweetId'] : (string) ($item['tweetId'] ?? '');

        return $item;
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function compareFeaturedItems(array $left, array $right): int
    {
        $scoreComparison = ((float) ($right['sortValue'] ?? 0.0)) <=> ((float) ($left['sortValue'] ?? 0.0));
        if ($scoreComparison !== 0) {
            return $scoreComparison;
        }

        $publishedComparison = strcmp((string) ($right['secondarySortValue'] ?? ''), (string) ($left['secondarySortValue'] ?? ''));
        if ($publishedComparison !== 0) {
            return $publishedComparison;
        }

        return strcmp((string) ($right['cursorTweetId'] ?? ''), (string) ($left['cursorTweetId'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isFeaturedItemAfterCursor(
        array $item,
        ?string $cursorScore,
        ?string $cursorPublishedAt,
        ?string $cursorTweetId
    ): bool {
        if ($cursorScore === null) {
            return true;
        }

        $itemScore = (float) ($item['sortValue'] ?? 0.0);
        $cursorScoreFloat = (float) $cursorScore;
        if ($itemScore < $cursorScoreFloat) {
            return true;
        }

        if ($itemScore > $cursorScoreFloat) {
            return false;
        }

        $itemPublishedAt = (string) ($item['secondarySortValue'] ?? '');
        $cursorPublishedAt = (string) ($cursorPublishedAt ?? '');
        if ($itemPublishedAt < $cursorPublishedAt) {
            return true;
        }

        if ($itemPublishedAt > $cursorPublishedAt) {
            return false;
        }

        return strcmp((string) ($item['cursorTweetId'] ?? ''), (string) ($cursorTweetId ?? '')) < 0;
    }

    /**
     * @return array{id:int,name:string,username:string,avatarUrl:?string}|null
     */
    private function mapViewerSummary(?User $viewer): ?array
    {
        if (! $viewer) {
            return null;
        }

        return [
            'id' => $viewer->id,
            'name' => trim((string) ($viewer->name ?? '')) !== '' ? trim((string) $viewer->name) : $viewer->username,
            'username' => $viewer->username,
            'avatarUrl' => ! empty($viewer->avatar_url) ? (string) $viewer->avatar_url : null,
        ];
    }

    private function normalizeLimit(int|string|null $limit, int $fallback): int
    {
        $numericLimit = is_numeric((string) $limit) ? (int) $limit : $fallback;

        return max(1, min(FeedConfig::MAX_FEED_LIMIT, $numericLimit));
    }

    private function normalizeMode(?string $mode): string
    {
        return match ($mode) {
            FeedConfig::MODE_FEATURED => FeedConfig::MODE_FEATURED,
            FeedConfig::MODE_FOLLOWING => FeedConfig::MODE_FOLLOWING,
            default => FeedConfig::MODE_EXPLORE,
        };
    }
}
