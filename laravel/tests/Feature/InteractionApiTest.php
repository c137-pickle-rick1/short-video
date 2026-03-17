<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
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
}
