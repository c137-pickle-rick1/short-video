@php
  $interactionPagination = is_array($interactionPagination ?? null) ? $interactionPagination : [];
  $interactionPaginationLinks = is_array($interactionPagination['links'] ?? null) ? $interactionPagination['links'] : [];
@endphp

<x-shortvideo.layout.app-shell :shell="$shell">
  <div class="grid gap-5 lg:gap-6 xl:gap-7">
    <x-shortvideo.layout.navigation-bar
      :title="$page['title'] ?? '我的互动'"
      container-class="relative flex h-12 items-center justify-center rounded-full border border-gray-200 bg-white/90 px-1 shadow-sm backdrop-blur-xl"
      title-wrapper-class="min-w-0 px-14 text-center"
      title-class="truncate text-sm font-semibold tracking-[0.08em] text-gray-950 sm:text-base"
      :leading-action="[
        'iconClass' => 'ph ph-arrow-left',
        'label' => '返回上一页',
        'attributes' => [
          'type' => 'button',
          'data-interaction-back' => 'true',
          'data-fallback-url' => route('profile.me'),
          'class' => 'inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-700 transition hover:text-gray-950',
        ],
      ]"
    />

    <p
      data-interaction-feedback="true"
      class="hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700"
      hidden
    ></p>

    <section
      data-interaction-list="true"
      data-interaction-previous-page-url="{{ $interactionPagination['previousPageUrl'] ?? '' }}"
      data-interaction-per-page="{{ $interactionPagination['perPage'] ?? 12 }}"
      data-interaction-total-count="{{ $interactionPagination['totalCount'] ?? 0 }}"
      class="overflow-hidden rounded-[32px] border border-gray-200 bg-white shadow-sm"
      aria-live="polite"
      @if(empty($interactionHasItems)) hidden @endif
    >
      <div class="divide-y divide-gray-200">
        @foreach($interactionItems as $item)
          <x-shortvideo.interactions.item
            :item-attributes="$item['itemAttributes'] ?? []"
            :interaction-label="(string) ($item['interactionLabel'] ?? '')"
            :interaction-icon-class="(string) ($item['interactionIconClass'] ?? '')"
            :interaction-at-text="(string) ($item['interactionAtText'] ?? '')"
            :video-title="(string) ($item['videoTitle'] ?? '')"
            :author-name="(string) ($item['authorName'] ?? '')"
            :author-handle="(string) ($item['authorHandle'] ?? '')"
            :comment-body="(string) ($item['commentBody'] ?? '')"
            :action-label="(string) ($item['actionLabel'] ?? '')"
            :action-url="(string) ($item['actionUrl'] ?? '')"
            :action-loading-label="(string) ($item['actionLoadingLabel'] ?? '')"
          >
            <x-slot:preview>
              <x-shortvideo.feed.item
                :tweet-id="(string) ($item['preview']['tweetId'] ?? '')"
                :status="(string) ($item['preview']['status'] ?? 'pending')"
                :author-name="(string) ($item['preview']['authorName'] ?? '@unknown')"
                :display-text="(string) ($item['preview']['displayText'] ?? '')"
                :posted-at-text="(string) ($item['preview']['postedAtText'] ?? '')"
                :title-line-clamp-class="(string) ($item['preview']['titleLineClampClass'] ?? 'line-clamp-1')"
                :interactive="false"
                :root-attributes="$item['preview']['rootAttributes'] ?? []"
                :card-class="(string) ($item['preview']['cardClass'] ?? '')"
              >
                <x-slot:media>
                  <x-shortvideo.feed.media
                    :frame-class="(string) ($item['preview']['media']['frameClass'] ?? 'aspect-video rounded-2xl')"
                    :poster-url="(string) ($item['preview']['media']['posterUrl'] ?? '')"
                    :hls-url="(string) ($item['preview']['media']['hlsUrl'] ?? '')"
                    :video-url="(string) ($item['preview']['media']['videoUrl'] ?? '')"
                    :author-handle="(string) ($item['preview']['media']['authorHandle'] ?? 'unknown')"
                    :video-preload="(string) ($item['preview']['media']['videoPreload'] ?? 'none')"
                    :show-video="($item['preview']['media']['showVideo'] ?? false) === true"
                    :duration-text="(string) ($item['preview']['media']['durationText'] ?? '')"
                  />
                </x-slot:media>
                <x-slot:author>
                  <x-shortvideo.feed.author-identity
                    :image-url="$item['preview']['author']['imageUrl'] ?? null"
                    :author-name="(string) ($item['preview']['author']['authorName'] ?? '@unknown')"
                    :author-handle="(string) ($item['preview']['author']['authorHandle'] ?? 'unknown')"
                    :author-initial="(string) ($item['preview']['author']['authorInitial'] ?? 'L')"
                    :avatar-size-class="(string) ($item['preview']['author']['avatarSizeClass'] ?? 'h-7 w-7')"
                    :name-class="(string) ($item['preview']['author']['nameClass'] ?? '')"
                    :handle-class="$item['preview']['author']['handleClass'] ?? null"
                    :wrapper-class="(string) ($item['preview']['author']['wrapperClass'] ?? 'flex min-w-0 items-center gap-3')"
                    :fallback-class="(string) ($item['preview']['author']['fallbackClass'] ?? 'bg-gray-100 text-gray-700')"
                    :image-class="(string) ($item['preview']['author']['imageClass'] ?? '')"
                    :profile-url="$item['preview']['author']['profileUrl'] ?? null"
                  />
                </x-slot:author>
              </x-shortvideo.feed.item>
            </x-slot:preview>
          </x-shortvideo.interactions.item>
        @endforeach
      </div>
    </section>

    @if(!empty($interactionPagination['hasPages']))
      <nav data-interaction-pagination="true" aria-label="我的互动分页" class="rounded-[28px] border border-gray-200 bg-white/90 px-4 py-4 shadow-sm sm:px-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <p class="text-sm font-medium text-gray-600">
            共 <span data-interaction-total-count="true" class="font-semibold text-gray-950">{{ $interactionPagination['totalCount'] ?? 0 }}</span> 条互动，
            第 {{ $interactionPagination['currentPage'] ?? 1 }} / {{ $interactionPagination['lastPage'] ?? 1 }} 页
          </p>

          <div class="flex flex-wrap items-center gap-2">
            @if(!empty($interactionPagination['previousPageUrl']))
              <a
                href="{{ $interactionPagination['previousPageUrl'] }}"
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

            @foreach($interactionPaginationLinks as $link)
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

            @if(!empty($interactionPagination['nextPageUrl']))
              <a
                href="{{ $interactionPagination['nextPageUrl'] }}"
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

    <div data-interaction-empty-state="true" @if(!empty($interactionHasItems)) hidden @endif>
      <x-shortvideo.feed.empty-state
        :title="(string) ($interactionEmptyState['title'] ?? '还没有互动内容')"
        :description="(string) ($interactionEmptyState['description'] ?? '')"
        :icon-class="(string) ($interactionEmptyState['iconClass'] ?? 'ph ph-chat-circle-dots')"
        :button-label="(string) ($interactionEmptyState['buttonLabel'] ?? '去探索')"
        :button-href="(string) ($interactionEmptyState['buttonHref'] ?? route('explore'))"
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
    @vite('laravel/resources/js/pages/interactions/index.js')
    @if(($auth['shouldRenderModal'] ?? false) === true)
      @vite('laravel/resources/js/pages/auth/modal.js')
    @endif
  </x-slot:scripts>
</x-shortvideo.layout.app-shell>
