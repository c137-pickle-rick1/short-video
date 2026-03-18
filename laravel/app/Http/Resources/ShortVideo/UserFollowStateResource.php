<?php

namespace App\Http\Resources\ShortVideo;

use Illuminate\Http\Request;

final class UserFollowStateResource extends ShortVideoResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'viewerUserId' => $this->resource['viewerUserId'] ?? null,
            'authorUserId' => $this->resource['authorUserId'] ?? null,
            'following' => (bool) ($this->resource['following'] ?? false),
        ];
    }
}
