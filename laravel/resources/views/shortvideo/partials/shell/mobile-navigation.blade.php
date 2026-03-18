<nav
  aria-label="移动主导航"
  class="{{ $wrapperClass }}"
>
  <div class="mx-auto grid w-full max-w-[26.25rem] items-stretch" style="grid-template-columns: repeat({{ $columnCount }}, minmax(0, 1fr));">
    {!! $itemsMarkup !!}
  </div>
</nav>
