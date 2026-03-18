<?php

namespace App\Http\Controllers;

use App\ShortVideo\Services\FeedService;
use App\ShortVideo\View\ShortVideoPageViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ViewerLibraryController extends Controller
{
    public function history(Request $request, FeedService $feedService, ShortVideoPageViewFactory $pages): View
    {
        return $pages->renderHistoryPage($feedService->getViewerHistoryPageViewModel($request->query('page')));
    }

    public function bookmarks(Request $request, FeedService $feedService, ShortVideoPageViewFactory $pages): View
    {
        return $pages->renderBookmarksPage($feedService->getViewerBookmarksPageViewModel($request->query('page')));
    }

    public function interactions(Request $request, FeedService $feedService, ShortVideoPageViewFactory $pages): View
    {
        return $pages->renderInteractionsPage($feedService->getViewerInteractionsPageViewModel($request->query('page')));
    }
}
