<?php

namespace App\ShortVideo\Support;

use Carbon\CarbonImmutable;

final class ShortVideoData
{
    public const TWEET_STATUSES = ['pending', 'resolved', 'external_only', 'skipped', 'failed'];

    public static function normalizeHandle(?string $handle): string
    {
        return mb_strtolower(ltrim(trim((string) ($handle ?? '')), '@'));
    }

    public static function normalizeSessionId(?string $sessionId): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9:_-]/', '', trim((string) ($sessionId ?? '')));

        return is_string($normalized) ? substr($normalized, 0, 120) : '';
    }

    public static function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return ! in_array(mb_strtolower(trim((string) $value)), ['0', 'false', 'no', 'off'], true);
    }

    public static function nowIso(): string
    {
        return CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s.v\Z');
    }

    public static function compactJson(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($text === false) {
            return null;
        }

        if (strlen($text) <= 50000) {
            return $text;
        }

        return substr($text, 0, 49950).'...[truncated]';
    }

    public static function extractDurationTextFromDiscoveryPayload(mixed $rawDiscoveryPayload): ?string
    {
        if ($rawDiscoveryPayload === null || $rawDiscoveryPayload === '') {
            return null;
        }

        $payload = $rawDiscoveryPayload;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $durationText = $payload['durationText'] ?? $payload['discoveredLink']['durationText'] ?? null;
        $durationText = is_string($durationText) ? trim($durationText) : '';

        return $durationText !== '' ? $durationText : null;
    }

    public static function parseDurationTextToSeconds(?string $durationText): ?int
    {
        $normalized = trim((string) ($durationText ?? ''));
        if ($normalized === '') {
            return null;
        }

        $parts = array_map('trim', explode(':', $normalized));
        if ($parts === [] || count($parts) > 3) {
            return null;
        }

        $seconds = 0;
        foreach ($parts as $part) {
            if ($part === '' || ! ctype_digit($part)) {
                return null;
            }

            $seconds = ($seconds * 60) + (int) $part;
        }

        return $seconds;
    }

    public static function isBackoffErrorCode(?string $code): bool
    {
        return in_array($code, ['rate_limited', 'auth_required'], true);
    }

    public static function calculateFeaturedScore(
        int $likeCount,
        int $bookmarkCount,
        int $commentCount,
        int $viewCount,
        ?string $publishedAt
    ): float {
        $ageHours = 0.0;
        if (is_string($publishedAt) && trim($publishedAt) !== '') {
            try {
                $ageHours = max(0.0, CarbonImmutable::parse($publishedAt, 'UTC')->diffInSeconds(CarbonImmutable::now('UTC')) / 3600);
            } catch (\Throwable) {
                $ageHours = 0.0;
            }
        }

        return (5.0 * log(1 + max(0, $commentCount)))
            + (4.0 * log(1 + max(0, $bookmarkCount)))
            + (3.0 * log(1 + max(0, $likeCount)))
            + (1.5 * log(1 + max(0, $viewCount)))
            - ($ageHours / 48.0);
    }
}
