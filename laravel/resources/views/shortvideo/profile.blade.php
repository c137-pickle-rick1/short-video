<!doctype html>
<html lang="zh-CN">
  <head>
{!! $documentHead !!}
  </head>
  <body class="overflow-x-hidden bg-white text-gray-900 antialiased">
    @php
      $profileAvatarInitial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(ltrim($profile['name'], '@'), 0, 1));
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
              <header class="rounded-[32px] border border-gray-200 bg-white px-5 py-6 shadow-sm sm:px-6 sm:py-7 lg:px-8 lg:py-8">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-rose-500">{{ $page['eyebrow'] }}</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-gray-950 sm:text-4xl">{{ $page['title'] }}</h1>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-gray-600 sm:text-base">{{ $page['description'] }}</p>
              </header>

              <section class="overflow-hidden rounded-[32px] border border-gray-200 bg-gradient-to-br from-stone-50 via-white to-sky-50/60 shadow-sm">
                <div class="grid gap-8 px-5 py-6 sm:px-6 sm:py-7 lg:px-8 lg:py-8">
                  <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 items-center gap-4 sm:gap-5">
                      <button
                        type="button"
                        data-avatar-dialog-trigger="true"
                        aria-haspopup="dialog"
                        aria-controls="profile-avatar-dialog"
                        class="group relative shrink-0 rounded-full focus:outline-none focus:ring-2 focus:ring-rose-300"
                      >
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
                        <span class="absolute inset-x-0 bottom-0 mx-auto inline-flex w-max translate-y-1/3 items-center gap-2 rounded-full border border-white/80 bg-gray-900 px-3 py-1 text-xs font-semibold text-white shadow-sm transition group-hover:bg-rose-600">
                          <i class="ph ph-pencil-simple text-sm leading-none" aria-hidden="true"></i>
                          修改头像
                        </span>
                      </button>

                      <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">个人资料</p>
                        <h2 class="mt-3 truncate text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl">{{ $profile['name'] }}</h2>
                        <p class="mt-2 truncate text-sm font-medium text-gray-500">&#64;{{ $profile['username'] }}</p>
                        @if(!empty($profile['bio']))
                          <p class="mt-3 max-w-2xl text-sm leading-7 text-gray-600">{{ $profile['bio'] }}</p>
                        @endif
                      </div>
                    </div>

                    <form method="POST" action="{{ $logoutUrl }}" class="shrink-0">
                      @csrf
                      <button
                        type="submit"
                        class="inline-flex h-11 items-center justify-center rounded-full border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:text-gray-950"
                      >
                        退出登录
                      </button>
                    </form>
                  </div>

                  <div class="grid gap-4 sm:grid-cols-2">
                    <article class="rounded-[28px] border border-gray-200 bg-white/90 px-5 py-5 backdrop-blur">
                      <p class="text-xs uppercase tracking-[0.14em] text-gray-400">关注了</p>
                      <p class="mt-3 text-3xl font-semibold tracking-tight text-gray-950">{{ $stats['followingCount'] }}</p>
                      <p class="mt-2 text-sm text-gray-500">你当前已关注的创作者数量。</p>
                    </article>

                    <article class="rounded-[28px] border border-gray-200 bg-white/90 px-5 py-5 backdrop-blur">
                      <p class="text-xs uppercase tracking-[0.14em] text-gray-400">粉丝数</p>
                      <p class="mt-3 text-3xl font-semibold tracking-tight text-gray-950">{{ $stats['followerCount'] }}</p>
                      <p class="mt-2 text-sm text-gray-500">当前关注你的账号数量。</p>
                    </article>
                  </div>
                </div>
              </section>
            </div>
          </section>
        </div>
        <div class="h-24 lg:hidden" aria-hidden="true"></div>
      </div>
    </main>

    <div
      id="profile-avatar-dialog"
      data-avatar-dialog="true"
      class="fixed inset-0 z-50 hidden flex items-center justify-center bg-gray-950/40 p-4 backdrop-blur-sm sm:p-6"
      hidden
    >
      <section
        id="profile-avatar-dialog-panel"
        data-avatar-dialog-panel="true"
        class="relative w-full max-w-2xl rounded-[32px] border border-gray-200 bg-white p-6 shadow-glass animate-card-in sm:p-7"
        role="dialog"
        aria-modal="true"
        aria-labelledby="profile-avatar-dialog-title"
        tabindex="-1"
      >
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-rose-500">Avatar</p>
            <h2 id="profile-avatar-dialog-title" class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl">修改头像</h2>
            <p class="mt-2 text-sm leading-6 text-gray-500">选择一张你喜欢的图片作为头像。支持 JPG、PNG、WEBP，文件大小不超过 5MB。</p>
          </div>

          <button
            type="button"
            data-avatar-dialog-close="true"
            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition hover:border-gray-300 hover:text-gray-950"
            aria-label="关闭修改头像弹窗"
          >
            <i class="ph ph-x text-xl leading-none" aria-hidden="true"></i>
          </button>
        </div>

        <div class="mt-6 grid gap-6 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-start">
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

          <div class="grid gap-4">
            <div class="rounded-[28px] border border-dashed border-gray-200 bg-stone-50 px-4 py-4">
              <label
                for="profile-avatar-input"
                class="inline-flex h-11 cursor-pointer items-center justify-center gap-2 rounded-full border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:text-gray-950"
              >
                <i class="ph ph-upload-simple text-base leading-none" aria-hidden="true"></i>
                选择图片
              </label>
              <input
                id="profile-avatar-input"
                data-avatar-file-input="true"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="sr-only"
              />
              <p data-avatar-file-name="true" class="mt-3 text-sm text-gray-500">尚未选择图片</p>
            </div>

            <p
              data-avatar-dialog-error="true"
              class="hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-700"
              aria-live="polite"
            ></p>

            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
              <button
                type="button"
                data-avatar-dialog-close="true"
                class="inline-flex h-11 items-center justify-center rounded-full border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:text-gray-950"
              >
                取消
              </button>
              <button
                type="button"
                data-avatar-dialog-submit="true"
                class="inline-flex h-11 items-center justify-center rounded-full bg-gray-900 px-5 text-sm font-semibold text-white transition hover:bg-rose-600 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400"
                disabled
              >
                保存头像
              </button>
            </div>
          </div>
        </div>
      </section>
    </div>

    <script type="module" src="/app/profileAvatar.js"></script>
  </body>
</html>
