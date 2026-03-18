<?php

namespace App\ShortVideo\View;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Routing\UrlGenerator;

final class FeedViewDataFactory
{
    public function __construct(private readonly UrlGenerator $url) {}

    /**
     * @param  array<string, mixed>  $viewModel
     * @return array<string, mixed>
     */
    public function makeFeedPageData(array $viewModel, bool $feedScriptsEnabled): array
    {
        $feed = is_array($viewModel['feed'] ?? null) ? $viewModel['feed'] : null;

        if (! $feedScriptsEnabled || $feed === null) {
            return [
                'enabled' => false,
                'gridItems' => [],
                'gridIsEmpty' => true,
                'bootstrapJson' => null,
                'emptyState' => null,
                'detailModalEnabled' => false,
            ];
        }

        $resolvedEmptyState = $this->resolveFeedEmptyState(
            $feed,
            is_array($viewModel['emptyState'] ?? null) ? $viewModel['emptyState'] : []
        );

        return [
            'enabled' => true,
            'gridItems' => array_map(
                fn (array $item): array => $this->makeFeedItemData($item),
                is_array($feed['items'] ?? null) ? $feed['items'] : []
            ),
            'gridMaxColumns' => max(1, min(4, (int) ($feed['gridMaxColumns'] ?? 4))),
            'gridIsEmpty' => ! empty($feed['isEmpty']),
            'bootstrapJson' => $this->serializeBootstrapData([
                'items' => $feed['items'] ?? [],
                'nextCursor' => $feed['nextCursor'] ?? null,
                'source' => $feed['source'] ?? '',
                'limit' => $feed['limit'] ?? null,
                'mode' => $feed['mode'] ?? null,
                'query' => $feed['query'] ?? null,
            ]),
            'emptyState' => $resolvedEmptyState,
            'detailModalEnabled' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $tweet
     * @param  array<string, mixed>  $rootAttributes
     * @return array<string, mixed>
     */
    public function makeFeedItemData(
        array $tweet,
        bool $interactive = true,
        bool $useStaticPreview = false,
        ?string $postedAtText = null,
        ?string $frameClassOverride = null,
        int $titleLineClamp = 2,
        array $rootAttributes = [],
        string $cardClass = ''
    ): array {
        $authorName = isset($tweet['authorName']) && trim((string) ($tweet['authorName'] ?? '')) !== ''
            ? trim((string) $tweet['authorName'])
            : '@'.(string) ($tweet['authorHandle'] ?? 'unknown');
        $authorHandle = (string) ($tweet['authorHandle'] ?? 'unknown');
        $authorUsername = isset($tweet['authorUsername']) ? trim((string) $tweet['authorUsername']) : '';
        $authorInitial = $this->getAuthorInitial($authorName);
        $frameClass = $frameClassOverride ?? $this->getMediaFrameClass($tweet);
        $durationText = $this->formatVideoDurationText((string) ($tweet['durationText'] ?? ''));

        return [
            'videoId' => isset($tweet['videoId']) && is_numeric((string) ($tweet['videoId'] ?? null)) ? (int) $tweet['videoId'] : null,
            'tweetId' => (string) ($tweet['tweetId'] ?? ''),
            'status' => (string) ($tweet['status'] ?? 'pending'),
            'authorName' => $authorName,
            'displayText' => $this->getDisplayText($tweet),
            'detailUrl' => $this->resolveDetailUrl($tweet),
            'postedAtText' => $postedAtText ?? $this->formatFeedDate($tweet['postedAt'] ?? null),
            'titleLineClampClass' => $this->resolveTitleLineClampClass($titleLineClamp),
            'interactive' => $interactive,
            'viewedByViewer' => ($tweet['viewedByViewer'] ?? false) === true,
            'isNewForViewer' => ($tweet['isNewForViewer'] ?? false) === true,
            'rootAttributes' => $rootAttributes,
            'cardClass' => $cardClass,
            'media' => [
                'frameClass' => $frameClass,
                'posterUrl' => (string) ($tweet['posterUrl'] ?? ''),
                'hlsUrl' => $useStaticPreview ? '' : (string) ($tweet['hlsUrl'] ?? ''),
                'videoUrl' => $useStaticPreview ? '' : (string) ($tweet['videoUrl'] ?? ''),
                'authorHandle' => $authorHandle,
                'videoPreload' => ! $useStaticPreview && ! empty($tweet['hlsUrl']) ? 'metadata' : 'none',
                'showVideo' => ! $useStaticPreview && ($tweet['status'] ?? null) === 'resolved' && (! empty($tweet['hlsUrl']) || ! empty($tweet['videoUrl'])),
                'durationText' => $durationText,
            ],
            'author' => [
                'imageUrl' => $tweet['authorAvatarUrl'] ?? null,
                'authorName' => $authorName,
                'authorHandle' => $authorHandle,
                'authorInitial' => $authorInitial,
                'avatarSizeClass' => 'h-7 w-7',
                'nameClass' => 'truncate text-sm font-semibold text-gray-900',
                'handleClass' => null,
                'wrapperClass' => 'flex min-w-0 items-center gap-3',
                'fallbackClass' => 'bg-gray-100 text-gray-700',
                'imageClass' => '',
                'profileUrl' => $this->profileUrlForUsername($authorUsername),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $tweet
     * @return array<string, mixed>
     */
    public function makeInteractionItemData(array $tweet, string $actionUrl): array
    {
        $authorHandle = (string) ($tweet['authorHandle'] ?? 'unknown');
        $actionType = (string) ($tweet['interactionType'] ?? '');

        return [
            'itemAttributes' => [
                'data-interaction-item' => 'true',
                'data-interaction-type' => $actionType,
                'data-interaction-video-id' => isset($tweet['videoId']) ? (string) $tweet['videoId'] : '',
                'data-interaction-comment-id' => isset($tweet['commentId']) && is_numeric((string) $tweet['commentId'])
                    ? (string) ((int) $tweet['commentId'])
                    : '',
            ],
            'preview' => [
                'tweetId' => (string) ($tweet['tweetId'] ?? ''),
                'status' => (string) ($tweet['status'] ?? 'pending'),
                'authorName' => isset($tweet['authorName']) && trim((string) ($tweet['authorName'] ?? '')) !== ''
                    ? trim((string) $tweet['authorName'])
                    : '@'.$authorHandle,
                'displayText' => $this->getDisplayText($tweet),
                'detailUrl' => $this->resolveDetailUrl($tweet),
                'postedAtText' => $this->formatFeedDate($tweet['postedAt'] ?? null),
                'titleLineClampClass' => 'line-clamp-1',
                'interactive' => false,
                'rootAttributes' => [],
                'cardClass' => '',
                'media' => [
                    'frameClass' => 'aspect-video rounded-2xl',
                    'posterUrl' => (string) ($tweet['posterUrl'] ?? ''),
                    'hlsUrl' => '',
                    'videoUrl' => '',
                    'authorHandle' => $authorHandle,
                    'videoPreload' => 'none',
                    'showVideo' => false,
                    'durationText' => $this->formatVideoDurationText((string) ($tweet['durationText'] ?? '')),
                ],
                'author' => [
                    'imageUrl' => $tweet['authorAvatarUrl'] ?? null,
                    'authorName' => isset($tweet['authorName']) && trim((string) ($tweet['authorName'] ?? '')) !== ''
                        ? trim((string) $tweet['authorName'])
                        : '@'.$authorHandle,
                    'authorHandle' => $authorHandle,
                    'authorInitial' => $this->getAuthorInitial(isset($tweet['authorName']) ? (string) $tweet['authorName'] : $authorHandle),
                    'avatarSizeClass' => 'h-7 w-7',
                    'nameClass' => 'truncate text-sm font-semibold text-gray-900',
                    'handleClass' => null,
                    'wrapperClass' => 'flex min-w-0 items-center gap-3',
                    'fallbackClass' => 'bg-gray-100 text-gray-700',
                    'imageClass' => '',
                    'profileUrl' => $this->profileUrlForUsername(isset($tweet['authorUsername']) ? (string) $tweet['authorUsername'] : ''),
                ],
            ],
            'interactionLabel' => $this->resolveInteractionLabel($tweet),
            'interactionIconClass' => $actionType === 'comment' ? 'ph ph-chat-circle-dots' : 'ph ph-heart-straight',
            'interactionAtText' => $this->formatFeedDate($tweet['interactionAt'] ?? null),
            'videoTitle' => $this->getDisplayText($tweet),
            'authorName' => isset($tweet['authorName']) && trim((string) ($tweet['authorName'] ?? '')) !== ''
                ? trim((string) $tweet['authorName'])
                : '@'.$authorHandle,
            'authorHandle' => $authorHandle,
            'commentBody' => $actionType === 'comment'
                ? trim((string) ($tweet['commentBody'] ?? ''))
                : '',
            'actionLabel' => $actionType === 'comment' ? '删除评论' : '取消点赞',
            'actionUrl' => $actionUrl,
            'actionLoadingLabel' => $actionType === 'comment' ? '删除中...' : '取消中...',
        ];
    }

    /**
     * @param  array<string, mixed>  $feed
     * @param  array<string, mixed>  $emptyState
     * @return array<string, mixed>
     */
    public function resolveFeedEmptyState(array $feed, array $emptyState): array
    {
        $mode = (string) ($feed['mode'] ?? '');
        $source = trim((string) ($feed['source'] ?? ''));
        $query = trim((string) ($feed['query'] ?? ''));
        $title = trim((string) ($emptyState['title'] ?? '')) ?: '还没有可展示的视频';
        $description = trim((string) (($emptyState['description'] ?? null) ?? ($emptyState['body'] ?? '')))
            ?: '先在 <code>config/sources.json</code> 启用来源并运行抓取。首页布局已经准备好，一旦有数据就会按瀑布流方式展示出来。';
        $iconClass = trim((string) ($emptyState['iconClass'] ?? ''));
        $buttonLabel = trim((string) ($emptyState['buttonLabel'] ?? ''));
        $buttonHref = trim((string) ($emptyState['buttonHref'] ?? ''));
        $buttonAttributes = is_array($emptyState['buttonAttributes'] ?? null) ? $emptyState['buttonAttributes'] : [];

        if ($iconClass === '') {
            $iconClass = match (true) {
                $query !== '' => 'ph ph-magnifying-glass',
                $mode === 'following' => 'ph ph-bell-slash',
                $mode === 'history' => 'ph ph-clock-counter-clockwise',
                $mode === 'bookmarks' => 'ph ph-bookmark-simple',
                $mode === 'interactions' => 'ph ph-chat-circle-dots',
                $mode === 'featured' => 'ph ph-shooting-star',
                $source !== '' => 'ph ph-hash',
                default => 'ph ph-compass-tool',
            };
        }

        if ($buttonLabel === '' || $buttonHref === '') {
            [$defaultButtonLabel, $defaultButtonHref] = $this->defaultFeedEmptyStateAction($mode, $source, $query);
            $buttonLabel = $buttonLabel !== '' ? $buttonLabel : $defaultButtonLabel;
            $buttonHref = $buttonHref !== '' ? $buttonHref : $defaultButtonHref;
        }

        return [
            'title' => $title,
            'description' => $description,
            'iconClass' => $iconClass,
            'buttonLabel' => $buttonLabel,
            'buttonHref' => $buttonHref,
            'buttonAttributes' => $buttonAttributes,
        ];
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

    /**
     * @return array{0:string,1:string}
     */
    private function defaultFeedEmptyStateAction(string $mode, string $source, string $query): array
    {
        if ($query !== '') {
            $params = $source !== '' ? ['source' => $source] : [];

            return ['清除搜索', $this->url->route('explore', $params)];
        }

        return match (true) {
            $mode === 'featured' => ['去探索', $this->url->route('explore')],
            $mode === 'following' => ['去探索', $this->url->route('explore')],
            $mode === 'history' => ['去探索', $this->url->route('explore')],
            $mode === 'bookmarks' => ['去探索', $this->url->route('explore')],
            $mode === 'interactions' => ['去探索', $this->url->route('explore')],
            $source !== '' => ['查看全部来源', $this->url->route('explore')],
            default => ['查看精选', $this->url->route('home')],
        };
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    private function resolveDetailUrl(array $tweet): ?string
    {
        $detailUrl = trim((string) ($tweet['detailUrl'] ?? ''));
        if ($detailUrl !== '') {
            return $detailUrl;
        }

        $videoId = isset($tweet['videoId']) && is_numeric((string) ($tweet['videoId'] ?? null))
            ? (int) $tweet['videoId']
            : null;

        return $videoId !== null
            ? $this->url->route('videos.show', ['video' => $videoId], false)
            : null;
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
        $frameMode = (string) config('shortvideo.feed_media_frame_mode', 'adaptive');
        if ($frameMode === '16:9') {
            return 'aspect-video';
        }

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

    /**
     * @param  array<string, mixed>  $tweet
     */
    private function resolveInteractionLabel(array $tweet): string
    {
        if (($tweet['interactionType'] ?? null) !== 'comment') {
            return '点赞了这个视频';
        }

        $parentAuthorUsername = trim((string) ($tweet['parentAuthorUsername'] ?? ''));
        if ($parentAuthorUsername !== '') {
            return '回复 @'.$parentAuthorUsername;
        }

        return '评论了这个视频';
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

    private function resolveTitleLineClampClass(int $titleLineClamp): string
    {
        return match ($titleLineClamp) {
            1 => 'line-clamp-1',
            3 => 'line-clamp-3',
            4 => 'line-clamp-4',
            default => 'line-clamp-2',
        };
    }

    private function profileUrlForUsername(?string $username): ?string
    {
        $normalizedUsername = trim((string) ($username ?? ''));

        return $normalizedUsername !== ''
            ? $this->url->route('profile.show', ['username' => $normalizedUsername], false)
            : null;
    }
}
