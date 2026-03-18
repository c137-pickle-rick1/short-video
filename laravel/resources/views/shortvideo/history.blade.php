<!doctype html>
<html lang="zh-CN">
  <head>
{!! $documentHead !!}
  </head>
  <body class="overflow-x-hidden bg-white text-gray-900 antialiased">
@php
  $historyPagination = is_array($historyPagination ?? null) ? $historyPagination : [];
  $historyPaginationLinks = is_array($historyPagination['links'] ?? null) ? $historyPagination['links'] : [];
  $historyHasAnyRecords = (int) ($historyPagination['totalCount'] ?? 0) > 0;
@endphp
    <main class="relative z-10">
{!! $pageHeader !!}

      <div class="h-[68px] sm:h-20" aria-hidden="true"></div>

      <div class="mx-auto w-full max-w-screen-2xl">
        <div class="flex flex-col gap-3 p-3 sm:gap-4 sm:p-4 lg:gap-5 lg:p-5 xl:gap-6 xl:p-6 2xl:gap-7 2xl:p-7 lg:flex-row lg:items-start">
{!! $desktopNavigation !!}
{!! $mobileNavigation !!}
          <section class="min-w-0 flex-1">
            <div class="grid gap-5 lg:gap-6 xl:gap-7">
              @include('shortvideo.partials.foundation.navigation-bar', [
                'title' => $page['title'] ?? '观看记录',
                'containerClass' => 'relative flex h-12 items-center justify-center rounded-full border border-gray-200 bg-white/90 px-1 shadow-sm backdrop-blur-xl',
                'titleWrapperClass' => 'min-w-0 px-14 text-center',
                'titleClass' => 'truncate text-sm font-semibold tracking-[0.08em] text-gray-950 sm:text-base',
                'leadingAction' => [
                  'iconClass' => 'ph ph-arrow-left',
                  'label' => '返回上一页',
                  'attributes' => [
                    'type' => 'button',
                    'data-history-back' => 'true',
                    'data-fallback-url' => route('profile.me'),
                    'class' => 'inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-700 transition hover:text-gray-950',
                  ],
                ],
                'trailingAction' => [
                  'iconClass' => 'ph ph-trash',
                  'label' => '清空记录',
                  'showLabel' => true,
                  'attributes' => [
                    'type' => 'button',
                    'data-history-clear-all' => 'true',
                    'data-clear-url' => '/api/history',
                    'class' => 'inline-flex h-9 items-center justify-center gap-2 rounded-full px-4 text-sm font-semibold text-gray-700 transition hover:text-rose-600 disabled:cursor-not-allowed disabled:text-gray-300',
                    'disabled' => ! $historyHasAnyRecords,
                  ],
                ],
              ])

              <p
                data-history-feedback="true"
                class="hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700"
                hidden
              ></p>

              <section
                data-history-record-grid="true"
                data-history-previous-page-url="{{ $historyPagination['previousPageUrl'] ?? '' }}"
                data-history-per-page="{{ $historyPagination['perPage'] ?? 12 }}"
                data-history-total-count="{{ $historyPagination['totalCount'] ?? 0 }}"
                class="grid grid-cols-2 gap-3 sm:gap-4 lg:gap-5 xl:grid-cols-3 xl:gap-6 2xl:grid-cols-4 2xl:gap-7"
                aria-live="polite"
                @if(empty($historyHasItems)) hidden @endif
              >
{!! $historyItemsMarkup !!}
              </section>

              @if(!empty($historyPagination['hasPages']))
                <nav data-history-pagination="true" aria-label="观看记录分页" class="rounded-[28px] border border-gray-200 bg-white/90 px-4 py-4 shadow-sm sm:px-5">
                  <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <p class="text-sm font-medium text-gray-600">
                      共 <span data-history-total-count="true" class="font-semibold text-gray-950">{{ $historyPagination['totalCount'] ?? 0 }}</span> 条记录，
                      第 {{ $historyPagination['currentPage'] ?? 1 }} / {{ $historyPagination['lastPage'] ?? 1 }} 页
                    </p>

                    <div class="flex flex-wrap items-center gap-2">
                      @if(!empty($historyPagination['previousPageUrl']))
                        <a
                          href="{{ $historyPagination['previousPageUrl'] }}"
                          aria-label="上一页"
                          class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 text-gray-700 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-950"
                        >
                          <i class="ph ph-caret-left text-base leading-none" aria-hidden="true"></i>
                        </a>
                      @else
                        <span
                          aria-label="上一页"
                          class="inline-flex h-10 w-10 cursor-not-allowed items-center justify-center rounded-full border border-gray-200 text-gray-300"
                        >
                          <i class="ph ph-caret-left text-base leading-none" aria-hidden="true"></i>
                        </span>
                      @endif

                      @foreach($historyPaginationLinks as $link)
                        @if(($link['type'] ?? 'page') === 'ellipsis')
                          <span class="inline-flex h-10 min-w-10 items-center justify-center px-1 text-sm font-semibold text-gray-400">…</span>
                        @elseif(!empty($link['active']))
                          <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-950 text-sm font-semibold text-white">
                            {{ $link['label'] ?? '' }}
                          </span>
                        @else
                          <a
                            href="{{ $link['url'] ?? '#' }}"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-950"
                          >
                            {{ $link['label'] ?? '' }}
                          </a>
                        @endif
                      @endforeach

                      @if(!empty($historyPagination['nextPageUrl']))
                        <a
                          href="{{ $historyPagination['nextPageUrl'] }}"
                          aria-label="下一页"
                          class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 text-gray-700 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-950"
                        >
                          <i class="ph ph-caret-right text-base leading-none" aria-hidden="true"></i>
                        </a>
                      @else
                        <span
                          aria-label="下一页"
                          class="inline-flex h-10 w-10 cursor-not-allowed items-center justify-center rounded-full border border-gray-200 text-gray-300"
                        >
                          <i class="ph ph-caret-right text-base leading-none" aria-hidden="true"></i>
                        </span>
                      @endif
                    </div>
                  </div>
                </nav>
              @endif

              <div data-history-empty-state="true" @if(!empty($historyHasItems)) hidden @endif>
                {!! $historyEmptyMarkup !!}
              </div>
            </div>
          </section>
        </div>
        <div class="h-24 lg:hidden" aria-hidden="true"></div>
      </div>
    </main>

    @if(!empty($authModalMarkup))
{!! $authModalMarkup !!}
    @endif

    @vite('laravel/resources/js/app/headerLanguageMenu.js')
    @vite('laravel/resources/js/historyPage.js')
    @if(!empty($authModalMarkup))
      @vite('laravel/resources/js/authModal.js')
    @endif
  </body>
</html>
