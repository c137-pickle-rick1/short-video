<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Video;
use App\Models\VideoComment;
use App\Policies\UserPolicy;
use App\Policies\VideoCommentPolicy;
use App\Policies\VideoPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Video::class, VideoPolicy::class);
        Gate::policy(VideoComment::class, VideoCommentPolicy::class);
    }
}
