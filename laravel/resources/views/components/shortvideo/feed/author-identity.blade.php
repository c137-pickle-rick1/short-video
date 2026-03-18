@props([
  'imageUrl' => null,
  'authorName' => '',
  'authorHandle' => '',
  'authorInitial' => 'L',
  'avatarSizeClass' => 'h-7 w-7',
  'nameClass' => '',
  'handleClass' => null,
  'wrapperClass' => 'flex min-w-0 items-center gap-3',
  'fallbackClass' => 'bg-gray-100 text-gray-700',
  'imageClass' => '',
  'profileUrl' => null,
])

@if(!empty($profileUrl))
  <a
    href="{{ $profileUrl }}"
    class="{{ $wrapperClass }} rounded-2xl transition hover:opacity-80 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-300"
  >
    <x-ui.avatar
      :image-url="$imageUrl"
      :label="$authorName"
      :initial="$authorInitial"
      :size-class="$avatarSizeClass"
      :fallback-class="$fallbackClass"
      :image-class="$imageClass"
    />
    <div class="min-w-0">
      <p class="{{ $nameClass }}">{{ $authorName }}</p>
      @if($handleClass !== null && $handleClass !== '')
        <p class="{{ $handleClass }}">&#64;{{ $authorHandle }}</p>
      @endif
    </div>
  </a>
@else
  <div class="{{ $wrapperClass }}">
    <x-ui.avatar
      :image-url="$imageUrl"
      :label="$authorName"
      :initial="$authorInitial"
      :size-class="$avatarSizeClass"
      :fallback-class="$fallbackClass"
      :image-class="$imageClass"
    />
    <div class="min-w-0">
      <p class="{{ $nameClass }}">{{ $authorName }}</p>
      @if($handleClass !== null && $handleClass !== '')
        <p class="{{ $handleClass }}">&#64;{{ $authorHandle }}</p>
      @endif
    </div>
  </div>
@endif
