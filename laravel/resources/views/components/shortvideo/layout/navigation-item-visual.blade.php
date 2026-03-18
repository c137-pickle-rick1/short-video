@props([
  'hasAvatar' => false,
  'avatarUrl' => '',
  'avatarInitial' => 'L',
  'icon' => '',
  'iconSizeClass' => 'text-2xl',
])

@if($hasAvatar)
  <span
    class="shrink-0"
    data-avatar-slot="nav"
    data-avatar-kind="nav"
    data-avatar-initial="{{ $avatarInitial }}"
    data-avatar-url="{{ $avatarUrl }}"
  >
    <x-ui.avatar
      :image-url="$avatarUrl !== '' ? $avatarUrl : null"
      label=""
      :initial="$avatarInitial"
      size-class="size-6"
      fallback-class="bg-gray-900 text-white"
      image-class=""
    />
  </span>
@else
  <i class="{{ $icon }} {{ $iconSizeClass }} leading-none" aria-hidden="true"></i>
@endif
