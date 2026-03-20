<?php

namespace App\ShortVideo\Services;

use App\Models\User;
use App\Models\Video;
use App\Models\VideoComment;
use App\ShortVideo\Auth\CurrentViewerResolver;
use App\ShortVideo\Repositories\SocialGraphRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class VideoDetailPageService
{
    private const SITE_NAME = 'Lagos Explore Feed';

    public function __construct(
        private readonly CurrentViewerResolver $currentViewerResolver,
        private readonly UrlGenerator $url,
        private readonly SocialGraphRepository $socialGraphRepository
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getPageViewModel(Video $video): array
    {
        abort_if($video->status !== 'published' || $video->visibility !== 'public', 404);

        $viewer = $this->currentViewerResolver->resolve();
        $video->loadMissing([
            'uploader:id,username,name,avatar_url',
            'source:id,handle',
        ]);
        $video->loadCount([
            'likedByUsers as like_count',
            'bookmarkedByUsers as bookmark_count',
            'allComments as comment_count',
        ]);

        $tweet = $this->resolveTweetPayload($video);
        $title = $this->resolveTitle($video, $tweet);
        $description = $this->resolveDescription($video, $tweet, $title);
        $publishedAt = $this->resolvePublishedAt($video, $tweet);
        $durationSeconds = $this->resolveDurationSeconds($video);
        $durationText = $this->resolveDurationText($video, $durationSeconds);
        $authorName = $this->resolveAuthorName($video, $tweet);
        $authorHandle = $this->resolveAuthorHandle($video);
        $authorProfileUrl = $authorHandle !== ''
            ? $this->url->route('profile.show', ['username' => $authorHandle], false)
            : null;
        $authorAvatarUrl = $video->uploader?->avatar_url ?: ($tweet['author_avatar_url'] ?? null);
        $followState = $this->buildFollowState($video, $viewer, $authorName, $authorHandle, $authorAvatarUrl);
        $playbackUrl = $this->resolvePlaybackUrl($video);
        $hlsUrl = is_string($video->hls_url) && trim($video->hls_url) !== ''
            ? trim($video->hls_url)
            : null;
        $posterUrl = $this->resolvePosterUrl($video, $tweet);
        $sourceHandle = trim((string) ($video->source?->handle ?? ''));
        $canonicalUrl = $this->url->route('videos.show', ['video' => $video], true);
        $contentUrl = $this->absoluteUrl($playbackUrl);
        $metaDescription = $this->buildMetaDescription($description, $title);
        $comments = $this->resolveComments($video);
        $canComment = $viewer !== null && $viewer->can('comment', $video);
        $likedByViewer = $this->hasViewerReaction($video, $viewer, 'likedByUsers');
        $bookmarkedByViewer = $this->hasViewerReaction($video, $viewer, 'bookmarkedByUsers');
        $playerSources = array_values(array_filter([
            $playbackUrl !== null ? ['src' => $playbackUrl, 'type' => null] : null,
            $hlsUrl !== null && $hlsUrl !== $playbackUrl ? ['src' => $hlsUrl, 'type' => 'application/x-mpegURL'] : null,
        ]));

        return [
            'pageTitle' => $title.' · '.($authorName !== '' ? $authorName.' · ' : '').self::SITE_NAME,
            'headerViewer' => $this->mapViewerSummary($viewer),
            'searchQuery' => null,
            'video' => [
                'id' => $video->id,
                'title' => $title,
                'description' => $description,
                'metaDescription' => $metaDescription,
                'canonicalUrl' => $canonicalUrl,
                'posterUrl' => $posterUrl,
                'ogImageUrl' => $this->absoluteUrl($posterUrl),
                'playbackUrl' => $playbackUrl,
                'playerSources' => $playerSources,
                'contentUrl' => $contentUrl,
                'publishedAtIso' => $publishedAt->toIso8601String(),
                'publishedAtText' => $publishedAt->format('Y-m-d H:i'),
                'publishedAtDetailText' => $publishedAt->format('Y年n月j日'),
                'durationText' => $durationText,
                'durationIso' => $this->toIso8601Duration($durationSeconds),
                'authorName' => $authorName,
                'authorHandle' => $authorHandle,
                'authorInitial' => $this->resolveAuthorInitial($authorName !== '' ? $authorName : $authorHandle),
                'authorAvatarUrl' => $authorAvatarUrl,
                'authorProfileUrl' => $authorProfileUrl,
                'followState' => $followState,
                'sourceHandle' => $sourceHandle,
                'sourceUrl' => $sourceHandle !== ''
                    ? $this->url->route('explore', ['source' => $sourceHandle], false)
                    : null,
                'originalUrl' => $tweet['tweet_url'] ?? null,
                'comments' => $comments,
                'commentsStatusText' => (int) ($video->comment_count ?? 0) > 0 ? (int) ($video->comment_count ?? 0).' 条评论' : '暂无评论',
                'canComment' => $canComment,
                'commentComposerPlaceholder' => $canComment ? '说点什么...' : '登录后参与评论',
                'interactionHint' => $canComment
                    ? '详情页暂不支持直接互动，点赞、收藏和评论仍在弹窗中处理。'
                    : '登录后可以在弹窗里继续点赞、收藏和评论。',
                'engagement' => [
                    'likeCount' => (int) ($video->like_count ?? 0),
                    'bookmarkCount' => (int) ($video->bookmark_count ?? 0),
                    'commentCount' => (int) ($video->comment_count ?? 0),
                    'viewCount' => $this->resolveViewCount($video),
                    'likedByViewer' => $likedByViewer,
                    'bookmarkedByViewer' => $bookmarkedByViewer,
                ],
                'structuredData' => $this->buildStructuredData([
                    'title' => $title,
                    'description' => $metaDescription,
                    'canonicalUrl' => $canonicalUrl,
                    'contentUrl' => $contentUrl,
                    'thumbnailUrl' => $this->absoluteUrl($posterUrl),
                    'publishedAtIso' => $publishedAt->toIso8601String(),
                    'durationIso' => $this->toIso8601Duration($durationSeconds),
                    'authorName' => $authorName,
                    'authorProfileUrl' => $authorProfileUrl,
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTweetPayload(Video $video): array
    {
        $tweetId = trim((string) ($video->tweet_id ?? ''));

        if ($tweetId === '') {
            return [];
        }

        $tweet = DB::table('tweets')
            ->select([
                'text',
                'tweet_url',
                'author_name',
                'author_avatar_url',
                'posted_at',
                'poster_url',
            ])
            ->where('tweet_id', $tweetId)
            ->first();

        return $tweet ? (array) $tweet : [];
    }

    private function resolveTitle(Video $video, array $tweet): string
    {
        $candidates = [
            trim((string) ($video->title ?? '')),
            trim((string) ($video->caption ?? '')),
            $this->sanitizeText($tweet['text'] ?? null),
            trim((string) ($video->description ?? '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '未命名视频';
    }

    private function resolveDescription(Video $video, array $tweet, string $title): string
    {
        $candidates = [
            trim((string) ($video->caption ?? '')),
            trim((string) ($video->description ?? '')),
            $this->sanitizeText($tweet['text'] ?? null),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && $candidate !== $title) {
                return $candidate;
            }
        }

        return '';
    }

    private function resolveAuthorName(Video $video, array $tweet): string
    {
        $tweetAuthorName = trim((string) ($tweet['author_name'] ?? ''));
        if ($tweetAuthorName !== '') {
            return $tweetAuthorName;
        }

        $uploaderName = trim((string) ($video->uploader?->name ?? ''));
        if ($uploaderName !== '') {
            return $uploaderName;
        }

        $authorHandle = $this->resolveAuthorHandle($video);

        return $authorHandle !== '' ? '@'.$authorHandle : '视频作者';
    }

    private function resolveAuthorHandle(Video $video): string
    {
        $username = trim((string) ($video->uploader?->username ?? ''));
        if ($username !== '') {
            return $username;
        }

        return trim((string) ($video->source?->handle ?? ''));
    }

    private function resolvePosterUrl(Video $video, array $tweet): ?string
    {
        $posterUrl = trim((string) ($video->poster_url ?? ''));

        if ($posterUrl !== '') {
            return $posterUrl;
        }

        $tweetPosterUrl = trim((string) ($tweet['poster_url'] ?? ''));

        return $tweetPosterUrl !== '' ? $tweetPosterUrl : null;
    }

    private function resolvePlaybackUrl(Video $video): ?string
    {
        $tweetId = trim((string) ($video->tweet_id ?? ''));
        if ($tweetId !== '') {
            return '/api/media/'.$tweetId;
        }

        $playbackUrl = trim((string) ($video->playback_url ?? ''));
        if ($playbackUrl !== '') {
            return $playbackUrl;
        }

        $hlsUrl = trim((string) ($video->hls_url ?? ''));

        return $hlsUrl !== '' ? $hlsUrl : null;
    }

    private function resolvePublishedAt(Video $video, array $tweet): CarbonImmutable
    {
        if ($video->published_at !== null) {
            return CarbonImmutable::instance($video->published_at);
        }

        if ($video->created_at !== null) {
            return CarbonImmutable::instance($video->created_at);
        }

        $tweetPostedAt = trim((string) ($tweet['posted_at'] ?? ''));
        if ($tweetPostedAt !== '') {
            return CarbonImmutable::parse($tweetPostedAt);
        }

        return CarbonImmutable::now();
    }

    private function resolveDurationSeconds(Video $video): ?int
    {
        if (is_numeric((string) ($video->duration_seconds ?? null))) {
            $durationSeconds = (int) $video->duration_seconds;

            return $durationSeconds > 0 ? $durationSeconds : null;
        }

        return $this->durationTextToSeconds(trim((string) ($video->duration_text ?? '')));
    }

    private function resolveDurationText(Video $video, ?int $durationSeconds): string
    {
        $durationText = trim((string) ($video->duration_text ?? ''));
        if ($durationText !== '') {
            return $durationText;
        }

        return $durationSeconds !== null ? $this->formatDurationTextFromSeconds($durationSeconds) : '';
    }

    private function resolveViewCount(Video $video): int
    {
        return (int) DB::table('video_views')
            ->where('video_id', $video->id)
            ->count();
    }

    private function buildMetaDescription(string $description, string $title): string
    {
        $summary = $this->sanitizeText($description !== '' ? $description : $title);

        return Str::limit($summary, 160, '...');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildStructuredData(array $payload): array
    {
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => $payload['title'],
            'description' => $payload['description'],
            'url' => $payload['canonicalUrl'],
            'uploadDate' => $payload['publishedAtIso'],
        ];

        if (! empty($payload['thumbnailUrl'])) {
            $structuredData['thumbnailUrl'] = [$payload['thumbnailUrl']];
        }

        if (! empty($payload['contentUrl'])) {
            $structuredData['contentUrl'] = $payload['contentUrl'];
        }

        if (! empty($payload['durationIso'])) {
            $structuredData['duration'] = $payload['durationIso'];
        }

        if (! empty($payload['authorName'])) {
            $structuredData['author'] = array_filter([
                '@type' => 'Person',
                'name' => $payload['authorName'],
                'url' => $payload['authorProfileUrl'] ?? null,
            ]);
        }

        return $structuredData;
    }

    private function absoluteUrl(?string $path): ?string
    {
        $normalizedPath = trim((string) ($path ?? ''));

        if ($normalizedPath === '') {
            return null;
        }

        if (Str::startsWith($normalizedPath, ['http://', 'https://'])) {
            return $normalizedPath;
        }

        return $this->url->to($normalizedPath);
    }

    private function toIso8601Duration(?int $seconds): ?string
    {
        if ($seconds === null || $seconds <= 0) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;
        $duration = 'PT';

        if ($hours > 0) {
            $duration .= $hours.'H';
        }

        if ($minutes > 0) {
            $duration .= $minutes.'M';
        }

        if ($remainingSeconds > 0 || $duration === 'PT') {
            $duration .= $remainingSeconds.'S';
        }

        return $duration;
    }

    private function durationTextToSeconds(string $durationText): ?int
    {
        if ($durationText === '') {
            return null;
        }

        $segments = array_map(
            static fn (string $segment): int => (int) $segment,
            array_values(array_filter(explode(':', $durationText), static fn (string $segment): bool => preg_match('/^\d+$/', $segment) === 1))
        );

        if ($segments === [] || count($segments) !== count(explode(':', $durationText))) {
            return null;
        }

        if (count($segments) === 2) {
            [$minutes, $seconds] = $segments;

            return ($minutes * 60) + $seconds;
        }

        if (count($segments) === 3) {
            [$hours, $minutes, $seconds] = $segments;

            return ($hours * 3600) + ($minutes * 60) + $seconds;
        }

        return null;
    }

    private function formatDurationTextFromSeconds(int $durationSeconds): string
    {
        $hours = intdiv($durationSeconds, 3600);
        $minutes = intdiv($durationSeconds % 3600, 60);
        $seconds = $durationSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    private function sanitizeText(mixed $value): string
    {
        $text = trim(strip_tags((string) ($value ?? '')));
        $text = preg_replace('/https?:\/\/\S+/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    /**
     * @return array<int, array{
     *   id:int,
     *   body:string,
     *   createdAtText:string,
     *   author: array{name:string,username:string,avatarUrl:?string,initial:string}
     * }>
     */
    private function resolveComments(Video $video): array
    {
        return $video->comments()
            ->with('user:id,username,name,avatar_url')
            ->latest('created_at')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function (VideoComment $comment): array {
                $authorName = trim((string) ($comment->user?->name ?? '')) !== ''
                    ? trim((string) $comment->user?->name)
                    : trim((string) ($comment->user?->username ?? '匿名用户'));

                return [
                    'id' => (int) $comment->id,
                    'body' => trim((string) $comment->body),
                    'createdAtText' => $comment->created_at?->format('m-d H:i') ?? '刚刚',
                    'author' => [
                        'name' => $authorName !== '' ? $authorName : '匿名用户',
                        'username' => trim((string) ($comment->user?->username ?? '')),
                        'avatarUrl' => $comment->user?->avatar_url,
                        'initial' => $this->resolveAuthorInitial($authorName),
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *   creator: array{userId:int|null,name:string,username:string,avatarUrl:?string},
     *   viewerUserId:int|null,
     *   canFollowCreator:bool,
     *   followedByViewer:bool
     * }
     */
    private function buildFollowState(
        Video $video,
        ?User $viewer,
        string $authorName,
        string $authorHandle,
        ?string $authorAvatarUrl
    ): array {
        $authorUserId = $video->uploader?->id;
        $canFollowCreator = $viewer !== null && $authorUserId !== null && $viewer->id !== $authorUserId;
        $followedUserIds = $viewer !== null && $authorUserId !== null
            ? $this->socialGraphRepository->getFollowedUserIds($viewer->id, [$authorUserId])
            : [];

        return [
            'creator' => [
                'userId' => $authorUserId,
                'name' => $authorName,
                'username' => $video->uploader?->username ?? $authorHandle,
                'avatarUrl' => $authorAvatarUrl,
            ],
            'viewerUserId' => $viewer?->id,
            'canFollowCreator' => $canFollowCreator,
            'followedByViewer' => $canFollowCreator && in_array($authorUserId, $followedUserIds, true),
        ];
    }

    private function hasViewerReaction(Video $video, ?User $viewer, string $relation): bool
    {
        if (! $viewer) {
            return false;
        }

        return $video->{$relation}()->where('users.id', $viewer->id)->exists();
    }

    private function resolveAuthorInitial(string $value): string
    {
        $normalized = ltrim(trim($value), '@');

        return $normalized !== '' ? mb_strtoupper(mb_substr($normalized, 0, 1)) : 'L';
    }

    /**
     * @return array{id:int,name:string,username:string,avatarUrl:?string}|null
     */
    private function mapViewerSummary(?User $viewer): ?array
    {
        if (! $viewer) {
            return null;
        }

        return [
            'id' => $viewer->id,
            'name' => trim((string) ($viewer->name ?? '')) !== '' ? trim((string) $viewer->name) : $viewer->username,
            'username' => $viewer->username,
            'avatarUrl' => $viewer->avatar_url,
        ];
    }
}
