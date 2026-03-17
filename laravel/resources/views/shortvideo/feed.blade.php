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
              <header class="rounded-[32px] border border-gray-200 bg-white px-5 py-6 shadow-sm sm:px-6 sm:py-7 lg:px-8 lg:py-8">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-rose-500">{{ $page['eyebrow'] }}</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-gray-950 sm:text-4xl">{{ $page['title'] }}</h1>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-gray-600 sm:text-base">{{ $page['description'] }}</p>
              </header>

              @if(!empty($toolbarMarkup))
{!! $toolbarMarkup !!}
              @endif

              @if(($state ?? null) !== 'ready' && ($state ?? null) !== null)
                <section class="grid gap-5">
                  @if(($state ?? null) === 'guest')
                    <article class="rounded-[32px] border border-gray-200 bg-gradient-to-br from-stone-50 via-white to-rose-50/50 px-5 py-6 shadow-sm sm:px-6 sm:py-7 lg:px-8 lg:py-8">
                      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                          <h2 class="text-2xl font-semibold tracking-tight text-gray-950">登录后查看订阅更新</h2>
                          <p class="mt-3 max-w-2xl text-sm leading-7 text-gray-600">
                            订阅页只展示你关注创作者的最新内容。先登录，再建立自己的长期追更列表。
                          </p>
                        </div>
                        <a
                          href="{{ $loginUrl }}"
                          class="inline-flex h-11 items-center justify-center rounded-full bg-gray-900 px-5 text-sm font-semibold text-white transition hover:bg-gray-800"
                        >
                          去登录
                        </a>
                      </div>
                    </article>
                  @elseif(($state ?? null) === 'empty_following')
                    <article class="rounded-[32px] border border-gray-200 bg-white px-5 py-6 shadow-sm sm:px-6 sm:py-7 lg:px-8 lg:py-8">
                      <h2 class="text-2xl font-semibold tracking-tight text-gray-950">先关注几个创作者</h2>
                      <p class="mt-3 max-w-2xl text-sm leading-7 text-gray-600">
                        你还没有任何订阅关系。下面先按近 7 天活跃度推荐一批创作者，关注后页面会立即刷新为订阅流。
                      </p>
                    </article>
                  @elseif(($state ?? null) === 'empty_updates')
                    <article class="rounded-[32px] border border-dashed border-gray-200 bg-white px-5 py-6 shadow-sm sm:px-6 sm:py-7 lg:px-8 lg:py-8">
                      <h2 class="text-2xl font-semibold tracking-tight text-gray-950">关注的创作者最近没有更新</h2>
                      <p class="mt-3 max-w-2xl text-sm leading-7 text-gray-600">
                        当前订阅关系已经建立，但最近 7 天没有新的公开内容。可以先从推荐列表补充新的候选创作者。
                      </p>
                    </article>
                  @endif

                  @if(!empty($recommendations))
                    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                      @foreach($recommendations as $item)
                        <article class="rounded-[30px] border border-gray-200 bg-white px-5 py-5 shadow-sm">
                          <div class="flex items-start justify-between gap-4">
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
                            @include('shortvideo.partials.follow-button', ['creator' => $item, 'loginUrl' => $loginUrl, 'reloadOnSuccess' => true, 'size' => 'compact'])
                          </div>

                          <div class="mt-5 grid grid-cols-3 gap-3 text-center">
                            <div class="rounded-2xl bg-gray-50 px-3 py-3">
                              <p class="text-xs uppercase tracking-[0.14em] text-gray-400">7天更新</p>
                              <p class="mt-2 text-lg font-semibold text-gray-950">{{ $item['publishedCount7d'] }}</p>
                            </div>
                            <div class="rounded-2xl bg-gray-50 px-3 py-3">
                              <p class="text-xs uppercase tracking-[0.14em] text-gray-400">累计视频</p>
                              <p class="mt-2 text-lg font-semibold text-gray-950">{{ $item['totalVideos'] }}</p>
                            </div>
                            <div class="rounded-2xl bg-gray-50 px-3 py-3">
                              <p class="text-xs uppercase tracking-[0.14em] text-gray-400">最近更新</p>
                              <p class="mt-2 text-sm font-semibold text-gray-950">
                                {{
                                  $item['lastPublishedAt']
                                    ? \Carbon\CarbonImmutable::parse($item['lastPublishedAt'])->diffForHumans()
                                    : '未知'
                                }}
                              </p>
                            </div>
                          </div>
                        </article>
                      @endforeach
                    </section>
                  @endif
                </section>
              @endif

              @if(!empty($feedGrid))
{!! $feedGrid !!}
              @endif
            </div>
          </section>
        </div>
        <div class="h-24 lg:hidden" aria-hidden="true"></div>
      </div>
    </main>

    @if(!empty($emptyStateMarkup))
      <template id="empty-state-template">
        {!! $emptyStateMarkup !!}
      </template>
    @endif

    @if(!empty($detailModalMarkup))
{!! $detailModalMarkup !!}
    @endif

    @if(!empty($feedBootstrapData))
      <script id="feed-bootstrap" type="application/json">{!! $feedBootstrapData !!}</script>
    @endif

    @if(!empty($feedScriptsEnabled))
      <script src="/vendor/plyr/plyr.min.js"></script>
      <script src="/vendor/colcade/colcade.js"></script>
      <script src="/vendor/hls/hls.min.js"></script>
      <script type="module" src="/app.js"></script>
    @endif

    @if(!empty($recommendations))
      <script type="module" src="/socialGraph.js"></script>
    @endif
  </body>
</html>
