@php
  $iconClass = trim((string) ($iconClass ?? 'ph ph-magnifying-glass'));
  $title = (string) ($title ?? '');
  $description = (string) ($description ?? '');
  $buttonLabel = trim((string) ($buttonLabel ?? ''));
  $buttonHref = trim((string) ($buttonHref ?? ''));
  $buttonAttributes = is_array($buttonAttributes ?? null) ? $buttonAttributes : [];
  $containerClass = trim((string) ($containerClass ?? 'flex min-h-[clamp(26rem,58vh,42rem)] w-full flex-col items-center justify-center px-6 py-12 text-center'));
  $iconShellClass = trim((string) ($iconShellClass ?? 'mx-auto flex items-center justify-center text-[4rem] text-gray-400'));
  $titleClass = trim((string) ($titleClass ?? 'mt-4 text-xl font-semibold tracking-tight text-gray-950 sm:text-2xl'));
  $descriptionClass = trim((string) ($descriptionClass ?? 'mt-4 mx-auto w-full max-w-xl text-sm leading-7 text-gray-500 sm:text-base'));
  $buttonClass = trim((string) ($buttonClass ?? 'inline-flex h-11 items-center justify-center gap-2 rounded-full bg-gray-900 px-5 text-sm font-semibold text-white transition hover:bg-rose-600'));
  $buttonTag = $buttonHref !== '' ? 'a' : 'button';
@endphp

<article
  class="{{ $containerClass }}"
  @if(($dataEmptyState ?? false) === true) data-empty-state="true" @endif
>
  <div class="{{ $iconShellClass }}">
    <i class="{{ $iconClass }}" aria-hidden="true"></i>
  </div>

  <h2 class="{{ $titleClass }}">
    {{ $title }}
  </h2>

  <p class="{{ $descriptionClass }}">
    {!! $description !!}
  </p>

  @if($buttonLabel !== '')
    <div class="mt-6">
      <{{ $buttonTag }}
        @if($buttonHref !== '') href="{{ $buttonHref }}" @else type="button" @endif
        @foreach($buttonAttributes as $attribute => $value)
          @if(is_bool($value))
            @if($value)
              {{ $attribute }}
            @endif
          @elseif($value !== null && $value !== '')
            {{ $attribute }}="{{ $value }}"
          @endif
        @endforeach
        class="{{ $buttonClass }}"
      >
        <span>{{ $buttonLabel }}</span>
        <i class="ph ph-arrow-right text-base leading-none" aria-hidden="true"></i>
      </{{ $buttonTag }}>
    </div>
  @endif
</article>
