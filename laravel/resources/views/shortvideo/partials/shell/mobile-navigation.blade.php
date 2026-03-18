<nav
  aria-label="移动主导航"
  class="{{ $wrapperClass }}"
>
  <div class="mx-auto grid max-w-md" style="grid-template-columns: repeat({{ $columnCount }}, minmax(0, 1fr));">
    {!! $itemsMarkup !!}
  </div>
</nav>
