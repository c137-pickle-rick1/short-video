<?php

namespace App\ShortVideo\View;

use Illuminate\Contracts\View\Factory as ViewFactory;

final class HomePageShellComponents
{
    public function __construct(private readonly ViewFactory $views) {}

    public function renderPageHeader(
        string $searchUrl,
        ?array $viewer = null,
        ?string $logoutUrl = null,
        ?string $searchQuery = null
    ): string {
        return $this->renderPageHeaderContainer(
            'fixed inset-x-0 top-0 z-40 w-full border-b border-gray-200 bg-white/95 backdrop-blur-xl',
            $searchUrl,
            $searchQuery
        );
    }

    /**
     * @param  array<int, array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null,authTriggerPanel?:string|null,mobileHidden?:bool,dividerBefore?:bool}>  $items
     */
    public function renderDesktopNavigation(array $items): string
    {
        return $this->renderDesktopNavigationContainer(
            $items,
            'hidden lg:block lg:sticky lg:top-[100px] lg:w-56 lg:flex-none xl:top-[104px] 2xl:top-[108px]'
        );
    }

    /**
     * @param  array<int, array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null,authTriggerPanel?:string|null,mobileHidden?:bool,dividerBefore?:bool}>  $items
     */
    public function renderMobileNavigation(array $items): string
    {
        return $this->renderMobileNavigationContainer(
            $items,
            'fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 px-0 pt-1 pb-[calc(env(safe-area-inset-bottom)+0.25rem)] backdrop-blur-2xl lg:hidden'
        );
    }

    /**
     * @param  array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null,authTriggerPanel?:string|null,mobileHidden?:bool,dividerBefore?:bool}  $item
     */
    private function renderDesktopNavItem(array $item): string
    {
        return $this->renderView('shortvideo.partials.shell.desktop-nav-item', [
            'item' => $item,
            'iconMarkup' => $this->renderNavigationItemVisual($item, 'desktop'),
        ]);
    }

    /**
     * @param  array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null,authTriggerPanel?:string|null,mobileHidden?:bool,dividerBefore?:bool}  $item
     */
    private function renderMobileNavItem(array $item): string
    {
        return $this->renderView('shortvideo.partials.shell.mobile-nav-item', [
            'item' => $item,
            'iconMarkup' => $this->renderNavigationItemVisual($item, 'mobile'),
        ]);
    }

    /**
     * @param  array<int, array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null,authTriggerPanel?:string|null,mobileHidden?:bool,dividerBefore?:bool}>  $items
     */
    private function renderDesktopNavigationContainer(array $items, string $wrapperClass): string
    {
        $itemsMarkup = implode('', array_map(fn (array $item) => $this->renderDesktopNavItem($item), $items));

        return $this->renderView('shortvideo.partials.shell.desktop-navigation', [
            'wrapperClass' => $wrapperClass,
            'itemsMarkup' => $itemsMarkup,
        ]);
    }

    /**
     * @param  array<int, array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null,authTriggerPanel?:string|null,mobileHidden?:bool,dividerBefore?:bool}>  $items
     */
    private function renderMobileNavigationContainer(array $items, string $wrapperClass): string
    {
        $itemsMarkup = implode('', array_map(fn (array $item) => $this->renderMobileNavItem($item), $items));

        return $this->renderView('shortvideo.partials.shell.mobile-navigation', [
            'wrapperClass' => $wrapperClass,
            'itemsMarkup' => $itemsMarkup,
            'columnCount' => max(1, count($items)),
        ]);
    }

    private function renderPageHeaderContainer(
        string $wrapperClass,
        string $searchUrl,
        ?string $searchQuery = null
    ): string {
        return $this->renderView('shortvideo.partials.shell.page-header', [
            'wrapperClass' => $wrapperClass,
            'searchUrl' => $searchUrl,
            'searchQuery' => $searchQuery ?? '',
        ]);
    }

    /**
     * @param  array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null,authTriggerPanel?:string|null,mobileHidden?:bool,dividerBefore?:bool}  $item
     */
    private function renderNavigationItemVisual(array $item, string $context): string
    {
        $avatarUrl = isset($item['avatarUrl']) ? trim((string) $item['avatarUrl']) : '';
        $avatarInitial = isset($item['avatarInitial']) ? trim((string) $item['avatarInitial']) : '我';
        $hasAvatar = array_key_exists('avatarUrl', $item);

        return $this->renderView('shortvideo.partials.shell.navigation-item-visual', [
            'hasAvatar' => $hasAvatar,
            'avatarUrl' => $avatarUrl,
            'avatarInitial' => $avatarInitial,
            'icon' => (string) $item['icon'],
            'iconSizeClass' => 'text-2xl',
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
