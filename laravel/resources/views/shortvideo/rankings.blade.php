<!doctype html>
<html lang="zh-CN">
  <head>
{!! $documentHead !!}
  </head>
  <body class="overflow-x-hidden bg-white text-gray-900 antialiased">
    <main class="relative z-10">
{!! $pageHeader !!}

      <div class="h-[68px] sm:h-20" aria-hidden="true"></div>

      <div class="mx-auto w-full max-w-screen-2xl">
        <div class="flex flex-col gap-3 p-3 sm:gap-4 sm:p-4 lg:gap-5 lg:p-5 xl:gap-6 xl:p-6 2xl:gap-7 2xl:p-7 lg:flex-row lg:items-start">
{!! $desktopNavigation !!}
{!! $mobileNavigation !!}
          <section class="min-w-0 flex-1">
            <div class="grid gap-5 lg:gap-6 xl:gap-7">
              <section class="grid gap-3">
                @forelse($rankingItems as $item)
                  <article class="flex flex-col gap-4 rounded-[28px] border border-gray-200 bg-gray-50/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div class="flex min-w-0 items-center gap-4">
                      <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gray-900 text-sm font-semibold text-white">
                        #{{ $item['rank'] }}
                      </div>
                      <div class="flex min-w-0 items-center gap-3">
                        @if(!empty($item['creator']['avatarUrl']))
                          <img
                            class="h-12 w-12 rounded-full object-cover ring-1 ring-gray-200"
                            src="{{ $item['creator']['avatarUrl'] }}"
                            alt="{{ $item['creator']['name'] }} 的头像"
                            loading="lazy"
                            referrerpolicy="no-referrer"
                          />
                        @else
                          <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-700">
                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(ltrim($item['creator']['name'], '@'), 0, 1)) }}
                          </span>
                        @endif
                        <div class="min-w-0">
                          <p class="truncate text-base font-semibold text-gray-950">{{ $item['creator']['name'] }}</p>
                          <p class="mt-1 truncate text-sm text-gray-500">&#64;{{ $item['creator']['username'] }}</p>
                        </div>
                      </div>
                    </div>

                    <div class="grid min-w-0 flex-1 gap-3 sm:grid-cols-[repeat(3,minmax(0,140px))_auto] sm:items-center sm:justify-end">
                      <div class="rounded-2xl bg-white px-3 py-3 text-center">
                        <p class="text-xs uppercase tracking-[0.14em] text-gray-400">7天更新</p>
                        <p class="mt-2 text-lg font-semibold text-gray-950">{{ $item['publishedCount7d'] }}</p>
                      </div>
                      <div class="rounded-2xl bg-white px-3 py-3 text-center">
                        <p class="text-xs uppercase tracking-[0.14em] text-gray-400">累计视频</p>
                        <p class="mt-2 text-lg font-semibold text-gray-950">{{ $item['totalVideos'] }}</p>
                      </div>
                      <div class="rounded-2xl bg-white px-3 py-3 text-center">
                        <p class="text-xs uppercase tracking-[0.14em] text-gray-400">最近更新</p>
                        <p class="mt-2 text-sm font-semibold text-gray-950">
                          {{
                            $item['lastPublishedAt']
                              ? \Carbon\CarbonImmutable::parse($item['lastPublishedAt'])->diffForHumans()
                              : '未知'
                          }}
                        </p>
                      </div>
                      <div class="sm:justify-self-end">
                        @include('shortvideo.partials.follow-button', ['creator' => $item, 'loginUrl' => $loginUrl])
                      </div>
                    </div>
                  </article>
                @empty
                  <article class="rounded-[28px] border border-dashed border-gray-200 px-5 py-8 text-sm leading-7 text-gray-500">
                    还没有足够的近 7 天活跃数据来生成创作者榜单。
                  </article>
                @endforelse
              </section>
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
    @vite('laravel/resources/js/socialGraph.js')
    @if(!empty($authModalMarkup))
      @vite('laravel/resources/js/authModal.js')
    @endif
  </body>
</html>
