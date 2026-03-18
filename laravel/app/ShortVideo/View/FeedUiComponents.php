<?php

namespace App\ShortVideo\View;

use Illuminate\Contracts\View\Factory as ViewFactory;

final class FeedUiComponents
{
    public function __construct(private readonly ViewFactory $views) {}

    public function renderEmptyStateCard(
        string $title = '还没有可展示的视频',
        string $body = '先在 <code>config/sources.json</code> 启用来源并运行抓取。首页布局已经准备好，一旦有数据就会按瀑布流方式展示出来。'
    ): string {
        return $this->renderView('shortvideo.partials.feed.empty-state-card', [
            'title' => $title,
            'body' => $body,
        ]);
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    public function renderFeedMedia(array $tweet, string $frameClass, string $durationText, string $authorHandle): string
    {
        return $this->renderView('shortvideo.partials.feed.media', [
            'frameClass' => $frameClass,
            'posterUrl' => (string) ($tweet['posterUrl'] ?? ''),
            'hlsUrl' => (string) ($tweet['hlsUrl'] ?? ''),
            'videoUrl' => (string) ($tweet['videoUrl'] ?? ''),
            'authorHandle' => $authorHandle,
            'videoPreload' => ! empty($tweet['hlsUrl']) ? 'metadata' : 'none',
            'showVideo' => ($tweet['status'] ?? null) === 'resolved' && (! empty($tweet['hlsUrl']) || ! empty($tweet['videoUrl'])),
            'durationBadge' => $this->renderDurationBadge($durationText),
        ]);
    }

    public function renderDurationBadge(string $durationText): string
    {
        return $this->renderView('shortvideo.partials.feed.duration-badge', [
            'durationText' => $durationText,
        ]);
    }

    public function renderAuthorIdentity(
        ?string $imageUrl,
        string $authorName,
        string $authorHandle,
        string $authorInitial,
        string $avatarSizeClass,
        string $nameClass,
        ?string $handleClass = null,
        string $wrapperClass = 'flex min-w-0 items-center gap-3',
        string $fallbackClass = 'bg-gray-100 text-gray-700',
        string $imageClass = ''
    ): string {
        return $this->renderView('shortvideo.partials.feed.author-identity', [
            'imageUrl' => $imageUrl,
            'authorName' => $authorName,
            'authorHandle' => $authorHandle,
            'authorInitial' => $authorInitial,
            'avatarSizeClass' => $avatarSizeClass,
            'nameClass' => $nameClass,
            'handleClass' => $handleClass,
            'wrapperClass' => $wrapperClass,
            'fallbackClass' => $fallbackClass,
            'imageClass' => $imageClass,
            'avatarMarkup' => $this->renderAvatar($imageUrl, $authorName, $authorInitial, $avatarSizeClass, $fallbackClass, $imageClass),
        ]);
    }

    public function renderAvatar(
        ?string $imageUrl,
        string $label,
        string $initial,
        string $sizeClass,
        string $fallbackClass = 'bg-gray-100 text-gray-700',
        string $imageClass = ''
    ): string {
        return $this->renderView('shortvideo.partials.feed.avatar', [
            'imageUrl' => $imageUrl,
            'label' => $label,
            'initial' => $initial,
            'sizeClass' => $sizeClass,
            'fallbackClass' => $fallbackClass,
            'imageClass' => $imageClass,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderView(string $view, array $data = []): string
    {
        return $this->views->make($view, $data)->render();
    }
}
