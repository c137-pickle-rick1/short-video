<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ManagedAvatarService
{
    public function __construct(private readonly UrlGenerator $url) {}

    /**
     * @return array{avatarUrl:string,path:string,previousPath:?string}|null
     */
    public function store(User $user, UploadedFile $avatar): ?array
    {
        $disk = Storage::disk('public');
        $previousAvatarPath = $this->managedAvatarPath($user->avatar_url);
        $extension = strtolower($avatar->guessExtension() ?: $avatar->extension() ?: 'jpg');
        $normalizedExtension = $extension === 'jpeg' ? 'jpg' : $extension;
        $filename = Str::uuid()->toString().'.'.$normalizedExtension;
        $path = $disk->putFileAs('avatars/'.$user->id, $avatar, $filename);

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        return [
            'avatarUrl' => $this->managedAvatarUrl($path),
            'path' => $path,
            'previousPath' => $previousAvatarPath,
        ];
    }

    public function delete(string $path): void
    {
        Storage::disk('public')->delete($path);
    }

    public function prunePrevious(?string $previousPath, string $currentPath): void
    {
        if ($previousPath !== null && $previousPath !== $currentPath) {
            Storage::disk('public')->delete($previousPath);
        }
    }

    public function managedAvatarUrl(string $path): string
    {
        $normalizedPath = trim($path, '/');

        if (! Str::startsWith($normalizedPath, 'avatars/')) {
            return '/'.$normalizedPath;
        }

        $segments = explode('/', $normalizedPath, 3);
        if (count($segments) !== 3 || $segments[0] !== 'avatars' || $segments[1] === '' || $segments[2] === '') {
            return '/'.$normalizedPath;
        }

        return $this->url->route('managed-avatar.show', [
            'user' => $segments[1],
            'filename' => $segments[2],
        ], false);
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
        if (Str::startsWith($normalizedPath, '/storage/avatars/')) {
            $candidatePath = ltrim(Str::after($normalizedPath, '/storage/'), '/');
        } elseif (Str::startsWith($normalizedPath, '/avatars/')) {
            $candidatePath = ltrim($normalizedPath, '/');
        } else {
            return null;
        }

        $avatarHost = parse_url($avatarUrl, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($avatarHost) && $avatarHost !== '' && $avatarHost !== $appHost) {
            return null;
        }

        return $candidatePath;
    }
}
