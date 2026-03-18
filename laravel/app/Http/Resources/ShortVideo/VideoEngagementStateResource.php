<?php

namespace App\Http\Resources\ShortVideo;

use Illuminate\Http\Request;

final class VideoEngagementStateResource extends ShortVideoResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'videoId' => $this->resource['videoId'] ?? null,
            'engagement' => $this->resource['engagement'] ?? [],
        ];
    }
}
