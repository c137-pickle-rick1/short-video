@php
  $rootAttributes = is_array($itemAttributes ?? null) ? $itemAttributes : [];
@endphp

<article
  @foreach($rootAttributes as $attribute => $value)
    {{ $attribute }}="{{ $value }}"
  @endforeach
  class="grid gap-5 p-4 sm:p-5 lg:grid-cols-[minmax(0,19rem)_minmax(0,1fr)] lg:items-start lg:gap-6 lg:p-6"
>
  <div class="min-w-0">
    {!! $previewMarkup !!}
  </div>

  <div class="flex min-w-0 flex-col gap-4">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
      <span class="inline-flex items-center gap-2 text-sm font-semibold tracking-[0.08em] text-gray-950">
        <i class="{{ $interactionIconClass }} text-base leading-none text-gray-500" aria-hidden="true"></i>
        {{ $interactionLabel }}
      </span>
      <span class="text-sm font-medium text-gray-500">{{ $interactionAtText }}</span>
    </div>

    <div class="grid gap-2">
      <p class="line-clamp-2 text-lg font-semibold leading-7 text-gray-950">{{ $videoTitle }}</p>
      <p class="text-sm font-medium text-gray-500">来自 {{ $authorName }} · &#64;{{ $authorHandle }}</p>
    </div>

    @if($commentBody !== '')
      <div class="rounded-2xl bg-gray-50 px-4 py-3 text-sm leading-6 text-gray-700">
        {{ $commentBody }}
      </div>
    @endif

    <div>
      <button
        type="button"
        data-interaction-action="true"
        data-action-url="{{ $actionUrl }}"
        data-loading-label="{{ $actionLoadingLabel }}"
        class="inline-flex h-10 items-center justify-center rounded-full border border-gray-200 px-4 text-sm font-semibold text-gray-700 transition hover:border-rose-300 hover:text-rose-600 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-300"
      >
        {{ $actionLabel }}
      </button>
    </div>
  </div>
</article>
