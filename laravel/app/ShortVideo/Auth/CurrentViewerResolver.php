<?php

namespace App\ShortVideo\Auth;

use App\Models\User;
use App\ShortVideo\Support\ShortVideoData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class CurrentViewerResolver
{
    public function resolve(?Request $request = null): ?User
    {
        $authenticatedUser = Auth::guard(config('auth.defaults.guard'))->user();
        if ($authenticatedUser instanceof User) {
            return $authenticatedUser;
        }

        if (! app()->environment(['local', 'testing'])) {
            return null;
        }

        $debugViewer = $request?->header('X-ShortVideo-Viewer');
        $fallbackUsername = ShortVideoData::normalizeHandle(
            is_string($debugViewer) && trim($debugViewer) !== ''
                ? $debugViewer
                : (string) config('shortvideo.dev_viewer_username', '')
        );

        if ($fallbackUsername === '') {
            return null;
        }

        return User::query()->where('username', $fallbackUsername)->first();
    }
}
