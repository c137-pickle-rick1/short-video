<?php

namespace App\Http\Resources\ShortVideo;

use Illuminate\Http\Request;

final class VideoCommentsResource extends ShortVideoResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'videoId' => $this->resource['videoId'] ?? null,
            'items' => $this->resource['items'] ?? [],
            'totalCount' => $this->resource['totalCount'] ?? 0,
        ];
    }
}
