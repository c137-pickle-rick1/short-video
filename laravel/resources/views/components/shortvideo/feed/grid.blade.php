@props([
  'isEmpty' => false,
  'maxColumns' => 4,
])

@php
  $resolvedMaxColumns = max(1, min(4, (int) ($maxColumns ?? 4)));
  $columnClasses = match ($resolvedMaxColumns) {
      1 => ['', 'hidden', 'hidden', 'hidden'],
      2 => ['', '', 'hidden', 'hidden'],
      3 => ['', '', 'hidden xl:block', 'hidden'],
      default => ['', '', 'hidden xl:block', 'hidden 2xl:block'],
  };
@endphp

<section
  class="feed-grid py-3 sm:py-4 lg:py-0"
  id="feed-grid"
  aria-live="polite"
  data-empty="{{ $isEmpty ? 'true' : 'false' }}"
  data-feed-grid-max-columns="{{ $resolvedMaxColumns }}"
>
  <div class="feed-grid-col {{ $columnClasses[0] }}">
    {{ $slot }}
  </div>
  <div class="feed-grid-col {{ $columnClasses[1] }}"></div>
  <div class="feed-grid-col {{ $columnClasses[2] }}"></div>
  <div class="feed-grid-col {{ $columnClasses[3] }}"></div>
</section>
<div id="feed-sentinel" class="h-px" aria-hidden="true"></div>
<x-shortvideo.feed.loading-indicator />
