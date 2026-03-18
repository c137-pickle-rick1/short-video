<!doctype html>
<html lang="zh-CN">
  <head>
{!! $documentHead !!}
  </head>
  <body class="overflow-x-hidden bg-white text-gray-900 antialiased">
@php
  $interactionPagination = is_array($interactionPagination ?? null) ? $interactionPagination : [];
  $interactionPaginationLinks = is_array($interactionPagination['links'] ?? null) ? $interactionPagination['links'] : [];
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
                'title' => $page['title'] ?? '我的互动',
                'containerClass' => 'relative flex h-12 items-center justify-center rounded-full border border-gray-200 bg-white/90 px-1 shadow-sm backdrop-blur-xl',
                'titleWrapperClass' => 'min-w-0 px-14 text-center',
                'titleClass' => 'truncate text-sm font-semibold tracking-[0.08em] text-gray-950 sm:text-base',
                'leadingAction' => [
                  'iconClass' => 'ph ph-arrow-left',
                  'label' => '返回上一页',
                  'attributes' => [
                    'type' => 'button',
                    'data-interaction-back' => 'true',
                    'data-fallback-url' => route('profile.me'),
                    'class' => 'inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-700 transition hover:text-gray-950',
                  ],
                ],
              ])

              <p
                data-interaction-feedback="true"
                class="hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700"
                hidden
              ></p>

              <section
                data-interaction-list="true"
                data-interaction-previous-page-url="{{ $interactionPagination['previousPageUrl'] ?? '' }}"
                data-interaction-per-page="{{ $interactionPagination['perPage'] ?? 12 }}"
                data-interaction-total-count="{{ $interactionPagination['totalCount'] ?? 0 }}"
                class="overflow-hidden rounded-[32px] border border-gray-200 bg-white shadow-sm"
                aria-live="polite"
                @if(empty($interactionHasItems)) hidden @endif
              >
                <div class="divide-y divide-gray-200">
{!! $interactionItemsMarkup !!}
                </div>
              </section>

              @if(!empty($interactionPagination['hasPages']))
                <nav data-interaction-pagination="true" aria-label="我的互动分页" class="rounded-[28px] border border-gray-200 bg-white/90 px-4 py-4 shadow-sm sm:px-5">
                  <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <p class="text-sm font-medium text-gray-600">
                      共 <span data-interaction-total-count="true" class="font-semibold text-gray-950">{{ $interactionPagination['totalCount'] ?? 0 }}</span> 条互动，
                      第 {{ $interactionPagination['currentPage'] ?? 1 }} / {{ $interactionPagination['lastPage'] ?? 1 }} 页
                    </p>

                    <div class="flex flex-wrap items-center gap-2">
                      @if(!empty($interactionPagination['previousPageUrl']))
                        <a
                          href="{{ $interactionPagination['previousPageUrl'] }}"
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

                      @foreach($interactionPaginationLinks as $link)
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

                      @if(!empty($interactionPagination['nextPageUrl']))
                        <a
                          href="{{ $interactionPagination['nextPageUrl'] }}"
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

              <div data-interaction-empty-state="true" @if(!empty($interactionHasItems)) hidden @endif>
                {!! $interactionEmptyMarkup !!}
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
    @vite('laravel/resources/js/interactionsPage.js')
    @if(!empty($authModalMarkup))
      @vite('laravel/resources/js/authModal.js')
    @endif
  </body>
</html>
