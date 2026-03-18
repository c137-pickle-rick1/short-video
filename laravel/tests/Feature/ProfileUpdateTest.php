<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ProfileUpdateTest extends TestCase
{
    public function test_guest_cannot_save_profile(): void
    {
        $this->useShortVideoDatabase();

        $response = $this
            ->withHeader('Accept', 'application/json')
            ->post('/api/profile');

        $response->assertStatus(401);
        $response->assertJsonPath('message', '请先登录后再保存资料。');
    }

    public function test_profile_update_can_save_name_and_clear_bio_without_replacing_avatar(): void
    {
        $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'name' => 'Old Name',
            'username' => 'profile_editor',
            'avatar_url' => 'https://example.com/original-avatar.jpg',
            'bio' => 'Old bio',
        ]);

        $response = $this
            ->actingAs($viewer)
            ->withHeader('Accept', 'application/json')
            ->post('/api/profile', [
                'name' => '  New Name  ',
                'bio' => '   ',
            ]);

        $response->assertOk();
        $response->assertJsonPath('name', 'New Name');
        $response->assertJsonPath('bio', null);
        $response->assertJsonPath('avatarUrl', 'https://example.com/original-avatar.jpg');

        $viewer->refresh();
        $this->assertSame('New Name', $viewer->name);
        $this->assertNull($viewer->bio);
        $this->assertSame('https://example.com/original-avatar.jpg', $viewer->avatar_url);
    }

    public function test_profile_update_can_save_avatar_name_and_bio_together_and_delete_previous_managed_avatar(): void
    {
        $this->useShortVideoDatabase();
        Storage::fake('public');
        $viewer = User::factory()->create([
            'name' => 'Before Update',
            'bio' => 'Before bio',
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
            ->post('/api/profile', [
                'name' => 'Updated Name',
                'bio' => 'Updated bio',
                'avatar' => UploadedFile::fake()->image('fresh-avatar.webp', 320, 320),
            ]);

        $response->assertOk();
        $response->assertJsonPath('name', 'Updated Name');
        $response->assertJsonPath('bio', 'Updated bio');
        $response->assertJsonStructure(['avatarUrl']);

        $avatarUrl = (string) $response->json('avatarUrl');
        $storedPath = $this->publicDiskPathFromUrl($avatarUrl);

        $this->assertStringStartsWith('/avatars/'.$viewer->id.'/', $avatarUrl);
        $this->assertNotNull($storedPath);
        $this->assertStringStartsWith('avatars/'.$viewer->id.'/', $storedPath);
        $this->assertNotSame($oldPath, $storedPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($storedPath);
        $this->get($avatarUrl)->assertOk();

        $viewer->refresh();
        $this->assertSame('Updated Name', $viewer->name);
        $this->assertSame('Updated bio', $viewer->bio);
        $this->assertSame($avatarUrl, $viewer->avatar_url);
    }

    public function test_profile_update_rejects_empty_name_after_trimming(): void
    {
        $this->useShortVideoDatabase();
        $viewer = User::factory()->create();

        $response = $this
            ->actingAs($viewer)
            ->withHeader('Accept', 'application/json')
            ->post('/api/profile', [
                'name' => '   ',
                'bio' => 'Still valid',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_profile_update_rejects_name_and_bio_that_are_too_long(): void
    {
        $this->useShortVideoDatabase();
        $viewer = User::factory()->create();

        $response = $this
            ->actingAs($viewer)
            ->withHeader('Accept', 'application/json')
            ->post('/api/profile', [
                'name' => str_repeat('a', 51),
                'bio' => str_repeat('b', 281),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'bio']);
    }

    public function test_profile_update_rejects_non_image_avatar_files(): void
    {
        $this->useShortVideoDatabase();
        Storage::fake('public');
        $viewer = User::factory()->create();

        $response = $this
            ->actingAs($viewer)
            ->withHeader('Accept', 'application/json')
            ->post('/api/profile', [
                'name' => 'Valid Name',
                'bio' => 'Valid bio',
                'avatar' => UploadedFile::fake()->create('avatar.pdf', 64, 'application/pdf'),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);
    }

    public function test_profile_update_rejects_oversized_avatar_files(): void
    {
        $this->useShortVideoDatabase();
        Storage::fake('public');
        $viewer = User::factory()->create();

        $response = $this
            ->actingAs($viewer)
            ->withHeader('Accept', 'application/json')
            ->post('/api/profile', [
                'name' => 'Valid Name',
                'bio' => 'Valid bio',
                'avatar' => UploadedFile::fake()->image('oversized-avatar.png')->size(6000),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);
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
