<?php

namespace App\ShortVideo\Repositories;

use App\ShortVideo\Support\ShortVideoData;
use Illuminate\Database\ConnectionInterface;

final class SourceRepository
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly CreatorIdentityRepository $creatorIdentities
    ) {}

    /**
     * @param  array<int, array{handle: string, enabled: bool}>  $sources
     * @return array<int, array<string, mixed>>
     */
    public function syncSources(array $sources): array
    {
        return $this->db->transaction(function () use ($sources) {
            $this->db->table('sources')->update(['enabled' => 0]);

            foreach ($sources as $source) {
                $normalizedHandle = ShortVideoData::normalizeHandle((string) ($source['handle'] ?? ''));
                if ($normalizedHandle === '') {
                    continue;
                }

                $existing = $this->db->table('sources')->where('handle', $normalizedHandle)->first();

                if ($existing) {
                    $this->db->table('sources')
                        ->where('handle', $normalizedHandle)
                        ->update(['enabled' => $source['enabled'] ? 1 : 0]);
                    $sourceId = (int) $existing->id;
                } else {
                    $sourceId = (int) $this->db->table('sources')->insertGetId([
                        'handle' => $normalizedHandle,
                        'enabled' => $source['enabled'] ? 1 : 0,
                    ]);
                }

                $this->creatorIdentities->syncSourceCreatorIdentity($sourceId, $normalizedHandle);
            }

            return $this->listSources();
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSources(): array
    {
        return array_map(
            fn (object $row) => [
                'id' => (int) $row->id,
                'handle' => (string) $row->handle,
                'userId' => $row->userId !== null ? (int) $row->userId : null,
                'enabled' => (bool) $row->enabled,
                'lastDiscoveredAt' => $row->lastDiscoveredAt ? (string) $row->lastDiscoveredAt : null,
            ],
            $this->db->select(
                <<<'SQL'
                    SELECT id, handle, user_id AS userId, enabled, last_discovered_at AS lastDiscoveredAt
                    FROM sources
                    ORDER BY handle ASC
                SQL
            )
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listEnabledSources(): array
    {
        return array_values(array_filter(
            $this->listSources(),
            fn (array $source) => $source['enabled'] === true
        ));
    }

    public function touchSourceLastDiscovered(int $sourceId): void
    {
        $this->db->table('sources')
            ->where('id', $sourceId)
            ->update(['last_discovered_at' => ShortVideoData::nowIso()]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSourcesOverview(): array
    {
        return array_map(function (object $row) {
            return [
                'id' => (int) $row->id,
                'handle' => (string) $row->handle,
                'userId' => $row->userId !== null ? (int) $row->userId : null,
                'creatorName' => $row->creatorName ? (string) $row->creatorName : null,
                'creatorUsername' => $row->creatorUsername ? (string) $row->creatorUsername : null,
                'creatorAccountType' => $row->creatorAccountType ? (string) $row->creatorAccountType : null,
                'enabled' => (bool) $row->enabled,
                'lastDiscoveredAt' => $row->lastDiscoveredAt ? (string) $row->lastDiscoveredAt : null,
                'lastRunStatus' => $row->lastRunStatus ? (string) $row->lastRunStatus : null,
                'publishedCount' => (int) $row->publishedCount,
                'pendingCount' => (int) $row->pendingCount,
            ];
        }, $this->db->select(
            <<<'SQL'
                SELECT
                    s.id,
                    s.handle,
                    s.user_id AS userId,
                    u.name AS creatorName,
                    u.username AS creatorUsername,
                    u.account_type AS creatorAccountType,
                    s.enabled,
                    s.last_discovered_at AS lastDiscoveredAt,
                    (
                        SELECT cr.status
                        FROM crawl_runs cr
                        WHERE cr.phase = 'discovery' AND cr.source_id = s.id
                        ORDER BY cr.started_at DESC
                        LIMIT 1
                    ) AS lastRunStatus,
                    (
                        SELECT COUNT(*)
                        FROM tweets t
                        WHERE t.source_id = s.id AND t.status IN ('resolved', 'external_only')
                    ) AS publishedCount,
                    (
                        SELECT COUNT(*)
                        FROM tweets t
                        WHERE t.source_id = s.id AND t.status = 'pending'
                    ) AS pendingCount
                FROM sources s
                LEFT JOIN users u ON u.id = s.user_id
                ORDER BY s.enabled DESC, s.handle ASC
            SQL
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $sevenDaysAgo = now()->subDays(7)->toISOString();
        $row = $this->db->selectOne(
            <<<'SQL'
                SELECT
                    SUM(CASE WHEN status IN ('resolved', 'external_only') THEN 1 ELSE 0 END) AS totalItems,
                    SUM(CASE WHEN status IN ('resolved', 'external_only') AND COALESCE(posted_at, ingested_at) >= ? THEN 1 ELSE 0 END) AS recentPublishedCount7d,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolvedCount,
                    SUM(CASE WHEN status = 'external_only' THEN 1 ELSE 0 END) AS externalOnlyCount,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failedCount,
                    MAX(COALESCE(resolved_at, ingested_at)) AS lastUpdatedAt
                FROM tweets t
                JOIN sources s ON s.id = t.source_id
                WHERE s.enabled = 1
            SQL,
            [$sevenDaysAgo]
        );

        return [
            'totalItems' => (int) ($row->totalItems ?? 0),
            'recentPublishedCount7d' => (int) ($row->recentPublishedCount7d ?? 0),
            'resolvedCount' => (int) ($row->resolvedCount ?? 0),
            'externalOnlyCount' => (int) ($row->externalOnlyCount ?? 0),
            'failedCount' => (int) ($row->failedCount ?? 0),
            'lastUpdatedAt' => $row->lastUpdatedAt ?? null,
        ];
    }

    public function countTweetsByStatus(string $status): int
    {
        $row = $this->db->selectOne('SELECT COUNT(*) AS count FROM tweets WHERE status = ?', [$status]);

        return (int) ($row->count ?? 0);
    }
}
