@props([
  'item' => [],
  'loginUrl' => '',
])

@php
  $item = is_array($item ?? null) ? $item : [];
  $creator = is_array($item['creator'] ?? null) ? $item['creator'] : [];
  $creatorName = trim((string) ($creator['name'] ?? '')) !== '' ? trim((string) $creator['name']) : '@unknown';
  $creatorUsername = trim((string) ($creator['username'] ?? '')) !== '' ? trim((string) $creator['username']) : 'unknown';
  $creatorBio = trim((string) ($creator['bio'] ?? ''));
  $creatorAvatarUrl = trim((string) ($creator['avatarUrl'] ?? ''));
  $creatorInitial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(ltrim($creatorName, '@'), 0, 1));
@endphp

<article class="flex items-center justify-between gap-4 py-5 first:pt-0 last:pb-0">
  <div class="flex min-w-0 items-center gap-4">
    <div class="shrink-0">
      <x-ui.avatar
        :image-url="$creatorAvatarUrl !== '' ? $creatorAvatarUrl : null"
        :label="$creatorName"
        :initial="$creatorInitial"
        size-class="h-14 w-14"
        fallback-class="bg-gray-900 text-white"
      />
    </div>

    <div class="min-w-0">
      <p class="truncate text-xl font-semibold text-gray-950">{{ $creatorName }}</p>
      <p class="mt-1 truncate text-sm text-gray-400">&#64;{{ $creatorUsername }}</p>
      @if($creatorBio !== '')
        <p class="mt-2 line-clamp-2 text-sm leading-6 text-gray-500">{{ $creatorBio }}</p>
      @endif
    </div>
  </div>

  <div class="shrink-0">
    <x-shortvideo.follow-button
      :creator="$item"
      :login-url="$loginUrl"
      size="compact"
    />
  </div>
</article>
