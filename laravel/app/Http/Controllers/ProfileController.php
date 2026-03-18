<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\ShortVideo\Services\FeedService;
use App\ShortVideo\View\ShortVideoPageViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    public function __invoke(Request $request, FeedService $feedService, ShortVideoPageViewFactory $pages): View
    {
        /** @var User $viewer */
        $viewer = $request->user();

        return $pages->renderProfilePage($feedService->getProfilePageViewModel($viewer));
    }
}
