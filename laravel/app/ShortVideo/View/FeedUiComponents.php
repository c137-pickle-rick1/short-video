<?php

namespace App\ShortVideo\View;

final class FeedUiComponents
{
    public function renderEmptyStateCard(
        string $title = '还没有可展示的视频',
        string $body = '先在 <code>config/sources.json</code> 启用来源并运行抓取。首页布局已经准备好，一旦有数据就会按瀑布流方式展示出来。'
    ): string {
        return <<<HTML
    <article
      class="feed-grid-item mb-3 inline-block w-full overflow-hidden rounded-3xl border border-gray-200 bg-white/95 px-6 py-8 text-center shadow-xl backdrop-blur-2xl sm:mb-4 lg:mb-5 xl:mb-6 2xl:mb-7"
      data-empty-state="true"
    >
      <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-xl text-gray-700">
        ⌕
      </div>
      <h2 class="mt-4 text-2xl font-semibold tracking-tight text-gray-900">
        {$this->escape($title)}
      </h2>
      <p class="mt-3 text-sm leading-6 text-gray-500 sm:text-base">
        {$body}
      </p>
    </article>
HTML;
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    public function renderFeedMedia(array $tweet, string $frameClass, string $durationText, string $authorHandle): string
    {
        $safePoster = $this->escape((string) ($tweet['posterUrl'] ?? ''));
        $safeHlsUrl = $this->escape((string) ($tweet['hlsUrl'] ?? ''));
        $safeVideoUrl = $this->escape((string) ($tweet['videoUrl'] ?? ''));
        $safeHandle = $this->escape($authorHandle);
        $videoPreload = ! empty($tweet['hlsUrl']) ? 'metadata' : 'none';
        $posterAttr = $safePoster !== '' ? ' poster="'.$safePoster.'"' : '';
        $hlsAttr = $safeHlsUrl !== '' ? ' data-hls-url="'.$safeHlsUrl.'"' : '';
        $fallbackAttr = $safeVideoUrl !== '' ? ' data-fallback-url="'.$safeVideoUrl.'"' : '';

        $mediaMarkup = ($tweet['status'] ?? null) === 'resolved' && (! empty($tweet['hlsUrl']) || ! empty($tweet['videoUrl']))
            ? <<<HTML
                  <video
                    class="js-feed-player h-full w-full object-cover"{$posterAttr}
                    data-poster="{$safePoster}"{$hlsAttr}{$fallbackAttr}
                    muted
                    loop
                    playsinline
                    disablepictureinpicture
                    preload="{$videoPreload}"
                    referrerpolicy="no-referrer"
                  ></video>
              HTML
            : <<<HTML
                  <img
                    class="h-full w-full object-cover"
                    src="{$safePoster}"
                    alt="Poster for @{$safeHandle}"
                    loading="lazy"
                  />
              HTML;

        $durationBadge = $this->renderDurationBadge($durationText);

        return <<<HTML
      <div class="relative {$frameClass} overflow-hidden bg-gray-100">
        {$mediaMarkup}
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-transparent via-black/5 to-black/10"></div>
        {$durationBadge}
      </div>
HTML;
    }

    public function renderDurationBadge(string $durationText): string
    {
        $safeDurationText = $this->escape($durationText);
        $hiddenClass = $safeDurationText === '' ? 'hidden ' : '';

        return <<<HTML
        <span
          class="pointer-events-none absolute right-3 top-3 z-10 {$hiddenClass}rounded-full bg-black/15 px-2.5 py-1.5 text-sm font-semibold leading-none text-white backdrop-blur-sm"
          data-video-duration
        >{$safeDurationText}</span>
HTML;
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
        $safeName = $this->escape($authorName);
        $safeHandle = $this->escape($authorHandle);
        $handleMarkup = $handleClass !== null && $handleClass !== ''
            ? <<<HTML
                  <p class="{$handleClass}">@{$safeHandle}</p>
              HTML
            : '';

        return <<<HTML
      <div class="{$wrapperClass}">
        {$this->renderAvatar($imageUrl, $authorName, $authorInitial, $avatarSizeClass, $fallbackClass, $imageClass)}
        <div class="min-w-0">
          <p class="{$nameClass}">{$safeName}</p>
          {$handleMarkup}
        </div>
      </div>
HTML;
    }

    public function renderAvatar(
        ?string $imageUrl,
        string $label,
        string $initial,
        string $sizeClass,
        string $fallbackClass = 'bg-gray-100 text-gray-700',
        string $imageClass = ''
    ): string {
        if ($imageUrl) {
            $imageClasses = trim("{$sizeClass} rounded-full object-cover ring-1 ring-gray-200 {$imageClass}");

            return <<<HTML
      <img
        class="{$imageClasses}"
        src="{$this->escape($imageUrl)}"
        alt="{$this->escape($label)} 的头像"
        loading="lazy"
        referrerpolicy="no-referrer"
      />
HTML;
        }

        return <<<HTML
    <span
      class="flex {$sizeClass} items-center justify-center rounded-full {$fallbackClass} text-xs font-semibold"
      aria-hidden="true"
    >
      {$this->escape($initial)}
    </span>
HTML;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
