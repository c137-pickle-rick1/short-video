<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../../laravel/vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../laravel/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

final class StaticHtmlExporter
{
    private const APP_HOST = 'localhost';

    private const COMMENT_SAMPLE_VIDEO_ID = 273;

    /**
     * @var list<array{
     *   id: int,
     *   body: string,
     *   createdAtText: string,
     *   author: array{name: string, username: string},
     *   replies: list<array{
     *     id: int,
     *     body: string,
     *     createdAtText: string,
     *     author: array{name: string, username: string},
     *     replyToAuthor?: array{name: string, username: string}|null
     *   }>
     * }>
     */
    private const STATIC_COMMENT_THREADS = [
        [
            'id' => 9001,
            'body' => '这个转身和表情衔接得很顺，第一眼就会让人停下来。',
            'createdAtText' => '03-19 21:42',
            'author' => [
                'name' => '阿棠',
                'username' => 'atang101',
            ],
            'replies' => [
                [
                    'id' => 9101,
                    'body' => '我也是看到这里直接循环了三遍，节奏感很好。',
                    'createdAtText' => '03-19 21:47',
                    'author' => [
                        'name' => '小雨',
                        'username' => 'rainy_cc',
                    ],
                    'replyToAuthor' => [
                        'name' => '阿棠',
                        'username' => 'atang101',
                    ],
                ],
                [
                    'id' => 9102,
                    'body' => '而且最后那个眼神特别到位，像是故意留了个钩子。',
                    'createdAtText' => '03-19 21:49',
                    'author' => [
                        'name' => '阿棠',
                        'username' => 'atang101',
                    ],
                    'replyToAuthor' => [
                        'name' => '小雨',
                        'username' => 'rainy_cc',
                    ],
                ],
                [
                    'id' => 9103,
                    'body' => '卡点也很准，拿来做详情页和弹窗样本都挺合适。',
                    'createdAtText' => '03-19 21:53',
                    'author' => [
                        'name' => 'Lina',
                        'username' => 'lina_view',
                    ],
                    'replyToAuthor' => [
                        'name' => '阿棠',
                        'username' => 'atang101',
                    ],
                ],
            ],
        ],
        [
            'id' => 9002,
            'body' => '封面、标题和正文气质比较统一，看起来不像临时拼出来的。',
            'createdAtText' => '03-19 20:18',
            'author' => [
                'name' => '南南',
                'username' => 'nan_nan',
            ],
            'replies' => [],
        ],
        [
            'id' => 9003,
            'body' => '评论区终于不是空壳了，这样交付时更像真实产品。',
            'createdAtText' => '03-19 18:26',
            'author' => [
                'name' => '阿泽',
                'username' => 'aze_studio',
            ],
            'replies' => [
                [
                    'id' => 9104,
                    'body' => '对，PHP 同事拿去接模板时也更容易理解最终态。',
                    'createdAtText' => '03-19 18:31',
                    'author' => [
                        'name' => '小禾',
                        'username' => 'grainnote',
                    ],
                    'replyToAuthor' => [
                        'name' => '阿泽',
                        'username' => 'aze_studio',
                    ],
                ],
            ],
        ],
    ];

    /**
     * @var list<array{
     *   filename: string,
     *   uri: string,
     *   authUserId: int|null,
     *   context: string,
     *   refererUri?: string|null,
     *   variant?: string|null
     * }>
     */
    private const PAGES = [
        ['filename' => 'index.html', 'uri' => '/', 'authUserId' => null, 'context' => 'guest'],
        ['filename' => 'explore.html', 'uri' => '/explore', 'authUserId' => null, 'context' => 'guest'],
        ['filename' => 'rankings.html', 'uri' => '/rankings', 'authUserId' => null, 'context' => 'guest'],
        ['filename' => 'login.html', 'uri' => '/login', 'authUserId' => null, 'context' => 'guest', 'variant' => 'auth-login'],
        ['filename' => 'register.html', 'uri' => '/login', 'authUserId' => null, 'context' => 'guest', 'variant' => 'auth-register'],
        ['filename' => 'forgot-password.html', 'uri' => '/login', 'authUserId' => null, 'context' => 'guest', 'variant' => 'auth-password-reset'],
        ['filename' => 'subscriptions-guest.html', 'uri' => '/subscriptions', 'authUserId' => null, 'context' => 'guest-subscriptions'],
        ['filename' => 'subscriptions-empty-following.html', 'uri' => '/subscriptions', 'authUserId' => 20, 'context' => 'auth-empty-following'],
        ['filename' => 'subscriptions-selection.html', 'uri' => '/subscriptions', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'subscriptions-77sunnyx.html', 'uri' => '/subscriptions/77sunnyx', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'profile-public-77sunnyx.html', 'uri' => '/77sunnyx', 'authUserId' => null, 'context' => 'guest'],
        ['filename' => 'profile-public-77sunnyx-following-modal.html', 'uri' => '/77sunnyx', 'authUserId' => null, 'context' => 'guest', 'variant' => 'profile-social-following'],
        ['filename' => 'profile-public-77sunnyx-followers-modal.html', 'uri' => '/77sunnyx', 'authUserId' => null, 'context' => 'guest', 'variant' => 'profile-social-followers'],
        ['filename' => 'profile-own-overview.html', 'uri' => '/user_vbwy5lipgn22', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'profile-own-edit-modal.html', 'uri' => '/user_vbwy5lipgn22', 'authUserId' => 19, 'context' => 'auth-main', 'variant' => 'profile-edit'],
        ['filename' => 'profile-own-following-modal.html', 'uri' => '/user_vbwy5lipgn22', 'authUserId' => 19, 'context' => 'auth-main', 'variant' => 'profile-social-following'],
        ['filename' => 'profile-own-followers-modal.html', 'uri' => '/user_vbwy5lipgn22', 'authUserId' => 19, 'context' => 'auth-main', 'variant' => 'profile-social-followers'],
        ['filename' => 'profile-own-creator.html', 'uri' => '/user_vbwy5lipgn22?panel=creator', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'profile-own-upload-video-modal.html', 'uri' => '/user_vbwy5lipgn22?panel=creator', 'authUserId' => 19, 'context' => 'auth-main', 'variant' => 'profile-upload-video'],
        ['filename' => 'profile-own-history.html', 'uri' => '/user_vbwy5lipgn22?panel=history', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'profile-own-bookmarks.html', 'uri' => '/user_vbwy5lipgn22?panel=bookmarks', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'profile-own-interactions.html', 'uri' => '/user_vbwy5lipgn22?panel=interactions', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'viewer-history.html', 'uri' => '/me/history', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'viewer-bookmarks.html', 'uri' => '/me/bookmarks', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'viewer-interactions.html', 'uri' => '/me/interactions', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'video-273-comments.html', 'uri' => '/videos/273', 'authUserId' => null, 'context' => 'guest', 'refererUri' => '/', 'variant' => 'video-comments'],
        ['filename' => 'index-video-273-comments-modal.html', 'uri' => '/', 'authUserId' => null, 'context' => 'guest', 'variant' => 'feed-detail-modal-comments'],
        ['filename' => 'index-video-273-mobile.html', 'uri' => '/', 'authUserId' => null, 'context' => 'guest', 'variant' => 'feed-detail-modal-mobile'],
        ['filename' => 'index-video-273-mobile-comments.html', 'uri' => '/', 'authUserId' => null, 'context' => 'guest', 'variant' => 'feed-detail-modal-mobile-comments'],
        ['filename' => 'video-278.html', 'uri' => '/videos/278', 'authUserId' => null, 'context' => 'guest', 'refererUri' => '/'],
    ];

    private readonly HttpKernel $kernel;

    private readonly UrlGenerator $url;

    public function __construct(
        private readonly string $repoRoot,
        private readonly string $outputRoot,
        private readonly string $laravelRoot,
        private readonly Application $app,
    ) {
        $this->kernel = $this->app->make(HttpKernel::class);
        $this->url = $this->app->make(UrlGenerator::class);
        $this->url->forceRootUrl('http://'.self::APP_HOST);
    }

    public function run(): void
    {
        $this->prepareOutputTree();
        $this->compileStyles();
        $this->copyVendorAssets();
        $this->exportPages();
    }

    private function prepareOutputTree(): void
    {
        $this->ensureDirectory($this->assetRoot());
        $this->removeDirectory($this->assetRoot().'/vendor');
        $this->removeDirectory($this->assetRoot().'/avatars');

        foreach (self::PAGES as $page) {
            $file = $this->outputRoot.'/'.$page['filename'];
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function compileStyles(): void
    {
        $binary = $this->repoRoot.'/node_modules/.bin/tailwindcss';
        if (! is_file($binary)) {
            throw new RuntimeException('Tailwind CLI not found at '.$binary);
        }

        $command = sprintf(
            '%s -c %s -i %s -o %s --minify',
            escapeshellarg($binary),
            escapeshellarg($this->repoRoot.'/tailwind.config.cjs'),
            escapeshellarg($this->laravelRoot.'/resources/shortvideo/styles.css'),
            escapeshellarg($this->assetRoot().'/styles.css'),
        );

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $this->repoRoot);

        if (! is_resource($process)) {
            throw new RuntimeException('Failed to start Tailwind CLI.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException(trim($stderr) !== '' ? trim($stderr) : trim((string) $stdout));
        }
    }

    private function copyVendorAssets(): void
    {
        $publicVendorRoot = $this->laravelRoot.'/public/vendor';
        $assetVendorRoot = $this->assetRoot().'/vendor';

        $this->copyDirectory($publicVendorRoot.'/fonts', $assetVendorRoot.'/fonts');
        $this->copyDirectory($publicVendorRoot.'/phosphor', $assetVendorRoot.'/phosphor');
        $this->copyDirectory($publicVendorRoot.'/plyr', $assetVendorRoot.'/plyr');
    }

    private function exportPages(): void
    {
        foreach (self::PAGES as $page) {
            $html = $this->renderPage(
                uri: $page['uri'],
                authUserId: $page['authUserId'],
                refererUri: $page['refererUri'] ?? null,
            );
            $processedHtml = $this->postProcessHtml($html, $page);

            file_put_contents($this->outputRoot.'/'.$page['filename'], $processedHtml);

            fwrite(STDOUT, sprintf("Exported %s\n", $page['filename']));
        }
    }

    private function renderPage(string $uri, ?int $authUserId, ?string $refererUri = null): string
    {
        $this->app['auth']->forgetGuards();

        if ($authUserId !== null) {
            Auth::onceUsingId($authUserId);
        }

        $request = Request::create($uri, 'GET', server: [
            'HTTP_HOST' => self::APP_HOST,
            'REQUEST_SCHEME' => 'http',
            'SERVER_PORT' => 80,
            'HTTP_REFERER' => $refererUri !== null ? 'http://'.self::APP_HOST.$refererUri : null,
        ]);

        $response = $this->kernel->handle($request);
        $statusCode = $response->getStatusCode();
        $content = (string) $response->getContent();
        $this->kernel->terminate($request, $response);

        if ($statusCode !== 200) {
            throw new RuntimeException(sprintf('Failed to render %s, received %d.', $uri, $statusCode));
        }

        return $content;
    }

    /**
     * @param  array{filename: string, uri: string, authUserId: int|null, context: string, refererUri?: string|null, variant?: string|null}  $page
     */
    private function postProcessHtml(string $html, array $page): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();

        foreach ($dom->childNodes as $childNode) {
            if ($childNode->nodeType === XML_PI_NODE) {
                $dom->removeChild($childNode);
                break;
            }
        }

        $dom->encoding = 'UTF-8';
        $xpath = new DOMXPath($dom);
        $feedBootstrap = $this->extractFeedBootstrapData($xpath);

        $this->removeNodes($xpath, '//script');
        $this->removeNodes($xpath, '//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = "csrf-token"]');
        $this->removeNodes($xpath, '//link[contains(@href, "/build/assets/")]');
        $this->removeNodes($xpath, '//link[contains(@href, "://127.0.0.1:5173/") or contains(@href, "://localhost:5173/")]');
        $this->removeNodes($xpath, '//link[@rel = "modulepreload"]');

        foreach ($xpath->query('//form') as $formNode) {
            if (! $formNode instanceof DOMElement) {
                continue;
            }

            $replacement = $dom->createElement('div');

            foreach (iterator_to_array($formNode->attributes) as $attribute) {
                if (! $attribute instanceof DOMAttr) {
                    continue;
                }

                if (in_array($attribute->name, ['action', 'method'], true)) {
                    continue;
                }

                $replacement->setAttribute($attribute->name, $attribute->value);
            }

            $replacement->setAttribute('data-static-form', 'true');

            if (! $replacement->hasAttribute('role')) {
                $replacement->setAttribute('role', 'form');
            }

            while ($formNode->firstChild !== null) {
                $replacement->appendChild($formNode->firstChild);
            }

            $formNode->parentNode?->replaceChild($replacement, $formNode);
        }

        $this->applyPageVariantState($dom, $xpath, $page, $feedBootstrap);
        $this->rewriteStaticTriggers($dom, $xpath, $page);

        foreach ($xpath->query('//*[@href]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $href = trim((string) $node->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            $rewrittenHref = $this->rewriteUrl($href, $page);
            if ($rewrittenHref !== null) {
                $node->setAttribute('href', $rewrittenHref);
            }
        }

        foreach ($xpath->query('//*[@src]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $src = trim((string) $node->getAttribute('src'));
            if ($src === '') {
                continue;
            }

            $rewrittenSrc = $this->rewriteUrl($src, $page);
            if ($rewrittenSrc !== null) {
                $node->setAttribute('src', $rewrittenSrc);
            }
        }

        foreach ($xpath->query('//*[@poster]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $poster = trim((string) $node->getAttribute('poster'));
            if ($poster === '') {
                continue;
            }

            $rewrittenPoster = $this->rewriteUrl($poster, $page);
            if ($rewrittenPoster !== null) {
                $node->setAttribute('poster', $rewrittenPoster);
            }
        }

        foreach ($xpath->query('//head') as $headNode) {
            if (! $headNode instanceof DOMElement) {
                continue;
            }

            $stylesheet = $dom->createElement('link');
            $stylesheet->setAttribute('rel', 'stylesheet');
            $stylesheet->setAttribute('href', 'assets/styles.css');
            $headNode->appendChild($stylesheet);
        }

        $output = $dom->saveHTML();
        if ($output === false) {
            throw new RuntimeException('Failed to serialise processed HTML.');
        }

        return preg_replace('/^<!DOCTYPE.+?>/i', '<!doctype html>', $output) ?? $output;
    }

    /**
     * @param  array{filename: string, uri: string, authUserId: int|null, context: string, refererUri?: string|null, variant?: string|null}  $page
     */
    private function rewriteUrl(string $url, array $page): ?string
    {
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, 'data:') || str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:')) {
            return $url;
        }

        $parsedUrl = parse_url($url);
        if ($parsedUrl === false) {
            return $url;
        }

        $scheme = strtolower((string) ($parsedUrl['scheme'] ?? ''));
        $host = strtolower((string) ($parsedUrl['host'] ?? ''));
        $path = (string) ($parsedUrl['path'] ?? '');

        if ($scheme !== '' && ! in_array($scheme, ['http', 'https'], true)) {
            return $url;
        }

        if ($host === self::APP_HOST && $path === '') {
            $path = '/';
        }

        $isLocalAppUrl = ($scheme === '' && str_starts_with($path, '/'))
            || ($host === self::APP_HOST && ($path === '/' || str_starts_with($path, '/')));

        if (! $isLocalAppUrl) {
            return $url;
        }

        if (str_starts_with($path, '/vendor/')) {
            return 'assets'.$path;
        }

        if (str_starts_with($path, '/avatars/')) {
            return $this->copyManagedAvatar($path);
        }

        if (str_starts_with($path, '/storage/avatars/')) {
            $avatarPath = '/'.ltrim((string) preg_replace('#^/storage/#', '/', $path), '/');

            return $this->copyManagedAvatar($avatarPath);
        }

        if (str_starts_with($path, '/build/assets/')) {
            return null;
        }

        return $this->resolveInternalPageUrl($path, (string) ($parsedUrl['query'] ?? ''), $page);
    }

    private function copyManagedAvatar(string $path): string
    {
        $normalizedPath = ltrim($path, '/');
        $storageRelativePath = str_starts_with($normalizedPath, 'avatars/')
            ? $normalizedPath
            : ltrim((string) preg_replace('#^storage/#', '', $normalizedPath), '/');

        $sourcePath = Storage::disk('public')->path($storageRelativePath);

        if (! is_file($sourcePath)) {
            return '#';
        }

        $destinationPath = $this->assetRoot().'/'.$storageRelativePath;
        $this->ensureDirectory(dirname($destinationPath));
        copy($sourcePath, $destinationPath);

        return 'assets/'.$storageRelativePath;
    }

    /**
     * @param  array{filename: string, uri: string, authUserId: int|null, context: string, refererUri?: string|null, variant?: string|null}  $page
     */
    private function resolveInternalPageUrl(string $path, string $queryString, array $page): string
    {
        $normalizedPath = $this->normalizePath($path);
        parse_str($queryString, $query);

        $exactMappings = [
            '/' => 'index.html',
            '/explore' => 'explore.html',
            '/rankings' => 'rankings.html',
            '/login' => 'login.html',
            '/77sunnyx' => 'profile-public-77sunnyx.html',
            '/videos/273' => 'video-273-comments.html',
            '/videos/278' => 'video-278.html',
            '/subscriptions/77sunnyx' => 'subscriptions-77sunnyx.html',
            '/me/history' => 'viewer-history.html',
            '/me/bookmarks' => 'viewer-bookmarks.html',
            '/me/interactions' => 'viewer-interactions.html',
        ];

        if (isset($exactMappings[$normalizedPath])) {
            return $exactMappings[$normalizedPath];
        }

        if ($normalizedPath === '/subscriptions') {
            return match ($page['context']) {
                'auth-main' => 'subscriptions-selection.html',
                'auth-empty-following' => 'subscriptions-empty-following.html',
                default => 'subscriptions-guest.html',
            };
        }

        if ($normalizedPath === '/me') {
            return 'profile-own-overview.html';
        }

        if ($normalizedPath === '/user_vbwy5lipgn22') {
            $panel = trim((string) ($query['panel'] ?? ''));
            $tab = trim((string) ($query['tab'] ?? ''));

            return match (true) {
                $panel === 'creator' || $tab !== '' => 'profile-own-creator.html',
                $panel === 'history' => 'profile-own-history.html',
                $panel === 'bookmarks' => 'profile-own-bookmarks.html',
                $panel === 'interactions' => 'profile-own-interactions.html',
                default => 'profile-own-overview.html',
            };
        }

        return '#';
    }

    /**
     * @param  array{filename: string, uri: string, authUserId: int|null, context: string, refererUri?: string|null, variant?: string|null}  $page
     */
    private function rewriteStaticTriggers(DOMDocument $dom, DOMXPath $xpath, array $page): void
    {
        foreach ($this->elementsFromQuery($xpath, '//*[@data-auth-panel-switch]') as $node) {
            $panel = trim((string) $node->getAttribute('data-auth-panel-switch'));
            $target = $this->authPanelFilename($panel);

            if ($target === null) {
                continue;
            }

            $this->replaceElementWithAnchor($dom, $node, $target);
        }

        foreach ($this->elementsFromQuery($xpath, '//*[@data-auth-modal-close]') as $node) {
            $this->replaceElementWithAnchor($dom, $node, 'index.html');
        }

        foreach ($this->elementsFromQuery($xpath, '//*[@data-profile-editor-trigger]') as $node) {
            $this->replaceElementWithAnchor($dom, $node, 'profile-own-edit-modal.html');
        }

        foreach ($this->elementsFromQuery($xpath, '//*[@data-profile-editor-close]') as $node) {
            $this->replaceElementWithAnchor($dom, $node, 'profile-own-overview.html');
        }

        foreach ($this->elementsFromQuery($xpath, '//*[@data-profile-video-upload-trigger]') as $node) {
            $this->replaceElementWithAnchor($dom, $node, 'profile-own-upload-video-modal.html');
        }

        foreach ($this->elementsFromQuery($xpath, '//*[@data-profile-video-upload-close]') as $node) {
            $this->replaceElementWithAnchor($dom, $node, 'profile-own-creator.html');
        }

        foreach ($this->elementsFromQuery($xpath, '//*[@data-profile-social-trigger]') as $node) {
            $tab = trim((string) $node->getAttribute('data-profile-social-trigger'));
            $target = $this->socialModalFilename($page, $tab);

            if ($target === null) {
                continue;
            }

            $this->replaceElementWithAnchor($dom, $node, $target);
        }

        foreach ($this->elementsFromQuery($xpath, '//*[@data-profile-social-close]') as $node) {
            $this->replaceElementWithAnchor($dom, $node, $this->profileBaseFilename($page));
        }

        foreach ($this->elementsFromQuery($xpath, '//*[@data-profile-social-tab-button]') as $node) {
            $tab = trim((string) $node->getAttribute('data-profile-social-tab-button'));
            $target = $this->socialModalFilename($page, $tab);

            if ($target === null) {
                continue;
            }

            $this->replaceElementWithAnchor($dom, $node, $target);
        }

        foreach ($this->elementsFromQuery($xpath, '//*[@data-mobile-detail-close="true"]') as $node) {
            $this->replaceElementWithAnchor($dom, $node, 'index.html');
        }

        $variant = trim((string) ($page['variant'] ?? ''));

        if ($variant === 'feed-detail-modal-mobile') {
            foreach ($this->elementsFromQuery($xpath, '//*[@data-detail-comments-open="true"]') as $node) {
                $this->replaceElementWithAnchor($dom, $node, 'index-video-273-mobile-comments.html');
            }
        }

        if ($variant === 'feed-detail-modal-mobile-comments') {
            foreach ($this->elementsFromQuery($xpath, '//*[@data-mobile-comments-close="true"]') as $node) {
                $this->replaceElementWithAnchor($dom, $node, 'index-video-273-mobile.html');
            }

            foreach ($this->elementsFromQuery($xpath, '//*[@data-mobile-comments-backdrop="true"]') as $node) {
                $this->replaceElementWithAnchor($dom, $node, 'index-video-273-mobile.html');
            }
        }
    }

    /**
     * @param  array{filename: string, uri: string, authUserId: int|null, context: string, refererUri?: string|null, variant?: string|null}  $page
     */
    private function applyPageVariantState(DOMDocument $dom, DOMXPath $xpath, array $page, array $feedBootstrap): void
    {
        $variant = trim((string) ($page['variant'] ?? ''));

        match ($variant) {
            'auth-login' => $this->setAuthPanelState($xpath, 'login'),
            'auth-register' => $this->setAuthPanelState($xpath, 'register'),
            'auth-password-reset' => $this->setAuthPanelState($xpath, 'password_reset'),
            'profile-edit' => $this->openModal($xpath, '//*[@data-profile-editor="true"]'),
            'profile-upload-video' => $this->openModal($xpath, '//*[@data-profile-video-upload="true"]'),
            'profile-social-following' => $this->setSocialModalState($xpath, 'following'),
            'profile-social-followers' => $this->setSocialModalState($xpath, 'followers'),
            'video-comments' => $this->applyStaticVideoComments($dom, $xpath),
            'feed-detail-modal-comments' => $this->applyStaticFeedDetailModal($dom, $xpath, $feedBootstrap),
            'feed-detail-modal-mobile' => $this->applyStaticMobileFeedDetailModal($dom, $xpath, $feedBootstrap, false),
            'feed-detail-modal-mobile-comments' => $this->applyStaticMobileFeedDetailModal($dom, $xpath, $feedBootstrap, true),
            default => null,
        };
    }

    private function setAuthPanelState(DOMXPath $xpath, string $panel): void
    {
        $modal = $this->firstElementFromQuery($xpath, '//*[@data-auth-modal="true"]');

        if ($modal !== null) {
            $modal->setAttribute('data-auth-default-panel', $panel);
            $modal->setAttribute('data-auth-modal-start-open', 'true');
            $this->setElementHidden($modal, false);
        }

        foreach ($this->elementsFromQuery($xpath, '//*[@data-auth-panel]') as $panelNode) {
            $isActive = trim((string) $panelNode->getAttribute('data-auth-panel')) === $panel;
            $this->setElementHidden($panelNode, ! $isActive);
        }

        foreach ($this->elementsFromQuery($xpath, '//*[@data-auth-tab="true"]') as $tabNode) {
            $isActive = trim((string) $tabNode->getAttribute('data-auth-panel-switch')) === $panel;
            $tabNode->setAttribute('aria-pressed', $isActive ? 'true' : 'false');

            if ($isActive) {
                $this->removeClassTokens($tabNode, ['text-gray-500', 'hover:text-gray-900']);
                $this->addClassTokens($tabNode, ['bg-white', 'text-gray-950', 'shadow-sm']);

                continue;
            }

            $this->removeClassTokens($tabNode, ['bg-white', 'text-gray-950', 'shadow-sm']);
            $this->addClassTokens($tabNode, ['text-gray-500', 'hover:text-gray-900']);
        }
    }

    private function setSocialModalState(DOMXPath $xpath, string $tab): void
    {
        $this->openModal($xpath, '//*[@data-profile-social-modal="true"]');

        foreach ($this->elementsFromQuery($xpath, '//*[@data-profile-social-tab-panel]') as $panelNode) {
            $isActive = trim((string) $panelNode->getAttribute('data-profile-social-tab-panel')) === $tab;
            $panelNode->setAttribute('data-active', $isActive ? 'true' : 'false');
            $this->setElementHidden($panelNode, ! $isActive);
        }

        foreach ($this->elementsFromQuery($xpath, '//*[@data-profile-social-tab-button]') as $buttonNode) {
            $isActive = trim((string) $buttonNode->getAttribute('data-profile-social-tab-button')) === $tab;
            $buttonNode->setAttribute('data-active', $isActive ? 'true' : 'false');

            if ($isActive) {
                $this->removeClassTokens($buttonNode, ['text-gray-400', 'hover:text-gray-700']);
                $this->addClassTokens($buttonNode, ['text-gray-950']);
            } else {
                $this->removeClassTokens($buttonNode, ['text-gray-950']);
                $this->addClassTokens($buttonNode, ['text-gray-400', 'hover:text-gray-700']);
            }

            $indicator = $this->firstElementFromQuery($xpath, './span[@aria-hidden="true"]', $buttonNode);

            if ($indicator !== null) {
                $this->setElementHidden($indicator, ! $isActive);
            }
        }
    }

    private function openModal(DOMXPath $xpath, string $expression): void
    {
        $modal = $this->firstElementFromQuery($xpath, $expression);

        if ($modal === null) {
            return;
        }

        $this->setElementHidden($modal, false);
    }

    /**
     * @return list<DOMElement>
     */
    private function elementsFromQuery(DOMXPath $xpath, string $expression, ?DOMNode $contextNode = null): array
    {
        $query = $xpath->query($expression, $contextNode);

        if ($query === false) {
            return [];
        }

        $elements = [];

        foreach ($query as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $elements[] = $node;
        }

        return $elements;
    }

    private function firstElementFromQuery(DOMXPath $xpath, string $expression, ?DOMNode $contextNode = null): ?DOMElement
    {
        $elements = $this->elementsFromQuery($xpath, $expression, $contextNode);

        return $elements[0] ?? null;
    }

    private function replaceElementWithAnchor(DOMDocument $dom, DOMElement $node, string $href): DOMElement
    {
        if (strtolower($node->tagName) === 'a') {
            $node->setAttribute('href', $href);

            return $node;
        }

        $anchor = $dom->createElement('a');

        foreach (iterator_to_array($node->attributes) as $attribute) {
            if (! $attribute instanceof DOMAttr) {
                continue;
            }

            if (in_array($attribute->name, ['type', 'disabled'], true)) {
                continue;
            }

            $anchor->setAttribute($attribute->name, $attribute->value);
        }

        $anchor->setAttribute('href', $href);

        while ($node->firstChild !== null) {
            $anchor->appendChild($node->firstChild);
        }

        $node->parentNode?->replaceChild($anchor, $node);

        return $anchor;
    }

    private function setElementHidden(DOMElement $node, bool $hidden): void
    {
        if ($hidden) {
            $node->setAttribute('hidden', 'hidden');
            $this->addClassTokens($node, ['hidden']);

            return;
        }

        $node->removeAttribute('hidden');
        $this->removeClassTokens($node, ['hidden']);
    }

    /**
     * @param  list<string>  $tokens
     */
    private function addClassTokens(DOMElement $node, array $tokens): void
    {
        $classTokens = $this->classTokens($node);

        foreach ($tokens as $token) {
            if ($token === '' || in_array($token, $classTokens, true)) {
                continue;
            }

            $classTokens[] = $token;
        }

        $this->setClassTokens($node, $classTokens);
    }

    /**
     * @param  list<string>  $tokens
     */
    private function removeClassTokens(DOMElement $node, array $tokens): void
    {
        $classTokens = array_values(array_filter(
            $this->classTokens($node),
            static fn (string $classToken): bool => ! in_array($classToken, $tokens, true),
        ));

        $this->setClassTokens($node, $classTokens);
    }

    /**
     * @return list<string>
     */
    private function classTokens(DOMElement $node): array
    {
        $className = trim((string) $node->getAttribute('class'));

        if ($className === '') {
            return [];
        }

        return preg_split('/\s+/', $className) ?: [];
    }

    /**
     * @param  list<string>  $tokens
     */
    private function setClassTokens(DOMElement $node, array $tokens): void
    {
        if ($tokens === []) {
            $node->removeAttribute('class');

            return;
        }

        $node->setAttribute('class', implode(' ', $tokens));
    }

    private function authPanelFilename(string $panel): ?string
    {
        return match ($panel) {
            'login' => 'login.html',
            'register' => 'register.html',
            'password_reset' => 'forgot-password.html',
            default => null,
        };
    }

    /**
     * @param  array{filename: string, uri: string, authUserId: int|null, context: string, refererUri?: string|null, variant?: string|null}  $page
     */
    private function socialModalFilename(array $page, string $tab): ?string
    {
        $profileKey = $this->profileKey($page);

        return match ($profileKey) {
            'public-77sunnyx' => match ($tab) {
                'following' => 'profile-public-77sunnyx-following-modal.html',
                'followers' => 'profile-public-77sunnyx-followers-modal.html',
                default => null,
            },
            'own' => match ($tab) {
                'following' => 'profile-own-following-modal.html',
                'followers' => 'profile-own-followers-modal.html',
                default => null,
            },
            default => null,
        };
    }

    /**
     * @param  array{filename: string, uri: string, authUserId: int|null, context: string, refererUri?: string|null, variant?: string|null}  $page
     */
    private function profileBaseFilename(array $page): string
    {
        return match ($this->profileKey($page)) {
            'public-77sunnyx' => 'profile-public-77sunnyx.html',
            default => 'profile-own-overview.html',
        };
    }

    /**
     * @param  array{filename: string, uri: string, authUserId: int|null, context: string, refererUri?: string|null, variant?: string|null}  $page
     */
    private function profileKey(array $page): string
    {
        $path = parse_url($page['uri'], PHP_URL_PATH);
        $normalizedPath = $this->normalizePath(is_string($path) ? $path : '/');

        return match ($normalizedPath) {
            '/77sunnyx' => 'public-77sunnyx',
            '/user_vbwy5lipgn22' => 'own',
            default => 'unknown',
        };
    }

    private function applyStaticVideoComments(DOMDocument $dom, DOMXPath $xpath): void
    {
        $commentCount = $this->staticCommentCount(self::STATIC_COMMENT_THREADS);
        $commentMarkup = $this->renderStaticCommentThreadsMarkup(self::STATIC_COMMENT_THREADS);

        $commentContainer = $this->firstElementFromQuery(
            $xpath,
            '//*[@data-video-detail-page="true"]//section[@aria-labelledby="detail-comments-title"]/div[last()]'
        );

        if ($commentContainer !== null) {
            $this->replaceElementChildrenWithHtml($commentContainer, $commentMarkup);
        }

        $statusNode = $this->firstElementFromQuery(
            $xpath,
            '//*[@data-video-detail-page="true"]//section[@aria-labelledby="detail-comments-title"]//div[1]/span'
        );

        if ($statusNode !== null) {
            $statusNode->textContent = $commentCount.' 条评论';
        }

        $metaCommentBadge = $this->firstElementFromQuery(
            $xpath,
            '(//*[@data-video-detail-meta="true"]/span)[last()]'
        );

        if ($metaCommentBadge !== null) {
            $metaCommentBadge->textContent = $commentCount.' 条评论';
        }
    }

    private function applyStaticFeedDetailModal(DOMDocument $dom, DOMXPath $xpath, array $feedBootstrap): void
    {
        $modal = $this->firstElementFromQuery($xpath, '//*[@id="feed-detail-modal"]');
        $panel = $this->firstElementFromQuery($xpath, '//*[@id="feed-detail-modal-panel"]');

        if ($modal === null || $panel === null) {
            throw new RuntimeException('Feed detail modal shell not found in exported page.');
        }

        $tweet = $this->resolveFeedBootstrapTweet($feedBootstrap, self::COMMENT_SAMPLE_VIDEO_ID);
        if ($tweet === null) {
            throw new RuntimeException('Video 273 not found in feed bootstrap payload.');
        }

        $this->setElementHidden($modal, false);
        $this->replaceElementChildrenWithHtml($panel, $this->renderStaticFeedDetailModalMarkup($tweet));
    }

    private function applyStaticMobileFeedDetailModal(
        DOMDocument $dom,
        DOMXPath $xpath,
        array $feedBootstrap,
        bool $withCommentsDrawer,
    ): void {
        $modal = $this->firstElementFromQuery($xpath, '//*[@id="feed-detail-modal"]');
        $panel = $this->firstElementFromQuery($xpath, '//*[@id="feed-detail-modal-panel"]');

        if ($modal === null || $panel === null) {
            throw new RuntimeException('Feed detail modal shell not found in exported page.');
        }

        $tweet = $this->resolveFeedBootstrapTweet($feedBootstrap, self::COMMENT_SAMPLE_VIDEO_ID);
        if ($tweet === null) {
            throw new RuntimeException('Video 273 not found in feed bootstrap payload.');
        }

        $this->setElementHidden($modal, false);
        $panel->removeAttribute('aria-labelledby');
        $panel->setAttribute('aria-label', '移动端短视频详情');
        $this->replaceElementChildrenWithHtml($panel, $this->renderStaticMobileFeedDetailMarkup($tweet, $withCommentsDrawer));
    }

    /**
     * @return array<string, mixed>
     */
    private function extractFeedBootstrapData(DOMXPath $xpath): array
    {
        $scriptNode = $this->firstElementFromQuery($xpath, '//script[@id="feed-bootstrap"]');

        if ($scriptNode === null) {
            return [];
        }

        $payload = json_decode(trim($scriptNode->textContent), true);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $feedBootstrap
     * @return array<string, mixed>|null
     */
    private function resolveFeedBootstrapTweet(array $feedBootstrap, int $videoId): ?array
    {
        $items = is_array($feedBootstrap['items'] ?? null) ? $feedBootstrap['items'] : [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if ((int) ($item['videoId'] ?? 0) === $videoId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  list<array{id:int,body:string,createdAtText:string,author:array{name:string,username:string},replies:list<array{id:int,body:string,createdAtText:string,author:array{name:string,username:string},replyToAuthor?:array{name:string,username:string}|null}>}>  $threads
     */
    private function staticCommentCount(array $threads): int
    {
        $count = 0;

        foreach ($threads as $thread) {
            $count += 1;
            $count += count($thread['replies']);
        }

        return $count;
    }

    /**
     * @param  list<array{id:int,body:string,createdAtText:string,author:array{name:string,username:string},replies:list<array{id:int,body:string,createdAtText:string,author:array{name:string,username:string},replyToAuthor?:array{name:string,username:string}|null}>}>  $threads
     */
    private function renderStaticCommentThreadsMarkup(array $threads): string
    {
        return implode('', array_map(
            fn (array $thread): string => $this->renderStaticCommentThreadMarkup($thread),
            $threads,
        ));
    }

    /**
     * @param  array{id:int,body:string,createdAtText:string,author:array{name:string,username:string},replies:list<array{id:int,body:string,createdAtText:string,author:array{name:string,username:string},replyToAuthor?:array{name:string,username:string}|null}>}  $thread
     */
    private function renderStaticCommentThreadMarkup(array $thread): string
    {
        $authorName = (string) ($thread['author']['name'] ?? '匿名用户');
        $authorUsername = trim((string) ($thread['author']['username'] ?? ''));
        $replyCount = count($thread['replies']);
        $replyToggle = $replyCount > 0
            ? '<span class="inline-flex items-center justify-center rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-500">'.$this->escapeHtml($replyCount.' 条回复').'</span>'
            : '';

        return '
    <article
      class="grid gap-3 rounded-[1.5rem] border border-gray-200/80 bg-white/80 p-4 shadow-sm"
      data-static-comment-thread="true"
      data-comment-id="'.$this->escapeHtml((string) $thread['id']).'"
    >
      <div class="flex items-start gap-3">
        '.$this->renderStaticAvatarMarkup($authorName, $authorUsername, 'h-10 w-10', 'bg-gray-100 text-gray-700').'
        <div class="min-w-0 flex-1">
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-gray-900">'.$this->escapeHtml($authorName).'</p>'.
            ($authorUsername !== '' ? '
            <p class="mt-1 truncate text-xs text-gray-400">'.$this->escapeHtml('@'.$authorUsername).'</p>' : '').'
            <p class="mt-2 text-sm leading-6 text-gray-700">'.$this->escapeHtml($thread['body']).'</p>
          </div>
          <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-400">
            <span>'.$this->escapeHtml($thread['createdAtText']).'</span>
            <span class="inline-flex items-center justify-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-500">回复</span>
            '.$replyToggle.'
          </div>
        </div>
      </div>'.
      ($replyCount > 0 ? '
      <div class="ml-[3.25rem] grid gap-3 border-l border-gray-200 pl-4">
        '.implode('', array_map(
          fn (array $reply): string => $this->renderStaticReplyMarkup($reply),
          $thread['replies'],
      )).'
      </div>' : '').'
    </article>';
    }

    /**
     * @param  array{id:int,body:string,createdAtText:string,author:array{name:string,username:string},replyToAuthor?:array{name:string,username:string}|null}  $reply
     */
    private function renderStaticReplyMarkup(array $reply): string
    {
        $authorName = (string) ($reply['author']['name'] ?? '匿名用户');
        $authorUsername = trim((string) ($reply['author']['username'] ?? ''));
        $replyToAuthor = is_array($reply['replyToAuthor'] ?? null) ? $reply['replyToAuthor'] : null;
        $replyToLabel = '';

        if ($replyToAuthor !== null) {
            $replyToUsername = trim((string) ($replyToAuthor['username'] ?? ''));
            $replyToName = trim((string) ($replyToAuthor['name'] ?? ''));
            $replyToLabel = $replyToUsername !== '' ? '@'.$replyToUsername : $replyToName;
        }

        return '
    <article class="grid gap-2" data-static-comment-item="true" data-comment-id="'.$this->escapeHtml((string) $reply['id']).'">
      <div class="flex items-start gap-3">
        '.$this->renderStaticAvatarMarkup($authorName, $authorUsername, 'h-9 w-9', 'bg-gray-100 text-gray-700').'
        <div class="min-w-0 flex-1">
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-gray-900">'.$this->escapeHtml($authorName).'</p>'.
            ($authorUsername !== '' ? '
            <p class="mt-1 truncate text-xs text-gray-400">'.$this->escapeHtml('@'.$authorUsername).'</p>' : '').
            ($replyToLabel !== '' ? '
            <p class="mt-2 text-xs font-semibold text-rose-500">'.$this->escapeHtml('回复 '.$replyToLabel).'</p>' : '').'
            <p class="mt-1 text-sm leading-6 text-gray-700">'.$this->escapeHtml($reply['body']).'</p>
          </div>
          <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-400">
            <span>'.$this->escapeHtml($reply['createdAtText']).'</span>
            <span class="inline-flex items-center justify-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-500">回复</span>
          </div>
        </div>
      </div>
    </article>';
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    private function renderStaticFeedDetailModalMarkup(array $tweet): string
    {
        $engagement = is_array($tweet['engagement'] ?? null) ? $tweet['engagement'] : [];
        $commentCount = $this->staticCommentCount(self::STATIC_COMMENT_THREADS);
        $authorName = trim((string) ($tweet['authorName'] ?? '')) !== '' ? trim((string) $tweet['authorName']) : '匿名作者';
        $authorHandle = trim((string) ($tweet['authorHandle'] ?? ''));
        $authorUsername = trim((string) ($tweet['authorUsername'] ?? ''));
        $detailUrl = trim((string) ($tweet['detailUrl'] ?? '')) !== '' ? (string) $tweet['detailUrl'] : '/videos/'.self::COMMENT_SAMPLE_VIDEO_ID;

        return '
    <a
      href="/"
      class="absolute right-4 top-4 z-20 inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-black/45 text-white backdrop-blur-xl transition hover:bg-black/60 lg:right-5 lg:top-5"
      aria-label="关闭视频弹窗"
      data-static-detail-close="true"
    >
      <i class="ph ph-x text-lg leading-none" aria-hidden="true"></i>
    </a>

    <div class="relative flex min-h-0 flex-1 items-center justify-center overflow-hidden bg-black" data-detail-layout-node="true">
      '.$this->renderStaticFeedDetailMediaMarkup($tweet, $authorName).'
    </div>

    <aside
      class="flex w-full max-w-full flex-col border-t border-gray-200 bg-white xl:w-[430px] xl:max-w-[430px] xl:border-l xl:border-t-0"
      data-detail-layout-node="true"
    >
      <div class="border-b border-gray-200 px-5 py-4 sm:px-6">
        <div class="flex items-start justify-between gap-4">
          <div class="flex min-w-0 items-center gap-3">
            '.$this->renderStaticAvatarMarkup($authorName, $authorUsername !== '' ? $authorUsername : $authorHandle, 'h-12 w-12', 'bg-gray-100 text-gray-700').'
            <div class="min-w-0">
              <a href="/'.$this->escapeHtml(rawurlencode($authorUsername !== '' ? $authorUsername : $authorHandle)).'" class="block truncate text-base font-semibold text-gray-950 transition hover:text-rose-600">'.$this->escapeHtml($authorName).'</a>'.
              (($authorUsername !== '' || $authorHandle !== '') ? '
              <p class="mt-1 truncate text-sm text-gray-500">'.$this->escapeHtml('@'.($authorUsername !== '' ? $authorUsername : $authorHandle)).'</p>' : '').'
            </div>
          </div>

          <a
            href="/login"
            class="inline-flex h-11 shrink-0 items-center justify-center rounded-full bg-rose-500 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-600"
            data-auth-modal-trigger="true"
            data-auth-modal-panel="login"
          >
            关注
          </a>
        </div>
      </div>

      <div class="flex-1 overflow-y-auto px-5 py-5 sm:px-6 sm:py-6">
        <h2 id="detail-modal-title" class="text-[1.2rem] font-semibold leading-[1.42] text-gray-950 sm:text-[1.35rem]">
          <a href="'.$this->escapeHtml($detailUrl).'" class="transition hover:text-rose-600">'.$this->escapeHtml($this->displayTextFromTweet($tweet)).'</a>
        </h2>

        <p class="mt-3 text-sm font-normal tracking-[0.02em] text-gray-500">
          发布日期 · '.$this->escapeHtml($this->formatDetailDateFromIso((string) ($tweet['postedAt'] ?? ''))).'
        </p>

        <div class="mt-4">
          <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
            <span class="rounded-full bg-gray-100 px-3 py-1">'.$this->escapeHtml((string) ((int) ($engagement['viewCount'] ?? 0))).' 次观看</span>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-500">'.$this->escapeHtml((string) $commentCount).' 条评论</span>
          </div>
        </div>

        <div class="my-5 h-px bg-gray-200"></div>

        <section aria-labelledby="detail-comments-title">
          <div class="flex items-end justify-between gap-3">
            <div>
              <h3 id="detail-comments-title" class="text-base font-semibold text-gray-950">
                评论区
              </h3>
            </div>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-500" data-detail-comments-status="true">
              '.$this->escapeHtml((string) $commentCount).' 条评论
            </span>
          </div>

          <div class="mt-5 grid gap-5" data-detail-comments-list="true">
            '.$this->renderStaticCommentThreadsMarkup(self::STATIC_COMMENT_THREADS).'
          </div>
        </section>
      </div>

      <div class="border-t border-gray-200 px-5 py-4 sm:px-6" data-detail-layout-node="true">
        <div class="flex flex-col gap-3">
          <div class="flex items-center gap-3">
            <div class="flex h-12 min-w-0 flex-1 items-center rounded-full bg-gray-100 px-4 text-sm text-gray-400">
              登录后参与评论
            </div>
            <a
              href="/login"
              class="inline-flex h-11 shrink-0 items-center justify-center rounded-full bg-gray-900 px-5 text-sm font-semibold text-white transition hover:bg-gray-800"
              data-auth-modal-trigger="true"
              data-auth-modal-panel="login"
            >
              去登录
            </a>
          </div>
          <div class="flex shrink-0 items-center gap-2">
            '.$this->renderStaticReactionBadge('heart', (int) ($engagement['likeCount'] ?? 0), false, 'rose').'
            '.$this->renderStaticReactionBadge('bookmark-simple', (int) ($engagement['bookmarkCount'] ?? 0), false, 'amber').'
          </div>
        </div>
      </div>
    </aside>';
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    private function renderStaticMobileFeedDetailMarkup(array $tweet, bool $withCommentsDrawer): string
    {
        $commentCount = $this->staticCommentCount(self::STATIC_COMMENT_THREADS);

        return '
    <div
      class="relative flex h-[100dvh] w-full flex-col overflow-hidden bg-black"
      data-detail-layout-node="true"
      data-mobile-detail-root="true"
    >
      <div
        class="detail-mobile-viewport relative h-full w-full overflow-hidden"
        data-mobile-detail-viewport="true"
      >
        <div
          class="detail-mobile-track relative flex h-full w-full flex-col"
          data-mobile-detail-track="true"
          style="transform: translate3d(0, 0, 0);"
        >
          '.$this->renderStaticMobileFeedSlideMarkup($tweet, $commentCount).'
        </div>
      </div>'.
      ($withCommentsDrawer ? $this->renderStaticMobileCommentsDrawerMarkup($commentCount) : '').'
    </div>';
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    private function renderStaticMobileFeedSlideMarkup(array $tweet, int $commentCount): string
    {
        $engagement = is_array($tweet['engagement'] ?? null) ? $tweet['engagement'] : [];
        $authorName = trim((string) ($tweet['authorName'] ?? '')) !== '' ? trim((string) $tweet['authorName']) : '匿名作者';
        $authorHandle = trim((string) ($tweet['authorHandle'] ?? ''));
        $authorUsername = trim((string) ($tweet['authorUsername'] ?? ''));
        $authorMeta = $authorUsername !== '' ? '@'.$authorUsername : ($authorHandle !== '' ? '@'.$authorHandle : '');
        $safeTweetId = $this->escapeHtml((string) ($tweet['tweetId'] ?? self::COMMENT_SAMPLE_VIDEO_ID));
        $detailText = $this->displayTextFromTweet($tweet);

        return '
    <article
      class="mobile-detail-slide relative flex h-full min-h-full w-full flex-none items-stretch justify-center overflow-hidden bg-black"
      data-detail-layout-node="true"
      data-mobile-detail-slide="true"
      data-tweet-id="'.$safeTweetId.'"
      data-active="true"
      aria-label="'.$this->escapeHtml($authorName).' 的视频"
    >
      <div class="detail-mobile-media-shell relative flex-1 overflow-hidden bg-black">
        '.$this->renderStaticMobileFeedDetailMediaMarkup($tweet, $authorName).'
      </div>

      <div class="pointer-events-none absolute inset-x-0 top-0 z-10 h-40 bg-gradient-to-b from-black/75 via-black/20 to-transparent"></div>
      <div class="pointer-events-none absolute inset-x-0 bottom-0 z-10 h-[44dvh] bg-gradient-to-t from-black/75 via-black/40 to-transparent"></div>

      <div class="absolute inset-x-0 top-0 z-30 flex items-center justify-between px-4 pt-[calc(env(safe-area-inset-top)+0.8rem)] sm:px-6">
        <button
          type="button"
          class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-black/35 text-white backdrop-blur-xl transition hover:bg-black/55"
          data-mobile-detail-close="true"
          aria-label="返回视频流"
        >
          <i class="ph ph-arrow-left text-lg leading-none" aria-hidden="true"></i>
        </button>

        <div class="flex items-center gap-3">
          <button
            type="button"
            class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-black/35 text-white backdrop-blur-xl transition hover:bg-black/55"
            data-detail-share-action="true"
            aria-label="分享视频"
          >
            <i class="ph ph-share text-lg leading-none" aria-hidden="true"></i>
          </button>
        </div>
      </div>

      <div class="absolute inset-x-0 bottom-0 z-20 px-4 pb-[calc(env(safe-area-inset-bottom)+6.1rem)] pt-24 sm:px-6">
        <div class="mx-auto flex w-full max-w-md flex-col gap-3.5">
          <div class="flex min-w-0 items-center gap-3">
            <div class="flex min-w-0 items-center gap-2.5">
              '.$this->renderStaticAvatarMarkup($authorName, $authorUsername !== '' ? $authorUsername : $authorHandle, 'h-10 w-10', 'bg-white/15 text-white').'
              <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-white">'.$this->escapeHtml($authorName).'</p>'.
                ($authorMeta !== '' ? '
                <p class="mt-1 truncate text-xs text-white/70">'.$this->escapeHtml($authorMeta).'</p>' : '').'
              </div>
            </div>

            <a
              href="/login"
              class="inline-flex h-10 shrink-0 items-center justify-center rounded-full bg-rose-500 px-4 text-[0.8125rem] font-semibold text-white shadow-sm transition hover:bg-rose-600"
              data-auth-modal-trigger="true"
              data-auth-modal-panel="login"
            >
              关注
            </a>
          </div>

          <div class="grid gap-3">
            <h2 class="line-clamp-2 text-[1.05rem] font-semibold leading-7 text-white sm:text-lg">
              '.$this->escapeHtml($detailText).'
            </h2>
          </div>
        </div>
      </div>

      <div class="absolute inset-x-0 bottom-[calc(env(safe-area-inset-bottom)+4.7rem)] z-30 px-4 sm:px-6">
        <div class="mx-auto grid w-full max-w-md gap-2">
          <div
            class="detail-mobile-progress-time hidden text-center text-[0.95rem] font-medium tracking-[0.04em] text-white/95"
            data-detail-progress-time="true"
            aria-hidden="true"
          >
            00:00 / 00:00
          </div>
          <div class="pointer-events-auto" data-detail-progress-shell="true">
            <input
              type="range"
              min="0"
              max="1000"
              step="1"
              value="0"
              class="detail-mobile-progress-range w-full"
              data-detail-progress-range="true"
              style="--detail-progress-value: 0%;"
              aria-label="视频播放进度"
            />
          </div>
        </div>
      </div>

      <div class="absolute inset-x-0 bottom-0 z-30 border-t border-white/10 bg-black/90 px-4 pb-[calc(env(safe-area-inset-bottom)+0.9rem)] pt-3 backdrop-blur-xl sm:px-6">
        <div class="mx-auto flex w-full max-w-md items-center gap-3">
          <button
            type="button"
            class="inline-flex h-12 min-w-0 flex-1 items-center gap-3 rounded-full bg-white/10 px-4 text-sm text-white/60 transition hover:bg-white/15"
            data-detail-comments-open="true"
            aria-label="打开评论输入"
          >
            <i class="ph ph-pencil-simple-line text-lg leading-none text-white/70" aria-hidden="true"></i>
            <span class="truncate">说点什么...</span>
          </button>

          <div class="flex shrink-0 items-center gap-4">
            '.$this->renderStaticMobileReactionStatMarkup('heart', (int) ($engagement['likeCount'] ?? 0), false, '点赞').'
            '.$this->renderStaticMobileReactionStatMarkup('bookmark-simple', (int) ($engagement['bookmarkCount'] ?? 0), false, '收藏').'
            <button
              type="button"
              class="inline-flex min-w-[3.75rem] items-center justify-center gap-2 text-white transition"
              data-detail-comments-open="true"
              aria-label="打开评论"
            >
              <i class="ph ph-chat-circle text-[1.85rem] leading-none text-white" aria-hidden="true"></i>
              <span class="text-[0.95rem] font-semibold leading-none tabular-nums text-white/95">'.$this->escapeHtml((string) $commentCount).'</span>
            </button>
          </div>
        </div>
      </div>
    </article>';
    }

    private function renderStaticMobileCommentsDrawerMarkup(int $commentCount): string
    {
        return '
    <div
      class="pointer-events-auto absolute inset-0 z-40"
      data-detail-layout-node="true"
      data-mobile-comments-layer="true"
      aria-hidden="false"
    >
      <button
        type="button"
        class="absolute inset-0 bg-black/55 opacity-100 transition duration-200"
        data-mobile-comments-backdrop="true"
        aria-label="关闭评论"
      ></button>

      <section
        class="absolute inset-x-0 bottom-0 flex max-h-[82dvh] translate-y-0 flex-col overflow-hidden rounded-t-[28px] bg-white shadow-2xl transition duration-300 ease-out"
        data-mobile-comments-drawer="true"
        aria-labelledby="mobile-detail-comments-title"
      >
        <header class="flex items-center justify-between border-b border-gray-200 px-4 py-4 sm:px-5">
          <div class="flex items-center gap-3">
            <h2 id="mobile-detail-comments-title" class="text-base font-semibold text-gray-950">
              评论
            </h2>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-500" data-detail-comments-status="true">
              '.$this->escapeHtml((string) $commentCount).' 条评论
            </span>
          </div>

          <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition hover:bg-gray-200 hover:text-gray-900"
            data-mobile-comments-close="true"
            aria-label="关闭评论"
          >
            <i class="ph ph-x text-lg leading-none" aria-hidden="true"></i>
          </button>
        </header>

        <div class="flex-1 overflow-y-auto px-4 py-5 sm:px-5">
          <div class="grid gap-5" data-detail-comments-list="true">
            '.$this->renderStaticCommentThreadsMarkup(self::STATIC_COMMENT_THREADS).'
          </div>
        </div>

        <div class="border-t border-gray-200 bg-white px-4 py-4 pb-[calc(env(safe-area-inset-bottom)+1rem)] sm:px-5">
          <div class="flex items-center gap-3">
            <div class="flex h-12 min-w-0 flex-1 items-center rounded-full bg-gray-100 px-4 text-sm text-gray-400">
              登录后参与评论
            </div>
            <a
              href="/login"
              class="inline-flex h-11 shrink-0 items-center justify-center rounded-full bg-gray-900 px-5 text-sm font-semibold text-white transition hover:bg-gray-800"
              data-auth-modal-trigger="true"
              data-auth-modal-panel="login"
            >
              去登录
            </a>
          </div>
        </div>
      </section>
    </div>';
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    private function renderStaticFeedDetailMediaMarkup(array $tweet, string $authorName): string
    {
        $posterUrl = trim((string) ($tweet['posterUrl'] ?? ''));
        $videoUrl = trim((string) ($tweet['videoUrl'] ?? ''));
        $shouldUseVideo = $videoUrl !== '' && ! str_starts_with($videoUrl, '/api/');

        if ($shouldUseVideo) {
            return '
      <video
        class="detail-modal-video h-full w-full bg-black object-contain shadow-[0_28px_80px_rgba(0,0,0,0.42)]"
        src="'.$this->escapeHtml($videoUrl).'"
        '.($posterUrl !== '' ? 'poster="'.$this->escapeHtml($posterUrl).'"' : '').'
        autoplay
        muted
        loop
        playsinline
        preload="metadata"
        referrerpolicy="no-referrer"
      ></video>';
        }

        return '
      <div class="relative flex h-full w-full items-center justify-center">
        <img
          class="max-h-full w-auto max-w-full object-contain shadow-[0_28px_80px_rgba(0,0,0,0.42)]"
          src="'.$this->escapeHtml($posterUrl).'"
          alt="'.$this->escapeHtml($authorName).' 的视频封面"
          loading="lazy"
          referrerpolicy="no-referrer"
        />
        <span class="absolute bottom-6 left-6 rounded-full bg-white/10 px-4 py-2 text-xs font-semibold tracking-[0.18em] text-white backdrop-blur-md">
          VIDEO PREVIEW
        </span>
      </div>';
    }

    /**
     * @param  array<string, mixed>  $tweet
     */
    private function renderStaticMobileFeedDetailMediaMarkup(array $tweet, string $authorName): string
    {
        $posterUrl = trim((string) ($tweet['posterUrl'] ?? ''));
        $videoUrl = trim((string) ($tweet['videoUrl'] ?? ''));
        $shouldUseVideo = $videoUrl !== '' && ! str_starts_with($videoUrl, '/api/');

        if ($shouldUseVideo) {
            return '
      <video
        class="detail-modal-video h-full w-full bg-black object-cover"
        src="'.$this->escapeHtml($videoUrl).'"
        '.($posterUrl !== '' ? 'poster="'.$this->escapeHtml($posterUrl).'"' : '').'
        autoplay
        muted
        loop
        playsinline
        preload="metadata"
        referrerpolicy="no-referrer"
      ></video>';
        }

        return '
      <div class="relative flex h-full w-full items-center justify-center">
        <img
          class="h-full w-full object-cover"
          src="'.$this->escapeHtml($posterUrl).'"
          alt="'.$this->escapeHtml($authorName).' 的视频封面"
          loading="lazy"
          referrerpolicy="no-referrer"
        />
      </div>';
    }

    private function renderStaticReactionBadge(string $icon, int $count, bool $active, string $tone): string
    {
        $className = match (true) {
            $tone === 'rose' && $active => 'border-rose-200 bg-rose-50 text-rose-600',
            $tone === 'amber' && $active => 'border-amber-200 bg-amber-50 text-amber-700',
            default => 'border-gray-200 bg-white text-gray-500',
        };

        $iconClass = match (true) {
            $icon === 'heart' && $active => 'ph-fill ph-heart',
            $icon === 'bookmark-simple' && $active => 'ph-fill ph-bookmark-simple',
            $icon === 'bookmark-simple' => 'ph ph-bookmark-simple',
            default => 'ph ph-heart',
        };

        return '
      <span class="inline-flex h-11 shrink-0 items-center gap-2.5 rounded-full border px-4 text-sm font-semibold '.$className.'">
        <i class="'.$this->escapeHtml($iconClass).' text-[1.05rem] leading-none" aria-hidden="true"></i>
      <span class="text-xs font-semibold tabular-nums opacity-80">'.$this->escapeHtml((string) max(0, $count)).'</span>
      </span>';
    }

    private function renderStaticMobileReactionStatMarkup(
        string $icon,
        int $count,
        bool $active,
        string $label,
    ): string {
        $iconClass = match (true) {
            $icon === 'heart' && $active => 'ph-fill ph-heart text-rose-300',
            $icon === 'heart' => 'ph ph-heart text-white',
            $icon === 'bookmark-simple' && $active => 'ph-fill ph-bookmark-simple text-amber-200',
            default => 'ph ph-bookmark-simple text-white',
        };

        return '
      <span
        class="inline-flex min-w-[3.75rem] items-center justify-center gap-2 text-white"
        aria-label="'.$this->escapeHtml($label).'"
      >
        <i class="'.$this->escapeHtml($iconClass).' text-[1.85rem] leading-none" aria-hidden="true"></i>
        <span class="text-[0.95rem] font-semibold leading-none tabular-nums text-white/95">'.$this->escapeHtml((string) max(0, $count)).'</span>
      </span>';
    }

    private function renderStaticAvatarMarkup(
        string $name,
        string $username,
        string $sizeClass,
        string $fallbackClass,
    ): string {
        $label = $name !== '' ? $name : ($username !== '' ? $username : '匿名用户');
        $initial = $this->authorInitial($label);

        return '
      <span
        class="flex '.$this->escapeHtml($sizeClass).' items-center justify-center rounded-full '.$this->escapeHtml($fallbackClass).' text-xs font-semibold"
        aria-hidden="true"
      >
        '.$this->escapeHtml($initial).'
      </span>';
    }

    private function displayTextFromTweet(array $tweet): string
    {
        $text = preg_replace('/https?:\/\/\S+/u', ' ', (string) ($tweet['text'] ?? '')) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        $text = trim($text);

        return $text !== '' ? $text : '未填写内容文案';
    }

    private function formatDetailDateFromIso(string $value): string
    {
        if (trim($value) === '') {
            return '发布日期待更新';
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (Throwable) {
            return '发布日期待更新';
        }

        return $date->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('Y年n月j日');
    }

    private function authorInitial(string $value): string
    {
        $normalized = ltrim(trim($value), '@');

        return $normalized !== '' ? mb_strtoupper(mb_substr($normalized, 0, 1)) : 'L';
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function replaceElementChildrenWithHtml(DOMElement $target, string $html): void
    {
        while ($target->firstChild !== null) {
            $target->removeChild($target->firstChild);
        }

        $fragmentRootId = '__static_fragment_root__';
        $tempDom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $tempDom->loadHTML(
            '<?xml encoding="utf-8" ?><div id="'.$fragmentRootId.'">'.$html.'</div>',
            LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
        );
        libxml_clear_errors();

        $tempXPath = new DOMXPath($tempDom);
        $wrapper = $this->firstElementFromQuery($tempXPath, '//*[@id="'.$fragmentRootId.'"]');

        if ($wrapper === null) {
            return;
        }

        foreach (iterator_to_array($wrapper->childNodes) as $childNode) {
            $importedNode = $target->ownerDocument?->importNode($childNode, true);

            if ($importedNode !== null) {
                $target->appendChild($importedNode);
            }
        }
    }

    private function assetRoot(): string
    {
        return $this->outputRoot.'/assets';
    }

    private function normalizePath(string $path): string
    {
        $trimmedPath = trim($path);
        if ($trimmedPath === '') {
            return '/';
        }

        if ($trimmedPath !== '/') {
            return '/'.trim($trimmedPath, '/');
        }

        return $trimmedPath;
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        mkdir($path, 0777, true);
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if (in_array($entry, ['.', '..'], true)) {
                continue;
            }

            $entryPath = $path.'/'.$entry;
            if (is_dir($entryPath)) {
                $this->removeDirectory($entryPath);

                continue;
            }

            unlink($entryPath);
        }

        rmdir($path);
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (! is_dir($source)) {
            throw new RuntimeException('Missing source directory: '.$source);
        }

        $this->ensureDirectory($destination);

        $entries = scandir($source);
        if ($entries === false) {
            throw new RuntimeException('Failed to read directory: '.$source);
        }

        foreach ($entries as $entry) {
            if (in_array($entry, ['.', '..'], true)) {
                continue;
            }

            $sourcePath = $source.'/'.$entry;
            $destinationPath = $destination.'/'.$entry;

            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $destinationPath);

                continue;
            }

            copy($sourcePath, $destinationPath);
        }
    }

    private function removeNodes(DOMXPath $xpath, string $query): void
    {
        $nodes = $xpath->query($query);
        if ($nodes === false) {
            return;
        }

        foreach ($nodes as $node) {
            $node->parentNode?->removeChild($node);
        }
    }
}

$exporter = new StaticHtmlExporter(
    repoRoot: dirname(__DIR__, 2),
    outputRoot: __DIR__,
    laravelRoot: dirname(__DIR__, 2).'/laravel',
    app: $app,
);

$exporter->run();
