<?php

namespace App\ShortVideo\Services;

use App\Models\User;
use App\ShortVideo\Auth\CurrentViewerResolver;
use App\ShortVideo\Repositories\SocialGraphRepository;
use App\ShortVideo\Support\FeedConfig;

final class FeedPageService
{
    public function __construct(
        private readonly FeedQueryService $feedQueries,
        private readonly CreatorRankingService $creatorRankings,
        private readonly SocialGraphRepository $socialGraph,
        private readonly CurrentViewerResolver $currentViewerResolver
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getFeaturedPageViewModel(int|string|null $limit = null): array
    {
        $feed = $this->feedQueries->getFeedPage(null, '', $limit ?? FeedConfig::HOME_PAGE_FEED_LIMIT, FeedConfig::MODE_FEATURED);
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
                'summaryText' => $this->feedQueries->formatFeedSummary(FeedConfig::MODE_FEATURED, '', $renderedCount, $done),
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
                'mode' => FeedConfig::MODE_FEATURED,
                'source' => '',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getExplorePageViewModel(?string $sourceHandle = '', int|string|null $limit = null): array
    {
        $feed = $this->feedQueries->getFeedPage(null, $sourceHandle, $limit ?? FeedConfig::HOME_PAGE_FEED_LIMIT, FeedConfig::MODE_EXPLORE);
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
                'summaryText' => $this->feedQueries->formatFeedSummary(FeedConfig::MODE_EXPLORE, $feed['sourceHandle'], $renderedCount, $done),
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
                'mode' => FeedConfig::MODE_EXPLORE,
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
        $recommendations = $this->creatorRankings->getRecommendedCreators($viewer?->id, FeedConfig::RECOMMENDED_CREATORS_LIMIT);

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

        $followingCount = $this->socialGraph->countFollowingUsers($viewer->id);
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

        $feed = $this->feedQueries->getFeedPage(null, '', $limit ?? FeedConfig::HOME_PAGE_FEED_LIMIT, FeedConfig::MODE_FOLLOWING);
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
                'summaryText' => $this->feedQueries->formatFeedSummary(FeedConfig::MODE_FOLLOWING, '', $renderedCount, $done),
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
                'mode' => FeedConfig::MODE_FOLLOWING,
                'source' => '',
            ],
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
                'followingCount' => $this->socialGraph->countFollowingUsers($viewer->id),
                'followerCount' => $this->socialGraph->countFollowerUsers($viewer->id),
            ],
        ];
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
}
