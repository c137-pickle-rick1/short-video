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
            ->assertJsonPath('item.replyCount', 0)
            ->assertJsonPath('item.isDeleted', false)
            ->assertJsonPath('engagement.commentCount', 1);

        $this->getJson("/api/videos/{$video->id}/comments")
            ->assertOk()
            ->assertJsonPath('items.0.body', '第一条真实评论')
            ->assertJsonPath('items.0.replyCount', 0);
    }

    public function test_comment_reply_endpoints_create_two_level_threads_and_flatten_reply_to_reply(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'thread_root_viewer',
            'email' => 'thread_root_viewer@example.com',
        ]);
        $replier = User::factory()->create([
            'username' => 'thread_replier',
            'email' => 'thread_replier@example.com',
        ]);
        $nestedReplier = User::factory()->create([
            'username' => 'thread_nested_replier',
            'email' => 'thread_nested_replier@example.com',
        ]);
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '5011',
            'tweet' => [
                'text' => '评论线程样本',
            ],
        ]);

        $video = Video::query()->where('tweet_id', '5011')->firstOrFail();

        $rootResponse = $this->actingAs($viewer)->postJson("/api/videos/{$video->id}/comments", [
            'body' => '主评论',
        ]);
        $rootResponse->assertCreated();
        $rootCommentId = (int) $rootResponse->json('item.id');

        $replyResponse = $this->actingAs($replier)->postJson("/api/videos/{$video->id}/comments", [
            'body' => '一级回复',
            'replyToCommentId' => $rootCommentId,
        ]);
        $replyResponse
            ->assertCreated()
            ->assertJsonPath('item.replyToCommentId', $rootCommentId)
            ->assertJsonPath('item.replyToAuthor.username', $viewer->username)
            ->assertJsonPath('engagement.commentCount', 2);
        $replyCommentId = (int) $replyResponse->json('item.id');

        $nestedReplyResponse = $this->actingAs($nestedReplier)->postJson("/api/videos/{$video->id}/comments", [
            'body' => '回复回复',
            'replyToCommentId' => $replyCommentId,
        ]);
        $nestedReplyResponse
            ->assertCreated()
            ->assertJsonPath('item.replyToCommentId', $replyCommentId)
            ->assertJsonPath('item.replyToAuthor.username', $replier->username)
            ->assertJsonPath('engagement.commentCount', 3);

        $this->getJson("/api/videos/{$video->id}/comments")
            ->assertOk()
            ->assertJsonPath('items.0.body', '主评论')
            ->assertJsonPath('items.0.replyCount', 2)
            ->assertJsonCount(1, 'items');

        $this->getJson("/api/videos/{$video->id}/comments/{$rootCommentId}/replies")
            ->assertOk()
            ->assertJsonPath('parentCommentId', $rootCommentId)
            ->assertJsonPath('items.0.body', '一级回复')
            ->assertJsonPath('items.0.replyToCommentId', $rootCommentId)
            ->assertJsonPath('items.1.body', '回复回复')
            ->assertJsonPath('items.1.replyToCommentId', $replyCommentId)
            ->assertJsonCount(2, 'items');

        $replyComment = VideoComment::withTrashed()->findOrFail($replyCommentId);
        $nestedReplyComment = VideoComment::withTrashed()->where('body', '回复回复')->firstOrFail();

        $this->assertSame($rootCommentId, $replyComment->parent_id);
        $this->assertSame($rootCommentId, $nestedReplyComment->parent_id);
        $this->assertSame($replyCommentId, $nestedReplyComment->reply_to_comment_id);
    }

    public function test_comment_reply_target_must_belong_to_same_video_and_not_be_deleted(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'reply_validation_viewer',
            'email' => 'reply_validation_viewer@example.com',
        ]);
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '5012',
            'tweet' => [
                'text' => '回复校验样本 A',
            ],
        ]);
        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '5013',
            'tweet' => [
                'text' => '回复校验样本 B',
            ],
        ]);

        $video = Video::query()->where('tweet_id', '5012')->firstOrFail();
        $otherVideo = Video::query()->where('tweet_id', '5013')->firstOrFail();

        $deletedComment = VideoComment::query()->create([
            'video_id' => $video->id,
            'user_id' => $viewer->id,
            'body' => '已删除目标',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $deletedComment->forceFill([
            'deleted_at' => now(),
        ])->save();
        $otherVideoComment = VideoComment::query()->create([
            'video_id' => $otherVideo->id,
            'user_id' => $viewer->id,
            'body' => '其他视频评论',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($viewer)->postJson("/api/videos/{$video->id}/comments", [
            'body' => '跨视频回复',
            'replyToCommentId' => $otherVideoComment->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('replyToCommentId');

        $this->actingAs($viewer)->postJson("/api/videos/{$video->id}/comments", [
            'body' => '回复已删除目标',
            'replyToCommentId' => $deletedComment->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('replyToCommentId');
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

        $comment = VideoComment::withTrashed()->findOrFail($comment->id);
        $this->assertNotNull($comment->deleted_at);
        $this->assertTrue(VideoComment::query()->whereKey($otherComment->id)->exists());

        $this->getJson("/api/videos/{$video->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $otherComment->id);
    }

    public function test_comment_delete_endpoint_keeps_tombstone_for_root_threads_with_replies(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'comment_thread_delete_viewer',
            'email' => 'comment_thread_delete_viewer@example.com',
        ]);
        $replier = User::factory()->create([
            'username' => 'comment_thread_delete_replier',
            'email' => 'comment_thread_delete_replier@example.com',
        ]);
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '5014',
            'tweet' => [
                'text' => '评论线程删除样本',
            ],
        ]);

        $video = Video::query()->where('tweet_id', '5014')->firstOrFail();
        $rootComment = VideoComment::query()->create([
            'video_id' => $video->id,
            'user_id' => $viewer->id,
            'body' => '由当前用户删除的根评论',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $replyComment = VideoComment::query()->create([
            'video_id' => $video->id,
            'user_id' => $replier->id,
            'parent_id' => $rootComment->id,
            'reply_to_comment_id' => $rootComment->id,
            'body' => '保留在线程中的回复',
            'created_at' => now()->addSecond(),
            'updated_at' => now()->addSecond(),
        ]);

        $this->actingAs($viewer)->deleteJson("/api/videos/{$video->id}/comments/{$rootComment->id}")
            ->assertOk()
            ->assertJsonPath('removed', true)
            ->assertJsonPath('engagement.commentCount', 1);

        $rootComment = VideoComment::withTrashed()->findOrFail($rootComment->id);
        $this->assertNotNull($rootComment->deleted_at);

        $this->getJson("/api/videos/{$video->id}/comments")
            ->assertOk()
            ->assertJsonPath('items.0.id', $rootComment->id)
            ->assertJsonPath('items.0.isDeleted', true)
            ->assertJsonPath('items.0.body', '该评论已删除')
            ->assertJsonPath('items.0.replyCount', 1);

        $this->getJson("/api/videos/{$video->id}/comments/{$rootComment->id}/replies")
            ->assertOk()
            ->assertJsonPath('items.0.id', $replyComment->id)
            ->assertJsonPath('items.0.body', '保留在线程中的回复')
            ->assertJsonPath('items.0.replyToCommentId', $rootComment->id);
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
