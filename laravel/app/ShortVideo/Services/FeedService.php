<?php

namespace App\ShortVideo\Services;

use App\Models\User;
use App\ShortVideo\Auth\CurrentViewerResolver;
use App\ShortVideo\Repositories\ShortVideoRepository;
use App\ShortVideo\Support\FeedCursor;
use App\ShortVideo\Support\ShortVideoData;

final class FeedService
{
    public const MODE_FEATURED = 'featured';
    public const MODE_EXPLORE = 'explore';
    public const MODE_FOLLOWING = 'following';
    public const DEFAULT_FEED_LIMIT = 12;
    public const MAX_FEED_LIMIT = 24;
    public const FEATURED_CANDIDATE_LIMIT = 1000;
    public const HOME_PAGE_FEED_LIMIT = 8;
    public const HOME_PREVIEW_LIMIT = 4;
    public const RANKINGS_LIMIT = 50;
    public const RANKINGS_PREVIEW_LIMIT = 5;
    public const RECOMMENDED_CREATORS_LIMIT = 6;

    public function __construct(
        private readonly ShortVideoRepository $repository,
        private readonly CurrentViewerResolver $currentViewerResolver
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getFeedPage(
        ?string $cursor = null,
        ?string $sourceHandle = '',
        int|string|null $limit = null,
        string $mode = self::MODE_EXPLORE
    ): array {
        $normalizedMode = $this->normalizeMode($mode);
        $normalizedSourceHandle = $normalizedMode === self::MODE_EXPLORE
            ? ShortVideoData::normalizeHandle($sourceHandle)
            : '';
        $normalizedLimit = $this->normalizeLimit($limit, self::DEFAULT_FEED_LIMIT);
        $viewer = $this->currentViewerResolver->resolve();

        if ($normalizedMode === self::MODE_FOLLOWING && ! $viewer) {
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

        $feed = $normalizedMode === self::MODE_FEATURED
            ? $this->getFeaturedFeedPayload($cursor, $normalizedLimit, $viewer?->id)
            : $this->repository->getFeed(
                $cursor,
                $normalizedSourceHandle !== '' ? $normalizedSourceHandle : null,
                $normalizedLimit,
                $normalizedMode,
                $viewer?->id
            );

        $followedAuthorIds = $viewer
            ? $this->repository->getFollowedUserIds(
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

    /**
     * @return array<string, mixed>
     */
    public function getFeaturedPageViewModel(int|string|null $limit = null): array
    {
        $feed = $this->getFeedPage(null, '', $limit ?? self::HOME_PAGE_FEED_LIMIT, self::MODE_FEATURED);
        $renderedCount = count($feed['items']);
        $done = empty($feed['nextCursor']);

        return [
            'pageTitle' => '精选 · Lagos Explore Feed',
            'headerViewer' => $feed['headerViewer'],
            'page' => [
                'eyebrow' => 'Featured',
                'title' => '精选',
                'description' => '这里按真实互动和发布时间做混排，强互动内容会优先浮出，新内容也会持续进入候选池。',
            ],
            'toolbar' => [
                'summaryText' => $this->formatFeedSummary(self::MODE_FEATURED, '', $renderedCount, $done),
                'statusText' => $renderedCount === 0 ? '等待精选内容进入列表' : ($done ? '当前精选结果已全部加载' : '继续下滑加载更多'),
                'showSourceFilter' => false,
            ],
            'emptyState' => [
                'title' => '还没有可展示的精选内容',
                'body' => '等第一批公开视频与互动信号进入后，这里会按精选排序持续展示。',
            ],
            'feed' => [
                'items' => $feed['items'],
                'nextCursor' => $feed['nextCursor'],
                'limit' => $feed['limit'],
                'renderedCount' => $renderedCount,
                'done' => $done,
                'isEmpty' => $renderedCount === 0,
                'mode' => self::MODE_FEATURED,
                'source' => '',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getExplorePageViewModel(?string $sourceHandle = '', int|string|null $limit = null): array
    {
        $feed = $this->getFeedPage(null, $sourceHandle, $limit ?? self::HOME_PAGE_FEED_LIMIT, self::MODE_EXPLORE);
        $renderedCount = count($feed['items']);
        $done = empty($feed['nextCursor']);

        return [
            'pageTitle' => $feed['sourceHandle'] !== ''
                ? '@'.$feed['sourceHandle'].' · 探索 · Lagos Explore Feed'
                : '探索 · Lagos Explore Feed',
            'headerViewer' => $feed['headerViewer'],
            'page' => [
                'eyebrow' => 'Explore',
                'title' => '探索',
                'description' => '最新公开内容会按发布时间持续流入这里，你可以按来源切换视角，继续向下滚动扩展样本。',
            ],
            'toolbar' => [
                'summaryText' => $this->formatFeedSummary(self::MODE_EXPLORE, $feed['sourceHandle'], $renderedCount, $done),
                'statusText' => $renderedCount === 0 ? '等待内容进入探索流' : ($done ? '当前结果已全部加载' : '继续下滑加载更多'),
                'showSourceFilter' => true,
            ],
            'emptyState' => [
                'title' => '还没有可展示的探索内容',
                'body' => '先在 <code>config/sources.json</code> 启用来源并运行抓取。一旦有数据，探索页会按瀑布流持续展示。',
            ],
            'feed' => [
                'items' => $feed['items'],
                'nextCursor' => $feed['nextCursor'],
                'limit' => $feed['limit'],
                'renderedCount' => $renderedCount,
                'done' => $done,
                'isEmpty' => $renderedCount === 0,
                'mode' => self::MODE_EXPLORE,
                'source' => $feed['sourceHandle'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscriptionsPageViewModel(int|string|null $limit = null): array
    {
        $viewer = $this->currentViewerResolver->resolve();
        $recommendations = $this->getRecommendedCreators($viewer?->id, self::RECOMMENDED_CREATORS_LIMIT);

        if (! $viewer) {
            return [
                'pageTitle' => '订阅 · Lagos Explore Feed',
                'headerViewer' => null,
                'page' => [
                    'eyebrow' => 'Subscriptions',
                    'title' => '订阅',
                    'description' => '关注后，这里会只保留你真正想追的创作者更新。当前先给出登录入口和推荐创作者，完成冷启动。',
                ],
                'state' => 'guest',
                'recommendations' => $recommendations,
                'feed' => null,
                'toolbar' => null,
            ];
        }

        $followingCount = $this->repository->countFollowingUsers($viewer->id);
        if ($followingCount === 0) {
            return [
                'pageTitle' => '订阅 · Lagos Explore Feed',
                'headerViewer' => $this->mapViewerSummary($viewer),
                'page' => [
                    'eyebrow' => 'Subscriptions',
                    'title' => '订阅',
                    'description' => '这里会收拢你已关注创作者的最新更新。先挑几个感兴趣的创作者，订阅流就会开始生长。',
                ],
                'state' => 'empty_following',
                'recommendations' => $recommendations,
                'feed' => null,
                'toolbar' => null,
            ];
        }

        $feed = $this->getFeedPage(null, '', $limit ?? self::HOME_PAGE_FEED_LIMIT, self::MODE_FOLLOWING);
        $renderedCount = count($feed['items']);
        $done = empty($feed['nextCursor']);
        $state = $renderedCount === 0 ? 'empty_updates' : 'ready';

        return [
            'pageTitle' => '订阅 · Lagos Explore Feed',
            'headerViewer' => $this->mapViewerSummary($viewer),
            'page' => [
                'eyebrow' => 'Subscriptions',
                'title' => '订阅',
                'description' => '你关注的创作者更新会按发布时间倒序排列。这里不混入收藏，也不掺入探索噪音。',
            ],
            'state' => $state,
            'recommendations' => $state === 'empty_updates' ? $recommendations : [],
            'toolbar' => [
                'summaryText' => $this->formatFeedSummary(self::MODE_FOLLOWING, '', $renderedCount, $done),
                'statusText' => $renderedCount === 0 ? '关注的创作者最近还没有更新' : ($done ? '订阅更新已全部加载' : '继续下滑加载更多'),
                'showSourceFilter' => false,
            ],
            'emptyState' => [
                'title' => '关注的创作者最近还没有新内容',
                'body' => '你已经建立了订阅关系，但最近 7 天还没有更新。可以先去探索页看看新的候选创作者。',
            ],
            'feed' => [
                'items' => $feed['items'],
                'nextCursor' => $feed['nextCursor'],
                'limit' => $feed['limit'],
                'renderedCount' => $renderedCount,
                'done' => $done,
                'isEmpty' => $renderedCount === 0,
                'mode' => self::MODE_FOLLOWING,
                'source' => '',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getHomePageViewModel(): array
    {
        $viewer = $this->currentViewerResolver->resolve();
        $stats = $this->repository->getStats();
        $rankingPreview = $this->getCreatorRankingItems(self::RANKINGS_PREVIEW_LIMIT, $viewer?->id);
        $subscriptionPreviewState = 'guest';
        $subscriptionPreviewItems = [];
        $followingCount = 0;

        if ($viewer) {
            $followingCount = $this->repository->countFollowingUsers($viewer->id);
            if ($followingCount === 0) {
                $subscriptionPreviewState = 'empty_following';
            } else {
                $subscriptionFeed = $this->getFeedPage(null, '', self::HOME_PREVIEW_LIMIT, self::MODE_FOLLOWING);
                $subscriptionPreviewItems = array_slice($subscriptionFeed['items'], 0, self::HOME_PREVIEW_LIMIT);
                $subscriptionPreviewState = $subscriptionPreviewItems === [] ? 'empty_updates' : 'ready';
            }
        }

        return [
            'pageTitle' => '首页 · Lagos Explore Feed',
            'headerViewer' => $this->mapViewerSummary($viewer),
            'explore' => [
                'recentPublishedCount7d' => (int) ($stats['recentPublishedCount7d'] ?? 0),
                'totalItems' => (int) ($stats['totalItems'] ?? 0),
                'lastUpdatedAt' => $stats['lastUpdatedAt'] ?? null,
            ],
            'subscriptions' => [
                'state' => $subscriptionPreviewState,
                'items' => $subscriptionPreviewItems,
                'followingCount' => $followingCount,
            ],
            'rankings' => [
                'items' => $rankingPreview,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCreatorRankingsPageViewModel(int|string|null $limit = null, ?string $window = '7d'): array
    {
        $normalizedWindow = $this->normalizeWindow($window);
        $viewer = $this->currentViewerResolver->resolve();
        $rankingLimit = $this->normalizeRankingLimit($limit, self::RANKINGS_LIMIT);

        return [
            'pageTitle' => '榜单 · Lagos Explore Feed',
            'headerViewer' => $this->mapViewerSummary($viewer),
            'page' => [
                'eyebrow' => 'Rankings',
                'title' => '榜单',
                'description' => 'v1 先只看创作者更新活跃度。排序口径固定为近 7 天更新数、最近更新时间、总视频数，避免伪热度噪音。',
            ],
            'window' => $normalizedWindow,
            'items' => $this->getCreatorRankingItems($rankingLimit, $viewer?->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCreatorRankingsApiPayload(int|string|null $limit = null, ?string $window = '7d'): array
    {
        $normalizedWindow = $this->normalizeWindow($window);
        $viewer = $this->currentViewerResolver->resolve();
        $items = $this->getCreatorRankingItems(
            $this->normalizeRankingLimit($limit, self::RANKINGS_LIMIT),
            $viewer?->id
        );

        return [
            'window' => $normalizedWindow,
            'items' => array_map(
                static fn (array $item): array => [
                    'rank' => $item['rank'],
                    'creator' => $item['creator'],
                    'publishedCount7d' => $item['publishedCount7d'],
                    'totalVideos' => $item['totalVideos'],
                    'lastPublishedAt' => $item['lastPublishedAt'],
                    'followedByViewer' => $item['followedByViewer'],
                ],
                $items
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getProfilePageViewModel(User $viewer): array
    {
        return [
            'pageTitle' => '个人中心 · Lagos Explore Feed',
            'headerViewer' => $this->mapViewerSummary($viewer),
            'page' => [
                'eyebrow' => 'Profile',
                'title' => '个人中心',
                'description' => '查看你的账号资料、关注关系和当前身份信息。',
            ],
            'profile' => [
                'name' => trim((string) ($viewer->name ?? '')) !== '' ? trim((string) $viewer->name) : $viewer->username,
                'username' => $viewer->username,
                'avatarUrl' => ! empty($viewer->avatar_url) ? (string) $viewer->avatar_url : null,
                'bio' => ! empty($viewer->bio) ? (string) $viewer->bio : null,
            ],
            'stats' => [
                'followingCount' => $this->repository->countFollowingUsers($viewer->id),
                'followerCount' => $this->repository->countFollowerUsers($viewer->id),
            ],
        ];
    }

    public function formatFeedSummary(string $mode, string $sourceHandle, int $renderedCount, bool $done): string
    {
        $normalizedMode = $this->normalizeMode($mode);
        $sourceLabel = match ($normalizedMode) {
            self::MODE_FEATURED => '精选',
            self::MODE_FOLLOWING => '订阅更新',
            default => $sourceHandle !== '' ? '@'.$sourceHandle : '全部来源',
        };

        if ($renderedCount === 0 && $done) {
            return "{$sourceLabel} 暂无内容";
        }

        if ($renderedCount === 0) {
            return match ($normalizedMode) {
                self::MODE_FEATURED, self::MODE_FOLLOWING => "{$sourceLabel} 正在加载…",
                default => "{$sourceLabel} 正在加载探索内容…",
            };
        }

        return $sourceLabel.' · 已展示 '.$renderedCount.' 条 · '.($done ? '已加载完毕' : '向下滚动继续加载');
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapFeedItemForPresentation(array $item, ?int $viewerUserId = null, array $followedAuthorIds = []): array
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
            $this->repository->getLatestPublicFeedCandidates(self::FEATURED_CANDIDATE_LIMIT, $viewerUserId)
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
     * @return array<int, array<string, mixed>>
     */
    private function getCreatorRankingItems(int $limit, ?int $viewerUserId): array
    {
        $items = $this->repository->getCreatorRankings(7, $limit);
        $followedUserIds = $viewerUserId !== null
            ? $this->repository->getFollowedUserIds(
                $viewerUserId,
                array_map(
                    static fn (array $item): ?int => is_numeric((string) ($item['userId'] ?? null)) ? (int) $item['userId'] : null,
                    $items
                )
            )
            : [];

        return array_map(
            fn (array $item, int $index): array => $this->mapCreatorItemForPresentation(
                $item,
                $index + 1,
                $viewerUserId,
                $followedUserIds
            ),
            $items,
            array_keys($items)
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getRecommendedCreators(?int $viewerUserId, int $limit): array
    {
        $candidateLimit = max($limit * 5, $limit);
        $candidates = $this->getCreatorRankingItems($candidateLimit, $viewerUserId);

        $filtered = array_values(array_filter(
            $candidates,
            static function (array $item) use ($viewerUserId): bool {
                $creatorUserId = $item['creator']['userId'] ?? null;
                if (! is_int($creatorUserId)) {
                    return false;
                }

                if ($viewerUserId !== null && $creatorUserId === $viewerUserId) {
                    return false;
                }

                return $item['followedByViewer'] !== true;
            }
        ));

        return array_slice($filtered, 0, $limit);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<int>  $followedUserIds
     * @return array<string, mixed>
     */
    private function mapCreatorItemForPresentation(
        array $item,
        int $rank,
        ?int $viewerUserId,
        array $followedUserIds
    ): array {
        $creatorUserId = is_numeric((string) ($item['userId'] ?? null)) ? (int) $item['userId'] : null;
        $canFollowCreator = $viewerUserId !== null && $creatorUserId !== null && $viewerUserId !== $creatorUserId;

        return [
            'rank' => $rank,
            'creator' => [
                'userId' => $creatorUserId,
                'username' => (string) ($item['username'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'avatarUrl' => ! empty($item['avatarUrl']) ? (string) $item['avatarUrl'] : null,
            ],
            'publishedCount7d' => (int) ($item['publishedCount7d'] ?? 0),
            'totalVideos' => (int) ($item['totalVideos'] ?? 0),
            'lastPublishedAt' => $item['lastPublishedAt'] ?? null,
            'viewerUserId' => $viewerUserId,
            'canFollowCreator' => $canFollowCreator,
            'followedByViewer' => $canFollowCreator && in_array($creatorUserId, $followedUserIds, true),
        ];
    }

    private function normalizeLimit(int|string|null $limit, int $fallback): int
    {
        $numericLimit = is_numeric((string) $limit) ? (int) $limit : $fallback;

        return max(1, min(self::MAX_FEED_LIMIT, $numericLimit));
    }

    private function normalizeRankingLimit(int|string|null $limit, int $fallback): int
    {
        $numericLimit = is_numeric((string) $limit) ? (int) $limit : $fallback;

        return max(1, min(self::RANKINGS_LIMIT, $numericLimit));
    }

    private function normalizeMode(?string $mode): string
    {
        return match ($mode) {
            self::MODE_FEATURED => self::MODE_FEATURED,
            self::MODE_FOLLOWING => self::MODE_FOLLOWING,
            default => self::MODE_EXPLORE,
        };
    }

    private function normalizeWindow(?string $window): string
    {
        return $window === '7d' ? '7d' : '7d';
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
}
