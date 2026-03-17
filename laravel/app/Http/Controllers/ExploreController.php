<?php

namespace App\Http\Controllers;

use App\ShortVideo\Services\FeedService;
use App\ShortVideo\View\HomePageRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ExploreController extends Controller
{
    public function __invoke(Request $request, FeedService $feedService, HomePageRenderer $renderer): View
    {
        $viewModel = $feedService->getExplorePageViewModel($request->query('source'));
        $feed = $viewModel['feed'];

        return view('shortvideo.feed', [
            'documentHead' => $renderer->renderDocumentHead((string) $viewModel['pageTitle']),
            'pageHeader' => $renderer->renderPageHeader(route('login'), $viewModel['headerViewer'], route('logout')),
            'desktopNavigation' => $renderer->renderDesktopNavigation('explore', $viewModel['headerViewer']),
            'mobileNavigation' => $renderer->renderMobileNavigation('explore', $viewModel['headerViewer']),
            'page' => $viewModel['page'],
            'toolbarMarkup' => $renderer->renderFeedToolbar(
                $feed['mode'],
                (string) $feed['source'],
                (int) $feed['renderedCount'],
                (bool) $feed['done'],
                (bool) ($viewModel['toolbar']['showSourceFilter'] ?? false)
            ),
            'feedGrid' => $renderer->renderFeedGrid($viewModel),
            'feedBootstrapData' => $renderer->serializeBootstrapData([
                'items' => $feed['items'],
                'nextCursor' => $feed['nextCursor'],
                'source' => $feed['source'],
                'limit' => $feed['limit'],
                'mode' => $feed['mode'],
            ]),
            'emptyStateMarkup' => $renderer->renderFeedEmptyState(
                (string) $viewModel['emptyState']['title'],
                (string) $viewModel['emptyState']['body']
            ),
            'detailModalMarkup' => $renderer->renderDetailModal(),
            'feedScriptsEnabled' => true,
            'secondaryContent' => '',
            'loginUrl' => route('login'),
        ]);
    }
}
