<?php

namespace App\Http\Controllers;

use App\ShortVideo\Services\FeedService;
use App\ShortVideo\View\ShortVideoPageViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ExploreController extends Controller
{
    public function __invoke(Request $request, FeedService $feedService, ShortVideoPageViewFactory $pages): View
    {
        return $pages->renderFeedPage('explore', $feedService->getExplorePageViewModel($request->query('source')));
    }
}
