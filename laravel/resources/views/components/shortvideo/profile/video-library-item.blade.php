@props([
  'item' => [],
])

@php
  $statusTag = is_array($item['statusTag'] ?? null) ? $item['statusTag'] : null;
  $actions = is_array($item['actions'] ?? null) ? $item['actions'] : [];
  $thumbnailImageUrl = trim((string) ($item['thumbnailImageUrl'] ?? ''));
  $thumbnailVideoUrl = trim((string) ($item['thumbnailVideoUrl'] ?? ''));
  $dateLabel = trim((string) ($item['dateLabel'] ?? ''));
  $dateText = trim((string) ($item['dateText'] ?? ''));
  $progressLabel = trim((string) ($item['progressLabel'] ?? ''));
@endphp

<article
  data-profile-library-item="true"
  data-profile-library-status="{{ $item['status'] }}"
  class="rounded-[28px] border border-gray-200 bg-white px-4 py-4 shadow-sm sm:px-5 sm:py-5"
>
  <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
    <div
      data-profile-library-thumbnail="true"
      class="relative h-[120px] w-full shrink-0 overflow-hidden rounded-2xl bg-gray-950 sm:w-[160px]"
    >
      @if($thumbnailImageUrl !== '')
        <img
          src="{{ $thumbnailImageUrl }}"
          alt="{{ $item['title'] }} 的缩略图"
          class="h-full w-full object-cover"
          loading="lazy"
          referrerpolicy="no-referrer"
        />
      @elseif($thumbnailVideoUrl !== '')
        <video
          src="{{ $thumbnailVideoUrl }}"
          class="h-full w-full object-cover"
          muted
          playsinline
          preload="metadata"
          aria-label="{{ $item['title'] }} 的视频预览"
        ></video>
      @else
        <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-900 via-gray-800 to-gray-700 text-white">
          <span class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/15 bg-white/10">
            <i class="ph ph-video-camera text-xl leading-none" aria-hidden="true"></i>
          </span>
        </div>
      @endif

      <span
        data-profile-library-duration="true"
        class="absolute bottom-2 right-2 inline-flex items-center rounded-full bg-gray-950/75 px-2.5 py-1 text-[0.7rem] font-semibold tracking-[0.08em] text-white backdrop-blur-sm"
      >
        {{ $item['durationText'] }}
      </span>
    </div>

    <div class="min-w-0 flex-1">
      <div class="flex items-start gap-3">
        @if($statusTag !== null)
          <span
            data-profile-library-status-tag="true"
            @class([
                'mt-0.5 inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-semibold',
                'bg-amber-100 text-amber-700' => $statusTag['tone'] === 'warning',
                'bg-rose-100 text-rose-700' => $statusTag['tone'] === 'danger',
            ])
          >
            {{ $statusTag['label'] }}
          </span>
        @endif

        <h4 class="min-w-0 flex-1 text-base font-semibold leading-6 text-gray-950 sm:text-lg">
          <span class="line-clamp-2">{{ $item['title'] }}</span>
        </h4>
      </div>

      <div class="mt-2 grid gap-1.5">
        <p data-profile-library-tag-line="true" class="text-sm leading-6 text-gray-500">
          {{ $item['tagLine'] }}
        </p>

        @if($dateLabel !== '' && $dateText !== '')
          <p data-profile-library-date-line="true" class="text-xs font-medium tracking-[0.08em] text-gray-400">
            {{ $dateLabel }}：{{ $dateText }}
          </p>
        @endif
      </div>

      @if($progressLabel !== '')
        <div data-profile-library-progress="true" class="mt-4 flex items-center gap-3">
          <div class="relative h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
            <div class="absolute inset-y-0 left-0 w-1/3 rounded-full bg-amber-500/80 animate-pulse"></div>
          </div>
          <span class="shrink-0 text-xs font-medium text-gray-500">{{ $progressLabel }}</span>
        </div>
      @elseif($actions !== [])
        <div data-profile-library-actions="true" class="mt-4 flex flex-wrap gap-2.5">
          @foreach($actions as $action)
            <button
              type="button"
              disabled
              data-profile-library-action="{{ $action['key'] }}"
              class="inline-flex items-center justify-center rounded-full border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-500"
            >
              {{ $action['label'] }}
            </button>
          @endforeach
        </div>
      @endif
    </div>
  </div>
</article>
