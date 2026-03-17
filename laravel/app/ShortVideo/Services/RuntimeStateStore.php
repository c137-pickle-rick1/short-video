<?php

namespace App\ShortVideo\Services;

use App\ShortVideo\Support\ShortVideoData;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

final class RuntimeStateStore
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function get(string $key): ?string
    {
        $row = $this->db->table('runtime_states')->where('key', $key)->first();

        return $row?->value !== null ? (string) $row->value : null;
    }

    public function put(string $key, ?string $value): void
    {
        if ($value === null) {
            $this->db->table('runtime_states')->where('key', $key)->delete();

            return;
        }

        $this->db->table('runtime_states')->updateOrInsert(
            ['key' => $key],
            [
                'value' => $value,
                'updated_at' => ShortVideoData::nowIso(),
            ]
        );
    }

    /**
     * @return array{backoffUntil: ?string, backoffReason: ?string}
     */
    public function getBackoffState(): array
    {
        $this->clearExpiredBackoff();

        return [
            'backoffUntil' => $this->get(config('shortvideo.runtime_keys.backoff_until')),
            'backoffReason' => $this->get(config('shortvideo.runtime_keys.backoff_reason')),
        ];
    }

    public function setBackoff(string $reason, int $minutes = 15): void
    {
        $until = CarbonImmutable::now('UTC')->addMinutes($minutes)->format('Y-m-d\TH:i:s.v\Z');

        $this->put(config('shortvideo.runtime_keys.backoff_reason'), $reason);
        $this->put(config('shortvideo.runtime_keys.backoff_until'), $until);
    }

    public function clearExpiredBackoff(): void
    {
        $until = $this->get(config('shortvideo.runtime_keys.backoff_until'));
        if (! $until) {
            return;
        }

        $date = CarbonImmutable::parse($until, 'UTC');
        if ($date->isFuture()) {
            return;
        }

        $this->put(config('shortvideo.runtime_keys.backoff_reason'), null);
        $this->put(config('shortvideo.runtime_keys.backoff_until'), null);
    }

    public function acquireCrawlLock(string $owner, int $ttlSeconds = 1800): bool
    {
        return $this->db->transaction(function () use ($owner, $ttlSeconds) {
            $ownerKey = config('shortvideo.runtime_keys.crawl_lock_owner');
            $untilKey = config('shortvideo.runtime_keys.crawl_lock_until');
            $currentOwner = $this->get($ownerKey);
            $currentUntil = $this->get($untilKey);

            if ($currentUntil) {
                $until = CarbonImmutable::parse($currentUntil, 'UTC');
                if ($until->isFuture() && $currentOwner !== $owner) {
                    return false;
                }
            }

            $this->put($ownerKey, $owner);
            $this->put(
                $untilKey,
                CarbonImmutable::now('UTC')->addSeconds($ttlSeconds)->format('Y-m-d\TH:i:s.v\Z')
            );

            return true;
        });
    }

    public function releaseCrawlLock(?string $owner = null): void
    {
        $ownerKey = config('shortvideo.runtime_keys.crawl_lock_owner');
        $untilKey = config('shortvideo.runtime_keys.crawl_lock_until');
        $currentOwner = $this->get($ownerKey);

        if ($owner !== null && $currentOwner !== null && $owner !== $currentOwner) {
            return;
        }

        $this->put($ownerKey, null);
        $this->put($untilKey, null);
    }
}
