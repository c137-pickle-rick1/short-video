<?php

namespace App\Auth;

enum AuthEmailCodePurpose: string
{
    case Register = 'register';
    case PasswordReset = 'password_reset';

    public function subject(): string
    {
        return match ($this) {
            self::Register => 'Short Video 注册验证码',
            self::PasswordReset => 'Short Video 重置密码验证码',
        };
    }

    public function headline(): string
    {
        return match ($this) {
            self::Register => '注册验证码',
            self::PasswordReset => '重置密码验证码',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Register => '你正在注册 Short Video 账号，请使用下面的验证码完成验证。',
            self::PasswordReset => '你正在重置 Short Video 账号密码，请使用下面的验证码完成验证。',
        };
    }
}
