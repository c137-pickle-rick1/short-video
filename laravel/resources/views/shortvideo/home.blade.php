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
            <div class="grid gap-6 lg:gap-7 xl:gap-8">
              <header class="rounded-[32px] border border-gray-200 bg-gradient-to-br from-stone-50 via-white to-rose-50/40 px-5 py-6 shadow-sm sm:px-6 sm:py-7 lg:px-8 lg:py-8">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-rose-500">Home</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-gray-950 sm:text-4xl">轻推荐首页</h1>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-gray-600 sm:text-base">
                  首页只负责把你送进真正的内容消费场景。探索承接全量发现，订阅承接稳定追更，榜单负责快速识别最近最活跃的创作者。
                </p>
              </header>

              <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">
                <section class="overflow-hidden rounded-[32px] border border-gray-200 bg-gray-950 text-white shadow-[0_24px_80px_rgba(17,24,39,0.18)]">
                  <div class="grid gap-8 px-5 py-6 sm:px-6 sm:py-7 lg:px-8 lg:py-8">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                      <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-rose-200/90">Explore</p>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight text-white">继续探索</h2>
                        <p class="mt-3 max-w-xl text-sm leading-7 text-white/72">
                          完整瀑布流、来源切换和详情弹层都放在探索页。这里仅保留最近一周的内容密度和入口。
                        </p>
                      </div>
                      <a
                        href="{{ $exploreUrl }}"
                        class="inline-flex h-11 items-center justify-center rounded-full bg-white px-5 text-sm font-semibold text-gray-950 transition hover:bg-rose-50"
                      >
                        进入探索
                      </a>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                      <article class="rounded-3xl border border-white/10 bg-white/5 px-4 py-4 backdrop-blur">
                        <p class="text-xs uppercase tracking-[0.14em] text-white/55">近 7 天内容</p>
                        <p class="mt-3 text-3xl font-semibold text-white">{{ $home['explore']['recentPublishedCount7d'] }}</p>
                      </article>
                      <article class="rounded-3xl border border-white/10 bg-white/5 px-4 py-4 backdrop-blur">
                        <p class="text-xs uppercase tracking-[0.14em] text-white/55">累计公开内容</p>
                        <p class="mt-3 text-3xl font-semibold text-white">{{ $home['explore']['totalItems'] }}</p>
                      </article>
                      <article class="rounded-3xl border border-white/10 bg-white/5 px-4 py-4 backdrop-blur">
                        <p class="text-xs uppercase tracking-[0.14em] text-white/55">最近同步</p>
                        <p class="mt-3 text-lg font-semibold text-white">
                          {{
                            $home['explore']['lastUpdatedAt']
                              ? \Carbon\CarbonImmutable::parse($home['explore']['lastUpdatedAt'])->diffForHumans()
                              : '等待首次同步'
                          }}
                        </p>
                      </article>
                    </div>
                  </div>
                </section>

                <section class="rounded-[32px] border border-gray-200 bg-white px-5 py-6 shadow-sm sm:px-6 sm:py-7">
                  <div class="flex items-center justify-between gap-4">
                    <div>
                      <p class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-600">Rankings</p>
                      <h2 class="mt-3 text-2xl font-semibold tracking-tight text-gray-950">活跃创作者 TOP 5</h2>
                    </div>
                    <a
                      href="{{ $rankingsUrl }}"
                      class="inline-flex h-10 items-center justify-center rounded-full border border-gray-200 px-4 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:text-gray-950"
                    >
                      查看完整榜单
                    </a>
                  </div>

                  <div class="mt-6 grid gap-3">
                    @forelse($home['rankings']['items'] as $item)
                      <article class="flex items-center gap-4 rounded-3xl border border-gray-200 bg-gray-50/80 px-4 py-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gray-900 text-sm font-semibold text-white">
                          #{{ $item['rank'] }}
                        </div>
                        <div class="min-w-0 flex-1">
                          <p class="truncate text-sm font-semibold text-gray-950">{{ $item['creator']['name'] }}</p>
                          <p class="mt-1 text-xs text-gray-500">&#64;{{ $item['creator']['username'] }}</p>
                        </div>
                        <div class="text-right text-xs text-gray-500">
                          <p class="font-semibold text-gray-900">{{ $item['publishedCount7d'] }} 条 / 7天</p>
                          <p class="mt-1">{{ $item['totalVideos'] }} 条累计</p>
                        </div>
                      </article>
                    @empty
                      <article class="rounded-3xl border border-dashed border-gray-200 px-4 py-6 text-sm text-gray-500">
                        还没有足够的近 7 天活跃数据生成榜单预览。
                      </article>
                    @endforelse
                  </div>
                </section>
              </div>

              <section class="rounded-[32px] border border-gray-200 bg-white px-5 py-6 shadow-sm sm:px-6 sm:py-7 lg:px-8 lg:py-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                  <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-amber-600">Subscriptions</p>
                    <h2 class="mt-3 text-2xl font-semibold tracking-tight text-gray-950">订阅更新预览</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-gray-600">
                      订阅页只保留你关注创作者的更新，和探索流完全分开。这里展示的是一个轻量预览，不承载完整内容消费。
                    </p>
                  </div>
                  <a
                    href="{{ $subscriptionsUrl }}"
                    class="inline-flex h-10 items-center justify-center rounded-full border border-gray-200 px-4 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:text-gray-950"
                  >
                    打开订阅页
                  </a>
                </div>

                @if($home['subscriptions']['state'] === 'guest')
                  <article class="mt-6 flex flex-col gap-4 rounded-[28px] border border-gray-200 bg-gray-50 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                      <h3 class="text-lg font-semibold text-gray-950">登录后建立稳定订阅流</h3>
                      <p class="mt-2 text-sm leading-7 text-gray-600">关注你想长期追更的创作者后，订阅页会只呈现他们的最新内容。</p>
                    </div>
                    <a
                      href="{{ $loginUrl }}"
                      class="inline-flex h-11 items-center justify-center rounded-full bg-gray-900 px-5 text-sm font-semibold text-white transition hover:bg-gray-800"
                    >
                      去登录
                    </a>
                  </article>
                @elseif($home['subscriptions']['state'] === 'empty_following')
                  <article class="mt-6 rounded-[28px] border border-dashed border-gray-200 px-5 py-6 text-sm leading-7 text-gray-600">
                    你还没有关注任何创作者。先去订阅页挑几个账号，首页才会开始出现更新预览。
                  </article>
                @elseif($home['subscriptions']['state'] === 'empty_updates')
                  <article class="mt-6 rounded-[28px] border border-dashed border-gray-200 px-5 py-6 text-sm leading-7 text-gray-600">
                    你已经建立了订阅关系，但最近还没有新的更新内容，可以先去探索页补充候选创作者。
                  </article>
                @else
                  <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    @foreach($home['subscriptions']['items'] as $item)
                      <article class="rounded-[28px] border border-gray-200 bg-gray-50/70 px-4 py-4">
                        <p class="line-clamp-2 text-sm font-semibold leading-6 text-gray-950">
                          {{ \Illuminate\Support\Str::limit(preg_replace('/https?:\/\/\S+/u', ' ', (string) ($item['text'] ?? '')) ?: '未填写内容文案', 58) }}
                        </p>
                        <div class="mt-4 flex items-center justify-between gap-3">
                          <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-900">{{ $item['authorName'] ?? '@'.$item['authorHandle'] }}</p>
                            <p class="mt-1 truncate text-xs text-gray-500">&#64;{{ $item['authorHandle'] }}</p>
                          </div>
                          <span class="shrink-0 text-xs text-gray-500">
                            {{
                              !empty($item['postedAt'])
                                ? \Carbon\CarbonImmutable::parse($item['postedAt'])->diffForHumans()
                                : '未知时间'
                            }}
                          </span>
                        </div>
                      </article>
                    @endforeach
                  </div>
                @endif
              </section>
            </div>
          </section>
        </div>
        <div class="h-24 lg:hidden" aria-hidden="true"></div>
      </div>
    </main>
  </body>
</html>
