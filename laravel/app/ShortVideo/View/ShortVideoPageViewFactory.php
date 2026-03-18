<?php

namespace App\ShortVideo\View;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

final class ShortVideoPageViewFactory
{
    public function __construct(
        private readonly HomePageRenderer $renderer,
        private readonly LoginPageRenderer $loginPageRenderer,
        private readonly UrlGenerator $url,
        private readonly ViewFactory $views
    ) {}

    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function renderFeedPage(string $activePage, array $viewModel): View
    {
        return $this->views->make('shortvideo.feed', array_merge(
            $this->buildShellViewData($activePage, $viewModel),
            $this->buildFeedPayload(
                $viewModel,
                feedScriptsEnabled: true,
                showSourceFilter: (bool) ($viewModel['toolbar']['showSourceFilter'] ?? false)
            ),
            [
                'secondaryContent' => '',
            ]
        ));
    }

    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function renderSubscriptionsPage(array $viewModel): View
    {
        $feed = is_array($viewModel['feed'] ?? null) ? $viewModel['feed'] : null;
        $feedScriptsEnabled = $feed !== null && ($viewModel['state'] ?? '') === 'ready';

        return $this->views->make('shortvideo.feed', array_merge(
            $this->buildShellViewData('subscriptions', $viewModel),
            $this->buildFeedPayload($viewModel, $feedScriptsEnabled, false),
            [
                'state' => $viewModel['state'] ?? null,
                'recommendations' => [],
                'subscriptionsFollowTabs' => $viewModel['subscriptionsFollowTabs'] ?? [],
                'selectedSubscriptionsAccount' => $viewModel['selectedSubscriptionsAccount'] ?? null,
                'secondaryContent' => '',
            ]
        ));
    }

    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function renderRankingsPage(array $viewModel): View
    {
        return $this->views->make('shortvideo.rankings', array_merge(
            $this->buildShellViewData('rankings', $viewModel),
            [
                'rankingItems' => $viewModel['items'] ?? [],
            ]
        ));
    }

    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function renderHistoryPage(array $viewModel): View
    {
        $history = is_array($viewModel['history'] ?? null) ? $viewModel['history'] : [];
        $historyItems = is_array($history['items'] ?? null) ? $history['items'] : [];
        $historyPagination = $this->resolveCollectionPagination(is_array($history['pagination'] ?? null) ? $history['pagination'] : []);
        $eligibleHistoryItems = array_values(array_filter(
            $historyItems,
            static fn (array $item): bool => isset($item['videoId']) && is_numeric((string) $item['videoId'])
        ));
        $historyCount = count($eligibleHistoryItems);
        $historyCardsMarkup = implode('', array_map(
            fn (array $item): string => $this->renderer->renderHistoryFeedItem(
                $item,
                '/api/videos/'.(int) ($item['videoId'] ?? 0).'/history'
            ),
            $eligibleHistoryItems
        ));
        $historyEmptyState = is_array($history['emptyState'] ?? null) ? $history['emptyState'] : [];

        return $this->views->make('shortvideo.history', array_merge(
            $this->buildShellViewData('history', $viewModel),
            [
                'historyCount' => $historyCount,
                'historyHasItems' => $historyCount > 0,
                'historyPagination' => $historyPagination,
                'historyItemsMarkup' => $historyCardsMarkup,
                'historyEmptyMarkup' => $this->renderer->renderFeedEmptyState(
                    title: (string) ($historyEmptyState['title'] ?? '还没有观看记录'),
                    description: (string) ($historyEmptyState['description'] ?? '继续去探索页看看新内容。只要开始浏览，真实观看记录就会按时间倒序出现在这里。'),
                    iconClass: 'ph ph-clock-counter-clockwise',
                    buttonLabel: '去探索',
                    buttonHref: $this->url->route('explore'),
                ),
            ]
        ));
    }

    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function renderBookmarksPage(array $viewModel): View
    {
        $bookmarks = is_array($viewModel['bookmarks'] ?? null) ? $viewModel['bookmarks'] : [];
        $bookmarkItems = is_array($bookmarks['items'] ?? null) ? $bookmarks['items'] : [];
        $bookmarkPagination = $this->resolveCollectionPagination(is_array($bookmarks['pagination'] ?? null) ? $bookmarks['pagination'] : []);
        $eligibleBookmarkItems = array_values(array_filter(
            $bookmarkItems,
            static fn (array $item): bool => isset($item['videoId']) && is_numeric((string) $item['videoId'])
        ));
        $bookmarkCount = count($eligibleBookmarkItems);
        $bookmarkCardsMarkup = implode('', array_map(
            fn (array $item): string => $this->renderer->renderBookmarksFeedItem(
                $item,
                '/api/videos/'.(int) ($item['videoId'] ?? 0).'/bookmarks'
            ),
            $eligibleBookmarkItems
        ));
        $bookmarkEmptyState = is_array($bookmarks['emptyState'] ?? null) ? $bookmarks['emptyState'] : [];

        return $this->views->make('shortvideo.bookmarks', array_merge(
            $this->buildShellViewData('bookmarks', $viewModel),
            [
                'bookmarkCount' => $bookmarkCount,
                'bookmarkHasItems' => $bookmarkCount > 0,
                'bookmarkPagination' => $bookmarkPagination,
                'bookmarkItemsMarkup' => $bookmarkCardsMarkup,
                'bookmarkEmptyMarkup' => $this->renderer->renderFeedEmptyState(
                    title: (string) ($bookmarkEmptyState['title'] ?? '还没有收藏内容'),
                    description: (string) ($bookmarkEmptyState['description'] ?? '看到想回看的视频时点一下收藏。你保存过的内容会按最近收藏时间排在这里。'),
                    iconClass: 'ph ph-bookmark-simple',
                    buttonLabel: '去探索',
                    buttonHref: $this->url->route('explore'),
                ),
            ]
        ));
    }

    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function renderInteractionsPage(array $viewModel): View
    {
        $interactions = is_array($viewModel['interactions'] ?? null) ? $viewModel['interactions'] : [];
        $interactionItems = is_array($interactions['items'] ?? null) ? $interactions['items'] : [];
        $interactionPagination = $this->resolveCollectionPagination(is_array($interactions['pagination'] ?? null) ? $interactions['pagination'] : []);
        $eligibleInteractionItems = array_values(array_filter(
            $interactionItems,
            static fn (array $item): bool => isset($item['videoId']) && is_numeric((string) $item['videoId'])
                && in_array((string) ($item['interactionType'] ?? ''), ['like', 'comment'], true)
                && (
                    (string) ($item['interactionType'] ?? '') !== 'comment'
                    || (isset($item['commentId']) && is_numeric((string) $item['commentId']))
                )
        ));
        $interactionCount = count($eligibleInteractionItems);
        $interactionItemsMarkup = implode('', array_map(
            fn (array $item): string => $this->renderer->renderInteractionListItem(
                $item,
                (string) ($item['interactionType'] ?? '') === 'comment'
                    ? '/api/videos/'.(int) ($item['videoId'] ?? 0).'/comments/'.(int) ($item['commentId'] ?? 0)
                    : '/api/videos/'.(int) ($item['videoId'] ?? 0).'/likes'
            ),
            $eligibleInteractionItems
        ));
        $interactionEmptyState = is_array($interactions['emptyState'] ?? null) ? $interactions['emptyState'] : [];

        return $this->views->make('shortvideo.interactions', array_merge(
            $this->buildShellViewData('interactions', $viewModel),
            [
                'interactionCount' => $interactionCount,
                'interactionHasItems' => $interactionCount > 0,
                'interactionPagination' => $interactionPagination,
                'interactionItemsMarkup' => $interactionItemsMarkup,
                'interactionEmptyMarkup' => $this->renderer->renderFeedEmptyState(
                    title: (string) ($interactionEmptyState['title'] ?? '还没有互动内容'),
                    description: (string) ($interactionEmptyState['description'] ?? '先去探索页和内容发生一点互动。你点赞或评论过的视频会按时间倒序出现在这里。'),
                    iconClass: 'ph ph-chat-circle-dots',
                    buttonLabel: '去探索',
                    buttonHref: $this->url->route('explore'),
                ),
            ]
        ));
    }

    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function renderProfilePage(array $viewModel): View
    {
        $isOwnProfile = ($viewModel['isOwnProfile'] ?? false) === true;
        $followState = is_array($viewModel['followState'] ?? null) ? $viewModel['followState'] : [];
        $followViewerUserId = isset($followState['viewerUserId']) && is_int($followState['viewerUserId'])
            ? $followState['viewerUserId']
            : null;
        $publicProfileFeedViewModel = ! $isOwnProfile && is_array($viewModel['publicProfileFeed'] ?? null)
            ? $viewModel['publicProfileFeed']
            : null;
        $publicProfileFeedPayload = $publicProfileFeedViewModel !== null
            ? $this->buildFeedPayload(
                [
                    'feed' => is_array($publicProfileFeedViewModel['feed'] ?? null) ? $publicProfileFeedViewModel['feed'] : [],
                    'emptyState' => is_array($publicProfileFeedViewModel['emptyState'] ?? null) ? $publicProfileFeedViewModel['emptyState'] : [],
                ],
                true,
                false
            )
            : [
                'toolbarMarkup' => '',
                'feedGrid' => '',
                'feedBootstrapData' => null,
                'emptyStateMarkup' => null,
                'detailModalMarkup' => null,
                'feedScriptsEnabled' => false,
            ];

        return $this->views->make('shortvideo.profile', array_merge(
            $this->buildShellViewData($isOwnProfile ? 'profile' : '', $viewModel),
            [
                'isOwnProfile' => $isOwnProfile,
                'profile' => $viewModel['profile'] ?? [],
                'stats' => $viewModel['stats'] ?? [],
                'socialConnections' => $viewModel['socialConnections'] ?? null,
                'profileVideoLibrary' => $viewModel['profileVideoLibrary'] ?? null,
                'publicProfileFeed' => $publicProfileFeedViewModel,
                'publicProfileFeedGrid' => $publicProfileFeedPayload['feedGrid'] ?? '',
                'publicProfileFeedBootstrapData' => $publicProfileFeedPayload['feedBootstrapData'] ?? null,
                'publicProfileFeedEmptyStateMarkup' => $publicProfileFeedPayload['emptyStateMarkup'] ?? null,
                'publicProfileFeedDetailModalMarkup' => $publicProfileFeedPayload['detailModalMarkup'] ?? null,
                'publicProfileFeedScriptsEnabled' => ($publicProfileFeedPayload['feedScriptsEnabled'] ?? false) === true,
                'followState' => $followState,
                'logoutUrl' => $this->url->route('logout'),
                'profileEditorScriptsEnabled' => $isOwnProfile,
                'profileVideoUploadScriptsEnabled' => $isOwnProfile,
                'profileFollowScriptsEnabled' => $followViewerUserId !== null,
            ]
        ));
    }

    /**
     * @param  array<string, mixed>  $viewModel
     * @return array<string, mixed>
     */
    private function buildShellViewData(string $activePage, array $viewModel): array
    {
        $headerViewer = is_array($viewModel['headerViewer'] ?? null) ? $viewModel['headerViewer'] : null;
        $loginUrl = $this->url->route('login');
        $logoutUrl = $this->url->route('logout');
        $searchUrl = $this->url->route('explore');
        $searchQuery = (string) ($viewModel['searchQuery'] ?? '');

        return [
            'documentHead' => $this->renderer->renderDocumentHead((string) ($viewModel['pageTitle'] ?? '')),
            'pageHeader' => $this->renderer->renderPageHeader($searchUrl, $headerViewer, $logoutUrl, $searchQuery),
            'desktopNavigation' => $this->renderer->renderDesktopNavigation($activePage, $headerViewer),
            'mobileNavigation' => $this->renderer->renderMobileNavigation($activePage, $headerViewer),
            'page' => $viewModel['page'] ?? [],
            'loginUrl' => $loginUrl,
            'authModalMarkup' => $headerViewer === null
                ? $this->loginPageRenderer->renderAuthModal(
                    initialPanel: 'login',
                    open: false,
                    standalone: false,
                    loginFormAction: $loginUrl
                )
                : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $viewModel
     * @return array<string, mixed>
     */
    private function buildFeedPayload(array $viewModel, bool $feedScriptsEnabled, bool $showSourceFilter): array
    {
        $feed = is_array($viewModel['feed'] ?? null) ? $viewModel['feed'] : null;

        if (! $feedScriptsEnabled || $feed === null) {
            return [
                'toolbarMarkup' => '',
                'feedGrid' => '',
                'feedBootstrapData' => null,
                'emptyStateMarkup' => null,
                'detailModalMarkup' => null,
                'feedScriptsEnabled' => false,
            ];
        }

        $resolvedEmptyState = $this->resolveFeedEmptyState(
            $feed,
            is_array($viewModel['emptyState'] ?? null) ? $viewModel['emptyState'] : []
        );
        $viewModelWithEmptyState = array_merge($viewModel, [
            'emptyState' => $resolvedEmptyState,
        ]);

        return [
            'toolbarMarkup' => $this->renderer->renderFeedToolbar(
                (string) ($feed['mode'] ?? ''),
                (string) ($feed['source'] ?? ''),
                (int) ($feed['renderedCount'] ?? 0),
                (bool) ($feed['done'] ?? false),
                $showSourceFilter,
                isset($feed['query']) ? (string) $feed['query'] : null,
                isset($viewModel['toolbar']['summaryText']) ? (string) $viewModel['toolbar']['summaryText'] : null,
                isset($viewModel['toolbar']['statusText']) ? (string) $viewModel['toolbar']['statusText'] : null
            ),
            'feedGrid' => $this->renderer->renderFeedGrid($viewModelWithEmptyState),
            'feedBootstrapData' => $this->renderer->serializeBootstrapData([
                'items' => $feed['items'] ?? [],
                'nextCursor' => $feed['nextCursor'] ?? null,
                'source' => $feed['source'] ?? '',
                'limit' => $feed['limit'] ?? null,
                'mode' => $feed['mode'] ?? null,
                'query' => $feed['query'] ?? null,
            ]),
            'emptyStateMarkup' => $this->renderer->renderFeedEmptyState(
                title: (string) ($resolvedEmptyState['title'] ?? ''),
                description: (string) ($resolvedEmptyState['description'] ?? ''),
                iconClass: (string) ($resolvedEmptyState['iconClass'] ?? 'ph ph-magnifying-glass'),
                buttonLabel: isset($resolvedEmptyState['buttonLabel']) ? (string) $resolvedEmptyState['buttonLabel'] : null,
                buttonHref: isset($resolvedEmptyState['buttonHref']) ? (string) $resolvedEmptyState['buttonHref'] : null,
                buttonAttributes: is_array($resolvedEmptyState['buttonAttributes'] ?? null) ? $resolvedEmptyState['buttonAttributes'] : []
            ),
            'detailModalMarkup' => $this->renderer->renderDetailModal(),
            'feedScriptsEnabled' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $feed
     * @param  array<string, mixed>  $emptyState
     * @return array<string, mixed>
     */
    private function resolveFeedEmptyState(array $feed, array $emptyState): array
    {
        $mode = (string) ($feed['mode'] ?? '');
        $source = trim((string) ($feed['source'] ?? ''));
        $query = trim((string) ($feed['query'] ?? ''));
        $title = trim((string) ($emptyState['title'] ?? '')) ?: '还没有可展示的视频';
        $description = trim((string) (($emptyState['description'] ?? null) ?? ($emptyState['body'] ?? '')))
            ?: '先在 <code>config/sources.json</code> 启用来源并运行抓取。首页布局已经准备好，一旦有数据就会按瀑布流方式展示出来。';
        $iconClass = trim((string) ($emptyState['iconClass'] ?? ''));
        $buttonLabel = trim((string) ($emptyState['buttonLabel'] ?? ''));
        $buttonHref = trim((string) ($emptyState['buttonHref'] ?? ''));
        $buttonAttributes = is_array($emptyState['buttonAttributes'] ?? null) ? $emptyState['buttonAttributes'] : [];

        if ($iconClass === '') {
            $iconClass = match (true) {
                $query !== '' => 'ph ph-magnifying-glass',
                $mode === 'following' => 'ph ph-bell-slash',
                $mode === 'history' => 'ph ph-clock-counter-clockwise',
                $mode === 'bookmarks' => 'ph ph-bookmark-simple',
                $mode === 'interactions' => 'ph ph-chat-circle-dots',
                $mode === 'featured' => 'ph ph-shooting-star',
                $source !== '' => 'ph ph-hash',
                default => 'ph ph-compass-tool',
            };
        }

        if ($buttonLabel === '' || $buttonHref === '') {
            [$defaultButtonLabel, $defaultButtonHref] = $this->defaultFeedEmptyStateAction($mode, $source, $query);
            $buttonLabel = $buttonLabel !== '' ? $buttonLabel : $defaultButtonLabel;
            $buttonHref = $buttonHref !== '' ? $buttonHref : $defaultButtonHref;
        }

        return [
            'title' => $title,
            'description' => $description,
            'iconClass' => $iconClass,
            'buttonLabel' => $buttonLabel,
            'buttonHref' => $buttonHref,
            'buttonAttributes' => $buttonAttributes,
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function defaultFeedEmptyStateAction(string $mode, string $source, string $query): array
    {
        if ($query !== '') {
            $params = $source !== '' ? ['source' => $source] : [];

            return ['清除搜索', $this->url->route('explore', $params)];
        }

        return match (true) {
            $mode === 'featured' => ['去探索', $this->url->route('explore')],
            $mode === 'following' => ['去探索', $this->url->route('explore')],
            $mode === 'history' => ['去探索', $this->url->route('explore')],
            $mode === 'bookmarks' => ['去探索', $this->url->route('explore')],
            $mode === 'interactions' => ['去探索', $this->url->route('explore')],
            $source !== '' => ['查看全部来源', $this->url->route('explore')],
            default => ['查看精选', $this->url->route('home')],
        };
    }

    /**
     * @param  array<string, mixed>  $pagination
     * @return array<string, mixed>
     */
    private function resolveCollectionPagination(array $pagination): array
    {
        $currentPage = max(1, (int) ($pagination['currentPage'] ?? 1));
        $lastPage = max(1, (int) ($pagination['lastPage'] ?? 1));
        $totalCount = max(0, (int) ($pagination['totalCount'] ?? 0));
        $perPage = max(1, (int) ($pagination['perPage'] ?? 12));

        return [
            'currentPage' => $currentPage,
            'lastPage' => $lastPage,
            'totalCount' => $totalCount,
            'perPage' => $perPage,
            'hasPages' => $lastPage > 1,
            'previousPageUrl' => $currentPage > 1 ? $this->paginationPageUrl($currentPage - 1) : null,
            'nextPageUrl' => $currentPage < $lastPage ? $this->paginationPageUrl($currentPage + 1) : null,
            'links' => $this->buildCollectionPaginationLinks($currentPage, $lastPage),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCollectionPaginationLinks(int $currentPage, int $lastPage): array
    {
        if ($lastPage <= 1) {
            return [];
        }

        $candidatePages = array_values(array_unique(array_filter(
            [
                1,
                2,
                $currentPage - 1,
                $currentPage,
                $currentPage + 1,
                $lastPage - 1,
                $lastPage,
            ],
            static fn (int $page): bool => $page >= 1 && $page <= $lastPage
        )));
        sort($candidatePages);

        $links = [];
        $previousPage = null;

        foreach ($candidatePages as $page) {
            if ($previousPage !== null && ($page - $previousPage) > 1) {
                $links[] = [
                    'type' => 'ellipsis',
                    'label' => '...',
                    'url' => null,
                    'active' => false,
                ];
            }

            $links[] = [
                'type' => 'page',
                'label' => (string) $page,
                'url' => $this->paginationPageUrl($page),
                'active' => $page === $currentPage,
            ];
            $previousPage = $page;
        }

        return $links;
    }

    private function paginationPageUrl(int $page): string
    {
        $routeName = request()->route()?->getName();
        $resolvedRouteName = is_string($routeName) && $routeName !== '' ? $routeName : 'viewer.history';

        return $page <= 1
            ? $this->url->route($resolvedRouteName)
            : $this->url->route($resolvedRouteName, ['page' => $page]);
    }
}
