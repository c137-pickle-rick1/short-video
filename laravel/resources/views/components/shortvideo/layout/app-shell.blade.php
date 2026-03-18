@props([
  'shell' => [],
  'bodyClass' => 'overflow-x-hidden bg-white text-gray-900 antialiased',
  'showHeaderSpacer' => true,
  'showMobileSpacer' => true,
  'mainClass' => 'relative z-10',
  'contentSectionClass' => 'min-w-0 flex-1',
  'wrapMainContent' => true,
])

@php
  $shell = is_array($shell) ? $shell : [];
  $head = is_array($shell['head'] ?? null) ? $shell['head'] : [];
  $header = is_array($shell['header'] ?? null) ? $shell['header'] : [];
  $desktopNavigationItems = is_array($shell['desktopNavigationItems'] ?? null) ? $shell['desktopNavigationItems'] : [];
  $mobileNavigationItems = is_array($shell['mobileNavigationItems'] ?? null) ? $shell['mobileNavigationItems'] : [];
  $showHeader = ($shell['showHeader'] ?? true) === true;
  $showNavigation = ($shell['showNavigation'] ?? true) === true;
  $lang = trim((string) ($shell['lang'] ?? 'zh-CN')) ?: 'zh-CN';
@endphp

<!doctype html>
<html lang="{{ $lang }}">
  <head>
    <x-shortvideo.layout.document-head
      :page-title="(string) ($head['pageTitle'] ?? '')"
      :include-csrf-token="($head['includeCsrfToken'] ?? false) === true"
      :include-phosphor-styles="($head['includePhosphorStyles'] ?? false) === true"
      :include-plyr-styles="($head['includePlyrStyles'] ?? false) === true"
    />
    {{ $headExtra ?? '' }}
  </head>
  <body class="{{ $bodyClass }}">
    @if($wrapMainContent)
      <main class="{{ $mainClass }}">
        @if($showHeader)
          <x-shortvideo.layout.page-header
            :search-url="(string) ($header['searchUrl'] ?? '')"
            :search-query="(string) ($header['searchQuery'] ?? '')"
            wrapper-class="fixed inset-x-0 top-0 z-40 w-full border-b border-gray-200 bg-white/95 backdrop-blur-xl"
          />

          @if($showHeaderSpacer)
            <div class="h-[68px] sm:h-20" aria-hidden="true"></div>
          @endif
        @endif

        <div class="mx-auto w-full max-w-screen-2xl">
          <div class="flex flex-col gap-3 p-3 sm:gap-4 sm:p-4 lg:gap-5 lg:p-5 xl:gap-6 xl:p-6 2xl:gap-7 2xl:p-7 lg:flex-row lg:items-start">
            @if($showNavigation)
              <x-shortvideo.layout.desktop-navigation
                :items="$desktopNavigationItems"
                wrapper-class="hidden lg:block lg:sticky lg:top-[100px] lg:w-56 lg:flex-none xl:top-[104px] 2xl:top-[108px]"
              />
              <x-shortvideo.layout.mobile-navigation
                :items="$mobileNavigationItems"
                wrapper-class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 px-0 pt-1 pb-[calc(env(safe-area-inset-bottom)+0.25rem)] backdrop-blur-2xl lg:hidden"
              />
            @endif

            <section class="{{ $contentSectionClass }}">
              {{ $slot }}
            </section>
          </div>

          @if($showNavigation && $showMobileSpacer)
            <div class="h-24 lg:hidden" aria-hidden="true"></div>
          @endif
        </div>
      </main>
    @else
      {{ $slot }}
    @endif

    {{ $modals ?? '' }}
    {{ $templates ?? '' }}
    {{ $beforeScripts ?? '' }}

    @if($showHeader)
      @vite('laravel/resources/js/pages/layout/header-language-menu.js')
    @endif

    {{ $scripts ?? '' }}
  </body>
</html>
