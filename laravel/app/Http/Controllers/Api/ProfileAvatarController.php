<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadAvatarRequest;
use App\ShortVideo\Auth\CurrentViewerResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ProfileAvatarController extends Controller
{
    public function store(Request $request, CurrentViewerResolver $currentViewerResolver): JsonResponse
    {
        $viewer = $currentViewerResolver->resolve($request);
        if (! $viewer) {
            return response()->json([
                'message' => '请先登录后再修改头像。',
            ], 401);
        }

        Gate::forUser($viewer)->authorize('updateAvatar', $viewer);

        $payload = $request->validate(
            UploadAvatarRequest::rulesDefinition(),
            UploadAvatarRequest::messagesDefinition()
        );
        $avatar = $payload['avatar'];
        $disk = Storage::disk('public');
        $previousAvatarPath = $this->managedAvatarPath($viewer->avatar_url);
        $extension = strtolower($avatar->guessExtension() ?: $avatar->extension() ?: 'jpg');
        $normalizedExtension = $extension === 'jpeg' ? 'jpg' : $extension;
        $filename = Str::uuid()->toString().'.'.$normalizedExtension;
        $path = $disk->putFileAs('avatars/'.$viewer->id, $avatar, $filename);

        if (! is_string($path) || trim($path) === '') {
            return response()->json([
                'message' => '头像上传失败，请稍后重试。',
            ], 500);
        }

        $avatarUrl = $disk->url($path);

        try {
            $viewer->forceFill([
                'avatar_url' => $avatarUrl,
            ])->save();
        } catch (\Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }

        if ($previousAvatarPath !== null && $previousAvatarPath !== $path) {
            $disk->delete($previousAvatarPath);
        }

        return response()->json([
            'avatarUrl' => $avatarUrl,
        ]);
    }

    private function managedAvatarPath(?string $avatarUrl): ?string
    {
        if (! is_string($avatarUrl) || trim($avatarUrl) === '') {
            return null;
        }

        $parsedAvatarPath = parse_url($avatarUrl, PHP_URL_PATH);
        if (! is_string($parsedAvatarPath) || trim($parsedAvatarPath) === '') {
            return null;
        }

        $normalizedPath = '/'.ltrim($parsedAvatarPath, '/');
        if (! Str::startsWith($normalizedPath, '/storage/avatars/')) {
            return null;
        }

        $avatarHost = parse_url($avatarUrl, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($avatarHost) && $avatarHost !== '' && $avatarHost !== $appHost) {
            return null;
        }

        return ltrim(Str::after($normalizedPath, '/storage/'), '/');
    }
}
