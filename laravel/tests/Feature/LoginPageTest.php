<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

final class LoginPageTest extends TestCase
{
    public function test_login_page_renders_auth_modal_with_email_login_form(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('登录', false);
        $response->assertSee('注册', false);
        $response->assertSee('忘记密码', false);
        $response->assertSee('data-auth-modal="true"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('密码', false);
        $response->assertSee('登录使用邮箱和密码', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('method="POST"', false);
        $response->assertDontSee('用户名 / 邮箱 / 手机号', false);
        $response->assertDontSee('Google', false);
        $response->assertDontSee('Apple', false);
    }

    public function test_user_can_login_with_email(): void
    {
        $this->useShortVideoDatabase();
        $user = User::factory()->create([
            'name' => 'Lagos Tester',
            'username' => 'lagos_login',
            'email' => 'lagos@example.com',
            'password' => 'secret-pass',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'lagos@example.com',
            'password' => 'secret-pass',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()?->last_login_at);
    }

    public function test_login_rejects_invalid_credentials_and_preserves_email(): void
    {
        $this->useShortVideoDatabase();
        User::factory()->create([
            'username' => 'lagos_login',
            'email' => 'lagos@example.com',
            'password' => 'secret-pass',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'lagos@example.com',
            'password' => 'wrong-pass',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['auth']);
        $response->assertSessionHasInput('email', 'lagos@example.com');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $this->useShortVideoDatabase();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $response->assertSessionHas('status', '你已退出登录。');
        $this->assertGuest();
    }
}
