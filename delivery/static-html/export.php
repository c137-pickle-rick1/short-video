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

    /**
     * @var list<array{
     *   filename: string,
     *   uri: string,
     *   authUserId: int|null,
     *   context: string,
     *   refererUri?: string|null
     * }>
     */
    private const PAGES = [
        ['filename' => 'index.html', 'uri' => '/', 'authUserId' => null, 'context' => 'guest'],
        ['filename' => 'explore.html', 'uri' => '/explore', 'authUserId' => null, 'context' => 'guest'],
        ['filename' => 'rankings.html', 'uri' => '/rankings', 'authUserId' => null, 'context' => 'guest'],
        ['filename' => 'login.html', 'uri' => '/login', 'authUserId' => null, 'context' => 'guest'],
        ['filename' => 'subscriptions-guest.html', 'uri' => '/subscriptions', 'authUserId' => null, 'context' => 'guest-subscriptions'],
        ['filename' => 'subscriptions-empty-following.html', 'uri' => '/subscriptions', 'authUserId' => 20, 'context' => 'auth-empty-following'],
        ['filename' => 'subscriptions-selection.html', 'uri' => '/subscriptions', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'subscriptions-77sunnyx.html', 'uri' => '/subscriptions/77sunnyx', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'profile-public-77sunnyx.html', 'uri' => '/77sunnyx', 'authUserId' => null, 'context' => 'guest'],
        ['filename' => 'profile-own-overview.html', 'uri' => '/user_vbwy5lipgn22', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'profile-own-creator.html', 'uri' => '/user_vbwy5lipgn22?panel=creator', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'profile-own-history.html', 'uri' => '/user_vbwy5lipgn22?panel=history', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'profile-own-bookmarks.html', 'uri' => '/user_vbwy5lipgn22?panel=bookmarks', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'profile-own-interactions.html', 'uri' => '/user_vbwy5lipgn22?panel=interactions', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'viewer-history.html', 'uri' => '/me/history', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'viewer-bookmarks.html', 'uri' => '/me/bookmarks', 'authUserId' => 19, 'context' => 'auth-main'],
        ['filename' => 'viewer-interactions.html', 'uri' => '/me/interactions', 'authUserId' => 19, 'context' => 'auth-main'],
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
     * @param  array{filename: string, uri: string, authUserId: int|null, context: string, refererUri?: string|null}  $page
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

        $this->removeNodes($xpath, '//script');
        $this->removeNodes($xpath, '//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = "csrf-token"]');
        $this->removeNodes($xpath, '//link[contains(@href, "/build/assets/")]');
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
     * @param  array{filename: string, uri: string, authUserId: int|null, context: string, refererUri?: string|null}  $page
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
     * @param  array{filename: string, uri: string, authUserId: int|null, context: string, refererUri?: string|null}  $page
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
