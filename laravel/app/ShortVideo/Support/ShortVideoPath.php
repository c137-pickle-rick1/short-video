<?php

namespace App\ShortVideo\Support;

final class ShortVideoPath
{
    public static function repoRoot(): string
    {
        return dirname(base_path());
    }

    public static function resolve(?string $value, string $fallback): string
    {
        $candidate = trim((string) ($value ?? ''));
        if ($candidate === '') {
            $candidate = $fallback;
        }

        if (self::isAbsolutePath($candidate)) {
            return $candidate;
        }

        return self::repoRoot().'/'.ltrim($candidate, '/');
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}
