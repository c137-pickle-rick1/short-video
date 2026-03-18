@if($hasAvatar)
  <span
    class="shrink-0"
    data-avatar-slot="nav"
    data-avatar-kind="nav"
    data-avatar-initial="{{ $avatarInitial }}"
    data-avatar-url="{{ $avatarUrl }}"
  >
    @if($avatarUrl !== '')
      <img
        src="{{ $avatarUrl }}"
        alt=""
        class="size-6 rounded-full object-cover ring-1 ring-gray-200"
        loading="lazy"
        referrerpolicy="no-referrer"
      />
    @else
      <span class="flex size-6 items-center justify-center rounded-full bg-gray-900 text-xs font-semibold leading-none text-white">
        {{ $avatarInitial }}
      </span>
    @endif
  </span>
@else
  <i class="{{ $icon }} {{ $iconSizeClass }} leading-none" aria-hidden="true"></i>
@endif
