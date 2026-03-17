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

    public static function isBackoffErrorCode(?string $code): bool
    {
        return in_array($code, ['rate_limited', 'auth_required'], true);
    }
}
