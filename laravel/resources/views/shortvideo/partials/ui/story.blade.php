<article class="{{ $storyClass }}">
  <div>
    <h3 class="text-base font-semibold tracking-tight text-gray-950">{{ $title }}</h3>
  </div>
  <div @if($previewClass !== '') class="{{ $previewClass }}" @endif>
    {!! $preview !!}
  </div>
  @if($note !== '')
    <p class="text-sm leading-6 text-gray-500">{{ $note }}</p>
  @endif
</article>
