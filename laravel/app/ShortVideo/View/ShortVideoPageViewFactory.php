<?php

namespace App\ShortVideo\View;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

final class ShortVideoPageViewFactory
{
    public function __construct(
        private readonly ShellViewDataFactory $shellViewDataFactory,
        private readonly FeedViewDataFactory $feedViewDataFactory,
        private readonly AuthViewDataFactory $authViewDataFactory,
        private readonly UrlGenerator $url,
        private readonly ViewFactory $views
    ) {}

    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function renderFeedPage(string $activePage, array $viewModel): View
    {
        return $this->views->make('shortvideo.feed', array_merge(
            $this->buildBaseViewData($activePage, $viewModel, includePlyrStyles: true),
            [
                'feed' => $this->feedViewDataFactory->makeFeedPageData($viewModel, true),
                'state' => $viewModel['state'] ?? null,
                'recommendations' => is_array($viewModel['recommendations'] ?? null) ? $viewModel['recommendations'] : [],
                'subscriptionsFollowTabs' => [],
                'selectedSubscriptionsAccount' => null,
            ]
        ));
    }

    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function renderVideoDetailPage(array $viewModel): View
    {
        $video = is_array($viewModel['video'] ?? null) ? $viewModel['video'] : [];
        $videoId = isset($video['id']) && is_numeric((string) $video['id']) ? (int) $video['id'] : null;

        $video['commentsPagination'] = $this->resolveVideoDetailPagination(
            is_array($video['commentsPagination'] ?? null) ? $video['commentsPagination'] : [],
            $videoId
        );

        return $this->views->make('shortvideo.video-detail', array_merge(
            $this->buildBaseViewData('', $viewModel),
            [
                'video' => $video,
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
            $this->buildBaseViewData('subscriptions', $viewModel, includePlyrStyles: $feedScriptsEnabled),
            [
                'feed' => $this->feedViewDataFactory->makeFeedPageData($viewModel, $feedScriptsEnabled),
                'state' => $viewModel['state'] ?? null,
                'recommendations' => [],
                'subscriptionsFollowTabs' => is_array($viewModel['subscriptionsFollowTabs'] ?? null) ? $viewModel['subscriptionsFollowTabs'] : [],
                'selectedSubscriptionsAccount' => is_array($viewModel['selectedSubscriptionsAccount'] ?? null) ? $viewModel['selectedSubscriptionsAccount'] : null,
            ]
        ));
    }

    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function renderRankingsPage(array $viewModel): View
    {
        return $this->views->make('shortvideo.rankings', array_merge(
            $this->buildBaseViewData('rankings', $viewModel),
            [
                'rankingItems' => is_array($viewModel['items'] ?? null) ? $viewModel['items'] : [],
            ]
        ));
    }

    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function renderHistoryPage(array $viewModel): View
    {
        return $this->views->make('shortvideo.history', array_merge(
            $this->buildBaseViewData('history', $viewModel),
            $this->makeHistoryPanelViewData($viewModel)
        ));
    }

    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function renderBookmarksPage(array $viewModel): View
    {
        return $this->views->make('shortvideo.bookmarks', array_merge(
            $this->buildBaseViewData('bookmarks', $viewModel),
            $this->makeBookmarksPanelViewData($viewModel)
        ));
    }

    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function renderInteractionsPage(array $viewModel): View
    {
        return $this->views->make('shortvideo.interactions', array_merge(
            $this->buildBaseViewData('interactions', $viewModel),
            $this->makeInteractionsPanelViewData($viewModel)
        ));
    }

    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function renderProfilePage(array $viewModel): View
    {
        $isOwnProfile = ($viewModel['isOwnProfile'] ?? false) === true;
        $selectedProfilePanel = is_string($viewModel['selectedPanel'] ?? null)
            ? (string) $viewModel['selectedPanel']
            : 'profile';
        $profilePanelRequested = ($viewModel['hasExplicitPanelSelection'] ?? false) === true;
        $followState = is_array($viewModel['followState'] ?? null) ? $viewModel['followState'] : [];
        $followViewerUserId = isset($followState['viewerUserId']) && is_int($followState['viewerUserId'])
            ? $followState['viewerUserId']
            : null;
        $embeddedHistoryViewModel = $isOwnProfile && is_array($viewModel['embeddedHistory'] ?? null)
            ? $viewModel['embeddedHistory']
            : null;
        $embeddedBookmarksViewModel = $isOwnProfile && is_array($viewModel['embeddedBookmarks'] ?? null)
            ? $viewModel['embeddedBookmarks']
            : null;
        $embeddedInteractionsViewModel = $isOwnProfile && is_array($viewModel['embeddedInteractions'] ?? null)
            ? $viewModel['embeddedInteractions']
            : null;
        $publicProfileFeedViewModel = ! $isOwnProfile && is_array($viewModel['publicProfileFeed'] ?? null)
            ? $viewModel['publicProfileFeed']
            : null;
        $publicProfileFeed = $publicProfileFeedViewModel !== null
            ? $this->feedViewDataFactory->makeFeedPageData(
                [
                    'feed' => is_array($publicProfileFeedViewModel['feed'] ?? null) ? $publicProfileFeedViewModel['feed'] : [],
                    'emptyState' => is_array($publicProfileFeedViewModel['emptyState'] ?? null) ? $publicProfileFeedViewModel['emptyState'] : [],
                ],
                true
            )
            : [
                'enabled' => false,
                'gridItems' => [],
                'gridIsEmpty' => true,
                'bootstrapJson' => null,
                'emptyState' => null,
                'detailModalEnabled' => false,
            ];

        return $this->views->make('shortvideo.profile', array_merge(
            $this->buildBaseViewData($isOwnProfile ? 'profile' : '', $viewModel, includePlyrStyles: $publicProfileFeedViewModel !== null),
            [
                'isOwnProfile' => $isOwnProfile,
                'profile' => is_array($viewModel['profile'] ?? null) ? $viewModel['profile'] : [],
                'stats' => is_array($viewModel['stats'] ?? null) ? $viewModel['stats'] : [],
                'selectedProfilePanel' => $selectedProfilePanel,
                'profilePanelRequested' => $profilePanelRequested,
                'profileDashboardItems' => is_array($viewModel['panelItems'] ?? null) ? $viewModel['panelItems'] : [],
                'profilePanelMeta' => is_array($viewModel['profilePanel'] ?? null) ? $viewModel['profilePanel'] : [],
                'creatorCenter' => is_array($viewModel['creatorCenter'] ?? null) ? $viewModel['creatorCenter'] : [],
                'profileHistoryPage' => $embeddedHistoryViewModel !== null && is_array($embeddedHistoryViewModel['page'] ?? null)
                    ? $embeddedHistoryViewModel['page']
                    : [],
                'profileHistoryPanel' => $embeddedHistoryViewModel !== null
                    ? $this->makeHistoryPanelViewData($embeddedHistoryViewModel)
                    : null,
                'profileBookmarksPage' => $embeddedBookmarksViewModel !== null && is_array($embeddedBookmarksViewModel['page'] ?? null)
                    ? $embeddedBookmarksViewModel['page']
                    : [],
                'profileBookmarksPanel' => $embeddedBookmarksViewModel !== null
                    ? $this->makeBookmarksPanelViewData($embeddedBookmarksViewModel)
                    : null,
                'profileInteractionsPage' => $embeddedInteractionsViewModel !== null && is_array($embeddedInteractionsViewModel['page'] ?? null)
                    ? $embeddedInteractionsViewModel['page']
                    : [],
                'profileInteractionsPanel' => $embeddedInteractionsViewModel !== null
                    ? $this->makeInteractionsPanelViewData($embeddedInteractionsViewModel)
                    : null,
                'socialConnections' => $viewModel['socialConnections'] ?? null,
                'profileVideoLibrary' => $viewModel['profileVideoLibrary'] ?? null,
                'publicProfileFeed' => $publicProfileFeedViewModel,
                'publicProfileFeedData' => $publicProfileFeed,
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
    private function makeHistoryPanelViewData(array $viewModel): array
    {
        $history = is_array($viewModel['history'] ?? null) ? $viewModel['history'] : [];
        $historyItems = is_array($history['items'] ?? null) ? $history['items'] : [];
        $historyPagination = $this->resolveCollectionPagination(is_array($history['pagination'] ?? null) ? $history['pagination'] : []);
        $eligibleHistoryItems = array_values(array_filter(
            $historyItems,
            static fn (array $item): bool => isset($item['videoId']) && is_numeric((string) $item['videoId'])
        ));
        $historyCount = count($eligibleHistoryItems);
        $historyEmptyState = is_array($history['emptyState'] ?? null) ? $history['emptyState'] : [];

        return [
            'historyCount' => $historyCount,
            'historyHasItems' => $historyCount > 0,
            'historyPagination' => $historyPagination,
            'historyItems' => array_map(
                fn (array $item): array => $this->feedViewDataFactory->makeFeedItemData(
                    $item,
                    interactive: false,
                    useStaticPreview: true,
                    postedAtText: null,
                    frameClassOverride: 'aspect-video',
                    titleLineClamp: 1,
                    rootAttributes: [
                        'data-history-record-item' => 'true',
                        'data-history-video-id' => isset($item['videoId']) ? (string) $item['videoId'] : '',
                    ],
                    cardClass: 'h-full'
                ),
                $eligibleHistoryItems
            ),
            'historyEmptyState' => [
                'title' => (string) ($historyEmptyState['title'] ?? '还没有观看记录'),
                'description' => (string) ($historyEmptyState['description'] ?? '继续去探索页看看新内容。只要开始浏览，真实观看记录就会按时间倒序出现在这里。'),
                'iconClass' => 'ph ph-clock-counter-clockwise',
                'buttonLabel' => '去探索',
                'buttonHref' => $this->url->route('explore'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $viewModel
     * @return array<string, mixed>
     */
    private function makeBookmarksPanelViewData(array $viewModel): array
    {
        $bookmarks = is_array($viewModel['bookmarks'] ?? null) ? $viewModel['bookmarks'] : [];
        $bookmarkItems = is_array($bookmarks['items'] ?? null) ? $bookmarks['items'] : [];
        $bookmarkPagination = $this->resolveCollectionPagination(is_array($bookmarks['pagination'] ?? null) ? $bookmarks['pagination'] : []);
        $eligibleBookmarkItems = array_values(array_filter(
            $bookmarkItems,
            static fn (array $item): bool => isset($item['videoId']) && is_numeric((string) $item['videoId'])
        ));
        $bookmarkCount = count($eligibleBookmarkItems);
        $bookmarkEmptyState = is_array($bookmarks['emptyState'] ?? null) ? $bookmarks['emptyState'] : [];

        return [
            'bookmarkCount' => $bookmarkCount,
            'bookmarkHasItems' => $bookmarkCount > 0,
            'bookmarkPagination' => $bookmarkPagination,
            'bookmarkItems' => array_map(
                fn (array $item): array => $this->feedViewDataFactory->makeFeedItemData(
                    $item,
                    interactive: false,
                    useStaticPreview: true,
                    postedAtText: null,
                    frameClassOverride: 'aspect-video',
                    titleLineClamp: 1,
                    rootAttributes: [
                        'data-bookmark-record-item' => 'true',
                        'data-bookmark-video-id' => isset($item['videoId']) ? (string) $item['videoId'] : '',
                    ],
                    cardClass: 'h-full'
                ),
                $eligibleBookmarkItems
            ),
            'bookmarkEmptyState' => [
                'title' => (string) ($bookmarkEmptyState['title'] ?? '还没有收藏内容'),
                'description' => (string) ($bookmarkEmptyState['description'] ?? '看到想回看的视频时点一下收藏。你保存过的内容会按最近收藏时间排在这里。'),
                'iconClass' => 'ph ph-bookmark-simple',
                'buttonLabel' => '去探索',
                'buttonHref' => $this->url->route('explore'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $viewModel
     * @return array<string, mixed>
     */
    private function makeInteractionsPanelViewData(array $viewModel): array
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
        $interactionEmptyState = is_array($interactions['emptyState'] ?? null) ? $interactions['emptyState'] : [];

        return [
            'interactionCount' => $interactionCount,
            'interactionHasItems' => $interactionCount > 0,
            'interactionPagination' => $interactionPagination,
            'interactionItems' => array_map(
                fn (array $item): array => $this->feedViewDataFactory->makeInteractionItemData(
                    $item,
                    (string) ($item['interactionType'] ?? '') === 'comment'
                        ? '/api/videos/'.(int) ($item['videoId'] ?? 0).'/comments/'.(int) ($item['commentId'] ?? 0)
                        : '/api/videos/'.(int) ($item['videoId'] ?? 0).'/likes'
                ),
                $eligibleInteractionItems
            ),
            'interactionEmptyState' => [
                'title' => (string) ($interactionEmptyState['title'] ?? '还没有互动内容'),
                'description' => (string) ($interactionEmptyState['description'] ?? '先去探索页和内容发生一点互动。你点赞或评论过的视频会按时间倒序出现在这里。'),
                'iconClass' => 'ph ph-chat-circle-dots',
                'buttonLabel' => '去探索',
                'buttonHref' => $this->url->route('explore'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $viewModel
     * @return array<string, mixed>
     */
    private function buildBaseViewData(string $activePage, array $viewModel, bool $includePlyrStyles = false): array
    {
        $headerViewer = is_array($viewModel['headerViewer'] ?? null) ? $viewModel['headerViewer'] : null;
        $loginUrl = $this->url->route('login');

        return [
            'shell' => $this->shellViewDataFactory->makePageShell($activePage, $viewModel, $includePlyrStyles),
            'page' => is_array($viewModel['page'] ?? null) ? $viewModel['page'] : [],
            'loginUrl' => $loginUrl,
            'auth' => $headerViewer === null
                ? $this->authViewDataFactory->makeModalData(
                    initialPanel: 'login',
                    open: false,
                    standalone: false,
                    closeUrl: null,
                    loginFormAction: $loginUrl,
                    registerFormAction: $this->url->route('register.store'),
                    resetPasswordFormAction: $this->url->route('password.reset.store'),
                    sendCodeAction: $this->url->route('auth.email-codes.store')
                )
                : $this->authViewDataFactory->empty(),
        ];
    }

    /**
     * @param  array<string, mixed>  $pagination
     * @return array<string, mixed>
     */
    private function resolveCollectionPagination(array $pagination): array
    {
        return $this->resolvePagination(
            $pagination,
            fn (int $page): string => $this->paginationPageUrl($page)
        );
    }

    /**
     * @param  array<string, mixed>  $pagination
     * @return array<string, mixed>
     */
    private function resolveVideoDetailPagination(array $pagination, ?int $videoId): array
    {
        return $this->resolvePagination(
            $pagination,
            fn (int $page): string => $this->videoDetailPaginationPageUrl($page, $videoId)
        );
    }

    /**
     * @param  array<string, mixed>  $pagination
     * @param  callable(int): string  $pageUrlResolver
     * @return array<string, mixed>
     */
    private function resolvePagination(array $pagination, callable $pageUrlResolver): array
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
            'previousPageUrl' => $currentPage > 1 ? $pageUrlResolver($currentPage - 1) : null,
            'nextPageUrl' => $currentPage < $lastPage ? $pageUrlResolver($currentPage + 1) : null,
            'links' => $this->buildPaginationLinks($currentPage, $lastPage, $pageUrlResolver),
        ];
    }

    /**
     * @param  callable(int): string  $pageUrlResolver
     * @return array<int, array<string, mixed>>
     */
    private function buildPaginationLinks(int $currentPage, int $lastPage, callable $pageUrlResolver): array
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
                'url' => $pageUrlResolver($page),
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
        $routeParameters = is_array(request()->route()?->parametersWithoutNulls())
            ? request()->route()?->parametersWithoutNulls()
            : [];
        $queryParameters = $this->filteredQueryParameters();

        return $this->routePaginationPageUrl($resolvedRouteName, $routeParameters, $queryParameters, $page);
    }

    private function videoDetailPaginationPageUrl(int $page, ?int $videoId): string
    {
        if ($videoId === null || $videoId <= 0) {
            return '#';
        }

        return $this->routePaginationPageUrl(
            'videos.show',
            ['video' => $videoId],
            $this->filteredQueryParameters(),
            $page
        );
    }

    /**
     * @param  array<string, mixed>  $routeParameters
     * @param  array<string, mixed>  $queryParameters
     */
    private function routePaginationPageUrl(string $routeName, array $routeParameters, array $queryParameters, int $page): string
    {
        return $this->url->route($routeName, array_merge(
            $routeParameters,
            $queryParameters,
            $page <= 1 ? [] : ['page' => $page]
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function filteredQueryParameters(): array
    {
        $queryParameters = request()->query();

        if (is_array($queryParameters)) {
            unset($queryParameters['page']);
        } else {
            $queryParameters = [];
        }

        return $queryParameters;
    }
}
