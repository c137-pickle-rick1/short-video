<?php

namespace App\Http\Controllers;

use App\ShortVideo\Services\FeedService;
use App\ShortVideo\View\HomePageRenderer;
use Illuminate\Contracts\View\View;

final class RankingsController extends Controller
{
    public function __invoke(FeedService $feedService, HomePageRenderer $renderer): View
    {
        $viewModel = $feedService->getCreatorRankingsPageViewModel();

        return view('shortvideo.rankings', [
            'documentHead' => $renderer->renderDocumentHead((string) $viewModel['pageTitle']),
            'pageHeader' => $renderer->renderPageHeader(route('login'), $viewModel['headerViewer'], route('logout')),
            'desktopNavigation' => $renderer->renderDesktopNavigation('rankings', $viewModel['headerViewer']),
            'mobileNavigation' => $renderer->renderMobileNavigation('rankings', $viewModel['headerViewer']),
            'page' => $viewModel['page'],
            'rankingItems' => $viewModel['items'],
            'loginUrl' => route('login'),
        ]);
    }
}
