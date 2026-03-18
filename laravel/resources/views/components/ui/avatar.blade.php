@props([
  'imageUrl' => null,
  'label' => '',
  'initial' => 'L',
  'sizeClass' => 'h-7 w-7',
  'fallbackClass' => 'bg-gray-100 text-gray-700',
  'imageClass' => '',
])

@if($imageUrl)
  <img
    class="{{ trim($sizeClass.' rounded-full object-cover ring-1 ring-gray-200 '.$imageClass) }}"
    src="{{ $imageUrl }}"
    alt="{{ $label }} 的头像"
    loading="lazy"
    referrerpolicy="no-referrer"
  />
@else
  <span
    class="flex {{ $sizeClass }} items-center justify-center rounded-full {{ $fallbackClass }} text-xs font-semibold"
    aria-hidden="true"
  >
    {{ $initial }}
  </span>
@endif
