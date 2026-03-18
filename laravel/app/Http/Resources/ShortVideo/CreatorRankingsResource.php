<?php

namespace App\Http\Resources\ShortVideo;

use Illuminate\Http\Request;

final class CreatorRankingsResource extends ShortVideoResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'window' => $this->resource['window'] ?? '7d',
            'items' => $this->resource['items'] ?? [],
        ];
    }
}
