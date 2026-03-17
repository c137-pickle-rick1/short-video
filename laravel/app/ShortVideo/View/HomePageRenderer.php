<?php

namespace App\ShortVideo\View;

use Carbon\CarbonImmutable;

final class HomePageRenderer
{
    public function renderDocumentHead(string $pageTitle): string
    {
        return <<<HTML
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{$this->escape($pageTitle)}</title>
    <link rel="stylesheet" href="/vendor/fonts/fonts.css" />
    <link rel="stylesheet" href="/vendor/phosphor/regular/style.css" />
    <link rel="stylesheet" href="/vendor/phosphor/fill/style.css" />
    <link rel="stylesheet" href="/vendor/plyr/plyr.css" />
    <link rel="stylesheet" href="/styles.css" />
HTML;
    }

    public function renderDesktopNavigation(): string
    {
        return <<<'HTML'
          <aside class="hidden lg:block lg:sticky lg:top-[100px] lg:w-56 lg:flex-none xl:top-[104px] 2xl:top-[108px]">
            <nav aria-label="桌面主导航">
              <div class="grid gap-2">
                <button
                  type="button"
                  aria-current="page"
                  class="inline-flex h-12 w-full items-center gap-4 rounded-full bg-gray-100 px-6 text-left text-lg font-semibold text-gray-900 transition-colors hover:bg-gray-200"
                >
                  <i class="ph-fill ph-house text-2xl leading-none" aria-hidden="true"></i>
                  <span>首页</span>
                </button>
                <button
                  type="button"
                  class="inline-flex h-12 w-full items-center gap-4 rounded-full px-6 text-left text-lg font-medium text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-900"
                >
                  <i class="ph ph-bookmarks text-2xl leading-none" aria-hidden="true"></i>
                  <span>订阅</span>
                </button>
                <button
                  type="button"
                  class="inline-flex h-12 w-full items-center gap-4 rounded-full px-6 text-left text-lg font-medium text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-900"
                >
                  <i class="ph ph-compass text-2xl leading-none" aria-hidden="true"></i>
                  <span>探索</span>
                </button>
                <button
                  type="button"
                  class="inline-flex h-12 w-full items-center gap-4 rounded-full px-6 text-left text-lg font-medium text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-900"
                >
                  <i class="ph ph-chart-bar text-2xl leading-none" aria-hidden="true"></i>
                  <span>榜单</span>
                </button>
              </div>
            </nav>
          </aside>
HTML;
    }

    public function renderMobileNavigation(): string
    {
        return <<<'HTML'
          <nav
            aria-label="移动主导航"
            class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 px-3 py-2 backdrop-blur-2xl lg:hidden"
          >
            <div class="mx-auto grid max-w-md grid-cols-4">
              <button
                type="button"
                aria-current="page"
                class="flex flex-col items-center justify-center gap-1 py-2 text-center text-xs font-medium text-gray-900"
              >
                <i class="ph-fill ph-house text-[28px] leading-none" aria-hidden="true"></i>
                首页
              </button>
              <button
                type="button"
                class="flex flex-col items-center justify-center gap-1 py-2 text-center text-xs font-medium text-gray-500"
              >
                <i class="ph ph-bookmarks text-[28px] leading-none" aria-hidden="true"></i>
                订阅
              </button>
              <button
                type="button"
                class="flex flex-col items-center justify-center gap-1 py-2 text-center text-xs font-medium text-gray-500"
              >
                <i class="ph ph-compass text-[28px] leading-none" aria-hidden="true"></i>
                探索
              </button>
              <button
                type="button"
                class="flex flex-col items-center justify-center gap-1 py-2 text-center text-xs font-medium text-gray-500"
              >
                <i class="ph ph-chart-bar text-[28px] leading-none" aria-hidden="true"></i>
                榜单
              </button>
            </div>
          </nav>
HTML;
    }

    public function renderFeedToolbar(string $activeSourceHandle, string $feedSummaryText, string $feedStatusText): string
    {
        $selectedAll = $activeSourceHandle === '' ? 'selected' : '';
        $activeSourceOption = $activeSourceHandle !== ''
            ? <<<HTML
                  <option value="{$this->escape($activeSourceHandle)}" selected>
                    {$this->escape('@'.$activeSourceHandle)}
                  </option>
              HTML
            : '';

        return <<<HTML
            <div class="mb-4 flex flex-col gap-3 rounded-[28px] border border-gray-200 bg-white/90 px-4 py-4 shadow-sm backdrop-blur-xl sm:px-5 lg:mb-5 lg:flex-row lg:items-center lg:justify-between lg:px-6">
              <div class="min-w-0">
                <p id="feed-summary" class="text-sm font-medium text-gray-700">{$this->escape($feedSummaryText)}</p>
                <p id="feed-status" class="mt-1 text-xs text-gray-500" aria-live="polite">{$this->escape($feedStatusText)}</p>
              </div>
              <label class="inline-flex items-center gap-3 text-sm text-gray-600">
                <span class="shrink-0 font-medium text-gray-700">来源</span>
                <select
                  id="source-filter"
                  class="h-11 min-w-[180px] rounded-full border border-gray-200 bg-gray-50 px-4 text-sm text-gray-900 outline-none transition focus:border-gray-300"
                >
                  <option value="" {$selectedAll}>全部来源</option>
                  {$activeSourceOption}
                </select>
              </label>
            </div>
HTML;
    }

    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function renderFeedGrid(array $viewModel): string
    {
        $feed = is_array($viewModel['feed'] ?? null) ? $viewModel['feed'] : [];
        $items = is_array($feed['items'] ?? null) ? $feed['items'] : [];
        $isEmpty = ! empty($feed['isEmpty']);
        $itemsMarkup = $isEmpty
            ? $this->renderFeedEmptyState()
            : implode('', array_map(fn (array $item) => $this->renderFeedItem($item), $items));
        $emptyAttr = $isEmpty ? 'true' : 'false';

        return <<<HTML
            <section class="feed-grid" id="feed-grid" aria-live="polite" data-empty="{$emptyAttr}">
              <div class="feed-grid-col">
                {$itemsMarkup}
              </div>
              <div class="feed-grid-col"></div>
              <div class="feed-grid-col hidden xl:block"></div>
              <div class="feed-grid-col hidden 2xl:block"></div>
            </section>
            <div id="feed-sentinel" class="h-px" aria-hidden="true"></div>
HTML;
    }

    public function renderFeedEmptyState(): string
    {
        return <<<'HTML'
    <article
      class="feed-grid-item mb-3 inline-block w-full overflow-hidden rounded-3xl border border-gray-200 bg-white/95 px-6 py-8 text-center shadow-xl backdrop-blur-2xl sm:mb-4 lg:mb-5 xl:mb-6 2xl:mb-7"
      data-empty-state="true"
    >
      <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-xl text-gray-700">
        ⌕
      </div>
      <h2 class="mt-4 text-2xl font-semibold tracking-tight text-gray-900">
        还没有可展示的视频
      </h2>
      <p class="mt-3 text-sm leading-6 text-gray-500 sm:text-base">
        先在 <code>config/sources.json</code> 启用来源并运行抓取。首页布局已经准备好，
        一旦有数据就会按瀑布流方式展示出来。
      </p>
    </article>
HTML;
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    public function renderFeedItem(array $tweet): string
    {
        $displayText = $this->getDisplayText($tweet);
        $safeText = $this->escape($displayText);
        $authorName = isset($tweet['authorName']) && $tweet['authorName'] !== null
            ? (string) $tweet['authorName']
            : '@'.($tweet['authorHandle'] ?? 'unknown');
        $safeAuthor = $this->escape($authorName);
        $safeHandle = $this->escape((string) ($tweet['authorHandle'] ?? 'unknown'));
        $safePoster = $this->escape((string) ($tweet['posterUrl'] ?? ''));
        $safeHlsUrl = $this->escape((string) ($tweet['hlsUrl'] ?? ''));
        $safeVideoUrl = $this->escape((string) ($tweet['videoUrl'] ?? ''));
        $safeStatus = $this->escape((string) ($tweet['status'] ?? 'pending'));
        $authorInitial = $this->getAuthorInitial($authorName);
        $frameClass = $this->getMediaFrameClass($tweet);
        $durationText = $this->formatVideoDurationText((string) ($tweet['durationText'] ?? ''));
        $durationVisibility = $durationText === '' ? 'hidden ' : '';
        $safeDurationText = $this->escape($durationText);
        $videoPreload = ! empty($tweet['hlsUrl']) ? 'metadata' : 'none';
        $mediaMarkup = ($tweet['status'] ?? null) === 'resolved' && (! empty($tweet['hlsUrl']) || ! empty($tweet['videoUrl']))
            ? <<<HTML
                  <video
                    class="js-feed-player h-full w-full object-cover"
                    poster="{$safePoster}"
                    data-poster="{$safePoster}"
                    data-hls-url="{$safeHlsUrl}"
                    data-fallback-url="{$safeVideoUrl}"
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

        return <<<HTML
    <article
      class="feed-grid-item group mb-3 inline-block w-full cursor-pointer overflow-hidden rounded-3xl border border-gray-200 bg-white/95 shadow-sm backdrop-blur-xl transition duration-300 hover:-translate-y-1 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-rose-300 sm:mb-4 lg:mb-5 xl:mb-6 2xl:mb-7"
      data-tweet-id="{$this->escape((string) ($tweet['tweetId'] ?? ''))}"
      data-status="{$safeStatus}"
      data-feed-detail-trigger="true"
      role="button"
      tabindex="0"
      aria-haspopup="dialog"
      aria-label="打开 {$safeAuthor} 的视频详情"
    >
      <div class="relative {$frameClass} overflow-hidden bg-gray-100">
        {$mediaMarkup}
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-transparent via-black/5 to-black/10"></div>
        <span
          class="pointer-events-none absolute right-3 top-3 z-10 {$durationVisibility}rounded-full bg-black/15 px-2.5 py-1.5 text-sm font-semibold leading-none text-white backdrop-blur-sm"
          data-video-duration
        >{$safeDurationText}</span>
      </div>
      <div class="grid gap-3 px-4 pb-4 pt-3">
        <p class="line-clamp-2 overflow-hidden text-base font-semibold leading-6 text-gray-900">
          {$safeText}
        </p>
        <div class="flex items-center justify-between gap-3">
          <div class="flex min-w-0 items-center gap-3">
            {$this->renderAvatarMarkup(
                imageUrl: $tweet['authorAvatarUrl'] ?? null,
                label: $authorName,
                initial: $authorInitial,
                sizeClass: 'h-7 w-7'
            )}
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-gray-900">{$safeAuthor}</p>
            </div>
          </div>
          <div class="shrink-0 text-xs text-gray-500">
            <span>{$this->escape($this->formatFeedDate($tweet['postedAt'] ?? null))}</span>
          </div>
        </div>
      </div>
    </article>
HTML;
    }

    public function renderDetailModal(): string
    {
        return <<<'HTML'
    <div
      id="feed-detail-modal"
      class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/20 p-3 sm:p-5 xl:p-7"
      hidden
    >
      <section
        id="feed-detail-modal-panel"
        class="relative z-10 flex h-[92vh] max-h-[920px] w-full max-w-[1520px] overflow-hidden rounded-[32px] bg-white shadow-glass animate-card-in"
        role="dialog"
        aria-modal="true"
        aria-labelledby="detail-modal-title"
        tabindex="-1"
      >
      </section>
    </div>
HTML;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function serializeBootstrapData(array $payload): string
    {
        return str_replace(
            ['&', '<', '>', "\u{2028}", "\u{2029}"],
            ['\u0026', '\u003c', '\u003e', '\u2028', '\u2029'],
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'
        );
    }

    public function formatFeedSummary(string $sourceHandle, int $renderedCount, bool $done): string
    {
        $sourceLabel = $sourceHandle !== '' ? '@'.$sourceHandle : '全部来源';

        if ($renderedCount === 0 && $done) {
            return "{$sourceLabel} 暂无内容";
        }

        if ($renderedCount === 0) {
            return "{$sourceLabel} 正在加载探索内容…";
        }

        return $sourceLabel.' · 已展示 '.$renderedCount.' 条 · '.($done ? '已加载完毕' : '向下滚动继续加载');
    }

    private function formatFeedDate(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '未知时间';
        }

        try {
            $date = CarbonImmutable::parse($value, 'UTC');
        } catch (\Throwable) {
            return '未知时间';
        }

        $diffSeconds = CarbonImmutable::now('UTC')->diffInSeconds($date, false);
        if ($diffSeconds > 0 || abs($diffSeconds) < 60) {
            return '刚刚';
        }

        $diffSeconds = abs($diffSeconds);

        return match (true) {
            $diffSeconds < 3600 => floor($diffSeconds / 60).'分钟前',
            $diffSeconds < 86400 => floor($diffSeconds / 3600).'小时前',
            $diffSeconds < 604800 => floor($diffSeconds / 86400).'天前',
            $diffSeconds < 2592000 => floor($diffSeconds / 604800).'周前',
            $diffSeconds < 31536000 => floor($diffSeconds / 2592000).'个月前',
            default => floor($diffSeconds / 31536000).'年前',
        };
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    private function getDisplayText(array $tweet): string
    {
        $text = preg_replace('/https?:\/\/\S+/u', ' ', (string) ($tweet['text'] ?? '')) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        $text = trim($text);

        return $text !== '' ? $text : '未填写内容文案';
    }

    private function getAuthorInitial(?string $value): string
    {
        $normalized = ltrim(trim((string) ($value ?? '')), '@');

        return $normalized !== '' ? mb_strtoupper(mb_substr($normalized, 0, 1)) : 'L';
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    private function getMediaFrameClass(array $tweet): string
    {
        $width = (int) ($tweet['mediaWidth'] ?? 0);
        $height = (int) ($tweet['mediaHeight'] ?? 0);

        if ($width > 0 && $height > 0) {
            $ratio = $width / $height;

            return match (true) {
                $ratio >= 1.15 => 'aspect-[6/5]',
                $ratio >= 0.92 => 'aspect-square',
                $ratio >= 0.72 => 'aspect-[4/5]',
                default => 'aspect-[3/4]',
            };
        }

        return mb_strlen((string) ($tweet['text'] ?? '')) > 110 ? 'aspect-[3/4]' : 'aspect-[4/5]';
    }

    private function formatVideoDurationText(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $parts = array_map('trim', explode(':', $normalized));
        if (count($parts) < 2 || count($parts) > 3) {
            return $normalized;
        }

        foreach ($parts as $index => $part) {
            if (! ctype_digit($part)) {
                return $normalized;
            }

            $parts[$index] = $index === 0 ? (string) ((int) $part) : str_pad($part, 2, '0', STR_PAD_LEFT);
        }

        return implode(':', $parts);
    }

    private function renderAvatarMarkup(
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
