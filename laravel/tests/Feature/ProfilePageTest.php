<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ProfilePageTest extends TestCase
{
    public function test_guest_is_redirected_to_login_when_opening_profile_page(): void
    {
        $this->useShortVideoDatabase();

        $response = $this->get('/me');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_is_redirected_from_me_to_canonical_profile_route(): void
    {
        $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'profile_tester',
        ]);

        $response = $this->actingAs($viewer)->get('/me');

        $response->assertRedirect(route('profile.show', ['username' => 'profile_tester']));
    }

    public function test_authenticated_user_redirect_from_me_preserves_panel_and_page_query(): void
    {
        $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'profile_tester_redirect',
        ]);

        $response = $this->actingAs($viewer)->get('/me?panel=history&page=2');

        $response->assertRedirect(route('profile.show', [
            'username' => 'profile_tester_redirect',
            'panel' => 'history',
            'page' => 2,
        ]));
    }

    public function test_authenticated_user_sees_profile_summary_and_follow_stats_on_own_profile_page(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'name' => 'Profile Tester',
            'username' => 'profile_tester',
            'avatar_url' => 'https://example.com/profile.jpg',
        ]);
        $followedOne = User::factory()->create(['username' => 'creator_alpha']);
        $followedTwo = User::factory()->create(['username' => 'creator_beta']);
        $followerOne = User::factory()->create(['username' => 'fan_alpha']);
        $followerTwo = User::factory()->create(['username' => 'fan_beta']);
        $followerThree = User::factory()->create(['username' => 'fan_gamma']);

        $repository->followUser($viewer->id, $followedOne->id);
        $repository->followUser($viewer->id, $followedTwo->id);
        $repository->followUser($followerOne->id, $viewer->id);
        $repository->followUser($followerTwo->id, $viewer->id);
        $repository->followUser($followerThree->id, $viewer->id);

        $response = $this->actingAs($viewer)->get(route('profile.show', ['username' => $viewer->username]));

        $response->assertOk();
        $response->assertSee('Profile Tester', false);
        $response->assertSee('&#64;profile_tester', false);
        $response->assertSee('https://example.com/profile.jpg', false);
        $response->assertSee('data-profile-stats="true"', false);
        $response->assertSee('data-profile-following-count="true"', false);
        $response->assertSee('data-profile-follower-count="true"', false);
        $response->assertSee('data-profile-social-trigger="following"', false);
        $response->assertSee('data-profile-social-trigger="followers"', false);
        $response->assertSee('data-profile-social-modal="true"', false);
        $response->assertSee('data-profile-dashboard-nav="true"', false);
        $response->assertSee('data-profile-dashboard-selected-panel="profile"', false);
        $response->assertSee('data-profile-dashboard-item="profile"', false);
        $response->assertSee('data-profile-dashboard-item="creator"', false);
        $response->assertSee('data-profile-dashboard-item="history"', false);
        $response->assertSee('data-profile-dashboard-item="bookmarks"', false);
        $response->assertSee('data-profile-dashboard-item="interactions"', false);
        $response->assertSee('data-profile-dashboard-item="logout"', false);
        $response->assertSee('data-profile-dashboard-detail="profile"', false);
        $response->assertDontSee('data-profile-dashboard-mobile-nav="true"', false);
        $response->assertDontSee('关注了', false);
        $response->assertDontSee('粉丝数', false);
        $response->assertDontSee('data-profile-video-upload-trigger="true"', false);
        $response->assertSee('id="profile-video-upload-dialog"', false);
        $response->assertSee('data-profile-video-upload-input="true"', false);
        $response->assertSee('data-profile-video-upload-title-input="true"', false);
        $response->assertSee('data-profile-video-upload-tags-input="true"', false);
        $response->assertSee('编辑资料', false);
        $response->assertSee('data-profile-editor-trigger="true"', false);
        $response->assertSee('id="profile-editor-dialog"', false);
        $response->assertSee('data-profile-name="true"', false);
        $response->assertSee('data-profile-bio="true"', false);
        $response->assertSee('data-profile-editor-name-input="true"', false);
        $response->assertSee('data-profile-editor-bio-input="true"', false);
        $response->assertDontSee('修改头像', false);
        $response->assertDontSee('data-avatar-dialog-trigger="true"', false);
        $response->assertSee('data-avatar-slot="profile"', false);
        $response->assertSee('data-avatar-slot="nav"', false);
        $response->assertDontSee('data-profile-library-tabs="true"', false);
        $response->assertDontSee('data-profile-library-empty-state="true"', false);
        $response->assertSee('creator_alpha', false);
        $response->assertSee('fan_alpha', false);
        $response->assertDontSee('搜索用户名', false);
        $response->assertDontSee('综合排序', false);
        $response->assertDontSee('作品未看', false);
        $response->assertSee('>2<', false);
        $response->assertSee('>3<', false);
        $response->assertSee('退出登录', false);
        $response->assertSee('data-author-follow-button="true"', false);

        $content = $response->getContent();
        self::assertIsString($content);
        $profilePosition = strpos($content, 'data-profile-dashboard-item="profile"');
        $creatorPosition = strpos($content, 'data-profile-dashboard-item="creator"');
        $historyPosition = strpos($content, 'data-profile-dashboard-item="history"');
        $bookmarksPosition = strpos($content, 'data-profile-dashboard-item="bookmarks"');
        $interactionsPosition = strpos($content, 'data-profile-dashboard-item="interactions"');
        $logoutPosition = strpos($content, 'data-profile-dashboard-item="logout"');
        self::assertNotFalse($profilePosition);
        self::assertNotFalse($creatorPosition);
        self::assertNotFalse($historyPosition);
        self::assertNotFalse($bookmarksPosition);
        self::assertNotFalse($interactionsPosition);
        self::assertNotFalse($logoutPosition);
        self::assertTrue($profilePosition < $creatorPosition);
        self::assertTrue($creatorPosition < $historyPosition);
        self::assertTrue($historyPosition < $bookmarksPosition);
        self::assertTrue($bookmarksPosition < $interactionsPosition);
        self::assertTrue($interactionsPosition < $logoutPosition);
    }

    public function test_authenticated_user_can_switch_profile_library_tabs_and_see_matching_status_items(): void
    {
        $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'name' => 'Video Owner',
            'username' => 'video_owner',
        ]);

        foreach ([
            ['title' => 'Published One', 'status' => 'published'],
            ['title' => 'Published Two', 'status' => 'published'],
            ['title' => 'Waiting Review', 'status' => 'reviewing'],
            ['title' => 'Uploading Draft', 'status' => 'uploading'],
            ['title' => 'Old Archived', 'status' => 'removed'],
        ] as $index => $video) {
            Video::query()->create([
                'origin' => 'manual_upload',
                'uploader_user_id' => $viewer->id,
                'title' => $video['title'],
                'caption' => $video['title'].' caption',
                'description' => '#演示 #'.$video['status'],
                'visibility' => 'public',
                'status' => $video['status'],
                'published_at' => now()->subHours($index + 1),
            ]);
        }

        $response = $this->actingAs($viewer)->get(route('profile.show', [
            'username' => $viewer->username,
            'tab' => 'uploading',
        ]));

        $response->assertOk();
        $response->assertSee('data-profile-dashboard-detail="creator"', false);
        $response->assertSee('data-profile-dashboard-item="creator"', false);
        $response->assertSee('data-active="true"', false);
        $response->assertSee('data-profile-dashboard-mobile-nav="true"', false);
        $response->assertSee('data-profile-library-selected-tab="uploading"', false);
        $response->assertSee('data-profile-library-selected-count="1"', false);
        $response->assertSee('data-profile-library-item="true"', false);
        $response->assertSee('data-profile-library-thumbnail="true"', false);
        $response->assertSee('data-profile-library-duration="true"', false);
        $response->assertSee('data-profile-library-tag-line="true"', false);
        $response->assertSee('标签：#演示，#uploading', false);
        $response->assertSee('data-profile-library-progress="true"', false);
        $response->assertSee('处理中', false);
        $response->assertSee('Uploading Draft', false);
        $response->assertDontSee('data-profile-library-actions="true"', false);
        $response->assertDontSee('data-profile-library-date-line="true"', false);
        $response->assertDontSee('data-profile-library-empty-state="true"', false);
    }

    public function test_profile_library_renders_status_specific_metadata_and_actions(): void
    {
        $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'name' => 'Video Owner',
            'username' => 'video_owner_controls',
        ]);

        Video::query()->create([
            'origin' => 'manual_upload',
            'uploader_user_id' => $viewer->id,
            'title' => 'Published Hero',
            'description' => '#发布 #精选',
            'poster_url' => 'https://example.com/poster.jpg',
            'playback_url' => 'https://example.com/video.mp4',
            'duration_text' => '02:13',
            'visibility' => 'public',
            'status' => 'published',
            'published_at' => now()->subMinutes(30),
        ]);

        Video::query()->create([
            'origin' => 'manual_upload',
            'uploader_user_id' => $viewer->id,
            'title' => 'Review Pending',
            'description' => '#审核 #待处理',
            'visibility' => 'public',
            'status' => 'reviewing',
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);

        Video::query()->create([
            'origin' => 'manual_upload',
            'uploader_user_id' => $viewer->id,
            'title' => 'Removed Archive',
            'description' => '#下架 #待整理',
            'visibility' => 'public',
            'status' => 'removed',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $publishedResponse = $this->actingAs($viewer)->get(route('profile.show', [
            'username' => $viewer->username,
            'tab' => 'published',
        ]));

        $publishedResponse->assertOk();
        $publishedResponse->assertSee('data-profile-dashboard-detail="creator"', false);
        $publishedResponse->assertSee('Published Hero', false);
        $publishedResponse->assertSee('data-profile-library-thumbnail="true"', false);
        $publishedResponse->assertSee('data-profile-library-duration="true"', false);
        $publishedResponse->assertSee('标签：#发布，#精选', false);
        $publishedResponse->assertSee('data-profile-library-date-line="true"', false);
        $publishedResponse->assertSee('发布日期：', false);
        $publishedResponse->assertSee('data-profile-library-actions="true"', false);
        $publishedResponse->assertSee('data-profile-library-action="take-down"', false);
        $publishedResponse->assertSee('data-profile-library-action="delete"', false);
        $publishedResponse->assertDontSee('data-profile-library-progress="true"', false);
        $publishedResponse->assertDontSee('data-profile-library-status-tag="true"', false);

        $reviewingResponse = $this->actingAs($viewer)->get(route('profile.show', [
            'username' => $viewer->username,
            'tab' => 'reviewing',
        ]));

        $reviewingResponse->assertOk();
        $reviewingResponse->assertSee('data-profile-dashboard-detail="creator"', false);
        $reviewingResponse->assertSee('Review Pending', false);
        $reviewingResponse->assertSee('data-profile-library-status-tag="true"', false);
        $reviewingResponse->assertSee('审核中', false);
        $reviewingResponse->assertSee('data-profile-library-date-line="true"', false);
        $reviewingResponse->assertDontSee('data-profile-library-actions="true"', false);
        $reviewingResponse->assertDontSee('data-profile-library-progress="true"', false);

        $removedResponse = $this->actingAs($viewer)->get(route('profile.show', [
            'username' => $viewer->username,
            'tab' => 'removed',
        ]));

        $removedResponse->assertOk();
        $removedResponse->assertSee('data-profile-dashboard-detail="creator"', false);
        $removedResponse->assertSee('Removed Archive', false);
        $removedResponse->assertSee('data-profile-library-actions="true"', false);
        $removedResponse->assertSee('data-profile-library-action="edit"', false);
        $removedResponse->assertSee('data-profile-library-action="resubmit"', false);
        $removedResponse->assertSee('data-profile-library-action="delete"', false);
        $removedResponse->assertSee('编辑信息', false);
        $removedResponse->assertSee('重新提交', false);
    }

    public function test_authenticated_user_can_open_history_panel_inside_profile_dashboard(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'name' => 'History Owner',
            'username' => 'history_owner',
        ]);
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '7788',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo Creator',
                'text' => '嵌入观看记录视频',
                'postedAt' => now()->subHour()->toISOString(),
            ],
        ]);

        $video = Video::query()->firstOrFail();

        DB::table('video_views')->insert([
            'video_id' => $video->id,
            'user_id' => $viewer->id,
            'session_id' => 'profile-history-session',
            'view_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($viewer)->get(route('profile.show', [
            'username' => $viewer->username,
            'panel' => 'history',
        ]));

        $response->assertOk();
        $response->assertSee('data-profile-dashboard-mobile-nav="true"', false);
        $response->assertSee('data-profile-dashboard-detail="history"', false);
        $response->assertSee('data-profile-dashboard-item="history"', false);
        $response->assertSee('嵌入观看记录视频', false);
        $response->assertSee('data-history-record-grid="true"', false);
        $response->assertSee('data-history-record-item="true"', false);
        $response->assertSee('data-history-clear-all="true"', false);
        $response->assertDontSee('data-history-back="true"', false);
    }

    public function test_authenticated_user_sees_public_profile_controls_when_opening_another_user_page(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'viewer_profile',
        ]);
        $profileUser = User::factory()->create([
            'name' => 'Public Creator',
            'username' => 'creator_public',
            'avatar_url' => 'https://example.com/creator.jpg',
        ]);

        $repository->followUser($viewer->id, $profileUser->id);

        $response = $this->actingAs($viewer)->get(route('profile.show', [
            'username' => $profileUser->username,
            'panel' => 'history',
        ]));

        $response->assertOk();
        $response->assertSee('Public Creator', false);
        $response->assertSee('&#64;creator_public', false);
        $response->assertSee('https://example.com/creator.jpg', false);
        $response->assertSee('data-author-follow-button="true"', false);
        $response->assertSee('data-author-user-id="'.$profileUser->id.'"', false);
        $response->assertSee('已关注', false);
        $response->assertDontSee('上传视频', false);
        $response->assertDontSee('data-profile-video-upload-trigger="true"', false);
        $response->assertDontSee('id="profile-video-upload-dialog"', false);
        $response->assertDontSee('编辑资料', false);
        $response->assertDontSee('修改头像', false);
        $response->assertDontSee('data-profile-editor-trigger="true"', false);
        $response->assertDontSee('id="profile-editor-dialog"', false);
        $response->assertDontSee('退出登录', false);
        $response->assertDontSee('data-profile-library-tabs="true"', false);
        $response->assertDontSee('data-profile-dashboard-nav="true"', false);
        $response->assertDontSee('data-profile-dashboard-detail="history"', false);
        $response->assertSee('data-profile-social-modal="true"', false);
    }

    public function test_public_profile_page_renders_published_videos_in_feed_grid(): void
    {
        $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'public_feed_viewer',
        ]);
        $profileUser = User::factory()->create([
            'name' => 'Feed Creator',
            'username' => 'feed_creator',
        ]);

        foreach ([
            ['title' => 'Creator Published One', 'status' => 'published'],
            ['title' => 'Creator Published Two', 'status' => 'published'],
            ['title' => 'Creator Draft Hidden', 'status' => 'reviewing'],
        ] as $index => $video) {
            Video::query()->create([
                'origin' => 'manual_upload',
                'uploader_user_id' => $profileUser->id,
                'title' => $video['title'],
                'caption' => $video['title'].' caption',
                'visibility' => 'public',
                'status' => $video['status'],
                'published_at' => now()->subHours($index + 1),
            ]);
        }

        $response = $this->actingAs($viewer)->get(route('profile.show', ['username' => $profileUser->username]));

        $response->assertOk();
        $response->assertSee('发布的视频', false);
        $response->assertSee('这里展示这个账号已经公开发布的视频内容', false);
        $response->assertSee('id="feed-grid"', false);
        $response->assertSee('id="feed-bootstrap"', false);
        $response->assertSee('id="feed-detail-modal"', false);
        $response->assertSee('data-empty="false"', false);
        $response->assertSee('Creator Published One', false);
        $response->assertSee('Creator Published Two', false);
        $response->assertDontSee('Creator Draft Hidden', false);
        $response->assertDontSee('data-profile-library-tabs="true"', false);
        $response->assertDontSee('data-profile-library-item="true"', false);
    }

    public function test_profile_page_normalizes_legacy_managed_avatar_urls_to_first_party_avatar_route(): void
    {
        $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'name' => 'Legacy Avatar',
            'username' => 'legacy_avatar',
        ]);

        $viewer->forceFill([
            'avatar_url' => 'http://localhost:8000/storage/avatars/'.$viewer->id.'/legacy-avatar.jpg',
        ])->save();

        $response = $this->actingAs($viewer)->get(route('profile.show', ['username' => $viewer->username]));

        $response->assertOk();
        $response->assertSee('/avatars/'.$viewer->id.'/legacy-avatar.jpg', false);
        $response->assertDontSee('/storage/avatars/'.$viewer->id.'/legacy-avatar.jpg', false);
    }

    public function test_guest_can_open_public_profile_page_and_is_prompted_to_log_in_for_follow(): void
    {
        $this->useShortVideoDatabase();
        $profileUser = User::factory()->create([
            'name' => 'Guest Visible',
            'username' => 'guest_visible',
        ]);

        $response = $this->get(route('profile.show', ['username' => $profileUser->username]));

        $response->assertOk();
        $response->assertSee('Guest Visible', false);
        $response->assertSee('&#64;guest_visible', false);
        $response->assertSee('data-auth-modal-trigger="true"', false);
        $response->assertDontSee('编辑资料', false);
        $response->assertDontSee('修改头像', false);
    }

    public function test_ui_path_returns_not_found_when_no_ui_username_exists(): void
    {
        $this->useShortVideoDatabase();

        $this->get('/ui')->assertNotFound();
    }

    public function test_ui_path_resolves_to_profile_page_when_ui_username_exists(): void
    {
        $this->useShortVideoDatabase();
        $profileUser = User::factory()->create([
            'name' => 'UI Profile',
            'username' => 'ui',
        ]);

        $response = $this->get('/ui');

        $response->assertOk();
        $response->assertSee('UI Profile', false);
        $response->assertSee('&#64;ui', false);
        $response->assertSee('data-auth-modal-trigger="true"', false);
        $response->assertDontSee('UI Library', false);
    }

    public function test_unknown_username_returns_not_found(): void
    {
        $this->useShortVideoDatabase();

        $this->get('/missing_profile_user')->assertNotFound();
    }
}
