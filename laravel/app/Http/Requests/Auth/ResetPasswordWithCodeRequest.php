<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class ResetPasswordWithCodeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
            'code' => is_string($this->input('code')) ? trim($this->input('code')) : $this->input('code'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => '请输入邮箱地址。',
            'email.email' => '请输入有效的邮箱地址。',
            'code.required' => '请输入邮箱验证码。',
            'code.digits' => '验证码必须为 6 位数字。',
            'password.required' => '请输入新密码。',
            'password.min' => '新密码至少需要 8 位。',
            'password.confirmed' => '两次输入的新密码不一致。',
        ];
    }
}
