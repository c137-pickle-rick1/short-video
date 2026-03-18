<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class VideoUploadTest extends TestCase
{
    public function test_guest_cannot_upload_video(): void
    {
        $this->useShortVideoDatabase();

        $response = $this
            ->withHeader('Accept', 'application/json')
            ->post('/api/videos');

        $response->assertStatus(401);
        $response->assertJsonPath('message', '请先登录后再上传视频。');
    }

    public function test_authenticated_user_can_upload_video_to_uploading_queue(): void
    {
        $this->useShortVideoDatabase();
        Storage::fake('public');
        $viewer = User::factory()->create([
            'name' => 'Uploader',
            'username' => 'video_uploader',
        ]);

        $response = $this
            ->actingAs($viewer)
            ->withHeader('Accept', 'application/json')
            ->post('/api/videos', [
                'title' => '  我的第一条短视频  ',
                'tags' => '旅行, 探店，Vlog',
                'video' => UploadedFile::fake()->create('first-video.mp4', 10240, 'video/mp4'),
            ]);

        $response->assertOk();
        $response->assertJsonPath('title', '我的第一条短视频');
        $response->assertJsonPath('tags', '#旅行 #探店 #Vlog');
        $response->assertJsonPath('status', 'uploading');
        $response->assertJsonPath('statusLabel', '上传中');
        $response->assertJsonPath('redirectUrl', route('profile.show', [
            'username' => $viewer->username,
            'tab' => 'uploading',
        ]));

        $video = Video::query()->first();

        $this->assertNotNull($video);
        $this->assertSame('manual_upload', $video->origin);
        $this->assertSame($viewer->id, $video->uploader_user_id);
        $this->assertSame('我的第一条短视频', $video->title);
        $this->assertSame('#旅行 #探店 #Vlog', $video->description);
        $this->assertSame('public', $video->storage_disk);
        $this->assertSame('uploading', $video->status);
        $this->assertNull($video->published_at);
        $this->assertIsString($video->storage_path);
        Storage::disk('public')->assertExists($video->storage_path);
    }
}
