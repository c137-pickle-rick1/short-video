<?php

namespace App\Http\Controllers;

use App\ShortVideo\Services\FeedService;
use App\ShortVideo\View\HomePageRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class HomeController extends Controller
{
    public function __invoke(Request $request, FeedService $feedService, HomePageRenderer $renderer): View
    {
        $viewModel = $feedService->getHomePageViewModel($request->query('source'));
        $feed = $viewModel['feed'];

        return view('shortvideo.home', [
            'documentHead' => $renderer->renderDocumentHead((string) $viewModel['pageTitle']),
            'desktopNavigation' => $renderer->renderDesktopNavigation(),
            'mobileNavigation' => $renderer->renderMobileNavigation(),
            'feedGrid' => $renderer->renderFeedGrid($viewModel),
            'emptyStateMarkup' => $renderer->renderFeedEmptyState(),
            'detailModalMarkup' => $renderer->renderDetailModal(),
            'bootstrapData' => $renderer->serializeBootstrapData([
                'items' => $feed['items'],
                'nextCursor' => $feed['nextCursor'],
                'source' => $viewModel['activeSourceHandle'],
                'limit' => $feed['limit'],
            ]),
        ]);
    }
}
