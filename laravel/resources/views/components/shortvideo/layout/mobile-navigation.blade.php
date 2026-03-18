@props([
  'items' => [],
  'wrapperClass' => '',
])

@php
  $items = is_array($items) ? $items : [];
  $columnCount = max(1, count($items));
@endphp

<nav
  aria-label="移动主导航"
  class="{{ $wrapperClass }}"
>
  <div class="mx-auto grid w-full max-w-[26.25rem] items-stretch" style="grid-template-columns: repeat({{ $columnCount }}, minmax(0, 1fr));">
    @foreach($items as $item)
      <x-shortvideo.layout.mobile-nav-item :item="$item" />
    @endforeach
  </div>
</nav>
