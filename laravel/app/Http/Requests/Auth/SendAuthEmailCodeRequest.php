<?php

namespace App\Http\Requests\Auth;

use App\Auth\AuthEmailCodePurpose;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SendAuthEmailCodeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
            'purpose' => is_string($this->input('purpose')) ? trim($this->input('purpose')) : $this->input('purpose'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'purpose' => ['required', 'string', Rule::in(array_map(
                static fn (AuthEmailCodePurpose $purpose): string => $purpose->value,
                AuthEmailCodePurpose::cases()
            ))],
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
            'purpose.required' => '验证码用途不能为空。',
            'purpose.in' => '验证码用途无效。',
        ];
    }
}
