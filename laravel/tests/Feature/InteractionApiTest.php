<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use App\Models\VideoComment;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class InteractionApiTest extends TestCase
{
    public function test_like_bookmark_and_comment_endpoints_return_real_engagement(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'engagement_tester',
            'email' => 'engagement_tester@example.com',
        ]);
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '5001',
            'tweet' => [
                'text' => '真实互动样本',
            ],
        ]);

        $video = Video::query()->where('tweet_id', '5001')->firstOrFail();

        $this->actingAs($viewer)->postJson("/api/videos/{$video->id}/likes")
            ->assertOk()
            ->assertJsonPath('engagement.likeCount', 1)
            ->assertJsonPath('engagement.likedByViewer', true);

        $this->actingAs($viewer)->postJson("/api/videos/{$video->id}/bookmarks")
            ->assertOk()
            ->assertJsonPath('engagement.bookmarkCount', 1)
            ->assertJsonPath('engagement.bookmarkedByViewer', true);

        $this->actingAs($viewer)->postJson("/api/videos/{$video->id}/comments", [
            'body' => '第一条真实评论',
        ])
            ->assertCreated()
            ->assertJsonPath('item.body', '第一条真实评论')
            ->assertJsonPath('engagement.commentCount', 1);

        $this->getJson("/api/videos/{$video->id}/comments")
            ->assertOk()
            ->assertJsonPath('items.0.body', '第一条真实评论');
    }

    public function test_bookmark_delete_endpoint_removes_only_current_viewer_bookmark(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'bookmark_delete_viewer',
            'email' => 'bookmark_delete_viewer@example.com',
        ]);
        $otherViewer = User::factory()->create([
            'username' => 'bookmark_delete_other',
            'email' => 'bookmark_delete_other@example.com',
        ]);
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '5005',
            'tweet' => [
                'text' => '收藏删除样本',
            ],
        ]);

        $video = Video::query()->where('tweet_id', '5005')->firstOrFail();

        $viewer->bookmarkedVideos()->attach($video->id, [
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherViewer->bookmarkedVideos()->attach($video->id, [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($viewer)->deleteJson("/api/videos/{$video->id}/bookmarks")
            ->assertOk()
            ->assertJsonPath('videoId', $video->id)
            ->assertJsonPath('engagement.bookmarkedByViewer', false);

        $this->assertFalse(
            DB::table('video_bookmarks')
                ->where('video_id', $video->id)
                ->where('user_id', $viewer->id)
                ->exists()
        );
        $this->assertTrue(
            DB::table('video_bookmarks')
                ->where('video_id', $video->id)
                ->where('user_id', $otherViewer->id)
                ->exists()
        );
    }

    public function test_like_delete_endpoint_removes_only_current_viewer_like(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'like_delete_viewer',
            'email' => 'like_delete_viewer@example.com',
        ]);
        $otherViewer = User::factory()->create([
            'username' => 'like_delete_other',
            'email' => 'like_delete_other@example.com',
        ]);
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '5006',
            'tweet' => [
                'text' => '点赞删除样本',
            ],
        ]);

        $video = Video::query()->where('tweet_id', '5006')->firstOrFail();

        $viewer->likedVideos()->attach($video->id, [
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherViewer->likedVideos()->attach($video->id, [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($viewer)->deleteJson("/api/videos/{$video->id}/likes")
            ->assertOk()
            ->assertJsonPath('videoId', $video->id)
            ->assertJsonPath('engagement.likedByViewer', false);

        $this->assertFalse(
            DB::table('video_likes')
                ->where('video_id', $video->id)
                ->where('user_id', $viewer->id)
                ->exists()
        );
        $this->assertTrue(
            DB::table('video_likes')
                ->where('video_id', $video->id)
                ->where('user_id', $otherViewer->id)
                ->exists()
        );
    }

    public function test_comment_delete_endpoint_removes_only_current_viewer_comment(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'comment_delete_viewer',
            'email' => 'comment_delete_viewer@example.com',
        ]);
        $otherViewer = User::factory()->create([
            'username' => 'comment_delete_other',
            'email' => 'comment_delete_other@example.com',
        ]);
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '5007',
            'tweet' => [
                'text' => '评论删除样本',
            ],
        ]);

        $video = Video::query()->where('tweet_id', '5007')->firstOrFail();
        $comment = VideoComment::query()->create([
            'video_id' => $video->id,
            'user_id' => $viewer->id,
            'body' => '由当前用户删除',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherComment = VideoComment::query()->create([
            'video_id' => $video->id,
            'user_id' => $otherViewer->id,
            'body' => '由其他用户保留',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($viewer)->deleteJson("/api/videos/{$video->id}/comments/{$comment->id}")
            ->assertOk()
            ->assertJsonPath('videoId', $video->id)
            ->assertJsonPath('commentId', $comment->id)
            ->assertJsonPath('removed', true)
            ->assertJsonPath('engagement.commentCount', 1);

        $this->assertFalse(VideoComment::query()->whereKey($comment->id)->exists());
        $this->assertTrue(VideoComment::query()->whereKey($otherComment->id)->exists());
    }

    public function test_comment_and_reaction_endpoints_require_login(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '5002',
        ]);

        $video = Video::query()->where('tweet_id', '5002')->firstOrFail();

        $this->postJson("/api/videos/{$video->id}/likes")->assertStatus(401);
        $this->postJson("/api/videos/{$video->id}/bookmarks")->assertStatus(401);
        $this->postJson("/api/videos/{$video->id}/comments", [
            'body' => '未登录评论',
        ])->assertStatus(401);
    }

    public function test_view_endpoint_dedupes_by_session_per_day_and_can_record_again_next_day(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '5003',
        ]);

        $video = Video::query()->where('tweet_id', '5003')->firstOrFail();

        $this->postJson("/api/videos/{$video->id}/views", [
            'sessionId' => 'guest_session_5003',
        ])
            ->assertOk()
            ->assertJsonPath('recorded', true)
            ->assertJsonPath('engagement.viewCount', 1);

        $this->postJson("/api/videos/{$video->id}/views", [
            'sessionId' => 'guest_session_5003',
        ])
            ->assertOk()
            ->assertJsonPath('recorded', false)
            ->assertJsonPath('engagement.viewCount', 1);

        DB::table('video_views')
            ->where('video_id', $video->id)
            ->update([
                'view_date' => now()->subDay()->toDateString(),
            ]);

        $this->postJson("/api/videos/{$video->id}/views", [
            'sessionId' => 'guest_session_5003',
        ])
            ->assertOk()
            ->assertJsonPath('recorded', true)
            ->assertJsonPath('engagement.viewCount', 2);
    }

    public function test_history_delete_endpoint_removes_only_current_viewer_history_for_video(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'history_delete_viewer',
            'email' => 'history_delete_viewer@example.com',
        ]);
        $otherViewer = User::factory()->create([
            'username' => 'history_delete_other',
            'email' => 'history_delete_other@example.com',
        ]);
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '5004',
            'tweet' => [
                'text' => '历史删除样本',
            ],
        ]);

        $video = Video::query()->where('tweet_id', '5004')->firstOrFail();

        DB::table('video_views')->insert([
            [
                'video_id' => $video->id,
                'user_id' => $viewer->id,
                'session_id' => 'history_delete_viewer_session',
                'view_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'video_id' => $video->id,
                'user_id' => $otherViewer->id,
                'session_id' => 'history_delete_other_session',
                'view_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($viewer)->deleteJson("/api/videos/{$video->id}/history")
            ->assertOk()
            ->assertJsonPath('videoId', $video->id)
            ->assertJsonPath('removed', true);

        $this->assertFalse(
            DB::table('video_views')
                ->where('video_id', $video->id)
                ->where('user_id', $viewer->id)
                ->exists()
        );
        $this->assertTrue(
            DB::table('video_views')
                ->where('video_id', $video->id)
                ->where('user_id', $otherViewer->id)
                ->exists()
        );
    }

    public function test_history_clear_endpoint_removes_only_current_viewer_history(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'history_clear_viewer',
            'email' => 'history_clear_viewer@example.com',
        ]);
        $otherViewer = User::factory()->create([
            'username' => 'history_clear_other',
            'email' => 'history_clear_other@example.com',
        ]);
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        foreach (['5010', '5011'] as $tweetId) {
            $this->insertResolvedTweet($repository, $source['id'], [
                'tweetId' => $tweetId,
                'tweet' => [
                    'text' => '历史清空样本 '.$tweetId,
                ],
            ]);
        }

        $videos = Video::query()
            ->whereIn('tweet_id', ['5010', '5011'])
            ->orderBy('tweet_id')
            ->get();

        DB::table('video_views')->insert([
            [
                'video_id' => $videos[0]->id,
                'user_id' => $viewer->id,
                'session_id' => 'history_clear_viewer_session_1',
                'view_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'video_id' => $videos[1]->id,
                'user_id' => $viewer->id,
                'session_id' => 'history_clear_viewer_session_2',
                'view_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'video_id' => $videos[0]->id,
                'user_id' => $otherViewer->id,
                'session_id' => 'history_clear_other_session',
                'view_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($viewer)->deleteJson('/api/history')
            ->assertOk()
            ->assertJsonPath('removedCount', 2);

        $this->assertSame(
            0,
            DB::table('video_views')
                ->where('user_id', $viewer->id)
                ->count()
        );
        $this->assertSame(
            1,
            DB::table('video_views')
                ->where('user_id', $otherViewer->id)
                ->count()
        );
    }
}
