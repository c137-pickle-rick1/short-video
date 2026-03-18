<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rulesDefinition(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:280'],
            'avatar' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesDefinition(): array
    {
        return [
            'name.required' => '请输入昵称。',
            'name.string' => '昵称格式不正确。',
            'name.max' => '昵称不能超过 50 个字符。',
            'bio.string' => '简介格式不正确。',
            'bio.max' => '简介不能超过 280 个字符。',
            'avatar.image' => '头像必须是图片文件。',
            'avatar.max' => '头像图片不能超过 5MB。',
            'avatar.mimes' => '头像仅支持 JPG、PNG、WEBP 格式。',
        ];
    }

    /**
     * @return array{name:mixed,bio:mixed}
     */
    public static function normalizedInput(Request $request): array
    {
        return [
            'name' => is_string($request->input('name'))
                ? trim($request->input('name'))
                : $request->input('name'),
            'bio' => self::normalizeBio($request->input('bio')),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::normalizedInput($this));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::rulesDefinition();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::messagesDefinition();
    }

    private static function normalizeBio(mixed $bio): mixed
    {
        if (! is_string($bio)) {
            return $bio;
        }

        $normalizedBio = trim($bio);

        return $normalizedBio === '' ? null : $normalizedBio;
    }
}
