@props([
  'item' => [],
])

@php
  $item = is_array($item) ? $item : [];
@endphp

@if(($item['dividerBefore'] ?? false) === true)
  <div class="mx-6 my-2 h-px bg-gray-200" aria-hidden="true"></div>
@endif

<a
  href="{{ $item['href'] }}"
  @if($item['active']) aria-current="page" @endif
  @if(($item['authTriggerPanel'] ?? null) !== null) data-auth-modal-trigger="true" data-auth-modal-panel="{{ $item['authTriggerPanel'] }}" @endif
  @class([
    'inline-flex h-12 w-full items-center gap-4 rounded-full px-6 text-left transition-colors',
    'bg-gray-100 text-lg font-semibold text-gray-900 hover:bg-gray-200' => $item['active'],
    'text-lg font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-900' => ! $item['active'],
  ])
>
  <x-shortvideo.layout.navigation-item-visual
    :has-avatar="array_key_exists('avatarUrl', $item)"
    :avatar-url="(string) ($item['avatarUrl'] ?? '')"
    :avatar-initial="(string) ($item['avatarInitial'] ?? '我')"
    :icon="(string) ($item['icon'] ?? '')"
    icon-size-class="text-2xl"
  />
  <span>{{ $item['label'] }}</span>
</a>
