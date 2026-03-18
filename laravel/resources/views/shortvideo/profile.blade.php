@php
  $isOwnProfile = ($isOwnProfile ?? false) === true;
  $profileAvatarInitial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(ltrim($profile['name'] ?? '@', '@'), 0, 1));
  $socialConnections = is_array($socialConnections ?? null) ? $socialConnections : [];
  $socialConnectionTabs = is_array($socialConnections['tabs'] ?? null) ? $socialConnections['tabs'] : [];
  $profileVideoLibrary = $isOwnProfile && is_array($profileVideoLibrary ?? null) ? $profileVideoLibrary : [];
  $profileVideoTabs = is_array($profileVideoLibrary['tabs'] ?? null) ? $profileVideoLibrary['tabs'] : [];
  $selectedProfileVideoTab = is_array($profileVideoLibrary['selectedTab'] ?? null) ? $profileVideoLibrary['selectedTab'] : [];
  $profileVideoItems = is_array($profileVideoLibrary['items'] ?? null) ? $profileVideoLibrary['items'] : [];
  $profileDashboardItems = $isOwnProfile && is_array($profileDashboardItems ?? null) ? $profileDashboardItems : [];
  $selectedProfilePanel = $isOwnProfile && is_string($selectedProfilePanel ?? null) ? (string) $selectedProfilePanel : 'profile';
  $profilePanelRequested = $isOwnProfile && (($profilePanelRequested ?? false) === true);
  $profilePanelMeta = $isOwnProfile && is_array($profilePanelMeta ?? null) ? $profilePanelMeta : [];
  $creatorCenter = $isOwnProfile && is_array($creatorCenter ?? null) ? $creatorCenter : [];
  $profileHistoryPage = $isOwnProfile && is_array($profileHistoryPage ?? null) ? $profileHistoryPage : [];
  $profileHistoryPanel = $isOwnProfile && is_array($profileHistoryPanel ?? null) ? $profileHistoryPanel : [];
  $profileBookmarksPage = $isOwnProfile && is_array($profileBookmarksPage ?? null) ? $profileBookmarksPage : [];
  $profileBookmarksPanel = $isOwnProfile && is_array($profileBookmarksPanel ?? null) ? $profileBookmarksPanel : [];
  $profileInteractionsPage = $isOwnProfile && is_array($profileInteractionsPage ?? null) ? $profileInteractionsPage : [];
  $profileInteractionsPanel = $isOwnProfile && is_array($profileInteractionsPanel ?? null) ? $profileInteractionsPanel : [];
  $publicProfileFeed = !$isOwnProfile && is_array($publicProfileFeed ?? null) ? $publicProfileFeed : [];
  $publicProfileFeedData = is_array($publicProfileFeedData ?? null) ? $publicProfileFeedData : [];
  $publicFeedItems = is_array($publicProfileFeedData['gridItems'] ?? null) ? $publicProfileFeedData['gridItems'] : [];
  $publicFeedEmptyState = is_array($publicProfileFeedData['emptyState'] ?? null) ? $publicProfileFeedData['emptyState'] : null;
  $profileUsername = (string) ($profile['username'] ?? '');
  $profileBaseUrl = route('profile.show', ['username' => $profileUsername]);
  $selectedProfileVideoTabKey = trim((string) ($selectedProfileVideoTab['key'] ?? 'published')) ?: 'published';
  $creatorPanelRouteParameters = [
    'username' => $profileUsername,
    'panel' => 'creator',
    'tab' => $selectedProfileVideoTabKey,
  ];
  $profilePanelUrls = [
    'profile' => route('profile.show', ['username' => $profileUsername, 'panel' => 'profile']),
    'creator' => route('profile.show', $creatorPanelRouteParameters),
    'history' => route('profile.show', ['username' => $profileUsername, 'panel' => 'history']),
    'bookmarks' => route('profile.show', ['username' => $profileUsername, 'panel' => 'bookmarks']),
    'interactions' => route('profile.show', ['username' => $profileUsername, 'panel' => 'interactions']),
  ];
  $profilePanelLookup = [];
  foreach ($profileDashboardItems as $item) {
      if (is_array($item) && is_string($item['key'] ?? null)) {
          $profilePanelLookup[(string) $item['key']] = $item;
      }
  }
  $selectedProfilePanelItem = is_array($profilePanelLookup[$selectedProfilePanel] ?? null)
    ? $profilePanelLookup[$selectedProfilePanel]
    : [];
  $selectedProfilePanelLabel = trim((string) ($selectedProfilePanelItem['label'] ?? '')) !== ''
    ? (string) $selectedProfilePanelItem['label']
    : match ($selectedProfilePanel) {
        'creator' => '创作者中心',
        'history' => '观看记录',
        'bookmarks' => '我的收藏',
        'interactions' => '我的互动',
        default => '个人资料卡片',
    };
@endphp

<x-shortvideo.layout.app-shell :shell="$shell">
  <div class="grid gap-0 lg:gap-6 xl:gap-7">
    @if($isOwnProfile && $profilePanelRequested)
      <x-shortvideo.layout.navigation-bar
        :title="$selectedProfilePanelLabel"
        container-class="sticky top-[68px] z-30 -mx-3 -mt-3 flex h-12 items-center justify-center border-b border-gray-200 bg-white px-1 shadow-none sm:top-20 sm:-mx-4 sm:-mt-4 lg:hidden"
        title-wrapper-class="min-w-0 px-12 text-center"
        title-class="truncate text-sm font-semibold tracking-[0.08em] text-gray-950"
        :leading-action="[
          'tag' => 'a',
          'href' => $profileBaseUrl,
          'iconClass' => 'ph ph-arrow-left',
          'label' => '返回我的列表',
          'attributes' => [
            'class' => 'inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-700 transition hover:text-gray-950',
          ],
        ]"
        data-profile-dashboard-mobile-nav="true"
      />
    @endif

    @if($isOwnProfile)
      <div class="grid min-h-0 gap-0 lg:gap-6 xl:gap-7 lg:grid-cols-[19rem_minmax(0,1fr)] xl:grid-cols-[20rem_minmax(0,1fr)]">
        <aside
          @class([
            'min-w-0 max-lg:min-h-0 lg:sticky lg:top-[100px] 2xl:top-[108px]' => true,
            'max-lg:hidden' => $profilePanelRequested,
          ])
        >
          <section class="overflow-hidden bg-white max-lg:-mx-3 max-lg:flex max-lg:h-full max-lg:min-h-0 max-lg:flex-col max-lg:border-b max-lg:border-gray-200 sm:max-lg:-mx-4 lg:rounded-[32px] lg:border lg:border-gray-200 lg:shadow-sm">
            <nav
              aria-label="个人中心导航"
              data-profile-dashboard-nav="true"
              data-profile-dashboard-selected-panel="{{ $selectedProfilePanel }}"
              class="detail-mobile-scroller flex min-h-0 flex-1 flex-col overflow-y-auto pb-24 lg:max-h-[calc(100vh-11rem)] lg:pb-0 2xl:max-h-[calc(100vh-12rem)]"
            >
              @foreach($profileDashboardItems as $item)
                @php
                  $itemKey = (string) ($item['key'] ?? '');
                  $itemType = (string) ($item['type'] ?? 'panel');
                  $itemIsActive = $itemKey !== 'logout' && $itemKey === $selectedProfilePanel;
                  $itemHref = $profilePanelUrls[$itemKey] ?? $profileBaseUrl;
                @endphp

                @if($itemType === 'profile-card')
                  <a
                    href="{{ $itemHref }}"
                    data-profile-dashboard-item="{{ $itemKey }}"
                    data-active="{{ $itemIsActive ? 'true' : 'false' }}"
                    aria-current="{{ $itemIsActive ? 'page' : 'false' }}"
                    class="group flex w-full items-start gap-4 border-b border-gray-200 px-5 py-5 text-left transition lg:px-6 {{ $itemIsActive ? 'bg-gray-100' : 'hover:bg-gray-50' }}"
                  >
                    <x-ui.avatar
                      :image-url="$item['avatarUrl'] ?? null"
                      :label="$item['name'] ?? ($profile['name'] ?? '')"
                      :initial="$item['avatarInitial'] ?? $profileAvatarInitial"
                      size-class="h-14 w-14"
                      :fallback-class="$itemIsActive ? 'bg-white text-gray-700' : 'bg-gray-100 text-gray-700'"
                    />

                    <div class="min-w-0 flex-1">
                      <p class="truncate text-base font-semibold text-gray-950">{{ $item['name'] ?? ($profile['name'] ?? '') }}</p>
                      <p class="mt-1 truncate text-sm font-medium text-gray-500">&#64;{{ $item['username'] ?? $profileUsername }}</p>
                      <div class="mt-3 flex flex-wrap items-center gap-4 text-xs font-medium text-gray-500">
                        <span class="inline-flex items-center gap-1.5">
                          <span class="text-sm font-semibold text-gray-950">{{ (int) ($item['followingCount'] ?? 0) }}</span>
                          关注
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                          <span class="text-sm font-semibold text-gray-950">{{ (int) ($item['followerCount'] ?? 0) }}</span>
                          粉丝
                        </span>
                      </div>
                    </div>
                  </a>
                @elseif($itemType === 'logout')
                  <form method="POST" action="{{ $logoutUrl }}" class="border-t border-gray-200">
                    @csrf
                    <button
                      type="submit"
                      data-profile-dashboard-item="logout"
                      class="group flex w-full items-center gap-4 px-5 py-4 text-left text-gray-900 transition hover:bg-gray-50 lg:px-6"
                    >
                      <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-700 transition group-hover:bg-white">
                        <i class="{{ $item['iconClass'] ?? 'ph ph-sign-out' }} text-lg leading-none" aria-hidden="true"></i>
                      </span>

                      <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-gray-950">{{ $item['label'] ?? '退出登录' }}</span>
                        <span class="mt-1 block truncate text-xs text-gray-500">{{ $item['description'] ?? '立即退出当前账号' }}</span>
                      </span>
                    </button>
                  </form>
                @else
                  <a
                    href="{{ $itemHref }}"
                    data-profile-dashboard-item="{{ $itemKey }}"
                    data-active="{{ $itemIsActive ? 'true' : 'false' }}"
                    aria-current="{{ $itemIsActive ? 'page' : 'false' }}"
                    class="group flex w-full items-center gap-4 px-5 py-4 text-left transition lg:px-6 {{ $itemIsActive ? 'bg-gray-100 text-gray-950' : 'text-gray-900 hover:bg-gray-50' }} {{ $loop->last ? '' : 'border-b border-gray-200' }}"
                  >
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $itemIsActive ? 'bg-white text-gray-700' : 'bg-gray-100 text-gray-700' }}">
                      <i class="{{ $item['iconClass'] ?? 'ph ph-squares-four' }} text-lg leading-none" aria-hidden="true"></i>
                    </span>

                    <span class="min-w-0 flex-1">
                      <span class="block truncate text-sm font-semibold text-gray-950">{{ $item['label'] ?? '' }}</span>
                      <span class="mt-1 block truncate text-xs text-gray-500">{{ $item['description'] ?? '' }}</span>
                    </span>
                  </a>
                @endif
              @endforeach
            </nav>
          </section>
        </aside>

        <div
          @class([
            'min-w-0 grid gap-5 lg:gap-6 xl:gap-7' => true,
            'hidden lg:grid' => !$profilePanelRequested,
          ])
        >
          @if($selectedProfilePanel === 'creator')
            <div data-profile-dashboard-detail="creator" class="grid gap-5 lg:gap-6 xl:gap-7">
              <section class="overflow-hidden rounded-[32px] border border-gray-200 bg-white shadow-sm">
                <div class="grid gap-6 px-5 py-6 sm:px-6 sm:py-7 lg:px-8 lg:py-8">
                  <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                      <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Creator Center</p>
                      <h2 class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl">
                        {{ $creatorCenter['title'] ?? '创作者中心' }}
                      </h2>
                      <p class="mt-3 max-w-2xl text-sm leading-7 text-gray-500">
                        {{ $creatorCenter['description'] ?? '管理上传内容和你的视频状态。' }}
                      </p>
                    </div>

                    <button
                      type="button"
                      data-profile-video-upload-trigger="true"
                      aria-haspopup="dialog"
                      aria-controls="profile-video-upload-dialog"
                      class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-full bg-gray-900 px-5 text-sm font-semibold text-white transition hover:bg-rose-600"
                    >
                      <i class="ph ph-upload-simple text-base leading-none" aria-hidden="true"></i>
                      上传视频
                    </button>
                  </div>
                </div>

                @if(!empty($profileVideoTabs))
                  <div class="border-t border-gray-200 px-4 pt-3 sm:px-6 lg:px-8">
                    <nav
                      aria-label="我的视频状态"
                      data-profile-library-tabs="true"
                      class="flex flex-wrap items-end gap-x-5 gap-y-3 sm:gap-x-6"
                    >
                      @foreach($profileVideoTabs as $tab)
                        <a
                          href="{{ route('profile.show', ['username' => $profileUsername, 'panel' => 'creator', 'tab' => $tab['key']]) }}"
                          data-profile-library-tab="{{ $tab['key'] }}"
                          @class([
                            'relative inline-flex items-center gap-2 pb-3 text-base font-semibold tracking-tight transition sm:text-lg',
                            'text-gray-950' => $tab['active'],
                            'text-gray-500 hover:text-gray-800' => !$tab['active'],
                          ])
                        >
                          <span
                            @class([
                              'absolute inset-x-0 bottom-0 h-1 rounded-full bg-rose-500' => $tab['active'],
                              'hidden' => !$tab['active'],
                            ])
                            aria-hidden="true"
                          ></span>
                          <span>{{ $tab['label'] }}</span>
                          <span
                            data-profile-library-tab-count="{{ $tab['key'] }}"
                            data-profile-library-tab-total="{{ $tab['count'] }}"
                            @class([
                              'text-base font-semibold sm:text-lg',
                              'text-gray-950' => $tab['active'],
                              'text-gray-500' => !$tab['active'],
                            ])
                          >{{ $tab['count'] }}</span>
                        </a>
                      @endforeach
                    </nav>
                  </div>
                @endif
              </section>

              <section class="overflow-hidden rounded-[32px] border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-4 sm:px-6 lg:px-8">
                  <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                      <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">My Videos</p>
                      <h3
                        data-profile-library-title="true"
                        data-profile-library-selected-tab="{{ $selectedProfileVideoTab['key'] ?? 'published' }}"
                        class="mt-2 text-xl font-semibold tracking-tight text-gray-950 sm:text-2xl"
                      >
                        {{ $selectedProfileVideoTab['label'] ?? '已发布' }}
                      </h3>
                    </div>

                    <span
                      data-profile-library-selected-count="{{ $selectedProfileVideoTab['count'] ?? 0 }}"
                      class="inline-flex h-10 min-w-10 items-center justify-center rounded-full bg-gray-100 px-3 text-sm font-semibold text-gray-700"
                    >
                      {{ $selectedProfileVideoTab['count'] ?? 0 }}
                    </span>
                  </div>
                </div>

                @if(empty($profileVideoItems))
                  <div data-profile-library-empty-state="true">
                    <x-ui.empty-state
                      :icon-class="$selectedProfileVideoTab['iconClass'] ?? 'ph ph-video-camera'"
                      :title="$selectedProfileVideoTab['emptyState']['title'] ?? '还没有内容'"
                      :description="$selectedProfileVideoTab['emptyState']['description'] ?? '当前标签下还没有任何内容。'"
                      container-class="flex min-h-[20rem] w-full flex-col items-center justify-center px-6 py-12 text-center"
                      icon-shell-class="mx-auto flex items-center justify-center text-[3rem] text-gray-300"
                      title-class="mt-4 text-lg font-semibold tracking-tight text-gray-950 sm:text-xl"
                      description-class="mt-3 mx-auto w-full max-w-2xl text-sm leading-7 text-gray-500"
                      :data-empty-state="true"
                    />
                  </div>
                @else
                  <div class="grid gap-3 p-4 sm:gap-4 sm:p-6 lg:p-8">
                    @foreach($profileVideoItems as $item)
                      <x-shortvideo.profile.video-library-item :item="$item" />
                    @endforeach
                  </div>
                @endif
              </section>
            </div>
          @elseif($selectedProfilePanel === 'history')
            <div data-profile-dashboard-detail="history" class="grid gap-5 lg:gap-6 xl:gap-7">
              <section class="overflow-hidden rounded-[32px] border border-gray-200 bg-white shadow-sm">
                <div class="grid gap-4 px-5 py-6 sm:px-6 sm:py-7 lg:px-8 lg:py-8">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">History</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl">
                      {{ $profileHistoryPage['title'] ?? '观看记录' }}
                    </h2>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-gray-500">
                      {{ $profileHistoryPage['description'] ?? '最近看过的内容会收拢在这里。' }}
                    </p>
                  </div>

                  <div class="flex justify-end">
                    <button
                      type="button"
                      data-history-clear-all="true"
                      data-clear-url="/api/history"
                      class="inline-flex h-11 items-center justify-center gap-2 rounded-full border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 transition hover:border-rose-300 hover:text-rose-600 disabled:cursor-not-allowed disabled:text-gray-300"
                      @disabled(((int) ($profileHistoryPanel['historyPagination']['totalCount'] ?? 0)) <= 0)
                    >
                      <i class="ph ph-trash text-base leading-none" aria-hidden="true"></i>
                      清空记录
                    </button>
                  </div>
                </div>
              </section>

              <x-shortvideo.history.panel-content
                :history-pagination="$profileHistoryPanel['historyPagination'] ?? []"
                :history-has-items="$profileHistoryPanel['historyHasItems'] ?? false"
                :history-items="$profileHistoryPanel['historyItems'] ?? []"
                :history-empty-state="$profileHistoryPanel['historyEmptyState'] ?? []"
              />
            </div>
          @elseif($selectedProfilePanel === 'bookmarks')
            <div data-profile-dashboard-detail="bookmarks" class="grid gap-5 lg:gap-6 xl:gap-7">
              <section class="overflow-hidden rounded-[32px] border border-gray-200 bg-white shadow-sm">
                <div class="grid gap-4 px-5 py-6 sm:px-6 sm:py-7 lg:px-8 lg:py-8">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Bookmarks</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl">
                      {{ $profileBookmarksPage['title'] ?? '我的收藏' }}
                    </h2>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-gray-500">
                      {{ $profileBookmarksPage['description'] ?? '收藏过的视频会按最近收藏时间倒序收拢在这里。' }}
                    </p>
                  </div>
                </div>
              </section>

              <x-shortvideo.bookmarks.panel-content
                :bookmark-pagination="$profileBookmarksPanel['bookmarkPagination'] ?? []"
                :bookmark-has-items="$profileBookmarksPanel['bookmarkHasItems'] ?? false"
                :bookmark-items="$profileBookmarksPanel['bookmarkItems'] ?? []"
                :bookmark-empty-state="$profileBookmarksPanel['bookmarkEmptyState'] ?? []"
              />
            </div>
          @elseif($selectedProfilePanel === 'interactions')
            <div data-profile-dashboard-detail="interactions" class="grid gap-5 lg:gap-6 xl:gap-7">
              <section class="overflow-hidden rounded-[32px] border border-gray-200 bg-white shadow-sm">
                <div class="grid gap-4 px-5 py-6 sm:px-6 sm:py-7 lg:px-8 lg:py-8">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Interactions</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl">
                      {{ $profileInteractionsPage['title'] ?? '我的互动' }}
                    </h2>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-gray-500">
                      {{ $profileInteractionsPage['description'] ?? '你点过赞和发过评论的视频会按互动时间倒序收拢在这里。' }}
                    </p>
                  </div>
                </div>
              </section>

              <x-shortvideo.interactions.panel-content
                :interaction-pagination="$profileInteractionsPanel['interactionPagination'] ?? []"
                :interaction-has-items="$profileInteractionsPanel['interactionHasItems'] ?? false"
                :interaction-items="$profileInteractionsPanel['interactionItems'] ?? []"
                :interaction-empty-state="$profileInteractionsPanel['interactionEmptyState'] ?? []"
              />
            </div>
          @else
            <section
              data-profile-dashboard-detail="profile"
              class="overflow-hidden rounded-[32px] border border-gray-200 bg-gradient-to-br from-stone-50 via-white to-sky-50/60 shadow-sm"
            >
              <div class="grid gap-8 px-5 py-6 sm:px-6 sm:py-7 lg:px-8 lg:py-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                  <div class="flex min-w-0 items-center gap-4 sm:gap-5">
                    <div class="shrink-0">
                      <span
                        data-avatar-slot="profile"
                        data-avatar-kind="profile"
                        data-avatar-label="{{ $profile['name'] ?? '' }}"
                        data-avatar-initial="{{ $profileAvatarInitial }}"
                        data-avatar-url="{{ $profile['avatarUrl'] ?? '' }}"
                        class="block"
                      >
                        <x-ui.avatar
                          :image-url="$profile['avatarUrl'] ?? null"
                          :label="$profile['name'] ?? ''"
                          :initial="$profileAvatarInitial"
                          size-class="h-20 w-20 sm:h-24 sm:w-24"
                          fallback-class="bg-gray-900 text-white"
                          image-class=""
                        />
                      </span>
                    </div>

                    <div class="min-w-0">
                      <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Profile</p>
                      <div class="mt-2 flex flex-wrap items-center gap-3 sm:gap-4">
                        <h2
                          data-profile-name="true"
                          class="min-w-0 flex-1 truncate text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl"
                        >
                          {{ $profile['name'] ?? '' }}
                        </h2>

                        <button
                          type="button"
                          data-profile-editor-trigger="true"
                          aria-haspopup="dialog"
                          aria-controls="profile-editor-dialog"
                          class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-full border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:text-gray-950"
                        >
                          <i class="ph ph-pencil-simple text-base leading-none" aria-hidden="true"></i>
                          编辑资料
                        </button>
                      </div>

                      <p class="mt-2 truncate text-sm font-medium text-gray-500">&#64;{{ $profileUsername }}</p>
                      <div
                        data-profile-stats="true"
                        class="mt-3 flex flex-wrap items-center gap-3 text-sm font-medium text-gray-500 sm:text-base"
                      >
                        <button
                          type="button"
                          data-profile-social-trigger="following"
                          class="inline-flex items-center gap-2 rounded-full transition hover:text-gray-950"
                        >
                          <span>关注</span>
                          <span data-profile-following-count="true" class="text-gray-950">{{ $stats['followingCount'] ?? 0 }}</span>
                        </button>
                        <span class="text-gray-300" aria-hidden="true">|</span>
                        <button
                          type="button"
                          data-profile-social-trigger="followers"
                          class="inline-flex items-center gap-2 rounded-full transition hover:text-gray-950"
                        >
                          <span>粉丝</span>
                          <span data-profile-follower-count="true" class="text-gray-950">{{ $stats['followerCount'] ?? 0 }}</span>
                        </button>
                      </div>
                      <p
                        data-profile-bio="true"
                        @class([
                          'mt-3 max-w-2xl text-sm leading-7 text-gray-600',
                          'hidden' => empty($profile['bio']),
                        ])
                        @if(empty($profile['bio'])) hidden @endif
                      >{{ $profile['bio'] ?? '' }}</p>
                    </div>
                  </div>
                </div>

                <div class="grid gap-4 rounded-[28px] border border-gray-200/80 bg-white/80 p-5 sm:p-6">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Profile Card</p>
                    <h3 class="mt-2 text-lg font-semibold tracking-tight text-gray-950">
                      {{ $profilePanelMeta['title'] ?? '个人资料卡片' }}
                    </h3>
                    <p class="mt-2 max-w-2xl text-sm leading-7 text-gray-500">
                      {{ $profilePanelMeta['description'] ?? '查看并编辑你的账号资料、关注关系和简介。' }}
                    </p>
                  </div>

                  <div class="grid gap-3 sm:grid-cols-2">
                    <button
                      type="button"
                      data-profile-social-trigger="following"
                      class="flex items-center justify-between rounded-2xl border border-gray-200 bg-white px-4 py-4 text-left transition hover:border-gray-300"
                    >
                      <span>
                        <span class="block text-sm font-semibold text-gray-950">我关注的人</span>
                        <span class="mt-1 block text-xs text-gray-500">进入列表查看已关注账号</span>
                      </span>
                      <span class="text-sm font-semibold text-gray-950">{{ $stats['followingCount'] ?? 0 }}</span>
                    </button>

                    <button
                      type="button"
                      data-profile-social-trigger="followers"
                      class="flex items-center justify-between rounded-2xl border border-gray-200 bg-white px-4 py-4 text-left transition hover:border-gray-300"
                    >
                      <span>
                        <span class="block text-sm font-semibold text-gray-950">关注我的人</span>
                        <span class="mt-1 block text-xs text-gray-500">进入列表查看粉丝账号</span>
                      </span>
                      <span class="text-sm font-semibold text-gray-950">{{ $stats['followerCount'] ?? 0 }}</span>
                    </button>
                  </div>
                </div>
              </div>
            </section>
          @endif
        </div>
      </div>
    @else
      <div class="grid gap-5 lg:gap-6 xl:gap-7">
        <section class="overflow-hidden rounded-[32px] border border-gray-200 bg-gradient-to-br from-stone-50 via-white to-sky-50/60 shadow-sm">
          <div class="grid gap-8 px-5 py-6 sm:px-6 sm:py-7 lg:px-8 lg:py-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
              <div class="flex min-w-0 items-center gap-4 sm:gap-5">
                <div class="shrink-0">
                  <span
                    data-avatar-slot="profile"
                    data-avatar-kind="profile"
                    data-avatar-label="{{ $profile['name'] ?? '' }}"
                    data-avatar-initial="{{ $profileAvatarInitial }}"
                    data-avatar-url="{{ $profile['avatarUrl'] ?? '' }}"
                    class="block"
                  >
                    <x-ui.avatar
                      :image-url="$profile['avatarUrl'] ?? null"
                      :label="$profile['name'] ?? ''"
                      :initial="$profileAvatarInitial"
                      size-class="h-20 w-20 sm:h-24 sm:w-24"
                      fallback-class="bg-gray-900 text-white"
                      image-class=""
                    />
                  </span>
                </div>

                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                    <h2
                      data-profile-name="true"
                      class="min-w-0 flex-1 truncate text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl"
                    >
                      {{ $profile['name'] ?? '' }}
                    </h2>
                  </div>

                  <p class="mt-2 truncate text-sm font-medium text-gray-500">&#64;{{ $profile['username'] ?? '' }}</p>
                  <div
                    data-profile-stats="true"
                    class="mt-3 flex flex-wrap items-center gap-3 text-sm font-medium text-gray-500 sm:text-base"
                  >
                    <button
                      type="button"
                      data-profile-social-trigger="following"
                      class="inline-flex items-center gap-2 rounded-full transition hover:text-gray-950"
                    >
                      <span>关注</span>
                      <span data-profile-following-count="true" class="text-gray-950">{{ $stats['followingCount'] ?? 0 }}</span>
                    </button>
                    <span class="text-gray-300" aria-hidden="true">|</span>
                    <button
                      type="button"
                      data-profile-social-trigger="followers"
                      class="inline-flex items-center gap-2 rounded-full transition hover:text-gray-950"
                    >
                      <span>粉丝</span>
                      <span data-profile-follower-count="true" class="text-gray-950">{{ $stats['followerCount'] ?? 0 }}</span>
                    </button>
                  </div>
                  <p
                    data-profile-bio="true"
                    @class([
                      'mt-3 max-w-2xl text-sm leading-7 text-gray-600',
                      'hidden' => empty($profile['bio']),
                    ])
                    @if(empty($profile['bio'])) hidden @endif
                  >{{ $profile['bio'] ?? '' }}</p>
                </div>
              </div>

              <div class="shrink-0">
                <x-shortvideo.follow-button :creator="$followState" :login-url="$loginUrl" :reload-on-success="true" />
              </div>
            </div>
          </div>
        </section>

        @if(!empty($publicProfileFeed))
          <section class="grid gap-4 lg:gap-5 xl:gap-6">
            <div class="px-1">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Published</p>
              <h3 class="mt-2 text-xl font-semibold tracking-tight text-gray-950 sm:text-2xl">
                {{ $publicProfileFeed['title'] ?? '发布的视频' }}
              </h3>
              <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500">
                {{ $publicProfileFeed['description'] ?? '这里展示这个账号已经公开发布的视频内容。' }}
              </p>
            </div>

            <div>
              @if(($publicProfileFeedData['enabled'] ?? false) === true)
                <x-shortvideo.feed.grid :is-empty="($publicProfileFeedData['gridIsEmpty'] ?? false) === true">
                  @if(($publicProfileFeedData['gridIsEmpty'] ?? false) === true && $publicFeedEmptyState !== null)
                    <x-shortvideo.feed.empty-state
                      :title="(string) ($publicFeedEmptyState['title'] ?? '')"
                      :description="(string) ($publicFeedEmptyState['description'] ?? '')"
                      :icon-class="(string) ($publicFeedEmptyState['iconClass'] ?? 'ph ph-magnifying-glass')"
                      :button-label="$publicFeedEmptyState['buttonLabel'] ?? null"
                      :button-href="$publicFeedEmptyState['buttonHref'] ?? null"
                      :button-attributes="$publicFeedEmptyState['buttonAttributes'] ?? []"
                    />
                  @else
                    @foreach($publicFeedItems as $item)
                      <x-shortvideo.feed.item
                        :tweet-id="(string) ($item['tweetId'] ?? '')"
                        :status="(string) ($item['status'] ?? 'pending')"
                        :author-name="(string) ($item['authorName'] ?? '@unknown')"
                        :display-text="(string) ($item['displayText'] ?? '')"
                        :detail-url="$item['detailUrl'] ?? null"
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
          </section>
        @endif
      </div>
    @endif
  </div>

  <x-slot:modals>
    @if(!empty($socialConnectionTabs))
      <x-shortvideo.profile.social-connections-modal
        :social-connection-tabs="$socialConnectionTabs"
        :login-url="$loginUrl"
      />
    @endif

    @if($isOwnProfile)
      <div
        id="profile-video-upload-dialog"
        data-profile-video-upload="true"
        data-profile-video-upload-action="/api/videos"
        class="fixed inset-0 z-50 hidden flex items-center justify-center bg-gray-950/40 p-4 backdrop-blur-sm sm:p-6"
        hidden
      >
        <section
          id="profile-video-upload-dialog-panel"
          data-profile-video-upload-panel="true"
          class="relative w-full max-w-2xl rounded-[32px] border border-gray-200 bg-white p-6 shadow-glass animate-card-in sm:p-7"
          role="dialog"
          aria-modal="true"
          aria-labelledby="profile-video-upload-dialog-title"
          tabindex="-1"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm font-semibold uppercase tracking-[0.18em] text-rose-500">Creator Studio</p>
              <h2 id="profile-video-upload-dialog-title" class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl">上传视频</h2>
              <p class="mt-2 text-sm leading-6 text-gray-500">选择视频文件，填写标题和标签后即可加入上传队列。支持 MP4、MOV、M4V、WEBM，文件大小不超过 200MB。</p>
            </div>

            <button
              type="button"
              data-profile-video-upload-close="true"
              class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition hover:border-gray-300 hover:text-gray-950"
              aria-label="关闭上传视频弹窗"
            >
              <i class="ph ph-x text-xl leading-none" aria-hidden="true"></i>
            </button>
          </div>

          <form data-profile-video-upload-form="true" class="mt-6 grid gap-6" novalidate>
            <p
              data-profile-video-upload-error="general"
              class="hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-700"
              aria-live="polite"
            ></p>

            <div class="grid gap-5">
              <div class="rounded-[28px] border border-dashed border-gray-200 bg-stone-50 px-4 py-5">
                <label
                  for="profile-video-upload-input"
                  class="inline-flex h-11 cursor-pointer items-center justify-center gap-2 rounded-full border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:text-gray-950"
                >
                  <i class="ph ph-video-camera text-base leading-none" aria-hidden="true"></i>
                  选择视频文件
                </label>
                <input
                  id="profile-video-upload-input"
                  data-profile-video-upload-input="true"
                  type="file"
                  accept="video/mp4,video/webm,video/quicktime,video/x-m4v,.mp4,.mov,.m4v,.webm"
                  class="sr-only"
                />
                <p data-profile-video-upload-file-name="true" class="mt-3 text-sm text-gray-500">未选择视频文件</p>
                <p class="mt-2 text-xs leading-6 text-gray-400">建议使用竖屏短视频，上传后会先进入“上传中”列表。</p>
                <p
                  data-profile-video-upload-error="video"
                  class="hidden mt-3 text-sm leading-6 text-rose-600"
                  aria-live="polite"
                ></p>
              </div>

              <div class="grid gap-2">
                <label for="profile-video-upload-title" class="text-sm font-semibold text-gray-700">标题</label>
                <input
                  id="profile-video-upload-title"
                  data-profile-video-upload-title-input="true"
                  type="text"
                  maxlength="120"
                  class="h-12 rounded-2xl border border-gray-200 bg-white px-4 text-base text-gray-950 outline-none transition placeholder:text-gray-400 focus:border-rose-300 focus:ring-2 focus:ring-rose-100"
                  placeholder="给这条视频起个醒目的标题"
                />
                <p
                  data-profile-video-upload-error="title"
                  class="hidden text-sm leading-6 text-rose-600"
                  aria-live="polite"
                ></p>
              </div>

              <div class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                  <label for="profile-video-upload-tags" class="text-sm font-semibold text-gray-700">标签</label>
                  <span class="text-xs text-gray-400">多个标签用逗号分隔</span>
                </div>
                <input
                  id="profile-video-upload-tags"
                  data-profile-video-upload-tags-input="true"
                  type="text"
                  maxlength="120"
                  class="h-12 rounded-2xl border border-gray-200 bg-white px-4 text-base text-gray-950 outline-none transition placeholder:text-gray-400 focus:border-rose-300 focus:ring-2 focus:ring-rose-100"
                  placeholder="例如：旅行, 探店, Vlog"
                />
                <p
                  data-profile-video-upload-error="tags"
                  class="hidden text-sm leading-6 text-rose-600"
                  aria-live="polite"
                ></p>
              </div>

              <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                <button
                  type="button"
                  data-profile-video-upload-close="true"
                  class="inline-flex h-11 items-center justify-center rounded-full border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:text-gray-950"
                >
                  取消
                </button>
                <button
                  type="submit"
                  data-profile-video-upload-submit="true"
                  class="inline-flex h-11 items-center justify-center rounded-full bg-gray-900 px-5 text-sm font-semibold text-white transition hover:bg-rose-600 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400"
                  disabled
                >
                  开始上传
                </button>
              </div>
            </div>
          </form>
        </section>
      </div>

      <div
        id="profile-editor-dialog"
        data-profile-editor="true"
        class="fixed inset-0 z-50 hidden flex items-center justify-center bg-gray-950/40 p-4 backdrop-blur-sm sm:p-6"
        hidden
      >
        <section
          id="profile-editor-dialog-panel"
          data-profile-editor-panel="true"
          class="relative w-full max-w-2xl rounded-[32px] border border-gray-200 bg-white p-6 shadow-glass animate-card-in sm:p-7"
          role="dialog"
          aria-modal="true"
          aria-labelledby="profile-editor-dialog-title"
          tabindex="-1"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm font-semibold uppercase tracking-[0.18em] text-rose-500">Profile</p>
              <h2 id="profile-editor-dialog-title" class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl">编辑资料</h2>
              <p class="mt-2 text-sm leading-6 text-gray-500">可以一次更新头像、昵称和简介。头像支持 JPG、PNG、WEBP，文件大小不超过 5MB。</p>
            </div>

            <button
              type="button"
              data-profile-editor-close="true"
              class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition hover:border-gray-300 hover:text-gray-950"
              aria-label="关闭编辑资料弹窗"
            >
              <i class="ph ph-x text-xl leading-none" aria-hidden="true"></i>
            </button>
          </div>

          <form data-profile-editor-form="true" class="mt-6 grid gap-6" novalidate>
            <p
              data-profile-editor-error="general"
              class="hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-700"
              aria-live="polite"
            ></p>

            <div class="grid gap-6 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-start">
              <div class="flex justify-center sm:justify-start">
                <span
                  data-avatar-slot="preview"
                  data-avatar-kind="preview"
                  data-avatar-label="{{ $profile['name'] ?? '' }}"
                  data-avatar-initial="{{ $profileAvatarInitial }}"
                  data-avatar-url="{{ $profile['avatarUrl'] ?? '' }}"
                  class="block"
                >
                  <x-ui.avatar
                    :image-url="$profile['avatarUrl'] ?? null"
                    :label="$profile['name'] ?? ''"
                    :initial="$profileAvatarInitial"
                    size-class="h-36 w-36"
                    fallback-class="bg-gray-900 text-white"
                    image-class=""
                  />
                </span>
              </div>

              <div class="grid gap-5">
                <div class="rounded-[28px] border border-dashed border-gray-200 bg-stone-50 px-4 py-4">
                  <label
                    for="profile-editor-avatar-input"
                    class="inline-flex h-11 cursor-pointer items-center justify-center gap-2 rounded-full border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:text-gray-950"
                  >
                    <i class="ph ph-upload-simple text-base leading-none" aria-hidden="true"></i>
                    选择图片
                  </label>
                  <input
                    id="profile-editor-avatar-input"
                    data-profile-editor-avatar-input="true"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="sr-only"
                  />
                  <p data-profile-editor-avatar-file-name="true" class="mt-3 text-sm text-gray-500">未选择新图片</p>
                  <p
                    data-profile-editor-error="avatar"
                    class="hidden mt-3 text-sm leading-6 text-rose-600"
                    aria-live="polite"
                  ></p>
                </div>

                <div class="grid gap-2">
                  <label for="profile-editor-name" class="text-sm font-semibold text-gray-700">昵称</label>
                  <input
                    id="profile-editor-name"
                    data-profile-editor-name-input="true"
                    type="text"
                    maxlength="50"
                    value="{{ $profile['name'] ?? '' }}"
                    autocomplete="name"
                    class="h-12 rounded-2xl border border-gray-200 bg-white px-4 text-base text-gray-950 outline-none transition placeholder:text-gray-400 focus:border-rose-300 focus:ring-2 focus:ring-rose-100"
                  />
                  <p
                    data-profile-editor-error="name"
                    class="hidden text-sm leading-6 text-rose-600"
                    aria-live="polite"
                  ></p>
                </div>

                <div class="grid gap-2">
                  <div class="flex items-center justify-between gap-3">
                    <label for="profile-editor-bio" class="text-sm font-semibold text-gray-700">简介</label>
                    <span class="text-xs text-gray-400">最多 280 字</span>
                  </div>
                  <textarea
                    id="profile-editor-bio"
                    data-profile-editor-bio-input="true"
                    rows="4"
                    maxlength="280"
                    class="min-h-28 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm leading-7 text-gray-950 outline-none transition placeholder:text-gray-400 focus:border-rose-300 focus:ring-2 focus:ring-rose-100"
                    placeholder="写点什么，让别人更快认识你。">{{ $profile['bio'] ?? '' }}</textarea>
                  <p
                    data-profile-editor-error="bio"
                    class="hidden text-sm leading-6 text-rose-600"
                    aria-live="polite"
                  ></p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                  <button
                    type="button"
                    data-profile-editor-close="true"
                    class="inline-flex h-11 items-center justify-center rounded-full border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:text-gray-950"
                  >
                    取消
                  </button>
                  <button
                    type="submit"
                    data-profile-editor-submit="true"
                    class="inline-flex h-11 items-center justify-center rounded-full bg-gray-900 px-5 text-sm font-semibold text-white transition hover:bg-rose-600 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400"
                    disabled
                  >
                    保存资料
                  </button>
                </div>
              </div>
            </div>
          </form>
        </section>
      </div>
    @endif

    @if(($publicProfileFeedData['detailModalEnabled'] ?? false) === true)
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
    @if($publicFeedEmptyState !== null)
      <template id="empty-state-template">
        <x-shortvideo.feed.empty-state
          :title="(string) ($publicFeedEmptyState['title'] ?? '')"
          :description="(string) ($publicFeedEmptyState['description'] ?? '')"
          :icon-class="(string) ($publicFeedEmptyState['iconClass'] ?? 'ph ph-magnifying-glass')"
          :button-label="$publicFeedEmptyState['buttonLabel'] ?? null"
          :button-href="$publicFeedEmptyState['buttonHref'] ?? null"
          :button-attributes="$publicFeedEmptyState['buttonAttributes'] ?? []"
        />
      </template>
    @endif

    @if(!empty($publicProfileFeedData['bootstrapJson']))
      <script id="feed-bootstrap" type="application/json">{!! $publicProfileFeedData['bootstrapJson'] !!}</script>
    @endif
  </x-slot:templates>

  <x-slot:beforeScripts>
    @if(($publicProfileFeedData['enabled'] ?? false) === true)
      <script src="/vendor/plyr/plyr.min.js"></script>
      <script src="/vendor/colcade/colcade.js"></script>
      <script src="/vendor/hls/hls.min.js"></script>
    @endif
  </x-slot:beforeScripts>

  <x-slot:scripts>
    @vite('laravel/resources/js/features/profile/social-modal.js')

    @if($isOwnProfile)
      @vite('laravel/resources/js/pages/history/index.js')
      @vite('laravel/resources/js/pages/bookmarks/index.js')
      @vite('laravel/resources/js/pages/interactions/index.js')
    @endif

    @if(($publicProfileFeedData['enabled'] ?? false) === true)
      @vite('laravel/resources/js/pages/feed/index.js')
    @endif

    @if(!empty($profileFollowScriptsEnabled))
      @vite('laravel/resources/js/features/social/follow-buttons.js')
    @endif

    @if(($auth['shouldRenderModal'] ?? false) === true)
      @vite('laravel/resources/js/pages/auth/modal.js')
    @endif

    @if(!empty($profileEditorScriptsEnabled))
      @vite('laravel/resources/js/features/profile/editor.js')
    @endif

    @if(!empty($profileVideoUploadScriptsEnabled))
      @vite('laravel/resources/js/features/profile/video-upload.js')
    @endif
  </x-slot:scripts>
</x-shortvideo.layout.app-shell>
