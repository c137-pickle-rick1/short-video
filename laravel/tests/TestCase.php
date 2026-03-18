<?php

namespace Tests;

use App\ShortVideo\Repositories\ShortVideoRepository;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * @var array<int, string>
     */
    protected array $shortVideoTempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function useShortVideoDatabase(array $sourcesConfig = []): ShortVideoRepository
    {
        $this->useUnmigratedShortVideoDatabase($sourcesConfig);
        Artisan::call('migrate', ['--force' => true]);

        return $this->app->make(ShortVideoRepository::class);
    }

    protected function useUnmigratedShortVideoDatabase(array $sourcesConfig = []): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'short-video-db-');
        $sourcesPath = tempnam(sys_get_temp_dir(), 'short-video-sources-');

        file_put_contents(
            $sourcesPath,
            json_encode($sourcesConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]'
        );

        $this->shortVideoTempFiles[] = $databasePath;
        $this->shortVideoTempFiles[] = $sourcesPath;

        Config::set('database.connections.sqlite.database', $databasePath);
        Config::set('shortvideo.db_path', $databasePath);
        Config::set('shortvideo.source_config_path', $sourcesPath);
        Config::set('shortvideo.browser_profile_dir', sys_get_temp_dir().'/short-video-browser-profile');
        Config::set('shortvideo.storage_state_path', sys_get_temp_dir().'/short-video-storage-state.json');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    protected function insertResolvedTweet(ShortVideoRepository $repository, int $sourceId, array $overrides = []): void
    {
        $tweetId = $overrides['tweetId'] ?? '3000';
        $tweet = $overrides['tweet'] ?? [];
        $media = $overrides['media'] ?? [];
        $mediaAssets = $overrides['mediaAssets'] ?? null;

        $repository->insertDiscoveredTweet([
            'tweetId' => $tweetId,
            'sourceId' => $sourceId,
            'tweetUrl' => $overrides['tweetUrl'] ?? "https://x.com/demo/status/{$tweetId}",
            'durationText' => $overrides['durationText'] ?? '0:21',
            'rawDiscoveryPayload' => ['link' => 'x'],
        ]);

        $repository->applyResolution((string) $tweetId, [
            'status' => $overrides['status'] ?? 'resolved',
            'tweet' => array_merge([
                'authorHandle' => 'demo',
                'authorName' => 'Demo',
                'authorAvatarUrl' => 'https://example.com/avatar.jpg',
                'text' => 'SSR 首页视频',
                'postedAt' => '2025-02-14T06:37:00.000Z',
                'posterUrl' => 'https://example.com/poster.jpg',
            ], $tweet),
            'mediaAssets' => is_array($mediaAssets) ? $mediaAssets : [[
                'url' => 'https://example.com/video.mp4',
                'bitrate' => 1000,
                'contentType' => 'video/mp4',
                'width' => 720,
                'height' => 1280,
                'sortOrder' => 0,
                'isPrimary' => true,
            ] + $media],
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->shortVideoTempFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }
}
