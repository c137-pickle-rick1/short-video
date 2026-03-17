<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\ShortVideo\Services\FeedService;
use App\ShortVideo\View\HomePageRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    public function __invoke(Request $request, FeedService $feedService, HomePageRenderer $renderer): View
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $viewModel = $feedService->getProfilePageViewModel($viewer);

        return view('shortvideo.profile', [
            'documentHead' => $renderer->renderDocumentHead((string) $viewModel['pageTitle']),
            'pageHeader' => $renderer->renderPageHeader(route('login'), $viewModel['headerViewer'], route('logout')),
            'desktopNavigation' => $renderer->renderDesktopNavigation('profile', $viewModel['headerViewer']),
            'mobileNavigation' => $renderer->renderMobileNavigation('profile', $viewModel['headerViewer']),
            'page' => $viewModel['page'],
            'profile' => $viewModel['profile'],
            'stats' => $viewModel['stats'],
            'logoutUrl' => route('logout'),
        ]);
    }
}
