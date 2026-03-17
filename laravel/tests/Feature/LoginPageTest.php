<?php

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LoginPageTest extends TestCase
{
    public function test_login_page_renders_login_form(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('登录', false);
        $response->assertSee('用户名 / 邮箱 / 手机号', false);
        $response->assertSee('密码', false);
        $response->assertSee('支持用户名、邮箱或手机号', false);
        $response->assertSee('name="login"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('method="POST"', false);
        $response->assertDontSee('Google', false);
        $response->assertDontSee('Apple', false);
    }

    #[DataProvider('loginIdentifierProvider')]
    public function test_user_can_login_with_supported_identifiers(string $identifier): void
    {
        $this->useShortVideoDatabase();
        $user = User::factory()->create([
            'name' => 'Lagos Tester',
            'username' => 'lagos_login',
            'email' => 'lagos@example.com',
            'phone' => '13800138000',
            'password' => 'secret-pass',
        ]);

        $response = $this->from('/login')->post('/login', [
            'login' => $identifier,
            'password' => 'secret-pass',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()?->last_login_at);
    }

    public function test_login_rejects_invalid_credentials_and_preserves_identifier(): void
    {
        $this->useShortVideoDatabase();
        User::factory()->create([
            'username' => 'lagos_login',
            'email' => 'lagos@example.com',
            'password' => 'secret-pass',
        ]);

        $response = $this->from('/login')->post('/login', [
            'login' => 'lagos_login',
            'password' => 'wrong-pass',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['auth']);
        $response->assertSessionHasInput('login', 'lagos_login');
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

    /**
     * @return array<int, array{0:string}>
     */
    public static function loginIdentifierProvider(): array
    {
        return [
            ['lagos_login'],
            ['lagos@example.com'],
            ['13800138000'],
        ];
    }
}
