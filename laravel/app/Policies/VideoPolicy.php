<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Video;

class VideoPolicy
{
    public function like(User $viewer, Video $video): bool
    {
        return $this->isInteractable($video);
    }

    public function bookmark(User $viewer, Video $video): bool
    {
        return $this->isInteractable($video);
    }

    public function comment(User $viewer, Video $video): bool
    {
        return $this->isInteractable($video);
    }

    private function isInteractable(Video $video): bool
    {
        return $video->status === 'published' && $video->visibility === 'public';
    }
}
