<?php

namespace App\Http\Requests\Api;

use App\Models\Video;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoCommentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'body' => is_string($this->input('body')) ? trim($this->input('body')) : $this->input('body'),
            'replyToCommentId' => self::normalizeReplyToCommentId($this->input('replyToCommentId')),
        ]);
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
        $video = $this->route('video');
        $videoId = $video instanceof Video ? $video->id : null;

        return [
            'body' => ['required', 'string', 'max:500'],
            'replyToCommentId' => [
                'nullable',
                'integer',
                Rule::exists('video_comments', 'id')->where(static function ($query) use ($videoId): void {
                    if ($videoId !== null) {
                        $query->where('video_id', $videoId);
                    }

                    $query->whereNull('deleted_at');
                }),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => '评论内容不能为空。',
            'body.max' => '评论内容不能超过 500 个字符。',
            'replyToCommentId.integer' => '回复目标无效。',
            'replyToCommentId.exists' => '回复目标不存在或已删除。',
        ];
    }

    private static function normalizeReplyToCommentId(mixed $replyToCommentId): mixed
    {
        if (! is_string($replyToCommentId)) {
            return $replyToCommentId;
        }

        $normalizedReplyToCommentId = trim($replyToCommentId);

        return $normalizedReplyToCommentId === '' ? null : $normalizedReplyToCommentId;
    }
}
