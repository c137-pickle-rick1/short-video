<?php

namespace App\Auth;

use App\Mail\AuthEmailCodeMail;
use App\Models\AuthEmailCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class AuthEmailCodeService
{
    public const RESEND_COOLDOWN_SECONDS = 60;

    public const EXPIRATION_MINUTES = 10;

    public function send(string $email, AuthEmailCodePurpose $purpose): void
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $latestCode = AuthEmailCode::query()
            ->forEmailAndPurpose($normalizedEmail, $purpose->value)
            ->active()
            ->latest('id')
            ->first();

        if (
            $latestCode !== null
            && $latestCode->sent_at !== null
            && $latestCode->sent_at->gt(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))
        ) {
            throw ValidationException::withMessages([
                'email' => ['验证码发送过于频繁，请 60 秒后再试。'],
            ]);
        }

        AuthEmailCode::query()
            ->forEmailAndPurpose($normalizedEmail, $purpose->value)
            ->active()
            ->update([
                'consumed_at' => now(),
            ]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(self::EXPIRATION_MINUTES);

        AuthEmailCode::query()->create([
            'email' => $normalizedEmail,
            'purpose' => $purpose->value,
            'code_hash' => Hash::make($code),
            'sent_at' => now(),
            'expires_at' => $expiresAt,
            'consumed_at' => null,
        ]);

        Mail::to($normalizedEmail)->send(new AuthEmailCodeMail($purpose, $code, $expiresAt));
    }

    public function consume(string $email, AuthEmailCodePurpose $purpose, string $code): void
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $normalizedCode = trim($code);

        $authEmailCode = AuthEmailCode::query()
            ->forEmailAndPurpose($normalizedEmail, $purpose->value)
            ->active()
            ->latest('id')
            ->first();

        if ($authEmailCode === null) {
            throw ValidationException::withMessages([
                'code' => ['验证码不存在或已失效。'],
            ]);
        }

        if ($authEmailCode->expires_at === null || $authEmailCode->expires_at->isPast()) {
            $authEmailCode->forceFill([
                'consumed_at' => now(),
            ])->save();

            throw ValidationException::withMessages([
                'code' => ['验证码已过期，请重新获取。'],
            ]);
        }

        if (! Hash::check($normalizedCode, $authEmailCode->code_hash)) {
            throw ValidationException::withMessages([
                'code' => ['验证码错误，请重新输入。'],
            ]);
        }

        $authEmailCode->forceFill([
            'consumed_at' => now(),
        ])->save();
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
