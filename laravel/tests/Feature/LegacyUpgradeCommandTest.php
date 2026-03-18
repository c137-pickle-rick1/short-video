<?php

namespace Tests\Feature;

use App\Models\Source;
use App\Models\UserExternalAccount;
use App\Models\Video;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LegacyUpgradeCommandTest extends TestCase
{
    public function test_upgrade_legacy_db_command_is_an_explicit_post_migrate_step(): void
    {
        $this->useUnmigratedShortVideoDatabase();

        $this->artisan('shortvideo:prepare-legacy-db --json')->assertExitCode(0);

        $sourceId = DB::table('sources')->insertGetId([
            'handle' => 'legacy_demo',
            'enabled' => 1,
            'last_discovered_at' => null,
        ]);

        DB::table('tweets')->insert([
            'tweet_id' => 'legacy-1001',
            'source_id' => $sourceId,
            'tweet_url' => 'https://x.com/legacy_demo/status/legacy-1001',
            'author_handle' => 'legacy_demo',
            'author_name' => 'Legacy Demo',
            'author_avatar_url' => 'https://example.com/legacy-avatar.jpg',
            'text' => '旧库中的推文记录',
            'posted_at' => now()->subDay()->toISOString(),
            'duration_text' => null,
            'poster_url' => 'https://example.com/legacy-poster.jpg',
            'status' => 'resolved',
            'raw_discovery_payload' => json_encode(['durationText' => '0:45'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'raw_resolve_payload' => null,
            'ingested_at' => now()->toISOString(),
            'resolved_at' => now()->toISOString(),
        ]);

        DB::table('media_assets')->insert([
            [
                'tweet_id' => 'legacy-1001',
                'url' => 'https://example.com/legacy-video.mp4',
                'bitrate' => 1000,
                'content_type' => 'video/mp4',
                'width' => 720,
                'height' => 1280,
                'sort_order' => 0,
                'is_primary' => 1,
            ],
            [
                'tweet_id' => 'legacy-1001',
                'url' => 'https://example.com/legacy-video.m3u8',
                'bitrate' => null,
                'content_type' => 'application/x-mpegURL',
                'width' => 720,
                'height' => 1280,
                'sort_order' => 1,
                'is_primary' => 0,
            ],
        ]);

        $this->artisan('migrate --force')->assertExitCode(0);

        $this->assertNull(DB::table('sources')->where('id', $sourceId)->value('user_id'));
        $this->assertNull(Video::query()->where('tweet_id', 'legacy-1001')->first());
        $this->assertNull(UserExternalAccount::query()->where('provider', 'x')->where('handle', 'legacy_demo')->first());

        $this->artisan('shortvideo:upgrade-legacy-db --json')->assertExitCode(0);

        $source = Source::query()->findOrFail($sourceId);
        $video = Video::query()->where('tweet_id', 'legacy-1001')->first();
        $externalAccount = UserExternalAccount::query()
            ->where('provider', 'x')
            ->where('handle', 'legacy_demo')
            ->first();

        $this->assertSame('0:45', DB::table('tweets')->where('tweet_id', 'legacy-1001')->value('duration_text'));
        $this->assertNotNull($source->user_id);
        $this->assertNotNull($externalAccount);
        $this->assertSame($source->user_id, $externalAccount->user_id);
        $this->assertNotNull($video);
        $this->assertSame($source->user_id, $video->uploader_user_id);
        $this->assertSame('https://example.com/legacy-video.mp4', $video->playback_url);
        $this->assertSame('https://example.com/legacy-video.m3u8', $video->hls_url);
        $this->assertSame(45, $video->duration_seconds);
    }
}
