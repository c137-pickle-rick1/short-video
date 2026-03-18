<!doctype html>
<html lang="zh-CN">
  <head>
{!! $documentHead !!}
  </head>
  <body class="overflow-x-hidden bg-white text-gray-900 antialiased">
    @php
      $profileAvatarInitial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(ltrim($profile['name'], '@'), 0, 1));
      $socialConnections = is_array($socialConnections ?? null) ? $socialConnections : [];
      $socialConnectionTabs = is_array($socialConnections['tabs'] ?? null) ? $socialConnections['tabs'] : [];
      $profileVideoLibrary = ($isOwnProfile ?? false) && is_array($profileVideoLibrary ?? null) ? $profileVideoLibrary : [];
      $profileVideoTabs = is_array($profileVideoLibrary['tabs'] ?? null) ? $profileVideoLibrary['tabs'] : [];
      $selectedProfileVideoTab = is_array($profileVideoLibrary['selectedTab'] ?? null) ? $profileVideoLibrary['selectedTab'] : [];
      $profileVideoItems = is_array($profileVideoLibrary['items'] ?? null) ? $profileVideoLibrary['items'] : [];
      $publicProfileFeed = !($isOwnProfile ?? false) && is_array($publicProfileFeed ?? null) ? $publicProfileFeed : [];
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
              <section class="overflow-hidden rounded-[32px] border border-gray-200 bg-gradient-to-br from-stone-50 via-white to-sky-50/60 shadow-sm">
                <div class="grid gap-8 px-5 py-6 sm:px-6 sm:py-7 lg:px-8 lg:py-8">
                  <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 items-center gap-4 sm:gap-5">
                      <div class="shrink-0">
                        <span
                          data-avatar-slot="profile"
                          data-avatar-kind="profile"
                          data-avatar-label="{{ $profile['name'] }}"
                          data-avatar-initial="{{ $profileAvatarInitial }}"
                          data-avatar-url="{{ $profile['avatarUrl'] ?? '' }}"
                          class="block"
                        >
                          @if(!empty($profile['avatarUrl']))
                            <img
                              class="h-20 w-20 rounded-full object-cover ring-1 ring-gray-200 sm:h-24 sm:w-24"
                              src="{{ $profile['avatarUrl'] }}"
                              alt="{{ $profile['name'] }} 的头像"
                              loading="lazy"
                              referrerpolicy="no-referrer"
                            />
                          @else
                            <span class="flex h-20 w-20 items-center justify-center rounded-full bg-gray-900 text-2xl font-semibold text-white sm:h-24 sm:w-24 sm:text-3xl">
                              {{ $profileAvatarInitial }}
                            </span>
                          @endif
                        </span>
                      </div>

                      <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                          <h2
                            data-profile-name="true"
                            class="min-w-0 flex-1 truncate text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl"
                          >
                            {{ $profile['name'] }}
                          </h2>

                          @if($isOwnProfile)
                            <button
                              type="button"
                              data-profile-video-upload-trigger="true"
                              aria-haspopup="dialog"
                              aria-controls="profile-video-upload-dialog"
                              class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-full bg-gray-900 px-4 text-sm font-semibold text-white transition hover:bg-rose-600"
                            >
                              <i class="ph ph-upload-simple text-base leading-none" aria-hidden="true"></i>
                              上传视频
                            </button>
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
                          @endif
                        </div>

                        <p class="mt-2 truncate text-sm font-medium text-gray-500">&#64;{{ $profile['username'] }}</p>
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
                            <span data-profile-following-count="true" class="text-gray-950">{{ $stats['followingCount'] }}</span>
                          </button>
                          <span class="text-gray-300" aria-hidden="true">|</span>
                          <button
                            type="button"
                            data-profile-social-trigger="followers"
                            class="inline-flex items-center gap-2 rounded-full transition hover:text-gray-950"
                          >
                            <span>粉丝</span>
                            <span data-profile-follower-count="true" class="text-gray-950">{{ $stats['followerCount'] }}</span>
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

                    @if($isOwnProfile)
                      <form method="POST" action="{{ $logoutUrl }}" class="shrink-0">
                        @csrf
                        <button
                          type="submit"
                          class="inline-flex h-11 items-center justify-center rounded-full border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:text-gray-950"
                        >
                          退出登录
                        </button>
                      </form>
                    @else
                      <div class="shrink-0">
                        @include('shortvideo.partials.follow-button', ['creator' => $followState, 'loginUrl' => $loginUrl, 'reloadOnSuccess' => true])
                      </div>
                    @endif
                  </div>
                </div>

                @if($isOwnProfile && !empty($profileVideoTabs))
                  <div class="border-t border-gray-200/80 bg-white/70 px-4 pt-3 sm:px-6 lg:px-8">
                    <nav
                      aria-label="我的视频状态"
                      data-profile-library-tabs="true"
                      class="flex flex-wrap items-end gap-x-5 gap-y-3 sm:gap-x-6"
                    >
                      @foreach($profileVideoTabs as $tab)
                        <a
                          href="{{ route('profile.show', ['username' => $profile['username'], 'tab' => $tab['key']]) }}"
                          data-profile-library-tab="{{ $tab['key'] }}"
                          @class([
                              'relative inline-flex items-center gap-2 pb-3 text-base font-semibold tracking-tight transition sm:text-lg',
                              'text-gray-950' => $tab['active'],
                              'text-gray-500 hover:text-gray-800' => ! $tab['active'],
                          ])
                        >
                          <span
                            @class([
                                'absolute inset-x-0 bottom-0 h-1 rounded-full bg-rose-500' => $tab['active'],
                                'hidden' => ! $tab['active'],
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
                                'text-gray-500' => ! $tab['active'],
                            ])
                          >{{ $tab['count'] }}</span>
                        </a>
                      @endforeach
                    </nav>
                  </div>
                @endif
              </section>

              @if($isOwnProfile && !empty($profileVideoTabs))
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
                      @include('shortvideo.partials.foundation.empty-state', [
                        'iconClass' => $selectedProfileVideoTab['iconClass'] ?? 'ph ph-video-camera',
                        'title' => $selectedProfileVideoTab['emptyState']['title'] ?? '还没有内容',
                        'description' => $selectedProfileVideoTab['emptyState']['description'] ?? '当前标签下还没有任何内容。',
                        'containerClass' => 'flex min-h-[20rem] w-full flex-col items-center justify-center px-6 py-12 text-center',
                        'iconShellClass' => 'mx-auto flex items-center justify-center text-[3rem] text-gray-300',
                        'titleClass' => 'mt-4 text-lg font-semibold tracking-tight text-gray-950 sm:text-xl',
                        'descriptionClass' => 'mt-3 mx-auto w-full max-w-2xl text-sm leading-7 text-gray-500',
                        'dataEmptyState' => true,
                      ])
                    </div>
                  @else
                    <div class="grid gap-3 p-4 sm:gap-4 sm:p-6 lg:p-8">
                      @foreach($profileVideoItems as $item)
                        @include('shortvideo.partials.profile.video-library-item', ['item' => $item])
                      @endforeach
                    </div>
                  @endif
                </section>
              @endif

              @if(!($isOwnProfile ?? false) && !empty($publicProfileFeed))
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
                    {!! $publicProfileFeedGrid ?? '' !!}
                  </div>
                </section>
              @endif
            </div>
          </section>
        </div>
        <div class="h-24 lg:hidden" aria-hidden="true"></div>
      </div>
    </main>

    @if(!empty($socialConnectionTabs))
      @include('shortvideo.partials.profile.social-connections-modal', [
        'socialConnectionTabs' => $socialConnectionTabs,
        'loginUrl' => $loginUrl,
      ])
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
                  data-avatar-label="{{ $profile['name'] }}"
                  data-avatar-initial="{{ $profileAvatarInitial }}"
                  data-avatar-url="{{ $profile['avatarUrl'] ?? '' }}"
                  class="block"
                >
                  @if(!empty($profile['avatarUrl']))
                    <img
                      class="h-36 w-36 rounded-full object-cover ring-1 ring-gray-200"
                      src="{{ $profile['avatarUrl'] }}"
                      alt="{{ $profile['name'] }} 的头像预览"
                      loading="lazy"
                      referrerpolicy="no-referrer"
                    />
                  @else
                    <span class="flex h-36 w-36 items-center justify-center rounded-full bg-gray-900 text-5xl font-semibold text-white">
                      {{ $profileAvatarInitial }}
                    </span>
                  @endif
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
                    value="{{ $profile['name'] }}"
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

    @if(!empty($authModalMarkup))
{!! $authModalMarkup !!}
    @endif

    @if(!empty($publicProfileFeedEmptyStateMarkup))
      <template id="empty-state-template">
        {!! $publicProfileFeedEmptyStateMarkup !!}
      </template>
    @endif

    @if(!empty($publicProfileFeedDetailModalMarkup))
{!! $publicProfileFeedDetailModalMarkup !!}
    @endif

    @if(!empty($publicProfileFeedBootstrapData))
      <script id="feed-bootstrap" type="application/json">{!! $publicProfileFeedBootstrapData !!}</script>
    @endif

    @vite('laravel/resources/js/app/headerLanguageMenu.js')
    @vite('laravel/resources/js/app/profileSocialModal.js')

    @if(!empty($publicProfileFeedScriptsEnabled))
      <script src="/vendor/plyr/plyr.min.js"></script>
      <script src="/vendor/colcade/colcade.js"></script>
      <script src="/vendor/hls/hls.min.js"></script>
      @vite('laravel/resources/js/app.js')
    @endif

    @if(!empty($profileFollowScriptsEnabled))
      @vite('laravel/resources/js/socialGraph.js')
    @endif

    @if(!empty($authModalMarkup))
      @vite('laravel/resources/js/authModal.js')
    @endif

    @if(!empty($profileEditorScriptsEnabled))
      @vite('laravel/resources/js/app/profileEditor.js')
    @endif

    @if(!empty($profileVideoUploadScriptsEnabled))
      @vite('laravel/resources/js/app/profileVideoUpload.js')
    @endif
  </body>
</html>
