<?php

namespace App\ShortVideo\View;

use Illuminate\Contracts\Routing\UrlGenerator;

final class ShellViewDataFactory
{
    public function __construct(private readonly UrlGenerator $url) {}

    /**
     * @param  array<string, mixed>  $viewModel
     * @return array<string, mixed>
     */
    public function makePageShell(string $activePage, array $viewModel, bool $includePlyrStyles = false): array
    {
        $viewer = is_array($viewModel['headerViewer'] ?? null) ? $viewModel['headerViewer'] : null;

        return [
            'lang' => 'zh-CN',
            'head' => [
                'pageTitle' => (string) ($viewModel['pageTitle'] ?? ''),
                'includeCsrfToken' => true,
                'includePhosphorStyles' => true,
                'includePlyrStyles' => $includePlyrStyles,
            ],
            'header' => [
                'searchUrl' => $this->url->route('explore'),
                'searchQuery' => (string) ($viewModel['searchQuery'] ?? ''),
            ],
            'desktopNavigationItems' => $this->navigationItems($activePage, $viewer),
            'mobileNavigationItems' => array_values(array_filter(
                $this->navigationItems($activePage, $viewer),
                static fn (array $item): bool => ($item['mobileHidden'] ?? false) !== true
            )),
            'showHeader' => true,
            'showNavigation' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function makeStandaloneShell(string $pageTitle, bool $includePlyrStyles = false): array
    {
        return [
            'lang' => 'zh-CN',
            'head' => [
                'pageTitle' => $pageTitle,
                'includeCsrfToken' => true,
                'includePhosphorStyles' => true,
                'includePlyrStyles' => $includePlyrStyles,
            ],
            'header' => [
                'searchUrl' => '',
                'searchQuery' => '',
            ],
            'desktopNavigationItems' => [],
            'mobileNavigationItems' => [],
            'showHeader' => false,
            'showNavigation' => false,
        ];
    }

    /**
     * @param  array{id?:int,name?:string,username:string,avatarUrl?:string|null}|null  $viewer
     * @return array<int, array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null,authTriggerPanel?:string|null,mobileHidden?:bool,dividerBefore?:bool}>
     */
    private function navigationItems(string $activePage, ?array $viewer = null): array
    {
        $items = [
            [
                'icon' => $activePage === 'featured' ? 'ph-fill ph-sparkle' : 'ph ph-sparkle',
                'label' => '精选',
                'active' => $activePage === 'featured',
                'href' => $this->url->route('home'),
            ],
            [
                'icon' => $activePage === 'explore' ? 'ph-fill ph-compass' : 'ph ph-compass',
                'label' => '探索',
                'active' => $activePage === 'explore',
                'href' => $this->url->route('explore'),
            ],
            [
                'icon' => $activePage === 'rankings' ? 'ph-fill ph-chart-bar' : 'ph ph-chart-bar',
                'label' => '榜单',
                'active' => $activePage === 'rankings',
                'href' => $this->url->route('rankings'),
            ],
            [
                'icon' => $activePage === 'subscriptions' ? 'ph-fill ph-users-three' : 'ph ph-users-three',
                'label' => '关注',
                'active' => $activePage === 'subscriptions',
                'href' => $this->url->route('subscriptions'),
            ],
        ];

        if ($viewer !== null && trim((string) ($viewer['username'] ?? '')) !== '') {
            $viewerName = trim((string) ($viewer['name'] ?? ''));
            $viewerUsername = trim((string) ($viewer['username'] ?? ''));

            $items[] = [
                'icon' => $activePage === 'history' ? 'ph-fill ph-clock-counter-clockwise' : 'ph ph-clock-counter-clockwise',
                'label' => '观看记录',
                'active' => $activePage === 'history',
                'href' => $this->url->route('viewer.history'),
                'mobileHidden' => true,
                'dividerBefore' => true,
            ];
            $items[] = [
                'icon' => $activePage === 'bookmarks' ? 'ph-fill ph-bookmark-simple' : 'ph ph-bookmark-simple',
                'label' => '我的收藏',
                'active' => $activePage === 'bookmarks',
                'href' => $this->url->route('viewer.bookmarks'),
                'mobileHidden' => true,
            ];
            $items[] = [
                'icon' => $activePage === 'interactions' ? 'ph-fill ph-chat-circle-dots' : 'ph ph-chat-circle-dots',
                'label' => '我的互动',
                'active' => $activePage === 'interactions',
                'href' => $this->url->route('viewer.interactions'),
                'mobileHidden' => true,
            ];
            $items[] = [
                'icon' => 'ph ph-user-circle',
                'label' => '我的',
                'active' => $activePage === 'profile',
                'href' => $this->profileUrlForUsername($viewerUsername) ?? $this->url->route('home'),
                'avatarUrl' => isset($viewer['avatarUrl']) ? trim((string) $viewer['avatarUrl']) ?: null : null,
                'avatarInitial' => $this->getAuthorInitial($viewerName !== '' ? $viewerName : $viewerUsername),
            ];

            return $items;
        }

        $items[] = [
            'icon' => 'ph ph-sign-in',
            'label' => '登录',
            'active' => false,
            'href' => $this->url->route('login'),
            'authTriggerPanel' => 'login',
            'dividerBefore' => true,
        ];

        return $items;
    }

    private function profileUrlForUsername(?string $username): ?string
    {
        $normalizedUsername = trim((string) ($username ?? ''));

        return $normalizedUsername !== ''
            ? $this->url->route('profile.show', ['username' => $normalizedUsername], false)
            : null;
    }

    private function getAuthorInitial(?string $value): string
    {
        $normalized = ltrim(trim((string) ($value ?? '')), '@');

        return $normalized !== '' ? mb_strtoupper(mb_substr($normalized, 0, 1)) : 'L';
    }
}
