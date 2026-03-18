<?php

namespace App\Http\Controllers;

use App\ShortVideo\Services\FeedService;
use App\ShortVideo\View\ShortVideoPageViewFactory;
use Illuminate\Contracts\View\View;

final class HomeController extends Controller
{
    public function __invoke(FeedService $feedService, ShortVideoPageViewFactory $pages): View
    {
        return $pages->renderFeedPage('featured', $feedService->getFeaturedPageViewModel());
    }
}
