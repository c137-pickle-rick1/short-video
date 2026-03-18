<?php

namespace App\Http\Resources\ShortVideo;

use Illuminate\Http\Request;

final class VideoViewStateResource extends ShortVideoResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'videoId' => $this->resource['videoId'] ?? null,
            'recorded' => (bool) ($this->resource['recorded'] ?? false),
            'engagement' => $this->resource['engagement'] ?? [],
        ];
    }
}
