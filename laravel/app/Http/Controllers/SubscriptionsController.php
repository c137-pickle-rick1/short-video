<?php

namespace App\Http\Controllers;

use App\ShortVideo\Services\FeedService;
use App\ShortVideo\View\ShortVideoPageViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class SubscriptionsController extends Controller
{
    public function __invoke(Request $request, FeedService $feedService, ShortVideoPageViewFactory $pages): View
    {
        $selectedAccount = $request->route('account');

        return $pages->renderSubscriptionsPage(
            $feedService->getSubscriptionsPageViewModel(
                selectedAccount: is_string($selectedAccount) && trim($selectedAccount) !== ''
                    ? $selectedAccount
                    : $request->query('account')
            )
        );
    }
}
