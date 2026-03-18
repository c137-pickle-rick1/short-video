<?php

namespace App\ShortVideo\View;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

final class ShortVideoPageViewFactory
{
    public function __construct(
        private readonly HomePageRenderer $renderer,
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
                'recommendations' => $viewModel['recommendations'] ?? [],
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
    public function renderProfilePage(array $viewModel): View
    {
        return $this->views->make('shortvideo.profile', array_merge(
            $this->buildShellViewData('profile', $viewModel),
            [
                'profile' => $viewModel['profile'] ?? [],
                'stats' => $viewModel['stats'] ?? [],
                'logoutUrl' => $this->url->route('logout'),
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

        return [
            'documentHead' => $this->renderer->renderDocumentHead((string) ($viewModel['pageTitle'] ?? '')),
            'pageHeader' => $this->renderer->renderPageHeader($loginUrl, $headerViewer, $logoutUrl),
            'desktopNavigation' => $this->renderer->renderDesktopNavigation($activePage, $headerViewer),
            'mobileNavigation' => $this->renderer->renderMobileNavigation($activePage, $headerViewer),
            'page' => $viewModel['page'] ?? [],
            'loginUrl' => $loginUrl,
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

        return [
            'toolbarMarkup' => $this->renderer->renderFeedToolbar(
                (string) ($feed['mode'] ?? ''),
                (string) ($feed['source'] ?? ''),
                (int) ($feed['renderedCount'] ?? 0),
                (bool) ($feed['done'] ?? false),
                $showSourceFilter
            ),
            'feedGrid' => $this->renderer->renderFeedGrid($viewModel),
            'feedBootstrapData' => $this->renderer->serializeBootstrapData([
                'items' => $feed['items'] ?? [],
                'nextCursor' => $feed['nextCursor'] ?? null,
                'source' => $feed['source'] ?? '',
                'limit' => $feed['limit'] ?? null,
                'mode' => $feed['mode'] ?? null,
            ]),
            'emptyStateMarkup' => $this->renderer->renderFeedEmptyState(
                (string) (($viewModel['emptyState']['title'] ?? null) ?? ''),
                (string) (($viewModel['emptyState']['body'] ?? null) ?? '')
            ),
            'detailModalMarkup' => $this->renderer->renderDetailModal(),
            'feedScriptsEnabled' => true,
        ];
    }
}
