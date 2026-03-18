<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadAvatarRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rulesDefinition(): array
    {
        return [
            'avatar' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesDefinition(): array
    {
        return [
            'avatar.required' => '请选择要上传的头像图片。',
            'avatar.image' => '头像必须是图片文件。',
            'avatar.max' => '头像图片不能超过 5MB。',
            'avatar.mimes' => '头像仅支持 JPG、PNG、WEBP 格式。',
        ];
    }

    public function authorize(): bool
    {
        return true;
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
}
