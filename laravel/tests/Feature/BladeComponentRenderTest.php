<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class BladeComponentRenderTest extends TestCase
{
    public function test_app_shell_component_renders_structured_shell_data_and_named_slots(): void
    {
        $shell = [
            'head' => [
                'pageTitle' => '组件壳层',
                'includePhosphorStyles' => true,
            ],
            'header' => [
                'searchUrl' => '/explore',
                'searchQuery' => 'lagos',
            ],
            'desktopNavigationItems' => [
                [
                    'href' => '/',
                    'label' => '首页',
                    'icon' => 'ph ph-house',
                    'active' => true,
                ],
                [
                    'href' => '/login',
                    'label' => '登录',
                    'icon' => 'ph ph-sign-in',
                    'active' => false,
                    'authTriggerPanel' => 'login',
                ],
            ],
            'mobileNavigationItems' => [
                [
                    'href' => '/',
                    'label' => '首页',
                    'icon' => 'ph ph-house',
                    'active' => true,
                ],
                [
                    'href' => '/me',
                    'label' => '我',
                    'avatarUrl' => 'https://example.com/me.jpg',
                    'avatarInitial' => '我',
                    'active' => false,
                ],
            ],
        ];

        $html = Blade::render(<<<'BLADE'
<x-shortvideo.layout.app-shell :shell="$shell">
  <div data-main-slot="true">Main content</div>

  <x-slot:modals>
    <div data-modal-slot="true">Modal slot</div>
  </x-slot:modals>
</x-shortvideo.layout.app-shell>
BLADE, ['shell' => $shell]);

        $this->assertStringContainsString('<title>组件壳层</title>', $html);
        $this->assertStringContainsString('action="/explore"', $html);
        $this->assertStringContainsString('value="lagos"', $html);
        $this->assertStringContainsString('data-language-menu="true"', $html);
        $this->assertStringContainsString('data-auth-modal-trigger="true"', $html);
        $this->assertStringContainsString('src="https://example.com/me.jpg"', $html);
        $this->assertStringContainsString('data-main-slot="true"', $html);
        $this->assertStringContainsString('data-modal-slot="true"', $html);
    }

    public function test_feed_item_component_renders_slot_based_media_author_and_overlay_content(): void
    {
        $rootAttributes = [
            'data-bookmark-video-id' => '42',
            'data-source-handle' => 'demo',
        ];

        $html = Blade::render(<<<'BLADE'
<x-shortvideo.feed.item
  tweet-id="42"
  status="resolved"
  author-name="Demo Creator"
  display-text="Lagos rooftop walkthrough"
  posted-at-text="2小时前"
  :root-attributes="$rootAttributes"
>
  <x-slot:overlay>
    <a href="/demo" data-overlay-link="true">Open profile</a>
  </x-slot:overlay>

  <x-slot:media>
    <x-shortvideo.feed.media
      frame-class="aspect-video rounded-2xl"
      poster-url="https://example.com/poster.jpg"
      hls-url="https://example.com/video.m3u8"
      video-url="https://example.com/video.mp4"
      author-handle="demo"
      video-preload="metadata"
      :show-video="true"
      duration-text="0:21"
    />
  </x-slot:media>

  <x-slot:author>
    <x-shortvideo.feed.author-identity
      image-url="https://example.com/avatar.jpg"
      author-name="Demo Creator"
      author-handle="demo"
      author-initial="D"
      avatar-size-class="h-7 w-7"
      name-class="truncate text-sm font-semibold text-gray-900"
      profile-url="/demo-creator"
    />
  </x-slot:author>
</x-shortvideo.feed.item>
BLADE, ['rootAttributes' => $rootAttributes]);

        $this->assertStringContainsString('data-feed-detail-trigger="true"', $html);
        $this->assertStringContainsString('data-bookmark-video-id="42"', $html);
        $this->assertStringContainsString('data-source-handle="demo"', $html);
        $this->assertStringContainsString('aria-label="打开 Demo Creator 的视频详情"', $html);
        $this->assertStringContainsString('class="js-feed-player h-full w-full object-cover"', $html);
        $this->assertStringContainsString('data-hls-url="https://example.com/video.m3u8"', $html);
        $this->assertStringContainsString('data-fallback-url="https://example.com/video.mp4"', $html);
        $this->assertStringContainsString('href="/demo"', $html);
        $this->assertStringContainsString('href="/demo-creator"', $html);
        $this->assertStringContainsString('Lagos rooftop walkthrough', $html);
    }

    public function test_empty_state_component_renders_html_description_and_call_to_action(): void
    {
        $html = Blade::render(<<<'BLADE'
<x-ui.empty-state
  title="没有找到相关内容"
  description="试试 <code>explore</code>"
  button-label="去探索"
  button-href="/explore"
  :data-empty-state="true"
  data-kind="feed"
/>
BLADE);

        $this->assertStringContainsString('data-empty-state="true"', $html);
        $this->assertStringContainsString('data-kind="feed"', $html);
        $this->assertStringContainsString('没有找到相关内容', $html);
        $this->assertStringContainsString('<code>explore</code>', $html);
        $this->assertStringContainsString('href="/explore"', $html);
        $this->assertStringContainsString('去探索', $html);
    }

    public function test_auth_modal_component_renders_requested_panel_and_actions(): void
    {
        $html = Blade::render(<<<'BLADE'
<x-shortvideo.auth.modal
  initial-panel="register"
  :open="true"
  :standalone="true"
  login-form-action="/login"
  register-form-action="/register"
  reset-password-form-action="/forgot-password"
  send-code-action="/email/code"
/>
BLADE);

        $this->assertStringContainsString('data-auth-modal="true"', $html);
        $this->assertStringContainsString('data-auth-default-panel="register"', $html);
        $this->assertStringContainsString('data-auth-modal-start-open="true"', $html);
        $this->assertStringContainsString('data-auth-email-code-action="/email/code"', $html);
        $this->assertStringContainsString('action="/login"', $html);
        $this->assertStringContainsString('action="/register"', $html);
        $this->assertStringContainsString('action="/forgot-password"', $html);
        $this->assertStringContainsString('欢迎回来', $html);
        $this->assertStringContainsString('data-auth-panel-switch="password_reset"', $html);
    }
}
