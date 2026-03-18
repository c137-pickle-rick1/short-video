<?php

namespace App\ShortVideo\Services;

use App\Models\User;
use App\Models\Video;
use App\ShortVideo\Auth\CurrentViewerResolver;
use App\ShortVideo\Repositories\SocialGraphRepository;
use App\ShortVideo\Support\FeedConfig;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class FeedPageService
{
    /**
     * @var list<string>
     */
    private const OWN_PROFILE_PANELS = ['profile', 'creator', 'history', 'bookmarks', 'interactions'];

    public function __construct(
        private readonly FeedQueryService $feedQueries,
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
            'searchQuery' => null,
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
                'query' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getExplorePageViewModel(
        ?string $sourceHandle = '',
        int|string|null $limit = null,
        ?string $query = null
    ): array {
        $feed = $this->feedQueries->getFeedPage(
            null,
            $sourceHandle,
            $limit ?? FeedConfig::HOME_PAGE_FEED_LIMIT,
            FeedConfig::MODE_EXPLORE,
            $query
        );
        $renderedCount = count($feed['items']);
        $done = empty($feed['nextCursor']);
        $searchQuery = is_string($feed['query'] ?? null) ? $feed['query'] : null;
        $pageTitle = $searchQuery !== null
            ? ($feed['sourceHandle'] !== ''
                ? '@'.$feed['sourceHandle'].' · 搜索 “'.$searchQuery.'” · 探索 · Lagos Explore Feed'
                : '搜索 “'.$searchQuery.'” · 探索 · Lagos Explore Feed')
            : ($feed['sourceHandle'] !== ''
                ? '@'.$feed['sourceHandle'].' · 探索 · Lagos Explore Feed'
                : '探索 · Lagos Explore Feed');
        $toolbarStatusText = $searchQuery !== null
            ? ($renderedCount === 0 ? '等待搜索结果进入列表' : ($done ? '搜索结果已全部加载' : '继续下滑加载更多搜索结果'))
            : ($renderedCount === 0 ? '等待内容进入探索流' : ($done ? '当前结果已全部加载' : '继续下滑加载更多'));
        $emptyState = $searchQuery !== null
            ? [
                'title' => '没有找到相关内容',
                'body' => '没有找到与 “'.$searchQuery.'” 相关的内容。试试更短的关键词，或切换来源后重试。',
            ]
            : [
                'title' => '还没有可展示的探索内容',
                'body' => '先在 <code>config/sources.json</code> 启用来源并运行抓取。一旦有数据，探索页会按瀑布流持续展示。',
            ];

        return [
            'pageTitle' => $pageTitle,
            'headerViewer' => $feed['headerViewer'],
            'searchQuery' => $searchQuery,
            'page' => [
                'eyebrow' => 'Explore',
                'title' => '探索',
                'description' => '最新公开内容会按发布时间持续流入这里，你可以按来源切换视角，继续向下滚动扩展样本。',
            ],
            'toolbar' => [
                'summaryText' => $this->feedQueries->formatFeedSummary(
                    FeedConfig::MODE_EXPLORE,
                    $feed['sourceHandle'],
                    $renderedCount,
                    $done,
                    $searchQuery
                ),
                'statusText' => $toolbarStatusText,
                'showSourceFilter' => true,
            ],
            'emptyState' => $emptyState,
            'feed' => [
                'items' => $feed['items'],
                'nextCursor' => $feed['nextCursor'],
                'limit' => $feed['limit'],
                'renderedCount' => $renderedCount,
                'done' => $done,
                'isEmpty' => $renderedCount === 0,
                'mode' => FeedConfig::MODE_EXPLORE,
                'source' => $feed['sourceHandle'],
                'query' => $searchQuery,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscriptionsPageViewModel(
        int|string|null $limit = null,
        ?string $selectedAccount = null
    ): array {
        $viewer = $this->currentViewerResolver->resolve();

        if (! $viewer) {
            return [
                'pageTitle' => '订阅 · Lagos Explore Feed',
                'headerViewer' => null,
                'searchQuery' => null,
                'page' => [
                    'eyebrow' => 'Subscriptions',
                    'title' => '订阅',
                    'description' => '关注后，这里会只保留你真正想追的创作者更新。',
                ],
                'state' => 'guest',
                'feed' => null,
                'toolbar' => null,
                'subscriptionsFollowTabs' => [],
                'selectedSubscriptionsAccount' => null,
            ];
        }

        $subscriptionsFollowTabs = $this->buildSubscriptionsFollowTabs($viewer, $selectedAccount);

        if ($subscriptionsFollowTabs['items'] === []) {
            return [
                'pageTitle' => '订阅 · Lagos Explore Feed',
                'headerViewer' => $this->mapViewerSummary($viewer),
                'searchQuery' => null,
                'page' => [
                    'eyebrow' => 'Subscriptions',
                    'title' => '订阅',
                    'description' => '这里会收拢你已关注创作者的最新更新。',
                ],
                'state' => 'empty_following',
                'feed' => null,
                'toolbar' => null,
                'subscriptionsFollowTabs' => [],
                'selectedSubscriptionsAccount' => null,
            ];
        }

        if (! is_array($subscriptionsFollowTabs['selected'])) {
            return [
                'pageTitle' => '订阅 · Lagos Explore Feed',
                'headerViewer' => $this->mapViewerSummary($viewer),
                'searchQuery' => null,
                'page' => [
                    'eyebrow' => 'Subscriptions',
                    'title' => '订阅',
                    'description' => '这里会收拢你已关注创作者的最新更新。',
                ],
                'state' => 'selection_required',
                'feed' => null,
                'toolbar' => null,
                'subscriptionsFollowTabs' => $subscriptionsFollowTabs['items'],
                'selectedSubscriptionsAccount' => null,
            ];
        }

        $selectedSubscriptionsAccount = $subscriptionsFollowTabs['selected'];
        $selectedFeed = $this->feedQueries->getPublishedFeedForProfile(
            (int) $selectedSubscriptionsAccount['userId'],
            $limit ?? FeedConfig::MAX_FEED_LIMIT
        );
        $renderedCount = count($selectedFeed['items']);
        $summaryLabel = trim((string) ($selectedSubscriptionsAccount['name'] ?? '')) !== ''
            ? trim((string) $selectedSubscriptionsAccount['name'])
            : (string) ($selectedSubscriptionsAccount['username'] ?? '');

        return [
            'pageTitle' => $summaryLabel.' · 订阅 · Lagos Explore Feed',
            'headerViewer' => $this->mapViewerSummary($viewer),
            'searchQuery' => null,
            'page' => [
                'eyebrow' => 'Subscriptions',
                'title' => '订阅',
                'description' => '左侧切换你已关注的账号，右侧只展示当前选中账号的公开视频瀑布流。',
            ],
            'state' => 'ready',
            'subscriptionsFollowTabs' => $subscriptionsFollowTabs['items'],
            'selectedSubscriptionsAccount' => $selectedSubscriptionsAccount,
            'toolbar' => [
                'summaryText' => $summaryLabel.' · 已展示 '.$renderedCount.' 条 · 已加载完毕',
                'statusText' => $renderedCount === 0
                    ? $summaryLabel.' 还没有公开发布的视频'
                    : $selectedSubscriptionsAccount['name'].' 的视频已全部加载',
                'showSourceFilter' => false,
            ],
            'emptyState' => [
                'title' => '这个账号还没有公开发布的视频',
                'description' => '切换到其他已关注账号，或者先去探索页继续补充订阅内容。',
                'iconClass' => 'ph ph-video-camera-slash',
                'buttonLabel' => '去探索',
                'buttonHref' => route('explore'),
            ],
            'feed' => [
                'items' => $selectedFeed['items'],
                'nextCursor' => null,
                'limit' => $selectedFeed['limit'],
                'renderedCount' => $renderedCount,
                'done' => true,
                'isEmpty' => $renderedCount === 0,
                'mode' => FeedConfig::MODE_FOLLOWING,
                'gridMaxColumns' => 3,
                'source' => '',
                'query' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewerHistoryPageViewModel(int|string|null $page = null): array
    {
        $historyFeed = $this->feedQueries->paginateViewerCollectionFeed(
            'history',
            $page,
            FeedConfig::VIEWER_LIBRARY_PAGE_LIMIT
        );
        $historyItems = is_array($historyFeed['items'] ?? null) ? array_values($historyFeed['items']) : [];

        return [
            'pageTitle' => '观看记录 · Lagos Explore Feed',
            'headerViewer' => $historyFeed['headerViewer'],
            'searchQuery' => null,
            'page' => [
                'eyebrow' => 'History',
                'title' => '观看记录',
                'description' => '最近看过的内容会收拢在这里。布局改成 Grid，但卡片继续复用内容流里的同一套视频卡。',
            ],
            'history' => [
                'items' => $historyItems,
                'pagination' => [
                    'currentPage' => (int) ($historyFeed['page'] ?? 1),
                    'lastPage' => (int) ($historyFeed['lastPage'] ?? 1),
                    'perPage' => (int) ($historyFeed['perPage'] ?? FeedConfig::VIEWER_LIBRARY_PAGE_LIMIT),
                    'totalCount' => (int) ($historyFeed['total'] ?? count($historyItems)),
                ],
                'emptyState' => [
                    'title' => '还没有观看记录',
                    'description' => '继续去探索页看看新内容。只要开始浏览，真实观看记录就会按时间倒序出现在这里。',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewerBookmarksPageViewModel(int|string|null $page = null): array
    {
        $bookmarkFeed = $this->feedQueries->paginateViewerCollectionFeed(
            'bookmarks',
            $page,
            FeedConfig::VIEWER_LIBRARY_PAGE_LIMIT
        );
        $bookmarkItems = is_array($bookmarkFeed['items'] ?? null) ? array_values($bookmarkFeed['items']) : [];

        return [
            'pageTitle' => '我的收藏 · Lagos Explore Feed',
            'headerViewer' => $bookmarkFeed['headerViewer'],
            'searchQuery' => null,
            'page' => [
                'eyebrow' => 'Bookmarks',
                'title' => '我的收藏',
                'description' => '收藏过的视频会按最近收藏时间倒序收拢在这里。布局改成 Grid，卡片参数和观看记录保持一致。',
            ],
            'bookmarks' => [
                'items' => $bookmarkItems,
                'pagination' => [
                    'currentPage' => (int) ($bookmarkFeed['page'] ?? 1),
                    'lastPage' => (int) ($bookmarkFeed['lastPage'] ?? 1),
                    'perPage' => (int) ($bookmarkFeed['perPage'] ?? FeedConfig::VIEWER_LIBRARY_PAGE_LIMIT),
                    'totalCount' => (int) ($bookmarkFeed['total'] ?? count($bookmarkItems)),
                ],
                'emptyState' => [
                    'title' => '还没有收藏内容',
                    'description' => '看到想回看的视频时点一下收藏。你保存过的内容会按最近收藏时间排在这里。',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewerInteractionsPageViewModel(int|string|null $page = null): array
    {
        $interactionFeed = $this->feedQueries->paginateViewerInteractionFeed(
            $page,
            FeedConfig::VIEWER_LIBRARY_PAGE_LIMIT
        );
        $interactionItems = is_array($interactionFeed['items'] ?? null) ? array_values($interactionFeed['items']) : [];

        return [
            'pageTitle' => '我的互动 · Lagos Explore Feed',
            'headerViewer' => $interactionFeed['headerViewer'],
            'searchQuery' => null,
            'page' => [
                'eyebrow' => 'Interactions',
                'title' => '我的互动',
                'description' => '你点过赞和发过评论的视频会按互动时间倒序收拢在这里。这里改成列表布局，方便单条撤回操作。',
            ],
            'interactions' => [
                'items' => $interactionItems,
                'pagination' => [
                    'currentPage' => (int) ($interactionFeed['page'] ?? 1),
                    'lastPage' => (int) ($interactionFeed['lastPage'] ?? 1),
                    'perPage' => (int) ($interactionFeed['perPage'] ?? FeedConfig::VIEWER_LIBRARY_PAGE_LIMIT),
                    'totalCount' => (int) ($interactionFeed['total'] ?? count($interactionItems)),
                ],
                'emptyState' => [
                    'title' => '还没有互动内容',
                    'description' => '先去探索页和内容发生一点互动。你点赞或评论过的视频会按时间倒序出现在这里。',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getProfilePageViewModel(
        ?User $viewer,
        User $profileUser,
        ?string $selectedLibraryTab = null,
        ?string $selectedPanel = null,
        int|string|null $selectedPanelPage = null
    ): array {
        $isOwnProfile = $viewer?->is($profileUser) === true;
        $profileName = trim((string) ($profileUser->name ?? '')) !== '' ? trim((string) $profileUser->name) : $profileUser->username;
        $followedUserIds = $viewer
            ? $this->socialGraph->getFollowedUserIds($viewer->id, [$profileUser->id])
            : [];
        $resolvedSelectedPanel = $isOwnProfile
            ? $this->resolveOwnProfilePanel($selectedPanel, $selectedLibraryTab)
            : 'profile';
        $hasExplicitPanelSelection = $isOwnProfile
            && $this->hasExplicitOwnProfileSelection($selectedPanel, $selectedLibraryTab);
        $socialConnections = $this->buildProfileSocialConnectionsViewModel($viewer, $profileUser);
        $profileVideoLibrary = $isOwnProfile
            ? $this->buildProfileVideoLibraryViewModel($profileUser, $selectedLibraryTab)
            : null;
        $embeddedHistory = $isOwnProfile && $resolvedSelectedPanel === 'history'
            ? $this->buildEmbeddedHistoryPanelViewModel($selectedPanelPage)
            : null;
        $embeddedBookmarks = $isOwnProfile && $resolvedSelectedPanel === 'bookmarks'
            ? $this->buildEmbeddedBookmarksPanelViewModel($selectedPanelPage)
            : null;
        $embeddedInteractions = $isOwnProfile && $resolvedSelectedPanel === 'interactions'
            ? $this->buildEmbeddedInteractionsPanelViewModel($selectedPanelPage)
            : null;
        $publicProfileFeed = ! $isOwnProfile
            ? $this->feedQueries->getPublishedFeedForProfile($profileUser->id, FeedConfig::MAX_FEED_LIMIT)
            : null;
        $publicProfileFeedItems = is_array($publicProfileFeed['items'] ?? null) ? $publicProfileFeed['items'] : [];
        $publicProfileFeedCount = count($publicProfileFeedItems);

        return [
            'pageTitle' => $isOwnProfile
                ? '个人中心 · Lagos Explore Feed'
                : $profileName.' · @'.$profileUser->username.' · Lagos Explore Feed',
            'headerViewer' => $this->mapViewerSummary($viewer),
            'searchQuery' => null,
            'page' => [
                'eyebrow' => 'Profile',
                'title' => $isOwnProfile ? '个人中心' : '用户主页',
                'description' => $isOwnProfile
                    ? '查看你的账号资料、关注关系和当前身份信息。'
                    : '查看这个账号的资料、关注关系和当前身份信息。',
            ],
            'isOwnProfile' => $isOwnProfile,
            'profile' => [
                'id' => $profileUser->id,
                'name' => $profileName,
                'username' => $profileUser->username,
                'avatarUrl' => ! empty($profileUser->avatar_url) ? (string) $profileUser->avatar_url : null,
                'bio' => ! empty($profileUser->bio) ? (string) $profileUser->bio : null,
            ],
            'stats' => [
                'followingCount' => (int) ($socialConnections['tabs']['following']['count'] ?? 0),
                'followerCount' => (int) ($socialConnections['tabs']['followers']['count'] ?? 0),
            ],
            'selectedPanel' => $resolvedSelectedPanel,
            'hasExplicitPanelSelection' => $hasExplicitPanelSelection,
            'panelItems' => $isOwnProfile
                ? $this->buildOwnProfilePanelItems($profileUser, $profileName, $socialConnections)
                : [],
            'profilePanel' => $isOwnProfile
                ? [
                    'title' => '个人资料卡片',
                    'description' => '查看并编辑你的账号资料、关注关系和简介。',
                ]
                : null,
            'creatorCenter' => $isOwnProfile
                ? [
                    'title' => '创作者中心',
                    'description' => '管理上传内容和你的视频状态。',
                ]
                : null,
            'embeddedHistory' => $embeddedHistory,
            'embeddedBookmarks' => $embeddedBookmarks,
            'embeddedInteractions' => $embeddedInteractions,
            'followState' => [
                'creator' => [
                    'userId' => $profileUser->id,
                    'name' => $profileName,
                    'username' => $profileUser->username,
                    'avatarUrl' => ! empty($profileUser->avatar_url) ? (string) $profileUser->avatar_url : null,
                ],
                'viewerUserId' => $viewer?->id,
                'canFollowCreator' => $viewer !== null && ! $isOwnProfile,
                'followedByViewer' => $viewer !== null
                    && ! $isOwnProfile
                    && in_array($profileUser->id, $followedUserIds, true),
            ],
            'socialConnections' => $socialConnections,
            'profileVideoLibrary' => $profileVideoLibrary,
            'publicProfileFeed' => ! $isOwnProfile ? [
                'title' => '发布的视频',
                'description' => '这里展示这个账号已经公开发布的视频内容，沿用和精选页一致的瀑布流展示方式。',
                'feed' => [
                    'items' => $publicProfileFeedItems,
                    'nextCursor' => null,
                    'limit' => (int) ($publicProfileFeed['limit'] ?? FeedConfig::MAX_FEED_LIMIT),
                    'renderedCount' => $publicProfileFeedCount,
                    'done' => true,
                    'isEmpty' => $publicProfileFeedCount === 0,
                    'mode' => FeedConfig::MODE_EXPLORE,
                    'source' => '',
                    'query' => null,
                ],
                'emptyState' => [
                    'title' => '还没有公开发布的视频',
                    'description' => '这个账号当前还没有可展示的公开视频。',
                    'iconClass' => 'ph ph-video-camera',
                    'buttonLabel' => '去探索',
                    'buttonHref' => route('explore'),
                ],
            ] : null,
        ];
    }

    private function resolveOwnProfilePanel(?string $selectedPanel, ?string $selectedLibraryTab): string
    {
        $normalizedPanel = trim((string) ($selectedPanel ?? ''));

        if (in_array($normalizedPanel, self::OWN_PROFILE_PANELS, true)) {
            return $normalizedPanel;
        }

        return trim((string) ($selectedLibraryTab ?? '')) !== '' ? 'creator' : 'profile';
    }

    private function hasExplicitOwnProfileSelection(?string $selectedPanel, ?string $selectedLibraryTab): bool
    {
        $normalizedPanel = trim((string) ($selectedPanel ?? ''));

        return in_array($normalizedPanel, self::OWN_PROFILE_PANELS, true)
            || trim((string) ($selectedLibraryTab ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $socialConnections
     * @return array<int, array<string, mixed>>
     */
    private function buildOwnProfilePanelItems(User $profileUser, string $profileName, array $socialConnections): array
    {
        return [
            [
                'key' => 'profile',
                'type' => 'profile-card',
                'label' => '个人资料卡片',
                'name' => $profileName,
                'username' => $profileUser->username,
                'avatarUrl' => ! empty($profileUser->avatar_url) ? (string) ($profileUser->avatar_url) : null,
                'avatarInitial' => $this->ownProfilePanelInitial($profileName),
                'followingCount' => (int) ($socialConnections['tabs']['following']['count'] ?? 0),
                'followerCount' => (int) ($socialConnections['tabs']['followers']['count'] ?? 0),
            ],
            [
                'key' => 'creator',
                'type' => 'panel',
                'label' => '创作者中心',
                'description' => '管理上传内容和视频状态',
                'iconClass' => 'ph ph-video-camera',
            ],
            [
                'key' => 'history',
                'type' => 'panel',
                'label' => '观看记录',
                'description' => '查看最近浏览过的视频',
                'iconClass' => 'ph ph-clock-counter-clockwise',
            ],
            [
                'key' => 'bookmarks',
                'type' => 'panel',
                'label' => '我的收藏',
                'description' => '管理保存下来的视频',
                'iconClass' => 'ph ph-bookmark-simple',
            ],
            [
                'key' => 'interactions',
                'type' => 'panel',
                'label' => '我的互动',
                'description' => '查看点赞和评论记录',
                'iconClass' => 'ph ph-chat-circle-dots',
            ],
            [
                'key' => 'logout',
                'type' => 'logout',
                'label' => '退出登录',
                'description' => '立即退出当前账号',
                'iconClass' => 'ph ph-sign-out',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEmbeddedHistoryPanelViewModel(int|string|null $page = null): array
    {
        $viewModel = $this->getViewerHistoryPageViewModel($page);

        return [
            'page' => is_array($viewModel['page'] ?? null) ? $viewModel['page'] : [],
            'history' => is_array($viewModel['history'] ?? null) ? $viewModel['history'] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEmbeddedBookmarksPanelViewModel(int|string|null $page = null): array
    {
        $viewModel = $this->getViewerBookmarksPageViewModel($page);

        return [
            'page' => is_array($viewModel['page'] ?? null) ? $viewModel['page'] : [],
            'bookmarks' => is_array($viewModel['bookmarks'] ?? null) ? $viewModel['bookmarks'] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEmbeddedInteractionsPanelViewModel(int|string|null $page = null): array
    {
        $viewModel = $this->getViewerInteractionsPageViewModel($page);

        return [
            'page' => is_array($viewModel['page'] ?? null) ? $viewModel['page'] : [],
            'interactions' => is_array($viewModel['interactions'] ?? null) ? $viewModel['interactions'] : [],
        ];
    }

    private function ownProfilePanelInitial(string $profileName): string
    {
        $label = ltrim(trim($profileName), '@');

        return $label !== '' ? mb_strtoupper(mb_substr($label, 0, 1)) : 'L';
    }

    /**
     * @return array{
     *   initialTab:string,
     *   tabs: array{
     *     following: array{
     *       key:string,
     *       label:string,
     *       count:int,
     *       items: array<int, array{
     *         creator: array{userId:int,name:string,username:string,avatarUrl:?string,bio:?string},
     *         viewerUserId:int|null,
     *         canFollowCreator:bool,
     *         followedByViewer:bool
     *       }>,
     *       emptyState: array{title:string,description:string,iconClass:string}
     *     },
     *     followers: array{
     *       key:string,
     *       label:string,
     *       count:int,
     *       items: array<int, array{
     *         creator: array{userId:int,name:string,username:string,avatarUrl:?string,bio:?string},
     *         viewerUserId:int|null,
     *         canFollowCreator:bool,
     *         followedByViewer:bool
     *       }>,
     *       emptyState: array{title:string,description:string,iconClass:string}
     *     }
     *   }
     * }
     */
    private function buildProfileSocialConnectionsViewModel(?User $viewer, User $profileUser): array
    {
        $followingUsers = $profileUser->followingUsers()
            ->select('users.id', 'users.name', 'users.username', 'users.avatar_url', 'users.bio')
            ->orderBy('user_follows.created_at', 'desc')
            ->get();
        $followerUsers = $profileUser->followerUsers()
            ->select('users.id', 'users.name', 'users.username', 'users.avatar_url', 'users.bio')
            ->orderBy('user_follows.created_at', 'desc')
            ->get();
        $candidateUserIds = array_values(array_unique(array_merge(
            $followingUsers->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
            $followerUsers->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
        )));
        $followedUserIds = $viewer !== null
            ? $this->socialGraph->getFollowedUserIds($viewer->id, $candidateUserIds)
            : [];

        return [
            'initialTab' => 'following',
            'tabs' => [
                'following' => [
                    'key' => 'following',
                    'label' => '关注',
                    'count' => $followingUsers->count(),
                    'items' => $followingUsers
                        ->map(fn (User $user): array => $this->mapProfileSocialConnection($user, $viewer, $followedUserIds))
                        ->values()
                        ->all(),
                    'emptyState' => [
                        'title' => '还没有关注任何用户',
                        'description' => '你关注的人会集中展示在这里，后续可以直接从列表里继续管理关注关系。',
                        'iconClass' => 'ph ph-users-three',
                    ],
                ],
                'followers' => [
                    'key' => 'followers',
                    'label' => '粉丝',
                    'count' => $followerUsers->count(),
                    'items' => $followerUsers
                        ->map(fn (User $user): array => $this->mapProfileSocialConnection($user, $viewer, $followedUserIds))
                        ->values()
                        ->all(),
                    'emptyState' => [
                        'title' => '还没有粉丝',
                        'description' => '当有用户开始关注你时，他们会出现在这里。',
                        'iconClass' => 'ph ph-user-circle-plus',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  list<int>  $followedUserIds
     * @return array{
     *   creator: array{userId:int,name:string,username:string,avatarUrl:?string,bio:?string},
     *   viewerUserId:int|null,
     *   canFollowCreator:bool,
     *   followedByViewer:bool
     * }
     */
    private function mapProfileSocialConnection(User $user, ?User $viewer, array $followedUserIds): array
    {
        $displayName = trim((string) ($user->name ?? '')) !== '' ? trim((string) $user->name) : $user->username;

        return [
            'creator' => [
                'userId' => $user->id,
                'name' => $displayName,
                'username' => $user->username,
                'avatarUrl' => ! empty($user->avatar_url) ? (string) $user->avatar_url : null,
                'bio' => ! empty($user->bio) ? (string) $user->bio : null,
            ],
            'viewerUserId' => $viewer?->id,
            'canFollowCreator' => $viewer !== null && $viewer->id !== $user->id,
            'followedByViewer' => $viewer !== null && $viewer->id !== $user->id && in_array($user->id, $followedUserIds, true),
        ];
    }

    /**
     * @return array{
     *   tabs: array<int, array{key:string,label:string,count:int,active:bool}>,
     *   selectedTab: array{
     *     key:string,
     *     label:string,
     *     count:int,
     *     iconClass:string,
     *     emptyState: array{title:string,description:string}
     *   },
     *   items: array<int, array{
     *     id:int,
     *     title:string,
     *     tagLine:string,
     *     status:string,
     *     statusLabel:string,
     *     durationText:string,
     *     thumbnailImageUrl:?string,
     *     thumbnailVideoUrl:?string,
     *     dateLabel:?string,
     *     dateText:?string,
     *     statusTag:?array{label:string,tone:string},
     *     progressLabel:?string,
     *     actions:array<int, array{key:string,label:string}>
     *   }>
     * }
     */
    private function buildProfileVideoLibraryViewModel(User $profileUser, ?string $selectedLibraryTab = null): array
    {
        $tabDefinitions = $this->profileVideoLibraryTabDefinitions();
        $selectedTabKey = array_key_exists($selectedLibraryTab ?? '', $tabDefinitions)
            ? (string) $selectedLibraryTab
            : 'published';
        $baseQuery = Video::query()->where('uploader_user_id', $profileUser->id);
        $tabs = [];

        foreach ($tabDefinitions as $key => $definition) {
            $tabs[] = [
                'key' => $key,
                'label' => $definition['label'],
                'count' => (clone $baseQuery)->whereIn('status', $definition['statuses'])->count(),
                'active' => $key === $selectedTabKey,
            ];
        }

        $selectedDefinition = $tabDefinitions[$selectedTabKey];
        $selectedTabMeta = collect($tabs)->firstWhere('key', $selectedTabKey);
        $selectedTabCount = is_array($selectedTabMeta)
            ? (int) ($selectedTabMeta['count'] ?? 0)
            : 0;
        $items = $selectedTabCount > 0
            ? (clone $baseQuery)
                ->whereIn('status', $selectedDefinition['statuses'])
                ->orderByRaw('COALESCE(published_at, created_at) DESC, id DESC')
                ->limit(12)
                ->get([
                    'id',
                    'title',
                    'caption',
                    'description',
                    'storage_disk',
                    'storage_path',
                    'poster_url',
                    'playback_url',
                    'duration_text',
                    'duration_seconds',
                    'status',
                    'published_at',
                    'created_at',
                    'updated_at',
                ])
                ->map(function (Video $video): array {
                    $title = trim((string) ($video->title ?? ''));
                    $resolvedTitle = $title !== '' ? $title : '未命名视频';
                    $dateMetadata = $this->profileVideoDateMetadata($video);

                    return [
                        'id' => $video->id,
                        'title' => $resolvedTitle,
                        'tagLine' => $this->profileVideoTagLine($video),
                        'status' => (string) $video->status,
                        'statusLabel' => $this->profileVideoStatusLabel((string) $video->status),
                        'durationText' => $this->profileVideoDurationText($video),
                        'thumbnailImageUrl' => $this->resolveProfileVideoThumbnailImageUrl($video),
                        'thumbnailVideoUrl' => $this->resolveProfileVideoThumbnailVideoUrl($video),
                        'dateLabel' => $dateMetadata['label'] ?? null,
                        'dateText' => $dateMetadata['text'] ?? null,
                        'statusTag' => $this->profileVideoStatusTag((string) $video->status),
                        'progressLabel' => $this->profileVideoProgressLabel((string) $video->status),
                        'actions' => $this->profileVideoActions((string) $video->status),
                    ];
                })
                ->values()
                ->all()
            : [];

        return [
            'tabs' => $tabs,
            'selectedTab' => [
                'key' => $selectedTabKey,
                'label' => $selectedDefinition['label'],
                'count' => $selectedTabCount,
                'iconClass' => $selectedDefinition['iconClass'],
                'emptyState' => $selectedDefinition['emptyState'],
            ],
            'items' => $items,
        ];
    }

    /**
     * @return array<string, array{
     *   label:string,
     *   statuses:array<int, string>,
     *   iconClass:string,
     *   emptyState: array{title:string,description:string}
     * }>
     */
    private function profileVideoLibraryTabDefinitions(): array
    {
        return [
            'published' => [
                'label' => '已发布',
                'statuses' => ['published'],
                'iconClass' => 'ph ph-video-camera',
                'emptyState' => [
                    'title' => '还没有已发布的视频',
                    'description' => '公开视频完成发布后，会出现在这里。现在可以先去补充你的第一条内容。',
                ],
            ],
            'reviewing' => [
                'label' => '审核中',
                'statuses' => ['reviewing', 'pending_review', 'under_review'],
                'iconClass' => 'ph ph-hourglass-medium',
                'emptyState' => [
                    'title' => '还没有审核中的视频',
                    'description' => '提交审核后的内容会临时停留在这里，方便你确认处理进度。',
                ],
            ],
            'uploading' => [
                'label' => '上传中',
                'statuses' => ['uploading', 'processing', 'queued'],
                'iconClass' => 'ph ph-upload-simple',
                'emptyState' => [
                    'title' => '还没有上传中的视频',
                    'description' => '正在上传或转码的内容会显示在这里。上传完成后会自动流转到下一个状态。',
                ],
            ],
            'removed' => [
                'label' => '已下架',
                'statuses' => ['removed', 'archived', 'unpublished', 'taken_down'],
                'iconClass' => 'ph ph-eye-slash',
                'emptyState' => [
                    'title' => '还没有已下架的视频',
                    'description' => '主动下架或不可见的内容会集中在这里，便于你后续重新整理。',
                ],
            ],
        ];
    }

    private function profileVideoStatusLabel(string $status): string
    {
        return match ($status) {
            'published' => '已发布',
            'reviewing', 'pending_review', 'under_review' => '审核中',
            'uploading', 'processing', 'queued' => '上传中',
            'removed', 'archived', 'unpublished', 'taken_down' => '已下架',
            default => '未归类',
        };
    }

    private function profileVideoTagLine(Video $video): string
    {
        $candidates = [
            trim((string) ($video->description ?? '')),
            trim((string) ($video->caption ?? '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            preg_match_all('/#([^\s#，,;；]+)/u', $candidate, $matches);
            $tags = array_values(array_filter(array_map(
                static fn (string $tag): string => trim($tag),
                $matches[1] ?? []
            )));

            if ($tags !== []) {
                return '标签：'.implode('，', array_map(
                    static fn (string $tag): string => '#'.$tag,
                    $tags
                ));
            }

            return '标签：'.Str::limit($candidate, 42, '…');
        }

        return '标签：暂无';
    }

    private function profileVideoDurationText(Video $video): string
    {
        $durationText = trim((string) ($video->duration_text ?? ''));

        if ($durationText !== '') {
            return $durationText;
        }

        $durationSeconds = is_numeric((string) ($video->duration_seconds ?? null))
            ? max(0, (int) $video->duration_seconds)
            : null;

        if ($durationSeconds === null) {
            return '--:--';
        }

        $hours = intdiv($durationSeconds, 3600);
        $minutes = intdiv($durationSeconds % 3600, 60);
        $seconds = $durationSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    private function resolveProfileVideoThumbnailImageUrl(Video $video): ?string
    {
        $posterUrl = trim((string) ($video->poster_url ?? ''));

        return $posterUrl !== '' ? $posterUrl : null;
    }

    private function resolveProfileVideoThumbnailVideoUrl(Video $video): ?string
    {
        $playbackUrl = trim((string) ($video->playback_url ?? ''));
        if ($playbackUrl !== '') {
            return $playbackUrl;
        }

        $storageDisk = trim((string) ($video->storage_disk ?? ''));
        $storagePath = trim((string) ($video->storage_path ?? ''));

        if ($storageDisk === '' || $storagePath === '') {
            return null;
        }

        try {
            $disk = Storage::disk($storageDisk);

            if (! $disk->exists($storagePath)) {
                return null;
            }

            $storageUrl = $disk->url($storagePath);

            return is_string($storageUrl) && trim($storageUrl) !== '' ? $storageUrl : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{label:string,text:string}|null
     */
    private function profileVideoDateMetadata(Video $video): ?array
    {
        if (in_array((string) $video->status, ['uploading', 'processing', 'queued'], true)) {
            return null;
        }

        $resolvedAt = $video->published_at ?? $video->created_at ?? $video->updated_at;

        if ($resolvedAt === null) {
            return null;
        }

        return [
            'label' => (string) $video->status === 'published' ? '发布日期' : '更新时间',
            'text' => $resolvedAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array{label:string,tone:string}|null
     */
    private function profileVideoStatusTag(string $status): ?array
    {
        return match ($status) {
            'reviewing', 'pending_review', 'under_review' => [
                'label' => '审核中',
                'tone' => 'warning',
            ],
            'review_failed', 'rejected' => [
                'label' => '未通过',
                'tone' => 'danger',
            ],
            default => null,
        };
    }

    private function profileVideoProgressLabel(string $status): ?string
    {
        return in_array($status, ['uploading', 'processing', 'queued'], true) ? '处理中' : null;
    }

    /**
     * @return array<int, array{key:string,label:string}>
     */
    private function profileVideoActions(string $status): array
    {
        return match ($status) {
            'published' => [
                ['key' => 'take-down', 'label' => '下架'],
                ['key' => 'delete', 'label' => '删除'],
            ],
            'removed', 'archived', 'unpublished', 'taken_down' => [
                ['key' => 'edit', 'label' => '编辑信息'],
                ['key' => 'resubmit', 'label' => '重新提交'],
                ['key' => 'delete', 'label' => '删除'],
            ],
            default => [],
        };
    }

    /**
     * @return array{
     *   items: list<array{
     *     userId:int,
     *     name:string,
     *     username:string,
     *     avatarUrl:?string,
     *     avatarInitial:string,
     *     latestPublishedAt:?string,
     *     latestPublishedAtText:string,
     *     publishedVideosCount:int,
     *     unreadVideosCount:int,
     *     hasUnread:bool,
     *     active:bool
     *   }>,
     *   selected:?array{
     *     userId:int,
     *     name:string,
     *     username:string,
     *     avatarUrl:?string,
     *     avatarInitial:string,
     *     latestPublishedAt:?string,
     *     latestPublishedAtText:string,
     *     publishedVideosCount:int,
     *     unreadVideosCount:int,
     *     hasUnread:bool,
     *     active:bool
     *   }
     * }
     */
    private function buildSubscriptionsFollowTabs(User $viewer, ?string $selectedAccount): array
    {
        $followingUsers = $viewer->followingUsers()
            ->select('users.id', 'users.name', 'users.username', 'users.avatar_url')
            ->get();

        if ($followingUsers->isEmpty()) {
            return [
                'items' => [],
                'selected' => null,
            ];
        }

        $publishedVideoStatsByUserId = $this->publishedVideoStatsByUserId(
            $followingUsers->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
            $viewer->id
        );
        $preferredDisplayNamesByUserId = $this->latestPublishedAuthorNamesByUserId(
            $followingUsers->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all()
        );
        $sortedFollowingUsers = $followingUsers
            ->sort(fn (User $left, User $right): int => $this->compareSubscriptionsUsers($left, $right, $publishedVideoStatsByUserId))
            ->values();
        $selectedUsername = $this->normalizeSelectedSubscriptionsAccount($selectedAccount);
        $selectedUser = $selectedUsername === null
            ? null
            : $sortedFollowingUsers->first(
                fn (User $user): bool => mb_strtolower($user->username) === $selectedUsername
            );

        $selectedUserId = $selectedUser instanceof User ? $selectedUser->id : null;
        $items = $sortedFollowingUsers
            ->map(function (User $user) use ($publishedVideoStatsByUserId, $preferredDisplayNamesByUserId, $selectedUserId): array {
                $stats = $publishedVideoStatsByUserId[$user->id] ?? [
                    'publishedVideosCount' => 0,
                    'latestPublishedAt' => null,
                    'unreadVideosCount' => 0,
                ];
                $latestPublishedAt = is_string($stats['latestPublishedAt'] ?? null) && trim((string) $stats['latestPublishedAt']) !== ''
                    ? trim((string) $stats['latestPublishedAt'])
                    : null;
                $unreadVideosCount = max(0, (int) ($stats['unreadVideosCount'] ?? 0));

                return [
                    'userId' => $user->id,
                    'name' => $preferredDisplayNamesByUserId[$user->id] ?? $this->subscriptionsAccountName($user),
                    'username' => $user->username,
                    'avatarUrl' => ! empty($user->avatar_url) ? (string) $user->avatar_url : null,
                    'avatarInitial' => $this->subscriptionsAccountInitial($user),
                    'latestPublishedAt' => $latestPublishedAt,
                    'latestPublishedAtText' => $this->formatSubscriptionsLatestPublishedAt($latestPublishedAt),
                    'publishedVideosCount' => (int) ($stats['publishedVideosCount'] ?? 0),
                    'unreadVideosCount' => $unreadVideosCount,
                    'hasUnread' => $unreadVideosCount > 0,
                    'active' => $selectedUserId === $user->id,
                ];
            })
            ->values()
            ->all();

        return [
            'items' => $items,
            'selected' => collect($items)->firstWhere('active', true),
        ];
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, array{publishedVideosCount:int,latestPublishedAt:?string,unreadVideosCount:int}>
     */
    private function publishedVideoStatsByUserId(array $userIds, int $viewerUserId): array
    {
        if ($userIds === []) {
            return [];
        }

        return Video::query()
            ->selectRaw(
                <<<'SQL'
                    uploader_user_id,
                    COUNT(*) AS published_videos_count,
                    MAX(COALESCE(published_at, created_at)) AS latest_published_at,
                    SUM(
                        CASE
                            WHEN EXISTS (
                                SELECT 1
                                FROM video_views vv
                                WHERE vv.video_id = videos.id
                                  AND vv.user_id = ?
                            ) THEN 0
                            ELSE 1
                        END
                    ) AS unread_videos_count
                SQL,
                [$viewerUserId]
            )
            ->whereIn('uploader_user_id', $userIds)
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->groupBy('uploader_user_id')
            ->get()
            ->mapWithKeys(static function (Video $video): array {
                $userId = is_numeric((string) $video->uploader_user_id) ? (int) $video->uploader_user_id : 0;

                return $userId > 0 ? [
                    $userId => [
                        'publishedVideosCount' => (int) ($video->published_videos_count ?? 0),
                        'latestPublishedAt' => is_string($video->latest_published_at) && trim($video->latest_published_at) !== ''
                            ? $video->latest_published_at
                            : null,
                        'unreadVideosCount' => max(0, (int) ($video->unread_videos_count ?? 0)),
                    ],
                ] : [];
            })
            ->all();
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, string>
     */
    private function latestPublishedAuthorNamesByUserId(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = Video::query()
            ->leftJoin('tweets as t', 't.tweet_id', '=', 'videos.tweet_id')
            ->whereIn('videos.uploader_user_id', $userIds)
            ->where('videos.status', 'published')
            ->where('videos.visibility', 'public')
            ->whereNotNull('t.author_name')
            ->selectRaw('videos.uploader_user_id AS user_id, t.author_name AS author_name')
            ->orderBy('videos.uploader_user_id')
            ->orderByRaw('COALESCE(videos.published_at, videos.created_at) DESC')
            ->get();

        $displayNamesByUserId = [];

        foreach ($rows as $row) {
            $userId = is_numeric((string) ($row->user_id ?? null)) ? (int) $row->user_id : 0;
            $authorName = is_string($row->author_name ?? null) ? trim((string) $row->author_name) : '';

            if ($userId <= 0 || $authorName === '' || isset($displayNamesByUserId[$userId])) {
                continue;
            }

            $displayNamesByUserId[$userId] = $authorName;
        }

        return $displayNamesByUserId;
    }

    /**
     * @param  array<int, array{publishedVideosCount:int,latestPublishedAt:?string}>  $publishedVideoStatsByUserId
     */
    private function compareSubscriptionsUsers(User $left, User $right, array $publishedVideoStatsByUserId): int
    {
        $leftStats = $publishedVideoStatsByUserId[$left->id] ?? [
            'publishedVideosCount' => 0,
            'latestPublishedAt' => null,
        ];
        $rightStats = $publishedVideoStatsByUserId[$right->id] ?? [
            'publishedVideosCount' => 0,
            'latestPublishedAt' => null,
        ];
        $leftPublishedTimestamp = $this->sortableTimestamp($leftStats['latestPublishedAt'] ?? null);
        $rightPublishedTimestamp = $this->sortableTimestamp($rightStats['latestPublishedAt'] ?? null);

        if ($leftPublishedTimestamp !== $rightPublishedTimestamp) {
            if ($leftPublishedTimestamp === null) {
                return 1;
            }

            if ($rightPublishedTimestamp === null) {
                return -1;
            }

            return $rightPublishedTimestamp <=> $leftPublishedTimestamp;
        }

        $leftPublishedCount = (int) ($leftStats['publishedVideosCount'] ?? 0);
        $rightPublishedCount = (int) ($rightStats['publishedVideosCount'] ?? 0);

        if ($leftPublishedCount !== $rightPublishedCount) {
            return $rightPublishedCount <=> $leftPublishedCount;
        }

        $leftFollowedTimestamp = $this->sortableTimestamp($left->pivot?->created_at ?? null) ?? 0;
        $rightFollowedTimestamp = $this->sortableTimestamp($right->pivot?->created_at ?? null) ?? 0;

        if ($leftFollowedTimestamp !== $rightFollowedTimestamp) {
            return $rightFollowedTimestamp <=> $leftFollowedTimestamp;
        }

        return strcmp($left->username, $right->username);
    }

    private function normalizeSelectedSubscriptionsAccount(?string $selectedAccount): ?string
    {
        $normalized = mb_strtolower(ltrim(trim((string) ($selectedAccount ?? '')), '@'));

        return $normalized !== '' ? $normalized : null;
    }

    private function subscriptionsAccountName(User $user): string
    {
        $resolvedName = trim((string) ($user->name ?? ''));
        $normalizedResolvedName = mb_strtolower(ltrim($resolvedName, '@'));

        if ($resolvedName === '' || $normalizedResolvedName === mb_strtolower($user->username)) {
            return $user->username;
        }

        return $resolvedName;
    }

    private function subscriptionsAccountInitial(User $user): string
    {
        $label = ltrim($this->subscriptionsAccountName($user), '@');

        return $label !== '' ? mb_strtoupper(mb_substr($label, 0, 1)) : 'L';
    }

    private function formatSubscriptionsLatestPublishedAt(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '暂无更新';
        }

        try {
            return CarbonImmutable::parse($value)->diffForHumans();
        } catch (\Throwable) {
            return '暂无更新';
        }
    }

    private function sortableTimestamp(mixed $value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : $timestamp;
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
