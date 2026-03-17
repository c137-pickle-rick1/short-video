<?php

namespace Tests\Feature;

use App\Models\Source;
use App\Models\User;
use App\Models\UserExternalAccount;
use App\Models\Video;
use App\Models\VideoComment;
use Tests\TestCase;

final class SocialGraphSchemaTest extends TestCase
{
    public function test_social_schema_supports_users_follows_interactions_and_replies(): void
    {
        $this->useShortVideoDatabase();

        $viewer = User::factory()->create([
            'username' => 'viewer',
            'email' => 'viewer@example.com',
        ]);
        $creator = User::factory()->create([
            'account_type' => 'external_creator',
            'name' => 'Creator X',
            'username' => 'creator',
            'email' => null,
            'password' => null,
        ]);
        $creator->externalAccounts()->create([
            'provider' => 'x',
            'handle' => 'creator_x',
            'profile_url' => 'https://x.com/creator_x',
        ]);

        $source = Source::query()->create([
            'handle' => 'creator_x',
            'user_id' => $creator->id,
            'enabled' => true,
        ]);

        $viewer->followingUsers()->attach($creator->id);

        $video = Video::query()->create([
            'origin' => 'x_tweet',
            'source_id' => $source->id,
            'uploader_user_id' => $creator->id,
            'title' => 'First upload',
            'caption' => '第一条用户上传视频',
            'storage_disk' => 'public',
            'storage_path' => 'videos/first-upload.mp4',
            'poster_url' => 'https://example.com/poster.jpg',
            'playback_url' => 'https://example.com/videos/first-upload.mp4',
            'duration_text' => '0:21',
            'duration_seconds' => 21,
            'width' => 720,
            'height' => 1280,
            'visibility' => 'public',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $viewer->likedVideos()->attach($video->id);
        $viewer->bookmarkedVideos()->attach($video->id);

        $comment = VideoComment::query()->create([
            'video_id' => $video->id,
            'user_id' => $viewer->id,
            'body' => '主评论',
        ]);
        VideoComment::query()->create([
            'video_id' => $video->id,
            'user_id' => $creator->id,
            'parent_id' => $comment->id,
            'body' => '回复评论',
        ]);

        $this->assertTrue($source->user()->whereKey($creator->id)->exists());
        $this->assertTrue($creator->externalAccounts()->where('provider', 'x')->where('handle', 'creator_x')->exists());
        $this->assertTrue($viewer->followingUsers()->whereKey($creator->id)->exists());
        $this->assertTrue($video->likedByUsers()->whereKey($viewer->id)->exists());
        $this->assertTrue($video->bookmarkedByUsers()->whereKey($viewer->id)->exists());
        $this->assertCount(1, $video->comments()->get());
        $this->assertCount(1, $comment->replies()->get());
    }

    public function test_resolved_tweets_are_synced_into_unified_videos(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '4100',
            'tweet' => [
                'text' => '回填到统一视频表',
            ],
            'mediaAssets' => [
                [
                    'url' => 'https://example.com/video.mp4',
                    'bitrate' => 1000,
                    'contentType' => 'video/mp4',
                    'width' => 720,
                    'height' => 1280,
                    'sortOrder' => 0,
                    'isPrimary' => true,
                ],
                [
                    'url' => 'https://example.com/video.m3u8',
                    'bitrate' => null,
                    'contentType' => 'application/x-mpegURL',
                    'width' => 720,
                    'height' => 1280,
                    'sortOrder' => 1,
                    'isPrimary' => false,
                ],
            ],
        ]);

        $video = Video::query()->where('tweet_id', '4100')->first();
        $sourceModel = Source::query()->find($source['id']);
        $externalAccount = UserExternalAccount::query()
            ->where('provider', 'x')
            ->where('handle', 'demo')
            ->first();

        $this->assertNotNull($video);
        $this->assertNotNull($sourceModel);
        $this->assertNotNull($externalAccount);
        $this->assertNotNull($sourceModel->user_id);
        $this->assertSame('x_tweet', $video->origin);
        $this->assertSame($source['id'], $video->source_id);
        $this->assertSame($sourceModel->user_id, $externalAccount->user_id);
        $this->assertSame($sourceModel->user_id, $video->uploader_user_id);
        $this->assertSame('回填到统一视频表', $video->caption);
        $this->assertSame('https://example.com/video.mp4', $video->playback_url);
        $this->assertSame('https://example.com/video.m3u8', $video->hls_url);
        $this->assertSame(21, $video->duration_seconds);
        $this->assertSame('external_creator', $externalAccount->user->account_type);
    }
}
