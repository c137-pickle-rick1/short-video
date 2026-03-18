<div class="{{ $wrapperClass }}">
  {!! $avatarMarkup !!}
  <div class="min-w-0">
    <p class="{{ $nameClass }}">{{ $authorName }}</p>
    @if($handleClass !== null && $handleClass !== '')
      <p class="{{ $handleClass }}">&#64;{{ $authorHandle }}</p>
    @endif
  </div>
</div>
