<?php

namespace App\ShortVideo\Support;

final class FeedCursor
{
    /**
     * @return array{cursorSort: ?string, cursorTweetId: ?string}
     */
    public static function decode(?string $cursor): array
    {
        if (! $cursor) {
            return [
                'cursorSort' => null,
                'cursorTweetId' => null,
            ];
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($decoded === false) {
            return [
                'cursorSort' => null,
                'cursorTweetId' => null,
            ];
        }

        $payload = json_decode($decoded, true);
        if (! is_array($payload)) {
            return [
                'cursorSort' => null,
                'cursorTweetId' => null,
            ];
        }

        return [
            'cursorSort' => isset($payload['sortValue']) ? (string) $payload['sortValue'] : null,
            'cursorTweetId' => isset($payload['tweetId']) ? (string) $payload['tweetId'] : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function encode(array $items): ?string
    {
        if ($items === []) {
            return null;
        }

        $last = $items[array_key_last($items)];
        $json = json_encode([
            'sortValue' => $last['sortValue'] ?? null,
            'tweetId' => $last['tweetId'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return null;
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}
