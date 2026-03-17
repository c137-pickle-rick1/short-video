<?php

namespace App\ShortVideo\Services;

use App\ShortVideo\Support\ShortVideoData;
use JsonException;

final class SourceConfigService
{
    /**
     * @return array<int, array{handle: string, enabled: bool}>
     */
    public function loadSources(): array
    {
        $path = config('shortvideo.source_config_path');
        if (! is_string($path) || ! is_file($path)) {
            return [];
        }

        try {
            $decoded = json_decode(file_get_contents($path) ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new \RuntimeException('config/sources.json must contain valid JSON.');
        }

        if (! is_array($decoded)) {
            throw new \RuntimeException('config/sources.json must contain an array.');
        }

        $items = [];

        foreach ($decoded as $source) {
            if (! is_array($source)) {
                continue;
            }

            $handle = ShortVideoData::normalizeHandle($source['handle'] ?? null);
            if ($handle === '') {
                continue;
            }

            $items[] = [
                'handle' => $handle,
                'enabled' => ShortVideoData::parseBoolean($source['enabled'] ?? true),
            ];
        }

        return $items;
    }
}
