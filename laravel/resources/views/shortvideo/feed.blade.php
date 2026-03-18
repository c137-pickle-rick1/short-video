<!doctype html>
<html lang="zh-CN">
  <head>
{!! $documentHead !!}
  </head>
  <body class="overflow-x-hidden bg-white text-gray-900 antialiased">
    @php
      $subscriptionsFollowTabs = is_array($subscriptionsFollowTabs ?? null) ? $subscriptionsFollowTabs : [];
      $selectedSubscriptionsAccount = is_array($selectedSubscriptionsAccount ?? null) ? $selectedSubscriptionsAccount : [];
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
              @if(!empty($subscriptionsFollowTabs))
                <section class="overflow-hidden rounded-[32px] border border-gray-200 bg-white shadow-sm">
                  <nav
                    aria-label="已关注账号"
                    data-subscriptions-follow-tabs="true"
                    data-subscriptions-selected-account="{{ $selectedSubscriptionsAccount['username'] ?? '' }}"
                    class="detail-mobile-scroller flex gap-3 overflow-x-auto px-[28px] pt-4 sm:gap-4"
                  >
                    @foreach($subscriptionsFollowTabs as $tab)
                      <a
                        href="{{ route('subscriptions', ['account' => $tab['username']]) }}"
                        aria-label="{{ $tab['name'] }}"
                        title="{{ $tab['name'] }}"
                        data-subscriptions-follow-tab="{{ $tab['username'] }}"
                        data-active="{{ $tab['active'] ? 'true' : 'false' }}"
                        class="relative flex w-14 shrink-0 flex-col items-center pb-4 text-center transition sm:w-16"
                      >
                        @include('shortvideo.partials.feed.avatar', [
                          'imageUrl' => $tab['avatarUrl'] ?? null,
                          'label' => $tab['name'] ?? ('@'.$tab['username']),
                          'initial' => $tab['avatarInitial'] ?? 'L',
                          'sizeClass' => 'h-14 w-14 sm:h-16 sm:w-16',
                          'fallbackClass' => ($tab['active'] ?? false) ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700',
                          'imageClass' => ($tab['active'] ?? false) ? 'ring-gray-900/10' : '',
                        ])
                        <span
                          @class([
                            'absolute bottom-0 left-1/2 h-1 w-6 -translate-x-1/2 rounded-full bg-gray-950' => $tab['active'] ?? false,
                            'hidden' => !($tab['active'] ?? false),
                          ])
                          aria-hidden="true"
                        ></span>
                      </a>
                    @endforeach
                  </nav>
                </section>
              @endif

              @if(!empty($toolbarMarkup))
{!! $toolbarMarkup !!}
              @endif

              @if(($state ?? null) !== 'ready' && ($state ?? null) !== null)
                <section class="grid gap-5">
                  @if(($state ?? null) === 'guest')
                    @include('shortvideo.partials.feed.empty-state-card', [
                      'iconClass' => 'ph ph-sign-in',
                      'title' => '登录后查看订阅更新',
                      'description' => '订阅页只展示你关注创作者的最新内容。先登录，再建立自己的长期追更列表。',
                      'buttonLabel' => '去登录',
                      'buttonHref' => $loginUrl,
                      'buttonAttributes' => [
                        'data-auth-modal-trigger' => 'true',
                        'data-auth-modal-panel' => 'login',
                      ],
                    ])
                  @elseif(($state ?? null) === 'empty_following')
                    @include('shortvideo.partials.feed.empty-state-card', [
                      'iconClass' => 'ph ph-user-plus',
                      'title' => '先关注几个创作者',
                      'description' => '你还没有任何订阅关系。下面先按近 7 天活跃度推荐一批创作者，关注后页面会立即刷新为订阅流。',
                    ])
                  @elseif(($state ?? null) === 'empty_updates')
                    @include('shortvideo.partials.feed.empty-state-card', [
                      'iconClass' => 'ph ph-bell-slash',
                      'title' => '关注的创作者最近没有更新',
                      'description' => '当前订阅关系已经建立，但最近 7 天没有新的公开内容。可以先从推荐列表补充新的候选创作者。',
                    ])
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

    @if(!empty($authModalMarkup))
{!! $authModalMarkup !!}
    @endif

    @if(!empty($feedBootstrapData))
      <script id="feed-bootstrap" type="application/json">{!! $feedBootstrapData !!}</script>
    @endif

    @vite('laravel/resources/js/app/headerLanguageMenu.js')

    @if(!empty($feedScriptsEnabled))
      <script src="/vendor/plyr/plyr.min.js"></script>
      <script src="/vendor/colcade/colcade.js"></script>
      <script src="/vendor/hls/hls.min.js"></script>
      @vite('laravel/resources/js/app.js')
    @endif

    @if(!empty($recommendations))
      @vite('laravel/resources/js/socialGraph.js')
    @endif

    @if(!empty($authModalMarkup))
      @vite('laravel/resources/js/authModal.js')
    @endif
  </body>
</html>
