<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VideoComment;

class VideoCommentPolicy
{
    public function delete(User $user, VideoComment $videoComment): bool
    {
        return $user->id === $videoComment->user_id;
    }
}
