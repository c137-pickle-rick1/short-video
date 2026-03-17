<?php

namespace App\Http\Controllers;

use App\ShortVideo\Services\FeedService;
use App\ShortVideo\View\HomePageRenderer;
use Illuminate\Contracts\View\View;

final class SubscriptionsController extends Controller
{
    public function __invoke(FeedService $feedService, HomePageRenderer $renderer): View
    {
        $viewModel = $feedService->getSubscriptionsPageViewModel();
        $feed = is_array($viewModel['feed'] ?? null) ? $viewModel['feed'] : null;
        $feedScriptsEnabled = $feed !== null && ($viewModel['state'] ?? '') === 'ready';

        return view('shortvideo.feed', [
            'documentHead' => $renderer->renderDocumentHead((string) $viewModel['pageTitle']),
            'pageHeader' => $renderer->renderPageHeader(route('login'), $viewModel['headerViewer'], route('logout')),
            'desktopNavigation' => $renderer->renderDesktopNavigation('subscriptions', $viewModel['headerViewer']),
            'mobileNavigation' => $renderer->renderMobileNavigation('subscriptions', $viewModel['headerViewer']),
            'page' => $viewModel['page'],
            'state' => $viewModel['state'],
            'recommendations' => $viewModel['recommendations'],
            'toolbarMarkup' => $feedScriptsEnabled
                ? $renderer->renderFeedToolbar(
                    $feed['mode'],
                    (string) $feed['source'],
                    (int) $feed['renderedCount'],
                    (bool) $feed['done'],
                    false
                )
                : '',
            'feedGrid' => $feedScriptsEnabled ? $renderer->renderFeedGrid($viewModel) : '',
            'feedBootstrapData' => $feedScriptsEnabled
                ? $renderer->serializeBootstrapData([
                    'items' => $feed['items'],
                    'nextCursor' => $feed['nextCursor'],
                    'source' => $feed['source'],
                    'limit' => $feed['limit'],
                    'mode' => $feed['mode'],
                ])
                : null,
            'emptyStateMarkup' => $feedScriptsEnabled
                ? $renderer->renderFeedEmptyState(
                    (string) $viewModel['emptyState']['title'],
                    (string) $viewModel['emptyState']['body']
                )
                : null,
            'detailModalMarkup' => $feedScriptsEnabled ? $renderer->renderDetailModal() : null,
            'feedScriptsEnabled' => $feedScriptsEnabled,
            'secondaryContent' => '',
            'loginUrl' => route('login'),
        ]);
    }
}
