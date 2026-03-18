<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ProfileAvatarUploadTest extends TestCase
{
    public function test_guest_cannot_upload_profile_avatar(): void
    {
        $this->useShortVideoDatabase();
        Storage::fake('public');

        $response = $this
            ->withHeader('Accept', 'application/json')
            ->post('/api/profile/avatar');

        $response->assertStatus(401);
        $response->assertJsonPath('message', '请先登录后再修改头像。');
    }

    public function test_avatar_upload_rejects_non_image_files(): void
    {
        $this->useShortVideoDatabase();
        Storage::fake('public');
        $viewer = User::factory()->create();

        $response = $this
            ->actingAs($viewer)
            ->withHeader('Accept', 'application/json')
            ->post('/api/profile/avatar', [
                'avatar' => UploadedFile::fake()->create('avatar.pdf', 64, 'application/pdf'),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);
    }

    public function test_avatar_upload_rejects_files_larger_than_five_megabytes(): void
    {
        $this->useShortVideoDatabase();
        Storage::fake('public');
        $viewer = User::factory()->create();

        $response = $this
            ->actingAs($viewer)
            ->withHeader('Accept', 'application/json')
            ->post('/api/profile/avatar', [
                'avatar' => UploadedFile::fake()->image('oversized-avatar.png')->size(6000),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);
    }

    public function test_avatar_upload_stores_file_and_updates_user_avatar_url(): void
    {
        $this->useShortVideoDatabase();
        Storage::fake('public');
        $viewer = User::factory()->create([
            'avatar_url' => null,
        ]);

        $response = $this
            ->actingAs($viewer)
            ->withHeader('Accept', 'application/json')
            ->post('/api/profile/avatar', [
                'avatar' => UploadedFile::fake()->image('fresh-avatar.jpg', 320, 320),
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['avatarUrl']);

        $avatarUrl = (string) $response->json('avatarUrl');
        $storedPath = $this->publicDiskPathFromUrl($avatarUrl);

        $this->assertStringStartsWith('/avatars/'.$viewer->id.'/', $avatarUrl);
        $this->assertNotNull($storedPath);
        $this->assertStringStartsWith('avatars/'.$viewer->id.'/', $storedPath);
        Storage::disk('public')->assertExists($storedPath);
        $this->get($avatarUrl)->assertOk();

        $viewer->refresh();
        $this->assertSame($avatarUrl, $viewer->avatar_url);
    }

    public function test_avatar_upload_deletes_previous_managed_avatar_file(): void
    {
        $this->useShortVideoDatabase();
        Storage::fake('public');
        $viewer = User::factory()->create([
            'avatar_url' => null,
        ]);
        $oldPath = 'avatars/'.$viewer->id.'/previous-avatar.jpg';

        Storage::disk('public')->put($oldPath, 'old-avatar-content');
        $viewer->forceFill([
            'avatar_url' => Storage::disk('public')->url($oldPath),
        ])->save();

        $response = $this
            ->actingAs($viewer)
            ->withHeader('Accept', 'application/json')
            ->post('/api/profile/avatar', [
                'avatar' => UploadedFile::fake()->image('replacement-avatar.webp', 320, 320),
            ]);

        $response->assertOk();

        $newAvatarUrl = (string) $response->json('avatarUrl');
        $newPath = $this->publicDiskPathFromUrl($newAvatarUrl);

        $this->assertNotNull($newPath);
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_avatar_upload_keeps_working_when_previous_avatar_is_external(): void
    {
        $this->useShortVideoDatabase();
        Storage::fake('public');
        $viewer = User::factory()->create([
            'avatar_url' => 'https://example.com/legacy-avatar.jpg',
        ]);

        $response = $this
            ->actingAs($viewer)
            ->withHeader('Accept', 'application/json')
            ->post('/api/profile/avatar', [
                'avatar' => UploadedFile::fake()->image('external-replacement.png', 320, 320),
            ]);

        $response->assertOk();

        $avatarUrl = (string) $response->json('avatarUrl');
        $storedPath = $this->publicDiskPathFromUrl($avatarUrl);

        $this->assertNotNull($storedPath);
        Storage::disk('public')->assertExists($storedPath);

        $viewer->refresh();
        $this->assertSame($avatarUrl, $viewer->avatar_url);
        $this->assertNotSame('https://example.com/legacy-avatar.jpg', $viewer->avatar_url);
    }

    private function publicDiskPathFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $normalizedPath = ltrim($path, '/');

        return str_starts_with($normalizedPath, 'storage/')
            ? substr($normalizedPath, strlen('storage/'))
            : $normalizedPath;
    }
}
