@props([
  'historyPagination' => [],
  'historyHasItems' => false,
  'historyItems' => [],
  'historyEmptyState' => [],
  'containerClass' => 'grid gap-5 lg:gap-6 xl:gap-7',
])

@php
  $historyPagination = is_array($historyPagination ?? null) ? $historyPagination : [];
  $historyPaginationLinks = is_array($historyPagination['links'] ?? null) ? $historyPagination['links'] : [];
@endphp

<div class="{{ $containerClass }}">
  <p
    data-history-feedback="true"
    class="hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700"
    hidden
  ></p>

  <section
    data-history-record-grid="true"
    data-history-previous-page-url="{{ $historyPagination['previousPageUrl'] ?? '' }}"
    data-history-per-page="{{ $historyPagination['perPage'] ?? 12 }}"
    data-history-total-count="{{ $historyPagination['totalCount'] ?? 0 }}"
    class="grid grid-cols-2 gap-3 sm:gap-4 lg:gap-5 xl:grid-cols-3 xl:gap-6 2xl:grid-cols-4 2xl:gap-7"
    aria-live="polite"
    @if(empty($historyHasItems)) hidden @endif
  >
    @foreach($historyItems as $item)
      <x-shortvideo.feed.item
        :tweet-id="(string) ($item['tweetId'] ?? '')"
        :status="(string) ($item['status'] ?? 'pending')"
        :author-name="(string) ($item['authorName'] ?? '@unknown')"
        :display-text="(string) ($item['displayText'] ?? '')"
        :detail-url="$item['detailUrl'] ?? null"
        :posted-at-text="(string) ($item['postedAtText'] ?? '')"
        :title-line-clamp-class="(string) ($item['titleLineClampClass'] ?? 'line-clamp-1')"
        :interactive="false"
        :root-attributes="$item['rootAttributes'] ?? []"
        :card-class="(string) ($item['cardClass'] ?? '')"
      >
        <x-slot:overlay>
          <x-shortvideo.history.delete-button :delete-url="'/api/videos/'.(int) (($item['rootAttributes']['data-history-video-id'] ?? 0)).'/history'" />
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

  @if(!empty($historyPagination['hasPages']))
    <nav data-history-pagination="true" aria-label="观看记录分页" class="rounded-[28px] border border-gray-200 bg-white/90 px-4 py-4 shadow-sm sm:px-5">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <p class="text-sm font-medium text-gray-600">
          共 <span data-history-total-count="true" class="font-semibold text-gray-950">{{ $historyPagination['totalCount'] ?? 0 }}</span> 条记录，
          第 {{ $historyPagination['currentPage'] ?? 1 }} / {{ $historyPagination['lastPage'] ?? 1 }} 页
        </p>

        <div class="flex flex-wrap items-center gap-2">
          @if(!empty($historyPagination['previousPageUrl']))
            <a
              href="{{ $historyPagination['previousPageUrl'] }}"
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

          @foreach($historyPaginationLinks as $link)
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

          @if(!empty($historyPagination['nextPageUrl']))
            <a
              href="{{ $historyPagination['nextPageUrl'] }}"
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

  <div data-history-empty-state="true" @if(!empty($historyHasItems)) hidden @endif>
    <x-shortvideo.feed.empty-state
      :title="(string) ($historyEmptyState['title'] ?? '还没有观看记录')"
      :description="(string) ($historyEmptyState['description'] ?? '')"
      :icon-class="(string) ($historyEmptyState['iconClass'] ?? 'ph ph-clock-counter-clockwise')"
      :button-label="(string) ($historyEmptyState['buttonLabel'] ?? '去探索')"
      :button-href="(string) ($historyEmptyState['buttonHref'] ?? route('explore'))"
    />
  </div>
</div>
