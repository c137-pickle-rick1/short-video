<?php

namespace App\ShortVideo\Services;

use App\Models\User;
use App\Models\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class VideoUploadService
{
    public function store(User $viewer, UploadedFile $uploadedVideo, string $title, ?string $tags = null): ?Video
    {
        $disk = Storage::disk('public');
        $extension = $this->resolveExtension($uploadedVideo);
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $disk->putFileAs('videos/'.$viewer->id, $uploadedVideo, $filename);

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        try {
            return Video::query()->create([
                'origin' => 'manual_upload',
                'uploader_user_id' => $viewer->id,
                'title' => trim($title),
                'description' => $this->formatTags($tags),
                'storage_disk' => 'public',
                'storage_path' => $path,
                'visibility' => 'public',
                'status' => 'uploading',
            ]);
        } catch (\Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }
    }

    private function resolveExtension(UploadedFile $uploadedVideo): string
    {
        $extension = strtolower(
            $uploadedVideo->guessExtension()
            ?: $uploadedVideo->extension()
            ?: $uploadedVideo->getClientOriginalExtension()
            ?: 'mp4'
        );

        return match ($extension) {
            'qt' => 'mov',
            default => $extension,
        };
    }

    private function formatTags(?string $tags): ?string
    {
        if (! is_string($tags) || trim($tags) === '') {
            return null;
        }

        $resolvedTags = [];

        foreach (preg_split('/[\r\n,，;；]+/u', $tags) ?: [] as $tag) {
            $normalizedTag = ltrim(trim((string) $tag), '#');

            if ($normalizedTag === '' || in_array($normalizedTag, $resolvedTags, true)) {
                continue;
            }

            $resolvedTags[] = $normalizedTag;
        }

        if ($resolvedTags === []) {
            return null;
        }

        return implode(' ', array_map(
            static fn (string $tag): string => '#'.$tag,
            $resolvedTags
        ));
    }
}
