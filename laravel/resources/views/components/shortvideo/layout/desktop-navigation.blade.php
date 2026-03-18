@props([
  'items' => [],
  'wrapperClass' => '',
])

@php
  $items = is_array($items) ? $items : [];
@endphp

<aside class="{{ $wrapperClass }}">
  <nav aria-label="桌面主导航">
    <div class="grid gap-2">
      @foreach($items as $item)
        <x-shortvideo.layout.desktop-nav-item :item="$item" />
      @endforeach
    </div>
  </nav>
</aside>
