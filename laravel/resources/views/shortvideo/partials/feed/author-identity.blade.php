@if(!empty($profileUrl))
  <a
    href="{{ $profileUrl }}"
    class="{{ $wrapperClass }} rounded-2xl transition hover:opacity-80 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-300"
  >
    {!! $avatarMarkup !!}
    <div class="min-w-0">
      <p class="{{ $nameClass }}">{{ $authorName }}</p>
      @if($handleClass !== null && $handleClass !== '')
        <p class="{{ $handleClass }}">&#64;{{ $authorHandle }}</p>
      @endif
    </div>
  </a>
@else
  <div class="{{ $wrapperClass }}">
    {!! $avatarMarkup !!}
    <div class="min-w-0">
      <p class="{{ $nameClass }}">{{ $authorName }}</p>
      @if($handleClass !== null && $handleClass !== '')
        <p class="{{ $handleClass }}">&#64;{{ $authorHandle }}</p>
      @endif
    </div>
  </div>
@endif
