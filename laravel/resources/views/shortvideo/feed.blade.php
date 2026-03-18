@php
  $feed = is_array($feed ?? null) ? $feed : [];
  $subscriptionsFollowTabs = is_array($subscriptionsFollowTabs ?? null) ? $subscriptionsFollowTabs : [];
  $selectedSubscriptionsAccount = is_array($selectedSubscriptionsAccount ?? null) ? $selectedSubscriptionsAccount : [];
  $feedItems = is_array($feed['gridItems'] ?? null) ? $feed['gridItems'] : [];
  $feedEmptyState = is_array($feed['emptyState'] ?? null) ? $feed['emptyState'] : null;
@endphp

<x-shortvideo.layout.app-shell :shell="$shell">
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
              <x-ui.avatar
                :image-url="$tab['avatarUrl'] ?? null"
                :label="$tab['name'] ?? ('@'.$tab['username'])"
                :initial="$tab['avatarInitial'] ?? 'L'"
                :size-class="($tab['active'] ?? false) ? 'h-14 w-14 sm:h-16 sm:w-16' : 'h-14 w-14 sm:h-16 sm:w-16'"
                :fallback-class="($tab['active'] ?? false) ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700'"
                :image-class="($tab['active'] ?? false) ? 'ring-gray-900/10' : ''"
              />
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

    @if(($state ?? null) !== 'ready' && ($state ?? null) !== null)
      <section class="grid gap-5">
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
      <x-shortvideo.feed.grid :is-empty="($feed['gridIsEmpty'] ?? false) === true">
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
            <x-shortvideo.feed.item
              :tweet-id="(string) ($item['tweetId'] ?? '')"
              :status="(string) ($item['status'] ?? 'pending')"
              :author-name="(string) ($item['authorName'] ?? '@unknown')"
              :display-text="(string) ($item['displayText'] ?? '')"
              :posted-at-text="(string) ($item['postedAtText'] ?? '')"
              :title-line-clamp-class="(string) ($item['titleLineClampClass'] ?? 'line-clamp-2')"
              :interactive="($item['interactive'] ?? true) === true"
              :root-attributes="$item['rootAttributes'] ?? []"
              :card-class="(string) ($item['cardClass'] ?? '')"
            >
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

    @if(!empty($recommendations))
      @vite('laravel/resources/js/features/social/follow-buttons.js')
    @endif

    @if(($auth['shouldRenderModal'] ?? false) === true)
      @vite('laravel/resources/js/pages/auth/modal.js')
    @endif
  </x-slot:scripts>
</x-shortvideo.layout.app-shell>
