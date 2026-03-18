<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function follow(User $viewer, User $target): bool
    {
        return ! $viewer->is($target);
    }

    public function updateAvatar(User $viewer, User $target): bool
    {
        return $viewer->is($target);
    }

    public function updateProfile(User $viewer, User $target): bool
    {
        return $viewer->is($target);
    }

    public function uploadVideo(User $viewer, User $target): bool
    {
        return $viewer->is($target);
    }
}
