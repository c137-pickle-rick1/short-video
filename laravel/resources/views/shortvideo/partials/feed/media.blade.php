<div class="relative {{ $frameClass }} overflow-hidden bg-gray-100">
  @if($showVideo)
    <video
      class="js-feed-player h-full w-full object-cover"
      @if($posterUrl !== '') poster="{{ $posterUrl }}" @endif
      data-poster="{{ $posterUrl }}"
      @if($hlsUrl !== '') data-hls-url="{{ $hlsUrl }}" @endif
      @if($videoUrl !== '') data-fallback-url="{{ $videoUrl }}" @endif
      muted
      loop
      playsinline
      disablepictureinpicture
      preload="{{ $videoPreload }}"
      referrerpolicy="no-referrer"
    ></video>
  @else
    <img
      class="h-full w-full object-cover"
      src="{{ $posterUrl }}"
      alt="Poster for &#64;{{ $authorHandle }}"
      loading="lazy"
    />
  @endif
  <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-transparent via-black/5 to-black/10"></div>
  {!! $durationBadge !!}
</div>
