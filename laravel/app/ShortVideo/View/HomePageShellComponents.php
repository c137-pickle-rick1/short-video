<?php

namespace App\ShortVideo\View;

final class HomePageShellComponents
{
    public function renderPageHeader(string $loginUrl, ?array $viewer = null, ?string $logoutUrl = null): string
    {
        return $this->renderPageHeaderContainer(
            'fixed inset-x-0 top-0 z-40 w-full border-b border-gray-200 bg-white/95 backdrop-blur-xl',
        );
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
        $safeLabel = $this->escape($item['label']);
        $safeHref = $this->escape($item['href']);
        $iconMarkup = $this->renderNavigationItemVisual($item, 'desktop');

        if ($item['active']) {
            return <<<HTML
                <a
                  href="{$safeHref}"
                  aria-current="page"
                  class="inline-flex h-12 w-full items-center gap-4 rounded-full bg-gray-100 px-6 text-left text-lg font-semibold text-gray-900 transition-colors hover:bg-gray-200"
                >
                  {$iconMarkup}
                  <span>{$safeLabel}</span>
                </a>
HTML;
        }

        return <<<HTML
                <a
                  href="{$safeHref}"
                  class="inline-flex h-12 w-full items-center gap-4 rounded-full px-6 text-left text-lg font-medium text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-900"
                >
                  {$iconMarkup}
                  <span>{$safeLabel}</span>
                </a>
HTML;
    }

    /**
     * @param  array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null}  $item
     */
    private function renderMobileNavItem(array $item): string
    {
        $safeLabel = $this->escape($item['label']);
        $safeHref = $this->escape($item['href']);
        $currentAttr = $item['active'] ? ' aria-current="page"' : '';
        $buttonClass = $item['active']
            ? 'flex flex-col items-center justify-center gap-1 py-2 text-center text-xs font-medium text-gray-900'
            : 'flex flex-col items-center justify-center gap-1 py-2 text-center text-xs font-medium text-gray-500';
        $iconMarkup = $this->renderNavigationItemVisual($item, 'mobile');

        return <<<HTML
              <a
                href="{$safeHref}"{$currentAttr}
                class="{$buttonClass}"
              >
                {$iconMarkup}
                {$safeLabel}
              </a>
HTML;
    }

    /**
     * @param  array<int, array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null}>  $items
     */
    private function renderDesktopNavigationContainer(array $items, string $wrapperClass): string
    {
        $itemsMarkup = implode('', array_map(fn (array $item) => $this->renderDesktopNavItem($item), $items));

        return <<<HTML
          <aside class="{$wrapperClass}">
            <nav aria-label="桌面主导航">
              <div class="grid gap-2">
                {$itemsMarkup}
              </div>
            </nav>
          </aside>
HTML;
    }

    /**
     * @param  array<int, array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null}>  $items
     */
    private function renderMobileNavigationContainer(array $items, string $wrapperClass): string
    {
        $itemsMarkup = implode('', array_map(fn (array $item) => $this->renderMobileNavItem($item), $items));
        $columnCount = max(1, count($items));

        return <<<HTML
          <nav
            aria-label="移动主导航"
            class="{$wrapperClass}"
          >
            <div class="mx-auto grid max-w-md" style="grid-template-columns: repeat({$columnCount}, minmax(0, 1fr));">
              {$itemsMarkup}
            </div>
          </nav>
HTML;
    }

    private function renderPageHeaderContainer(
        string $wrapperClass
    ): string
    {
        return <<<HTML
      <header class="{$wrapperClass}">
        <div class="mx-auto flex w-full max-w-screen-2xl items-center gap-3 px-3 py-3 sm:gap-4 sm:px-4 sm:py-4 lg:gap-5 lg:px-5 lg:py-4 xl:gap-6 xl:px-6 xl:py-4 2xl:gap-7 2xl:px-7 2xl:py-4">
          <div class="shrink-0">
            <div class="flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-gray-100 sm:h-12 sm:w-12">
              <span class="h-2.5 w-2.5 rounded-full bg-gray-900"></span>
            </div>
          </div>
          <label
            class="mx-auto flex h-11 w-full min-w-0 max-w-[480px] items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-3 sm:h-12 sm:gap-3 sm:px-4"
          >
            <i class="ph ph-magnifying-glass text-base text-gray-500" aria-hidden="true"></i>
            <input
              type="search"
              placeholder="搜索视频、作者或关键词"
              class="min-w-0 flex-1 bg-transparent text-sm text-gray-900 outline-none placeholder:text-gray-400"
            />
          </label>
          <div class="flex shrink-0 items-center gap-2 sm:gap-3">
            <button
              type="button"
              aria-label="切换语言"
              title="切换语言"
              class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 text-lg text-gray-600 transition hover:text-gray-900 sm:h-12 sm:w-12"
            >
              <i class="ph ph-globe text-xl leading-none" aria-hidden="true"></i>
            </button>
          </div>
        </div>
      </header>
HTML;
    }

    /**
     * @param  array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null}  $item
     */
    private function renderNavigationItemVisual(array $item, string $context): string
    {
        $avatarUrl = isset($item['avatarUrl']) ? trim((string) $item['avatarUrl']) : '';
        $avatarInitial = isset($item['avatarInitial']) ? trim((string) $item['avatarInitial']) : '我';
        $safeAvatarInitial = $this->escape($avatarInitial);

        if (array_key_exists('avatarUrl', $item)) {
            $slotAttributes = <<<HTML
data-avatar-slot="nav"
                  data-avatar-kind="nav"
                  data-avatar-initial="{$safeAvatarInitial}"
                  data-avatar-url="{$this->escape($avatarUrl)}"
HTML;

            if ($avatarUrl !== '') {
                $safeAvatarUrl = $this->escape($avatarUrl);

                return <<<HTML
                <span
                  class="shrink-0"
                  {$slotAttributes}
                >
                  <img
                    src="{$safeAvatarUrl}"
                    alt=""
                    class="size-6 rounded-full object-cover ring-1 ring-gray-200"
                    loading="lazy"
                    referrerpolicy="no-referrer"
                  />
                </span>
HTML;
            }

            return <<<HTML
                <span
                  class="shrink-0"
                  {$slotAttributes}
                >
                  <span class="flex size-6 items-center justify-center rounded-full bg-gray-900 text-xs font-semibold leading-none text-white">
                    {$safeAvatarInitial}
                  </span>
                </span>
HTML;
        }

        $safeIcon = $this->escape($item['icon']);
        $iconSizeClass = $context === 'mobile' ? 'text-[28px]' : 'text-2xl';

        return <<<HTML
            <i class="{$safeIcon} {$iconSizeClass} leading-none" aria-hidden="true"></i>
HTML;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
