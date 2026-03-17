<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

final class ProfilePageTest extends TestCase
{
    public function test_guest_is_redirected_to_login_when_opening_profile_page(): void
    {
        $this->useShortVideoDatabase();

        $response = $this->get('/me');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_sees_profile_summary_and_follow_stats(): void
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

        $response = $this->actingAs($viewer)->get('/me');

        $response->assertOk();
        $response->assertSee('个人中心', false);
        $response->assertSee('Profile Tester', false);
        $response->assertSee('&#64;profile_tester', false);
        $response->assertSee('https://example.com/profile.jpg', false);
        $response->assertSee('关注了', false);
        $response->assertSee('粉丝数', false);
        $response->assertSee('修改头像', false);
        $response->assertSee('data-avatar-dialog-trigger="true"', false);
        $response->assertSee('id="profile-avatar-dialog"', false);
        $response->assertSee('data-avatar-slot="profile"', false);
        $response->assertSee('data-avatar-slot="nav"', false);
        $response->assertSee('/app/profileAvatar.js', false);
        $response->assertSee('>2<', false);
        $response->assertSee('>3<', false);
        $response->assertSee('退出登录', false);
    }
}
