@props([
  'wrapperClass' => '',
  'searchUrl' => '',
  'searchQuery' => '',
])

@php
  $languageMenuId = 'header-language-menu-'.\Illuminate\Support\Str::ulid();
  $languages = [
      ['label' => '简体中文', 'description' => 'Chinese', 'code' => 'ZH', 'current' => true],
      ['label' => 'English', 'description' => 'English', 'code' => 'EN', 'current' => false],
      ['label' => '日本語', 'description' => 'Japanese', 'code' => 'JP', 'current' => false],
      ['label' => '한국어', 'description' => 'Korean', 'code' => 'KR', 'current' => false],
  ];
  $currentLanguage = collect($languages)->firstWhere('current', true) ?? $languages[0];
@endphp

<header class="{{ $wrapperClass }}">
  <div class="mx-auto flex w-full max-w-screen-2xl items-center gap-3 px-3 py-3 sm:gap-4 sm:px-4 sm:py-4 lg:gap-5 lg:px-5 lg:py-4 xl:gap-6 xl:px-6 xl:py-4 2xl:gap-7 2xl:px-7 2xl:py-4">
    <div class="shrink-0">
      <div class="flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-gray-100 sm:h-12 sm:w-12">
        <span class="h-2.5 w-2.5 rounded-full bg-gray-900"></span>
      </div>
    </div>
    <form
      method="GET"
      action="{{ $searchUrl }}"
      role="search"
      class="mx-auto flex h-11 w-full min-w-0 max-w-[480px] items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-3 sm:h-12 sm:gap-3 sm:px-4"
    >
      <button type="submit" class="text-base text-gray-500 transition hover:text-gray-700" aria-label="搜索">
        <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
      </button>
      <input
        type="search"
        name="q"
        value="{{ $searchQuery }}"
        placeholder="搜索视频、作者或关键词"
        class="min-w-0 flex-1 bg-transparent text-sm text-gray-900 outline-none placeholder:text-gray-400"
        autocomplete="off"
      />
    </form>
    <div class="flex shrink-0 items-center gap-2 sm:gap-3">
      <div class="relative" data-language-menu="true">
        <button
          type="button"
          data-language-menu-trigger="true"
          aria-label="切换语言"
          aria-haspopup="menu"
          aria-expanded="false"
          aria-controls="{{ $languageMenuId }}"
          title="切换语言"
          class="inline-flex h-11 max-w-[9.5rem] items-center gap-2 rounded-full border border-gray-200 bg-white px-3 text-[15px] font-medium text-gray-700 transition hover:border-gray-300 hover:text-gray-900 sm:h-12 sm:max-w-[11rem] sm:px-4"
        >
          <i class="ph ph-globe text-base leading-none text-gray-500" aria-hidden="true"></i>
          <span data-language-menu-current-label="true" class="min-w-0 flex-1 truncate">{{ $currentLanguage['label'] }}</span>
          <i
            class="ph ph-caret-down text-base leading-none text-gray-500 transition duration-200"
            data-language-menu-chevron="true"
            aria-hidden="true"
          ></i>
        </button>

        <section
          id="{{ $languageMenuId }}"
          data-language-menu-panel="true"
          class="absolute right-0 top-full z-50 mt-3 hidden min-w-[15rem] max-w-[18rem] overflow-hidden rounded-[28px] border border-gray-200 bg-white p-2 shadow-glass"
          role="menu"
          aria-label="语言菜单"
          aria-hidden="true"
          hidden
        >
          <div class="grid gap-1">
            @foreach($languages as $language)
              <button
                type="button"
                data-language-menu-item="true"
                data-language-label="{{ $language['label'] }}"
                data-current="{{ $language['current'] ? 'true' : 'false' }}"
                @class([
                    'flex w-full items-center justify-between gap-4 rounded-2xl px-4 py-3 text-left text-[15px] transition hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none',
                    'font-semibold text-gray-900' => $language['current'],
                    'font-normal text-gray-700' => ! $language['current'],
                ])
                role="menuitemradio"
                aria-checked="{{ $language['current'] ? 'true' : 'false' }}"
              >
                <span class="min-w-0 truncate">{{ $language['label'] }}</span>
                <span
                  data-language-menu-check="true"
                  @class([
                      'text-lg leading-none text-gray-900',
                      'hidden' => ! $language['current'],
                  ])
                  aria-hidden="true"
                  @if(! $language['current']) hidden @endif
                >
                  <i class="ph ph-check"></i>
                </span>
              </button>
            @endforeach
          </div>
        </section>
      </div>
    </div>
  </div>
</header>
