<x-shortvideo.layout.app-shell :shell="$shell">
  <div class="grid gap-5 lg:gap-6 xl:gap-7">
    <section class="grid gap-3">
      @forelse($rankingItems as $item)
        @php
          $creator = is_array($item['creator'] ?? null) ? $item['creator'] : [];
          $creatorName = (string) ($creator['name'] ?? '@unknown');
          $creatorUsername = (string) ($creator['username'] ?? 'unknown');
          $creatorInitial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(ltrim($creatorName, '@'), 0, 1));
        @endphp
        <article class="flex flex-col gap-4 rounded-[28px] border border-gray-200 bg-gray-50/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
          <div class="flex min-w-0 items-center gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gray-900 text-sm font-semibold text-white">
              #{{ $item['rank'] }}
            </div>
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
                <p class="mt-1 truncate text-sm text-gray-500">&#64;{{ $creatorUsername }}</p>
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
              <x-shortvideo.follow-button :creator="$item" :login-url="$loginUrl" />
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

  <x-slot:modals>
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

  <x-slot:scripts>
    @vite('laravel/resources/js/features/social/follow-buttons.js')
    @if(($auth['shouldRenderModal'] ?? false) === true)
      @vite('laravel/resources/js/pages/auth/modal.js')
    @endif
  </x-slot:scripts>
</x-shortvideo.layout.app-shell>
