<?php

namespace App\Http\Resources\ShortVideo;

use Illuminate\Http\Request;

final class FeedPageResource extends ShortVideoResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'items' => $this->resource['items'] ?? [],
            'nextCursor' => $this->resource['nextCursor'] ?? null,
        ];
    }
}
