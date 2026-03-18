<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;

class UploadVideoRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:120'],
            'tags' => ['nullable', 'string', 'max:120'],
            'video' => ['required', File::types(['mp4', 'mov', 'm4v', 'webm'])->max(200 * 1024)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesDefinition(): array
    {
        return [
            'title.required' => '请输入视频标题。',
            'title.string' => '视频标题格式不正确。',
            'title.max' => '视频标题不能超过 120 个字符。',
            'tags.string' => '标签格式不正确。',
            'tags.max' => '标签不能超过 120 个字符。',
            'video.required' => '请选择要上传的视频文件。',
            'video.mimes' => '视频仅支持 MP4、MOV、M4V、WEBM 格式。',
            'video.max' => '视频文件不能超过 200MB。',
        ];
    }

    /**
     * @return array{title:mixed,tags:mixed}
     */
    public static function normalizedInput(Request $request): array
    {
        return [
            'title' => is_string($request->input('title'))
                ? trim($request->input('title'))
                : $request->input('title'),
            'tags' => self::normalizeTags($request->input('tags')),
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

    private static function normalizeTags(mixed $tags): mixed
    {
        if (! is_string($tags)) {
            return $tags;
        }

        $normalizedTags = trim($tags);

        return $normalizedTags === '' ? null : $normalizedTags;
    }
}
