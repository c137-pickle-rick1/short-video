<article
  class="feed-grid-item group mb-3 inline-block w-full cursor-pointer overflow-hidden rounded-3xl border border-gray-200 bg-white/95 shadow-sm backdrop-blur-xl transition duration-300 hover:-translate-y-1 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-rose-300 sm:mb-4 lg:mb-5 xl:mb-6 2xl:mb-7"
  data-tweet-id="{{ $tweetId }}"
  data-status="{{ $status }}"
  data-feed-detail-trigger="true"
  role="button"
  tabindex="0"
  aria-haspopup="dialog"
  aria-label="打开 {{ $authorName }} 的视频详情"
>
  {!! $mediaMarkup !!}
  <div class="grid gap-3 px-4 pb-4 pt-3">
    <p class="line-clamp-2 overflow-hidden text-base font-semibold leading-6 text-gray-900">
      {{ $displayText }}
    </p>
    <div class="flex items-center justify-between gap-3">
      {!! $authorMarkup !!}
      <div class="shrink-0 text-xs text-gray-500">
        <span>{{ $postedAtText }}</span>
      </div>
    </div>
  </div>
</article>
