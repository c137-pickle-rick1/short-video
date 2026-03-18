<?php

namespace App\ShortVideo\View;

use Illuminate\Contracts\View\Factory as ViewFactory;

final class HomePageShellComponents
{
    public function __construct(private readonly ViewFactory $views) {}

    public function renderPageHeader(string $loginUrl, ?array $viewer = null, ?string $logoutUrl = null): string
    {
        return $this->renderPageHeaderContainer('fixed inset-x-0 top-0 z-40 w-full border-b border-gray-200 bg-white/95 backdrop-blur-xl');
    }

    public function renderPageHeaderPreview(string $loginUrl): string
    {
        return $this->renderPageHeaderContainer('rounded-2xl border border-gray-200/80 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]');
    }

    /**
     * @param  array<int, array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null}>  $items
     */
    public function renderDesktopNavigation(array $items): string
    {
        return $this->renderDesktopNavigationContainer(
            $items,
            'hidden lg:block lg:sticky lg:top-[100px] lg:w-56 lg:flex-none xl:top-[104px] 2xl:top-[108px]'
        );
    }

    /**
     * @param  array<int, array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null}>  $items
     */
    public function renderMobileNavigation(array $items): string
    {
        return $this->renderMobileNavigationContainer(
            $items,
            'fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 px-3 py-2 backdrop-blur-2xl lg:hidden'
        );
    }

    /**
     * @param  array<int, array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null}>  $items
     */
    public function renderDesktopNavigationPreview(array $items): string
    {
        return $this->renderDesktopNavigationContainer($items, 'w-full');
    }

    /**
     * @param  array<int, array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null}>  $items
     */
    public function renderMobileNavigationPreview(array $items): string
    {
        return $this->renderMobileNavigationContainer(
            $items,
            'relative rounded-2xl border border-gray-200/80 bg-white px-3 py-2 shadow-[0_1px_2px_rgba(15,23,42,0.04)]'
        );
    }

    /**
     * @param  array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null}  $item
     */
    private function renderDesktopNavItem(array $item): string
    {
        return $this->renderView('shortvideo.partials.shell.desktop-nav-item', [
            'item' => $item,
            'iconMarkup' => $this->renderNavigationItemVisual($item, 'desktop'),
        ]);
    }

    /**
     * @param  array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null}  $item
     */
    private function renderMobileNavItem(array $item): string
    {
        return $this->renderView('shortvideo.partials.shell.mobile-nav-item', [
            'item' => $item,
            'iconMarkup' => $this->renderNavigationItemVisual($item, 'mobile'),
        ]);
    }

    /**
     * @param  array<int, array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null}>  $items
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
     * @param  array<int, array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null}>  $items
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
        string $wrapperClass
    ): string {
        return $this->renderView('shortvideo.partials.shell.page-header', [
            'wrapperClass' => $wrapperClass,
        ]);
    }

    /**
     * @param  array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null}  $item
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
            'iconSizeClass' => $context === 'mobile' ? 'text-[28px]' : 'text-2xl',
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
