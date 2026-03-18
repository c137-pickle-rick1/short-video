<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\ShortVideo\Services\FeedService;
use App\ShortVideo\View\ShortVideoPageViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    public function current(Request $request): RedirectResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $tab = trim((string) $request->query('tab', ''));
        $panel = trim((string) $request->query('panel', ''));
        $page = trim((string) $request->query('page', ''));

        return redirect()->route('profile.show', array_filter([
            'username' => $viewer->username,
            'tab' => $tab !== '' ? $tab : null,
            'panel' => $panel !== '' ? $panel : null,
            'page' => $page !== '' ? $page : null,
        ]));
    }

    public function show(
        Request $request,
        string $username,
        FeedService $feedService,
        ShortVideoPageViewFactory $pages
    ): View {
        /** @var User|null $viewer */
        $viewer = $request->user();
        $profileUser = User::query()->where('username', $username)->firstOrFail();

        return $pages->renderProfilePage($feedService->getProfilePageViewModel(
            $viewer,
            $profileUser,
            $request->string('tab')->toString(),
            $request->string('panel')->toString(),
            $request->query('page')
        ));
    }
}
