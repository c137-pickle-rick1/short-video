<?php

namespace App\ShortVideo\View;

use Carbon\CarbonImmutable;

final class HomePageRenderer
{
    public function __construct(
        private readonly FeedUiComponents $components,
        private readonly HomePageShellComponents $shellComponents
    ) {}

    public function renderDocumentHead(string $pageTitle): string
    {
        return <<<HTML
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{$this->escape(csrf_token())}" />
    <title>{$this->escape($pageTitle)}</title>
    <link rel="stylesheet" href="/vendor/fonts/fonts.css" />
    <link rel="stylesheet" href="/vendor/phosphor/regular/style.css" />
    <link rel="stylesheet" href="/vendor/phosphor/fill/style.css" />
    <link rel="stylesheet" href="/vendor/plyr/plyr.css" />
    <link rel="stylesheet" href="/styles.css" />
HTML;
    }

    /**
     * @param  array{id?:int,name?:string,username:string,avatarUrl?:string|null}|null  $viewer
     */
    public function renderPageHeader(string $loginUrl, ?array $viewer = null, ?string $logoutUrl = null): string
    {
        return $this->shellComponents->renderPageHeader($loginUrl, $viewer, $logoutUrl);
    }

    /**
     * @param  array{id?:int,name?:string,username:string,avatarUrl?:string|null}|null  $viewer
     */
    public function renderDesktopNavigation(string $activePage = 'explore', ?array $viewer = null): string
    {
        return $this->shellComponents->renderDesktopNavigation($this->navigationItems($activePage, $viewer));
    }

    /**
     * @param  array{id?:int,name?:string,username:string,avatarUrl?:string|null}|null  $viewer
     */
    public function renderMobileNavigation(string $activePage = 'explore', ?array $viewer = null): string
    {
        return $this->shellComponents->renderMobileNavigation($this->navigationItems($activePage, $viewer));
    }

    public function renderFeedToolbar(
        string $mode,
        string $activeSourceHandle,
        int $renderedCount,
        bool $done,
        bool $showSourceFilter = true
    ): string
    {
        $feedSummaryText = $this->formatFeedSummary($mode, $activeSourceHandle, $renderedCount, $done);
        $feedStatusText = $renderedCount === 0
            ? match ($mode) {
                'featured' => '等待精选内容进入列表',
                'following' => '等待订阅更新进入列表',
                default => '等待探索内容进入列表',
            }
            : ($done ? '当前结果已全部加载' : '向下滚动继续扩展列表');
        $selectedAll = $activeSourceHandle === '' ? 'selected' : '';
        $activeSourceOption = $activeSourceHandle !== ''
            ? <<<HTML
                  <option value="{$this->escape($activeSourceHandle)}" selected>
                    {$this->escape('@'.$activeSourceHandle)}
                  </option>
              HTML
            : '';
        $sourceFilterMarkup = $showSourceFilter
            ? <<<HTML
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
HTML
            : '';

        return <<<HTML
            <div class="mb-4 flex flex-col gap-3 rounded-[28px] border border-gray-200 bg-white/90 px-4 py-4 shadow-sm backdrop-blur-xl sm:px-5 lg:mb-5 lg:flex-row lg:items-center lg:justify-between lg:px-6">
              <div class="min-w-0">
                <p id="feed-summary" class="text-sm font-medium text-gray-700">{$this->escape($feedSummaryText)}</p>
                <p id="feed-status" class="mt-1 text-xs text-gray-500" aria-live="polite">{$this->escape($feedStatusText)}</p>
              </div>
              {$sourceFilterMarkup}
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
        $emptyState = is_array($viewModel['emptyState'] ?? null) ? $viewModel['emptyState'] : [];
        $itemsMarkup = $isEmpty
            ? $this->renderFeedEmptyState(
                (string) ($emptyState['title'] ?? '还没有可展示的视频'),
                (string) ($emptyState['body'] ?? '先在 <code>config/sources.json</code> 启用来源并运行抓取。首页布局已经准备好，一旦有数据就会按瀑布流方式展示出来。')
            )
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
            {$this->renderFeedLoadingIndicator()}
HTML;
    }

    public function renderFeedLoadingIndicator(): string
    {
        return <<<'HTML'
            <div
              id="feed-loading-indicator"
              class="hidden py-6 text-center"
              role="status"
              aria-live="polite"
              aria-hidden="true"
              hidden
            >
              <div class="inline-flex items-center gap-3 rounded-full border border-gray-200 bg-white/90 px-4 py-2 text-sm font-medium text-gray-500 shadow-sm backdrop-blur">
                <i class="ph ph-spinner-gap animate-spin text-base text-rose-500" aria-hidden="true"></i>
                <span>正在加载更多</span>
              </div>
            </div>
HTML;
    }

    public function renderFeedEmptyState(
        string $title = '还没有可展示的视频',
        string $body = '先在 <code>config/sources.json</code> 启用来源并运行抓取。首页布局已经准备好，一旦有数据就会按瀑布流方式展示出来。'
    ): string
    {
        return $this->components->renderEmptyStateCard(title: $title, body: $body);
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
        $authorHandle = (string) ($tweet['authorHandle'] ?? 'unknown');
        $safeAuthor = $this->escape($authorName);
        $safeStatus = $this->escape((string) ($tweet['status'] ?? 'pending'));
        $authorInitial = $this->getAuthorInitial($authorName);
        $frameClass = $this->getMediaFrameClass($tweet);
        $durationText = $this->formatVideoDurationText((string) ($tweet['durationText'] ?? ''));
        $mediaMarkup = $this->components->renderFeedMedia($tweet, $frameClass, $durationText, $authorHandle);
        $authorMarkup = $this->components->renderAuthorIdentity(
            imageUrl: $tweet['authorAvatarUrl'] ?? null,
            authorName: $authorName,
            authorHandle: $authorHandle,
            authorInitial: $authorInitial,
            avatarSizeClass: 'h-7 w-7',
            nameClass: 'truncate text-sm font-semibold text-gray-900'
        );

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
      {$mediaMarkup}
      <div class="grid gap-3 px-4 pb-4 pt-3">
        <p class="line-clamp-2 overflow-hidden text-base font-semibold leading-6 text-gray-900">
          {$safeText}
        </p>
        <div class="flex items-center justify-between gap-3">
          {$authorMarkup}
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

    public function formatFeedSummary(string $mode, string $sourceHandle, int $renderedCount, bool $done): string
    {
        $sourceLabel = match ($mode) {
            'featured' => '精选',
            'following' => '订阅更新',
            default => $sourceHandle !== '' ? '@'.$sourceHandle : '全部来源',
        };

        if ($renderedCount === 0 && $done) {
            return "{$sourceLabel} 暂无内容";
        }

        if ($renderedCount === 0) {
            return match ($mode) {
                'featured', 'following' => "{$sourceLabel} 正在加载…",
                default => "{$sourceLabel} 正在加载探索内容…",
            };
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

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param  array{id?:int,name?:string,username:string,avatarUrl?:string|null}|null  $viewer
     * @return array<int, array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null}>
     */
    private function navigationItems(string $activePage, ?array $viewer = null): array
    {
        $items = [
            [
                'icon' => $activePage === 'featured' ? 'ph-fill ph-sparkle' : 'ph ph-sparkle',
                'label' => '精选',
                'active' => $activePage === 'featured',
                'href' => route('home'),
            ],
            [
                'icon' => $activePage === 'explore' ? 'ph-fill ph-compass' : 'ph ph-compass',
                'label' => '探索',
                'active' => $activePage === 'explore',
                'href' => route('explore'),
            ],
            [
                'icon' => $activePage === 'rankings' ? 'ph-fill ph-chart-bar' : 'ph ph-chart-bar',
                'label' => '榜单',
                'active' => $activePage === 'rankings',
                'href' => route('rankings'),
            ],
            [
                'icon' => $activePage === 'subscriptions' ? 'ph-fill ph-bookmarks' : 'ph ph-bookmarks',
                'label' => '订阅',
                'active' => $activePage === 'subscriptions',
                'href' => route('subscriptions'),
            ],
        ];

        if ($viewer !== null && trim((string) ($viewer['username'] ?? '')) !== '') {
            $viewerName = trim((string) ($viewer['name'] ?? ''));
            $viewerUsername = trim((string) ($viewer['username'] ?? ''));
            $items[] = [
                'icon' => 'ph ph-user-circle',
                'label' => '我的',
                'active' => $activePage === 'profile',
                'href' => route('profile'),
                'avatarUrl' => isset($viewer['avatarUrl']) ? trim((string) $viewer['avatarUrl']) ?: null : null,
                'avatarInitial' => $this->getAuthorInitial($viewerName !== '' ? $viewerName : $viewerUsername),
            ];

            return $items;
        }

        $items[] = [
            'icon' => 'ph ph-sign-in',
            'label' => '登录',
            'active' => false,
            'href' => route('login'),
        ];

        return $items;
    }
}
