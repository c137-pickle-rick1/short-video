@php
  $feed = is_array($feed ?? null) ? $feed : [];
  $isSubscriptionsPage = request()->routeIs('subscriptions') || request()->routeIs('subscriptions.show');
  $subscriptionsFollowTabs = is_array($subscriptionsFollowTabs ?? null) ? $subscriptionsFollowTabs : [];
  $selectedSubscriptionsAccount = is_array($selectedSubscriptionsAccount ?? null) ? $selectedSubscriptionsAccount : [];
  $hasSelectedSubscriptionsAccount = !empty($selectedSubscriptionsAccount);
  $feedItems = is_array($feed['gridItems'] ?? null) ? $feed['gridItems'] : [];
  $feedEmptyState = is_array($feed['emptyState'] ?? null) ? $feed['emptyState'] : null;
  $hasSubscriptionsFollowList = !empty($subscriptionsFollowTabs);
  $subscriptionsMobileNavTitle = $hasSelectedSubscriptionsAccount
    ? (string) ($selectedSubscriptionsAccount['name'] ?? $selectedSubscriptionsAccount['username'] ?? '订阅')
    : ($hasSubscriptionsFollowList ? '已关注' : (string) (($page['title'] ?? '订阅')));
@endphp

<x-shortvideo.layout.app-shell :shell="$shell">
  <div @class([
    'grid gap-0 lg:gap-6 xl:gap-7' => true,
    'min-h-[calc(100dvh-68px)] sm:min-h-[calc(100dvh-80px)]' => $isSubscriptionsPage && !$hasSelectedSubscriptionsAccount,
    'lg:h-[calc(100dvh-100px)] xl:h-[calc(100dvh-104px)] 2xl:h-[calc(100dvh-108px)]' => ($state ?? null) === 'selection_required',
  ])>
    @if($isSubscriptionsPage && $hasSelectedSubscriptionsAccount)
      <x-shortvideo.layout.navigation-bar
        :title="$subscriptionsMobileNavTitle"
        container-class="sticky top-[68px] z-30 -mx-3 -mt-3 flex h-12 items-center justify-center border-b border-gray-200 bg-white px-1 shadow-none sm:top-20 sm:-mx-4 sm:-mt-4 lg:hidden"
        title-wrapper-class="min-w-0 px-12 text-center"
        title-class="truncate text-sm font-semibold tracking-[0.08em] text-gray-950"
        :leading-action="$hasSelectedSubscriptionsAccount ? [
          'tag' => 'a',
          'href' => route('subscriptions'),
          'iconClass' => 'ph ph-arrow-left',
          'label' => '返回已关注列表',
          'attributes' => [
            'class' => 'inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-700 transition hover:text-gray-950',
          ],
        ] : null"
        data-subscriptions-page-nav="true"
      />
    @endif

    <div
    @class([
      'grid min-h-0 gap-0 lg:gap-6 xl:gap-7',
      'h-full' => $isSubscriptionsPage && ! $hasSelectedSubscriptionsAccount,
      'lg:grid-cols-[18rem_minmax(0,1fr)] xl:grid-cols-[19rem_minmax(0,1fr)]' => $hasSubscriptionsFollowList,
    ])
    >
    @if($hasSubscriptionsFollowList)
      <aside @class([
        'min-w-0 max-lg:min-h-0 lg:sticky lg:top-[100px] lg:self-start xl:top-[104px] 2xl:top-[108px]' => true,
        'max-lg:hidden' => $hasSelectedSubscriptionsAccount,
      ])>
        <section class="overflow-hidden bg-white max-lg:-mx-3 max-lg:flex max-lg:h-full max-lg:min-h-0 max-lg:flex-col max-lg:border-b max-lg:border-gray-200 sm:max-lg:-mx-4 lg:flex lg:h-[calc(100dvh-100px)] lg:flex-col lg:rounded-[32px] lg:border lg:border-gray-200 lg:shadow-sm xl:h-[calc(100dvh-104px)] 2xl:h-[calc(100dvh-108px)]">
          <nav
            aria-label="已关注账号"
            data-subscriptions-follow-list="true"
            data-subscriptions-selected-account="{{ $selectedSubscriptionsAccount['username'] ?? '' }}"
            class="detail-mobile-scroller flex min-h-0 flex-1 flex-col overflow-y-auto pb-24 lg:h-full lg:overscroll-contain lg:pb-0"
          >
            @foreach($subscriptionsFollowTabs as $tab)
              <a
                href="{{ route('subscriptions.show', ['account' => $tab['username']]) }}"
                data-subscriptions-account-item="true"
                data-subscriptions-account-username="{{ $tab['username'] }}"
                data-active="{{ ($tab['active'] ?? false) ? 'true' : 'false' }}"
                data-unread-count="{{ (int) ($tab['unreadVideosCount'] ?? 0) }}"
                aria-current="{{ ($tab['active'] ?? false) ? 'page' : 'false' }}"
                @class([
                  'group flex w-full items-center gap-3 px-5 py-4 transition lg:px-6',
                  'bg-gray-100 text-gray-900 hover:bg-gray-200' => $tab['active'] ?? false,
                  'text-gray-900 hover:bg-gray-50' => !($tab['active'] ?? false),
                  'border-t border-gray-200' => ! $loop->first,
                ])
              >
                <x-ui.avatar
                  :image-url="$tab['avatarUrl'] ?? null"
                  :label="$tab['name'] ?? $tab['username']"
                  :initial="$tab['avatarInitial'] ?? 'L'"
                  size-class="h-11 w-11"
                  :fallback-class="($tab['active'] ?? false) ? 'bg-white text-gray-700' : 'bg-gray-100 text-gray-700'"
                  :image-class="($tab['active'] ?? false) ? 'ring-gray-200' : ''"
                />

                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-semibold leading-6">{{ $tab['name'] }}</p>
                  <p @class([
                    'mt-0.5 truncate text-xs' => true,
                    'text-gray-500' => $tab['active'] ?? false,
                    'text-gray-500' => !($tab['active'] ?? false),
                  ])>
                    {{ ($tab['latestPublishedAtText'] ?? '暂无更新') === '暂无更新' ? '暂无更新' : (($tab['latestPublishedAtText'] ?? '').' 更新') }}
                  </p>
                </div>

                <div class="shrink-0">
                  <span
                    data-subscriptions-unread-badge="true"
                    data-unread-count="{{ (int) ($tab['unreadVideosCount'] ?? 0) }}"
                    @class([
                      'inline-flex min-w-7 items-center justify-center rounded-full px-2.5 py-1 text-xs font-semibold tabular-nums transition' => true,
                      'bg-rose-500 text-white' => ($tab['hasUnread'] ?? false) && ($tab['active'] ?? false),
                      'bg-rose-50 text-rose-600' => ($tab['hasUnread'] ?? false) && !($tab['active'] ?? false),
                      'hidden' => !($tab['hasUnread'] ?? false),
                    ])
                    @if(!($tab['hasUnread'] ?? false)) hidden @endif
                  >
                    {{ (int) ($tab['unreadVideosCount'] ?? 0) }}
                  </span>
                </div>
              </a>
            @endforeach
          </nav>
        </section>
      </aside>
    @endif

    <div @class([
      'min-w-0 grid gap-5 lg:gap-6 xl:gap-7' => true,
      'hidden lg:grid' => $hasSubscriptionsFollowList && !$hasSelectedSubscriptionsAccount,
    ])>
      @if(($state ?? null) !== 'ready' && ($state ?? null) !== null)
        <section @class([
          'grid gap-5' => true,
          'hidden lg:grid' => ($state ?? null) === 'selection_required',
        ])>
          @if(($state ?? null) === 'guest')
            <x-shortvideo.feed.empty-state
              icon-class="ph ph-sign-in"
              title="登录后查看订阅更新"
              description="订阅页只展示你关注创作者的最新内容。先登录，再建立自己的长期追更列表。"
              button-label="去登录"
              :button-href="$loginUrl"
              :button-attributes="[
                'data-auth-modal-trigger' => 'true',
                'data-auth-modal-panel' => 'login',
              ]"
            />
          @elseif(($state ?? null) === 'empty_following')
            <x-shortvideo.feed.empty-state
              icon-class="ph ph-user-plus"
              title="先关注几个创作者"
              description="你还没有任何订阅关系。下面先按近 7 天活跃度推荐一批创作者，关注后页面会立即刷新为订阅流。"
            />
          @elseif(($state ?? null) === 'empty_updates')
            <x-shortvideo.feed.empty-state
              icon-class="ph ph-bell-slash"
              title="关注的创作者最近没有更新"
              description="当前订阅关系已经建立，但最近 7 天没有新的公开内容。可以先从推荐列表补充新的候选创作者。"
            />
          @elseif(($state ?? null) === 'selection_required')
            <x-shortvideo.feed.empty-state
              icon-class="ph ph-user-circle-plus"
              title="选择一个已关注账号"
              description="从左侧列表进入某个订阅者，右侧只会展示这个账号的公开视频。"
            />
          @endif

          @if(!empty($recommendations))
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              @foreach($recommendations as $item)
                @php
                  $creator = is_array($item['creator'] ?? null) ? $item['creator'] : [];
                  $creatorName = (string) ($creator['name'] ?? '@unknown');
                  $creatorInitial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(ltrim($creatorName, '@'), 0, 1));
                @endphp
                <article class="rounded-[30px] border border-gray-200 bg-white px-5 py-5 shadow-sm">
                  <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-3">
                      <x-ui.avatar
                        :image-url="$creator['avatarUrl'] ?? null"
                        :label="$creatorName"
                        :initial="$creatorInitial"
                        size-class="h-12 w-12"
                        fallback-class="bg-gray-100 text-gray-700"
                      />
                      <div class="min-w-0">
                        <p class="truncate text-base font-semibold text-gray-950">{{ $creatorName }}</p>
                        <p class="mt-1 truncate text-sm text-gray-500">&#64;{{ $creator['username'] ?? 'unknown' }}</p>
                      </div>
                    </div>
                    <x-shortvideo.follow-button :creator="$item" :login-url="$loginUrl" :reload-on-success="true" size="compact" />
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

      @if(($feed['enabled'] ?? false) === true)
        <x-shortvideo.feed.grid
          :is-empty="($feed['gridIsEmpty'] ?? false) === true"
          :max-columns="(int) ($feed['gridMaxColumns'] ?? 4)"
        >
          @if(($feed['gridIsEmpty'] ?? false) === true && $feedEmptyState !== null)
            <x-shortvideo.feed.empty-state
              :title="(string) ($feedEmptyState['title'] ?? '')"
              :description="(string) ($feedEmptyState['description'] ?? '')"
              :icon-class="(string) ($feedEmptyState['iconClass'] ?? 'ph ph-magnifying-glass')"
              :button-label="$feedEmptyState['buttonLabel'] ?? null"
              :button-href="$feedEmptyState['buttonHref'] ?? null"
              :button-attributes="$feedEmptyState['buttonAttributes'] ?? []"
            />
          @else
            @foreach($feedItems as $item)
              @php
                $itemRootAttributes = is_array($item['rootAttributes'] ?? null) ? $item['rootAttributes'] : [];
                if ($hasSubscriptionsFollowList) {
                    $itemRootAttributes = array_merge($itemRootAttributes, [
                        'data-subscriptions-feed-card' => 'true',
                        'data-video-id' => isset($item['videoId']) ? (string) $item['videoId'] : '',
                        'data-viewed-by-viewer' => ($item['viewedByViewer'] ?? false) === true ? 'true' : 'false',
                        'data-is-new-for-viewer' => ($item['isNewForViewer'] ?? false) === true ? 'true' : 'false',
                    ]);
                }
              @endphp
              <x-shortvideo.feed.item
                :tweet-id="(string) ($item['tweetId'] ?? '')"
                :status="(string) ($item['status'] ?? 'pending')"
                :author-name="(string) ($item['authorName'] ?? '@unknown')"
                :display-text="(string) ($item['displayText'] ?? '')"
                :detail-url="$item['detailUrl'] ?? null"
                :posted-at-text="(string) ($item['postedAtText'] ?? '')"
                :title-line-clamp-class="(string) ($item['titleLineClampClass'] ?? 'line-clamp-2')"
                :interactive="($item['interactive'] ?? true) === true"
                :root-attributes="$itemRootAttributes"
                :card-class="(string) ($item['cardClass'] ?? '')"
              >
                @if($hasSubscriptionsFollowList && ($item['isNewForViewer'] ?? false) === true)
                  <x-slot:overlay>
                    <div class="pointer-events-none absolute left-3 top-3 z-10">
                      <span
                        data-feed-new-badge="true"
                        class="inline-flex h-6 items-center justify-center rounded-full bg-rose-500 px-3 text-[11px] font-semibold lowercase leading-none tracking-[0.08em] text-white shadow-lg shadow-rose-500/20"
                      >
                        new
                      </span>
                    </div>
                  </x-slot:overlay>
                @endif
                <x-slot:media>
                  <x-shortvideo.feed.media
                    :frame-class="(string) ($item['media']['frameClass'] ?? 'aspect-[4/5]')"
                    :poster-url="(string) ($item['media']['posterUrl'] ?? '')"
                    :hls-url="(string) ($item['media']['hlsUrl'] ?? '')"
                    :video-url="(string) ($item['media']['videoUrl'] ?? '')"
                    :author-handle="(string) ($item['media']['authorHandle'] ?? 'unknown')"
                    :video-preload="(string) ($item['media']['videoPreload'] ?? 'none')"
                    :show-video="($item['media']['showVideo'] ?? false) === true"
                    :duration-text="(string) ($item['media']['durationText'] ?? '')"
                  />
                </x-slot:media>
                <x-slot:author>
                  <x-shortvideo.feed.author-identity
                    :image-url="$item['author']['imageUrl'] ?? null"
                    :author-name="(string) ($item['author']['authorName'] ?? '@unknown')"
                    :author-handle="(string) ($item['author']['authorHandle'] ?? 'unknown')"
                    :author-initial="(string) ($item['author']['authorInitial'] ?? 'L')"
                    :avatar-size-class="(string) ($item['author']['avatarSizeClass'] ?? 'h-7 w-7')"
                    :name-class="(string) ($item['author']['nameClass'] ?? '')"
                    :handle-class="$item['author']['handleClass'] ?? null"
                    :wrapper-class="(string) ($item['author']['wrapperClass'] ?? 'flex min-w-0 items-center gap-3')"
                    :fallback-class="(string) ($item['author']['fallbackClass'] ?? 'bg-gray-100 text-gray-700')"
                    :image-class="(string) ($item['author']['imageClass'] ?? '')"
                    :profile-url="$item['author']['profileUrl'] ?? null"
                  />
                </x-slot:author>
              </x-shortvideo.feed.item>
            @endforeach
          @endif
        </x-shortvideo.feed.grid>
      @endif
    </div>
    </div>
  </div>

  <x-slot:modals>
    @if(($feed['detailModalEnabled'] ?? false) === true)
      <x-shortvideo.feed.detail-modal />
    @endif

    @if(($auth['shouldRenderModal'] ?? false) === true)
      <x-shortvideo.auth.modal
        :initial-panel="(string) ($auth['initialPanel'] ?? 'login')"
        :open="($auth['open'] ?? false) === true"
        :standalone="($auth['standalone'] ?? false) === true"
        :close-url="$auth['closeUrl'] ?? null"
        :login-form-action="(string) ($auth['loginFormAction'] ?? '')"
        :register-form-action="(string) ($auth['registerFormAction'] ?? '')"
        :reset-password-form-action="(string) ($auth['resetPasswordFormAction'] ?? '')"
        :send-code-action="(string) ($auth['sendCodeAction'] ?? '')"
        :login-email-value="(string) ($auth['loginEmailValue'] ?? '')"
        :login-email-error="$auth['loginEmailError'] ?? null"
        :password-error="$auth['passwordError'] ?? null"
        :status-message="$auth['statusMessage'] ?? null"
        :error-message="$auth['errorMessage'] ?? null"
      />
    @endif
  </x-slot:modals>

  <x-slot:templates>
    @if($feedEmptyState !== null)
      <template id="empty-state-template">
        <x-shortvideo.feed.empty-state
          :title="(string) ($feedEmptyState['title'] ?? '')"
          :description="(string) ($feedEmptyState['description'] ?? '')"
          :icon-class="(string) ($feedEmptyState['iconClass'] ?? 'ph ph-magnifying-glass')"
          :button-label="$feedEmptyState['buttonLabel'] ?? null"
          :button-href="$feedEmptyState['buttonHref'] ?? null"
          :button-attributes="$feedEmptyState['buttonAttributes'] ?? []"
        />
      </template>
    @endif

    @if(!empty($feed['bootstrapJson']))
      <script id="feed-bootstrap" type="application/json">{!! $feed['bootstrapJson'] !!}</script>
    @endif
  </x-slot:templates>

  <x-slot:beforeScripts>
    @if(($feed['enabled'] ?? false) === true)
      <script src="/vendor/plyr/plyr.min.js"></script>
      <script src="/vendor/colcade/colcade.js"></script>
      <script src="/vendor/hls/hls.min.js"></script>
    @endif
  </x-slot:beforeScripts>

  <x-slot:scripts>
    @if(($feed['enabled'] ?? false) === true)
      @vite('laravel/resources/js/pages/feed/index.js')
    @endif

    @if($hasSubscriptionsFollowList && ($feed['enabled'] ?? false) === true)
      @vite('laravel/resources/js/pages/subscriptions/index.js')
    @endif

    @if(!empty($recommendations))
      @vite('laravel/resources/js/features/social/follow-buttons.js')
    @endif

    @if(($auth['shouldRenderModal'] ?? false) === true)
      @vite('laravel/resources/js/pages/auth/modal.js')
    @endif
  </x-slot:scripts>
</x-shortvideo.layout.app-shell>
