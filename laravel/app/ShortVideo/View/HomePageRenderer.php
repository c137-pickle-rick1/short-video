<?php

namespace App\ShortVideo\View;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\Factory as ViewFactory;

final class HomePageRenderer
{
    public function __construct(
        private readonly FeedUiComponents $components,
        private readonly HomePageShellComponents $shellComponents,
        private readonly ViewFactory $views
    ) {}

    public function renderDocumentHead(string $pageTitle): string
    {
        return $this->renderView('shortvideo.partials.document-head', [
            'pageTitle' => $pageTitle,
            'includeCsrfToken' => true,
            'includePhosphorStyles' => true,
            'includePlyrStyles' => true,
        ]);
    }

    /**
     * @param  array{id?:int,name?:string,username:string,avatarUrl?:string|null}|null  $viewer
     */
    public function renderPageHeader(
        string $searchUrl,
        ?array $viewer = null,
        ?string $logoutUrl = null,
        ?string $searchQuery = null
    ): string {
        return $this->shellComponents->renderPageHeader($searchUrl, $viewer, $logoutUrl, $searchQuery);
    }

    /**
     * @param  array{id?:int,name?:string,username:string,avatarUrl?:string|null}|null  $viewer
     */
    public function renderDesktopNavigation(string $activePage = 'explore', ?array $viewer = null): string
    {
        return $this->shellComponents->renderDesktopNavigation($this->navigationItems($activePage, $viewer));
    }

    /**
     * @param  array{id?:int,name?:string,username:string,avatarUrl?:string|null}|null  $viewer
     */
    public function renderMobileNavigation(string $activePage = 'explore', ?array $viewer = null): string
    {
        return $this->shellComponents->renderMobileNavigation(array_values(array_filter(
            $this->navigationItems($activePage, $viewer),
            static fn (array $item): bool => ($item['mobileHidden'] ?? false) !== true
        )));
    }

    public function renderFeedToolbar(
        string $mode,
        string $activeSourceHandle,
        int $renderedCount,
        bool $done,
        bool $showSourceFilter = true,
        ?string $searchQuery = null,
        ?string $summaryText = null,
        ?string $statusText = null
    ): string {
        $normalizedSearchQuery = trim((string) ($searchQuery ?? ''));
        $feedSummaryText = $summaryText ?? $this->formatFeedSummary(
            $mode,
            $activeSourceHandle,
            $renderedCount,
            $done,
            $normalizedSearchQuery !== '' ? $normalizedSearchQuery : null
        );
        $feedStatusText = $statusText ?? ($normalizedSearchQuery !== ''
            ? ($renderedCount === 0 ? '等待搜索结果进入列表' : ($done ? '搜索结果已全部加载' : '向下滚动继续扩展搜索结果'))
            : ($renderedCount === 0
                ? match ($mode) {
                    'featured' => '等待精选内容进入列表',
                    'following' => '等待订阅更新进入列表',
                    default => '等待探索内容进入列表',
                }
                : ($done ? '当前结果已全部加载' : '向下滚动继续扩展列表')));

        return $this->renderView('shortvideo.partials.feed.toolbar', [
            'feedSummaryText' => $feedSummaryText,
            'feedStatusText' => $feedStatusText,
            'activeSourceHandle' => $activeSourceHandle,
            'showSourceFilter' => $showSourceFilter,
            'searchQuery' => $normalizedSearchQuery,
        ]);
    }

    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function renderFeedGrid(array $viewModel): string
    {
        $feed = is_array($viewModel['feed'] ?? null) ? $viewModel['feed'] : [];
        $items = is_array($feed['items'] ?? null) ? $feed['items'] : [];
        $isEmpty = ! empty($feed['isEmpty']);
        $emptyState = is_array($viewModel['emptyState'] ?? null) ? $viewModel['emptyState'] : [];
        $emptyStateTitle = (string) ($emptyState['title'] ?? '还没有可展示的视频');
        $emptyStateDescription = (string) (($emptyState['description'] ?? null) ?? ($emptyState['body'] ?? '先在 <code>config/sources.json</code> 启用来源并运行抓取。首页布局已经准备好，一旦有数据就会按瀑布流方式展示出来。'));
        $emptyStateIconClass = (string) ($emptyState['iconClass'] ?? 'ph ph-magnifying-glass');
        $emptyStateButtonLabel = isset($emptyState['buttonLabel']) ? (string) $emptyState['buttonLabel'] : null;
        $emptyStateButtonHref = isset($emptyState['buttonHref']) ? (string) $emptyState['buttonHref'] : null;
        $emptyStateButtonAttributes = is_array($emptyState['buttonAttributes'] ?? null) ? $emptyState['buttonAttributes'] : [];
        $itemsMarkup = $isEmpty
            ? $this->renderFeedEmptyState(
                title: $emptyStateTitle,
                description: $emptyStateDescription,
                iconClass: $emptyStateIconClass,
                buttonLabel: $emptyStateButtonLabel,
                buttonHref: $emptyStateButtonHref,
                buttonAttributes: $emptyStateButtonAttributes
            )
            : implode('', array_map(fn (array $item) => $this->renderFeedItem($item), $items));

        return $this->renderView('shortvideo.partials.feed.grid', [
            'isEmpty' => $isEmpty,
            'itemsMarkup' => $itemsMarkup,
        ]);
    }

    public function renderFeedEmptyState(
        string $title = '还没有可展示的视频',
        string $description = '先在 <code>config/sources.json</code> 启用来源并运行抓取。首页布局已经准备好，一旦有数据就会按瀑布流方式展示出来。',
        string $iconClass = 'ph ph-magnifying-glass',
        ?string $buttonLabel = null,
        ?string $buttonHref = null,
        array $buttonAttributes = []
    ): string {
        return $this->components->renderEmptyStateCard(
            title: $title,
            description: $description,
            iconClass: $iconClass,
            buttonLabel: $buttonLabel,
            buttonHref: $buttonHref,
            buttonAttributes: $buttonAttributes
        );
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    public function renderFeedItem(array $tweet): string
    {
        return $this->renderFeedCard($tweet);
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    public function renderHistoryFeedItem(array $tweet, string $deleteUrl): string
    {
        return $this->renderLibraryFeedItem(
            $tweet,
            $this->renderView('shortvideo.partials.history.delete-button', [
                'deleteUrl' => $deleteUrl,
            ]),
            [
                'data-history-record-item' => 'true',
                'data-history-video-id' => isset($tweet['videoId']) ? (string) $tweet['videoId'] : '',
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    public function renderBookmarksFeedItem(array $tweet, string $removeUrl): string
    {
        return $this->renderLibraryFeedItem(
            $tweet,
            $this->renderView('shortvideo.partials.bookmarks/remove-button', [
                'removeUrl' => $removeUrl,
            ]),
            [
                'data-bookmark-record-item' => 'true',
                'data-bookmark-video-id' => isset($tweet['videoId']) ? (string) $tweet['videoId'] : '',
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    public function renderInteractionListItem(array $tweet, string $actionUrl): string
    {
        $authorHandle = (string) ($tweet['authorHandle'] ?? 'unknown');
        $actionType = (string) ($tweet['interactionType'] ?? '');
        $videoTitle = $this->getDisplayText($tweet);
        $previewMarkup = $this->components->renderFeedMediaPreview(
            $tweet,
            'aspect-video rounded-2xl',
            $this->formatVideoDurationText((string) ($tweet['durationText'] ?? '')),
            $authorHandle
        );

        return $this->renderView('shortvideo.partials.interactions.item', [
            'previewMarkup' => $previewMarkup,
            'interactionLabel' => $this->resolveInteractionLabel($tweet),
            'interactionIconClass' => $actionType === 'comment' ? 'ph ph-chat-circle-dots' : 'ph ph-heart-straight',
            'interactionAtText' => $this->formatFeedDate($tweet['interactionAt'] ?? null),
            'videoTitle' => $videoTitle,
            'authorName' => isset($tweet['authorName']) && trim((string) ($tweet['authorName'] ?? '')) !== ''
                ? trim((string) $tweet['authorName'])
                : '@'.$authorHandle,
            'authorHandle' => $authorHandle,
            'commentBody' => $actionType === 'comment'
                ? trim((string) ($tweet['commentBody'] ?? ''))
                : '',
            'actionLabel' => $actionType === 'comment' ? '删除评论' : '取消点赞',
            'actionUrl' => $actionUrl,
            'actionLoadingLabel' => $actionType === 'comment' ? '删除中...' : '取消中...',
            'itemAttributes' => [
                'data-interaction-item' => 'true',
                'data-interaction-type' => $actionType,
                'data-interaction-video-id' => isset($tweet['videoId']) ? (string) $tweet['videoId'] : '',
                'data-interaction-comment-id' => isset($tweet['commentId']) && is_numeric((string) $tweet['commentId'])
                    ? (string) ((int) $tweet['commentId'])
                    : '',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $tweet
     * @param  array<string, mixed>  $rootAttributes
     */
    private function renderLibraryFeedItem(array $tweet, string $overlayMarkup, array $rootAttributes): string
    {
        return $this->renderFeedCard(
            $tweet,
            interactive: false,
            useStaticPreview: true,
            postedAtText: $this->formatFeedDate($tweet['postedAt'] ?? null),
            frameClassOverride: 'aspect-video',
            titleLineClamp: 1,
            overlayMarkup: $overlayMarkup,
            rootAttributes: $rootAttributes,
            cardClass: 'h-full'
        );
    }

    private function renderFeedCard(
        array $tweet,
        bool $interactive = true,
        bool $useStaticPreview = false,
        ?string $postedAtText = null,
        ?string $frameClassOverride = null,
        int $titleLineClamp = 2,
        ?string $overlayMarkup = null,
        array $rootAttributes = [],
        string $cardClass = ''
    ): string {
        $displayText = $this->getDisplayText($tweet);
        $authorName = isset($tweet['authorName']) && $tweet['authorName'] !== null
            ? (string) $tweet['authorName']
            : '@'.($tweet['authorHandle'] ?? 'unknown');
        $authorHandle = (string) ($tweet['authorHandle'] ?? 'unknown');
        $authorUsername = isset($tweet['authorUsername']) ? trim((string) $tweet['authorUsername']) : '';
        $authorInitial = $this->getAuthorInitial($authorName);
        $frameClass = $frameClassOverride ?? $this->getMediaFrameClass($tweet);
        $durationText = $this->formatVideoDurationText((string) ($tweet['durationText'] ?? ''));
        $mediaMarkup = $useStaticPreview
            ? $this->components->renderFeedMediaPreview($tweet, $frameClass, $durationText, $authorHandle)
            : $this->components->renderFeedMedia($tweet, $frameClass, $durationText, $authorHandle);
        $authorMarkup = $this->components->renderAuthorIdentity(
            imageUrl: $tweet['authorAvatarUrl'] ?? null,
            authorName: $authorName,
            authorHandle: $authorHandle,
            authorInitial: $authorInitial,
            avatarSizeClass: 'h-7 w-7',
            nameClass: 'truncate text-sm font-semibold text-gray-900',
            profileUrl: $this->profileUrlForUsername($authorUsername)
        );

        return $this->renderView('shortvideo.partials.feed.item', [
            'tweetId' => (string) ($tweet['tweetId'] ?? ''),
            'status' => (string) ($tweet['status'] ?? 'pending'),
            'authorName' => $authorName,
            'displayText' => $displayText,
            'mediaMarkup' => $mediaMarkup,
            'authorMarkup' => $authorMarkup,
            'postedAtText' => $postedAtText ?? $this->formatFeedDate($tweet['postedAt'] ?? null),
            'titleLineClampClass' => $this->resolveTitleLineClampClass($titleLineClamp),
            'interactive' => $interactive,
            'overlayMarkup' => $overlayMarkup,
            'rootAttributes' => $rootAttributes,
            'cardClass' => $cardClass,
        ]);
    }

    private function resolveTitleLineClampClass(int $titleLineClamp): string
    {
        return match ($titleLineClamp) {
            1 => 'line-clamp-1',
            3 => 'line-clamp-3',
            4 => 'line-clamp-4',
            default => 'line-clamp-2',
        };
    }

    public function renderDetailModal(): string
    {
        return $this->renderView('shortvideo.partials.feed.detail-modal');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function serializeBootstrapData(array $payload): string
    {
        return str_replace(
            ['&', '<', '>', "\u{2028}", "\u{2029}"],
            ['\u0026', '\u003c', '\u003e', '\u2028', '\u2029'],
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'
        );
    }

    public function formatFeedSummary(
        string $mode,
        string $sourceHandle,
        int $renderedCount,
        bool $done,
        ?string $searchQuery = null
    ): string {
        $sourceLabel = match ($mode) {
            'featured' => '精选',
            'following' => '订阅更新',
            'history' => '观看记录',
            'bookmarks' => '我的收藏',
            'interactions' => '我的互动',
            default => $sourceHandle !== '' ? '@'.$sourceHandle : '全部来源',
        };
        $normalizedSearchQuery = trim((string) ($searchQuery ?? ''));
        $summaryLabel = $normalizedSearchQuery === ''
            ? $sourceLabel
            : ($mode === 'explore' && $sourceHandle === ''
                ? '搜索 “'.$normalizedSearchQuery.'”'
                : $sourceLabel.' · 搜索 “'.$normalizedSearchQuery.'”');

        if ($renderedCount === 0 && $done) {
            return "{$summaryLabel} 暂无内容";
        }

        if ($renderedCount === 0) {
            if ($normalizedSearchQuery !== '') {
                return "{$summaryLabel} 正在加载…";
            }

            return match ($mode) {
                'featured', 'following', 'history', 'bookmarks', 'interactions' => "{$summaryLabel} 正在加载…",
                default => "{$summaryLabel} 正在加载探索内容…",
            };
        }

        return $summaryLabel.' · 已展示 '.$renderedCount.' 条 · '.($done ? '已加载完毕' : '向下滚动继续加载');
    }

    private function formatFeedDate(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '未知时间';
        }

        try {
            $date = CarbonImmutable::parse($value, 'UTC');
        } catch (\Throwable) {
            return '未知时间';
        }

        $diffSeconds = CarbonImmutable::now('UTC')->diffInSeconds($date, false);
        if ($diffSeconds > 0 || abs($diffSeconds) < 60) {
            return '刚刚';
        }

        $diffSeconds = abs($diffSeconds);

        return match (true) {
            $diffSeconds < 3600 => floor($diffSeconds / 60).'分钟前',
            $diffSeconds < 86400 => floor($diffSeconds / 3600).'小时前',
            $diffSeconds < 604800 => floor($diffSeconds / 86400).'天前',
            $diffSeconds < 2592000 => floor($diffSeconds / 604800).'周前',
            $diffSeconds < 31536000 => floor($diffSeconds / 2592000).'个月前',
            default => floor($diffSeconds / 31536000).'年前',
        };
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    private function getDisplayText(array $tweet): string
    {
        $text = preg_replace('/https?:\/\/\S+/u', ' ', (string) ($tweet['text'] ?? '')) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        $text = trim($text);

        return $text !== '' ? $text : '未填写内容文案';
    }

    private function getAuthorInitial(?string $value): string
    {
        $normalized = ltrim(trim((string) ($value ?? '')), '@');

        return $normalized !== '' ? mb_strtoupper(mb_substr($normalized, 0, 1)) : 'L';
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    private function getMediaFrameClass(array $tweet): string
    {
        $frameMode = (string) config('shortvideo.feed_media_frame_mode', 'adaptive');
        if ($frameMode === '16:9') {
            return 'aspect-video';
        }

        $width = (int) ($tweet['mediaWidth'] ?? 0);
        $height = (int) ($tweet['mediaHeight'] ?? 0);

        if ($width > 0 && $height > 0) {
            $ratio = $width / $height;

            return match (true) {
                $ratio >= 1.15 => 'aspect-[6/5]',
                $ratio >= 0.92 => 'aspect-square',
                $ratio >= 0.72 => 'aspect-[4/5]',
                default => 'aspect-[3/4]',
            };
        }

        return mb_strlen((string) ($tweet['text'] ?? '')) > 110 ? 'aspect-[3/4]' : 'aspect-[4/5]';
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    private function resolveInteractionLabel(array $tweet): string
    {
        if (($tweet['interactionType'] ?? null) !== 'comment') {
            return '点赞了这个视频';
        }

        $parentAuthorUsername = trim((string) ($tweet['parentAuthorUsername'] ?? ''));
        if ($parentAuthorUsername !== '') {
            return '回复 @'.$parentAuthorUsername;
        }

        return '评论了这个视频';
    }

    private function formatVideoDurationText(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $parts = array_map('trim', explode(':', $normalized));
        if (count($parts) < 2 || count($parts) > 3) {
            return $normalized;
        }

        foreach ($parts as $index => $part) {
            if (! ctype_digit($part)) {
                return $normalized;
            }

            $parts[$index] = $index === 0 ? (string) ((int) $part) : str_pad($part, 2, '0', STR_PAD_LEFT);
        }

        return implode(':', $parts);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderView(string $view, array $data = []): string
    {
        return $this->views->make($view, $data)->render();
    }

    /**
     * @param  array{id?:int,name?:string,username:string,avatarUrl?:string|null}|null  $viewer
     * @return array<int, array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null,authTriggerPanel?:string|null,mobileHidden?:bool,dividerBefore?:bool}>
     */
    private function navigationItems(string $activePage, ?array $viewer = null): array
    {
        $items = [
            [
                'icon' => $activePage === 'featured' ? 'ph-fill ph-sparkle' : 'ph ph-sparkle',
                'label' => '精选',
                'active' => $activePage === 'featured',
                'href' => route('home'),
            ],
            [
                'icon' => $activePage === 'explore' ? 'ph-fill ph-compass' : 'ph ph-compass',
                'label' => '探索',
                'active' => $activePage === 'explore',
                'href' => route('explore'),
            ],
            [
                'icon' => $activePage === 'rankings' ? 'ph-fill ph-chart-bar' : 'ph ph-chart-bar',
                'label' => '榜单',
                'active' => $activePage === 'rankings',
                'href' => route('rankings'),
            ],
            [
                'icon' => $activePage === 'subscriptions' ? 'ph-fill ph-users-three' : 'ph ph-users-three',
                'label' => '关注',
                'active' => $activePage === 'subscriptions',
                'href' => route('subscriptions'),
            ],
        ];

        if ($viewer !== null && trim((string) ($viewer['username'] ?? '')) !== '') {
            $viewerName = trim((string) ($viewer['name'] ?? ''));
            $viewerUsername = trim((string) ($viewer['username'] ?? ''));
            $items[] = [
                'icon' => $activePage === 'history' ? 'ph-fill ph-clock-counter-clockwise' : 'ph ph-clock-counter-clockwise',
                'label' => '观看记录',
                'active' => $activePage === 'history',
                'href' => route('viewer.history'),
                'mobileHidden' => true,
                'dividerBefore' => true,
            ];
            $items[] = [
                'icon' => $activePage === 'bookmarks' ? 'ph-fill ph-bookmark-simple' : 'ph ph-bookmark-simple',
                'label' => '我的收藏',
                'active' => $activePage === 'bookmarks',
                'href' => route('viewer.bookmarks'),
                'mobileHidden' => true,
            ];
            $items[] = [
                'icon' => $activePage === 'interactions' ? 'ph-fill ph-chat-circle-dots' : 'ph ph-chat-circle-dots',
                'label' => '我的互动',
                'active' => $activePage === 'interactions',
                'href' => route('viewer.interactions'),
                'mobileHidden' => true,
            ];
            $items[] = [
                'icon' => 'ph ph-user-circle',
                'label' => '我的',
                'active' => $activePage === 'profile',
                'href' => $this->profileUrlForUsername($viewerUsername) ?? route('home'),
                'avatarUrl' => isset($viewer['avatarUrl']) ? trim((string) $viewer['avatarUrl']) ?: null : null,
                'avatarInitial' => $this->getAuthorInitial($viewerName !== '' ? $viewerName : $viewerUsername),
            ];

            return $items;
        }

        $items[] = [
            'icon' => 'ph ph-sign-in',
            'label' => '登录',
            'active' => false,
            'href' => route('login'),
            'authTriggerPanel' => 'login',
            'dividerBefore' => true,
        ];

        return $items;
    }

    private function profileUrlForUsername(?string $username): ?string
    {
        $normalizedUsername = trim((string) ($username ?? ''));

        return $normalizedUsername !== ''
            ? route('profile.show', ['username' => $normalizedUsername], false)
            : null;
    }
}
