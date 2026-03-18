@php
  $bookmarkPagination = is_array($bookmarkPagination ?? null) ? $bookmarkPagination : [];
  $bookmarkPaginationLinks = is_array($bookmarkPagination['links'] ?? null) ? $bookmarkPagination['links'] : [];
@endphp

<x-shortvideo.layout.app-shell :shell="$shell">
  <div class="grid gap-5 lg:gap-6 xl:gap-7">
    <x-shortvideo.layout.navigation-bar
      :title="$page['title'] ?? '我的收藏'"
      container-class="relative flex h-12 items-center justify-center rounded-full border border-gray-200 bg-white/90 px-1 shadow-sm backdrop-blur-xl"
      title-wrapper-class="min-w-0 px-14 text-center"
      title-class="truncate text-sm font-semibold tracking-[0.08em] text-gray-950 sm:text-base"
      :leading-action="[
        'iconClass' => 'ph ph-arrow-left',
        'label' => '返回上一页',
        'attributes' => [
          'type' => 'button',
          'data-bookmark-back' => 'true',
          'data-fallback-url' => route('profile.me'),
          'class' => 'inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-700 transition hover:text-gray-950',
        ],
      ]"
    />

    <p
      data-bookmark-feedback="true"
      class="hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700"
      hidden
    ></p>

    <section
      data-bookmark-record-grid="true"
      data-bookmark-previous-page-url="{{ $bookmarkPagination['previousPageUrl'] ?? '' }}"
      data-bookmark-per-page="{{ $bookmarkPagination['perPage'] ?? 12 }}"
      data-bookmark-total-count="{{ $bookmarkPagination['totalCount'] ?? 0 }}"
      class="grid grid-cols-2 gap-3 sm:gap-4 lg:gap-5 xl:grid-cols-3 xl:gap-6 2xl:grid-cols-4 2xl:gap-7"
      aria-live="polite"
      @if(empty($bookmarkHasItems)) hidden @endif
    >
      @foreach($bookmarkItems as $item)
        <x-shortvideo.feed.item
          :tweet-id="(string) ($item['tweetId'] ?? '')"
          :status="(string) ($item['status'] ?? 'pending')"
          :author-name="(string) ($item['authorName'] ?? '@unknown')"
          :display-text="(string) ($item['displayText'] ?? '')"
          :posted-at-text="(string) ($item['postedAtText'] ?? '')"
          :title-line-clamp-class="(string) ($item['titleLineClampClass'] ?? 'line-clamp-1')"
          :interactive="false"
          :root-attributes="$item['rootAttributes'] ?? []"
          :card-class="(string) ($item['cardClass'] ?? '')"
        >
          <x-slot:overlay>
            <x-shortvideo.bookmarks.remove-button :remove-url="'/api/videos/'.(int) (($item['rootAttributes']['data-bookmark-video-id'] ?? 0)).'/bookmarks'" />
          </x-slot:overlay>
          <x-slot:media>
            <x-shortvideo.feed.media
              :frame-class="(string) ($item['media']['frameClass'] ?? 'aspect-video')"
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
    </section>

    @if(!empty($bookmarkPagination['hasPages']))
      <nav data-bookmark-pagination="true" aria-label="我的收藏分页" class="rounded-[28px] border border-gray-200 bg-white/90 px-4 py-4 shadow-sm sm:px-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <p class="text-sm font-medium text-gray-600">
            共 <span data-bookmark-total-count="true" class="font-semibold text-gray-950">{{ $bookmarkPagination['totalCount'] ?? 0 }}</span> 条收藏，
            第 {{ $bookmarkPagination['currentPage'] ?? 1 }} / {{ $bookmarkPagination['lastPage'] ?? 1 }} 页
          </p>

          <div class="flex flex-wrap items-center gap-2">
            @if(!empty($bookmarkPagination['previousPageUrl']))
              <a
                href="{{ $bookmarkPagination['previousPageUrl'] }}"
                aria-label="上一页"
                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 text-gray-700 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-950"
              >
                <i class="ph ph-caret-left text-base leading-none" aria-hidden="true"></i>
              </a>
            @else
              <span
                aria-label="上一页"
                class="inline-flex h-10 w-10 cursor-not-allowed items-center justify-center rounded-full border border-gray-200 text-gray-300"
              >
                <i class="ph ph-caret-left text-base leading-none" aria-hidden="true"></i>
              </span>
            @endif

            @foreach($bookmarkPaginationLinks as $link)
              @if(($link['type'] ?? 'page') === 'ellipsis')
                <span class="inline-flex h-10 min-w-10 items-center justify-center px-1 text-sm font-semibold text-gray-400">…</span>
              @elseif(!empty($link['active']))
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-950 text-sm font-semibold text-white">
                  {{ $link['label'] ?? '' }}
                </span>
              @else
                <a
                  href="{{ $link['url'] ?? '#' }}"
                  class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-950"
                >
                  {{ $link['label'] ?? '' }}
                </a>
              @endif
            @endforeach

            @if(!empty($bookmarkPagination['nextPageUrl']))
              <a
                href="{{ $bookmarkPagination['nextPageUrl'] }}"
                aria-label="下一页"
                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 text-gray-700 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-950"
              >
                <i class="ph ph-caret-right text-base leading-none" aria-hidden="true"></i>
              </a>
            @else
              <span
                aria-label="下一页"
                class="inline-flex h-10 w-10 cursor-not-allowed items-center justify-center rounded-full border border-gray-200 text-gray-300"
              >
                <i class="ph ph-caret-right text-base leading-none" aria-hidden="true"></i>
              </span>
            @endif
          </div>
        </div>
      </nav>
    @endif

    <div data-bookmark-empty-state="true" @if(!empty($bookmarkHasItems)) hidden @endif>
      <x-shortvideo.feed.empty-state
        :title="(string) ($bookmarkEmptyState['title'] ?? '还没有收藏内容')"
        :description="(string) ($bookmarkEmptyState['description'] ?? '')"
        :icon-class="(string) ($bookmarkEmptyState['iconClass'] ?? 'ph ph-bookmark-simple')"
        :button-label="(string) ($bookmarkEmptyState['buttonLabel'] ?? '去探索')"
        :button-href="(string) ($bookmarkEmptyState['buttonHref'] ?? route('explore'))"
      />
    </div>
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
    @vite('laravel/resources/js/pages/bookmarks/index.js')
    @if(($auth['shouldRenderModal'] ?? false) === true)
      @vite('laravel/resources/js/pages/auth/modal.js')
    @endif
  </x-slot:scripts>
</x-shortvideo.layout.app-shell>
