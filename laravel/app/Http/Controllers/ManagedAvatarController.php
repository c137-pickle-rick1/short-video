<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ManagedAvatarController extends Controller
{
    public function __construct(private readonly FilesystemFactory $filesystems) {}

    public function __invoke(string $user, string $filename): StreamedResponse
    {
        $path = 'avatars/'.$user.'/'.trim($filename, '/');
        $disk = $this->filesystems->disk('public');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
