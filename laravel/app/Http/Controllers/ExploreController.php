<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExplorePageRequest;
use App\ShortVideo\Services\FeedService;
use App\ShortVideo\View\ShortVideoPageViewFactory;
use Illuminate\Contracts\View\View;

final class ExploreController extends Controller
{
    public function __invoke(ExplorePageRequest $request, FeedService $feedService, ShortVideoPageViewFactory $pages): View
    {
        return $pages->renderFeedPage('explore', $feedService->getExplorePageViewModel(
            sourceHandle: $request->validated('source'),
            query: $request->validated('q'),
        ));
    }
}
