<?php

namespace Tests\Feature;

use App\Mail\AuthEmailCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class AuthFlowTest extends TestCase
{
    public function test_register_email_code_can_be_sent_for_new_email(): void
    {
        $this->useShortVideoDatabase();
        Mail::fake();

        $response = $this->postJson('/auth/email-codes', [
            'email' => 'new-user@example.com',
            'purpose' => 'register',
        ]);

        $response->assertSuccessful()
            ->assertJson([
                'message' => '验证码已发送，请查看邮箱。',
                'cooldownSeconds' => 60,
                'expiresInSeconds' => 600,
            ]);

        Mail::assertSent(AuthEmailCodeMail::class, function (AuthEmailCodeMail $mail): bool {
            return $mail->hasTo('new-user@example.com')
                && $mail->purpose->value === 'register'
                && preg_match('/^\d{6}$/', $mail->code) === 1;
        });

        $this->assertDatabaseHas('auth_email_codes', [
            'email' => 'new-user@example.com',
            'purpose' => 'register',
        ]);
    }

    public function test_register_email_code_rejects_existing_email(): void
    {
        $this->useShortVideoDatabase();
        Mail::fake();
        User::factory()->create([
            'email' => 'used@example.com',
        ]);

        $response = $this->postJson('/auth/email-codes', [
            'email' => 'used@example.com',
            'purpose' => 'register',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
        Mail::assertNothingSent();
    }

    public function test_password_reset_email_code_rejects_unknown_and_non_local_emails(): void
    {
        $this->useShortVideoDatabase();
        Mail::fake();
        User::factory()->create([
            'email' => 'external@example.com',
            'account_type' => 'external',
            'password' => null,
        ]);

        $this->postJson('/auth/email-codes', [
            'email' => 'missing@example.com',
            'purpose' => 'password_reset',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        $this->postJson('/auth/email-codes', [
            'email' => 'external@example.com',
            'purpose' => 'password_reset',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        Mail::assertNothingSent();
    }

    public function test_register_email_code_is_throttled_and_can_be_resent_after_cooldown(): void
    {
        $this->useShortVideoDatabase();
        Mail::fake();

        $this->postJson('/auth/email-codes', [
            'email' => 'throttle@example.com',
            'purpose' => 'register',
        ])->assertSuccessful();

        $this->postJson('/auth/email-codes', [
            'email' => 'throttle@example.com',
            'purpose' => 'register',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        $this->travel(61)->seconds();

        $this->postJson('/auth/email-codes', [
            'email' => 'throttle@example.com',
            'purpose' => 'register',
        ])->assertSuccessful();
    }

    public function test_user_can_register_with_valid_email_code_and_is_logged_in(): void
    {
        $this->useShortVideoDatabase();
        Mail::fake();

        $this->postJson('/auth/email-codes', [
            'email' => 'fresh@example.com',
            'purpose' => 'register',
        ])->assertSuccessful();

        $code = $this->lastSentCode();

        $response = $this->postJson('/register', [
            'email' => 'fresh@example.com',
            'code' => $code,
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $response->assertCreated()->assertJson([
            'message' => '注册成功，已自动登录。',
        ]);

        $user = User::query()->where('email', 'fresh@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->last_login_at);
        $this->assertStringStartsWith('user_', $user->username);
    }

    public function test_expired_register_code_cannot_be_used(): void
    {
        $this->useShortVideoDatabase();
        Mail::fake();

        $this->postJson('/auth/email-codes', [
            'email' => 'expired@example.com',
            'purpose' => 'register',
        ])->assertSuccessful();

        $code = $this->lastSentCode();
        $this->travel(11)->minutes();

        $this->postJson('/register', [
            'email' => 'expired@example.com',
            'code' => $code,
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_user_can_reset_password_with_valid_email_code_and_code_cannot_be_reused(): void
    {
        $this->useShortVideoDatabase();
        Mail::fake();
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => 'old-password',
        ]);

        $this->postJson('/auth/email-codes', [
            'email' => 'reset@example.com',
            'purpose' => 'password_reset',
        ])->assertSuccessful();

        $code = $this->lastSentCode();

        $this->postJson('/password/reset', [
            'email' => 'reset@example.com',
            'code' => $code,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSuccessful()->assertJson([
            'message' => '密码已重置，请使用新密码登录。',
        ]);

        $this->post('/login', [
            'email' => 'reset@example.com',
            'password' => 'new-password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user->fresh());

        $this->postJson('/password/reset', [
            'email' => 'reset@example.com',
            'code' => $code,
            'password' => 'another-pass',
            'password_confirmation' => 'another-pass',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    private function lastSentCode(): string
    {
        $sentCode = null;

        Mail::assertSent(AuthEmailCodeMail::class, function (AuthEmailCodeMail $mail) use (&$sentCode): bool {
            $sentCode = $mail->code;

            return true;
        });

        return (string) $sentCode;
    }
}
