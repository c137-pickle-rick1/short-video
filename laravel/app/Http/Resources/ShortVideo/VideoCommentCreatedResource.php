<?php

namespace App\Http\Resources\ShortVideo;

use Illuminate\Http\Request;

final class VideoCommentCreatedResource extends ShortVideoResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'videoId' => $this->resource['videoId'] ?? null,
            'item' => $this->resource['item'] ?? null,
            'engagement' => $this->resource['engagement'] ?? [],
        ];
    }
}
