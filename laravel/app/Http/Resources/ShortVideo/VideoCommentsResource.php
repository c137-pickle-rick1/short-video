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
        $payload = [
            'videoId' => $this->resource['videoId'] ?? null,
            'items' => $this->resource['items'] ?? [],
            'totalCount' => $this->resource['totalCount'] ?? 0,
        ];

        if (array_key_exists('parentCommentId', $this->resource)) {
            $payload['parentCommentId'] = $this->resource['parentCommentId'];
        }

        return $payload;
    }
}
