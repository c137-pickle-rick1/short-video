@props([
  'title' => '',
  'containerClass' => 'relative flex h-12 items-center justify-center rounded-full border border-gray-200 bg-white/90 px-4 shadow-sm backdrop-blur-xl',
  'titleWrapperClass' => 'min-w-0 px-14 text-center',
  'titleClass' => 'truncate text-sm font-semibold tracking-[0.08em] text-gray-950 sm:text-base',
  'leadingWrapperClass' => 'absolute left-4',
  'trailingWrapperClass' => 'absolute right-4',
  'leadingAction' => null,
  'trailingAction' => null,
])

@php
  $title = (string) ($title ?? '');
  $containerClass = trim((string) ($containerClass ?? 'relative flex h-12 items-center justify-center rounded-full border border-gray-200 bg-white/90 px-4 shadow-sm backdrop-blur-xl'));
  $titleWrapperClass = trim((string) ($titleWrapperClass ?? 'min-w-0 px-14 text-center'));
  $titleClass = trim((string) ($titleClass ?? 'truncate text-sm font-semibold tracking-[0.08em] text-gray-950 sm:text-base'));
  $leadingWrapperClass = trim((string) ($leadingWrapperClass ?? 'absolute left-4'));
  $trailingWrapperClass = trim((string) ($trailingWrapperClass ?? 'absolute right-4'));
  $leadingAction = is_array($leadingAction ?? null) ? $leadingAction : null;
  $trailingAction = is_array($trailingAction ?? null) ? $trailingAction : null;
@endphp

<section class="{{ $containerClass }}" {{ $attributes }}>
  @if($leadingAction !== null)
    @php
      $leadingTag = ($leadingAction['tag'] ?? 'button') === 'a' ? 'a' : 'button';
      $leadingIconClass = trim((string) ($leadingAction['iconClass'] ?? ''));
      $leadingLabel = trim((string) ($leadingAction['label'] ?? ''));
      $leadingShowLabel = ($leadingAction['showLabel'] ?? false) === true;
      $leadingAttributes = is_array($leadingAction['attributes'] ?? null) ? $leadingAction['attributes'] : [];
      if (! $leadingShowLabel && $leadingLabel !== '' && ! array_key_exists('aria-label', $leadingAttributes)) {
          $leadingAttributes['aria-label'] = $leadingLabel;
      }
    @endphp
    <div class="{{ $leadingWrapperClass }}">
      <{{ $leadingTag }}
        @if($leadingTag === 'a')
          href="{{ (string) ($leadingAction['href'] ?? '#') }}"
        @elseif(! array_key_exists('type', $leadingAttributes))
          type="button"
        @endif
        @foreach($leadingAttributes as $attribute => $value)
          @if(is_bool($value))
            @if($value)
              {{ $attribute }}
            @endif
          @elseif($value !== null && $value !== '')
            {{ $attribute }}="{{ $value }}"
          @endif
        @endforeach
      >
        @if($leadingIconClass !== '')
          <i class="{{ $leadingIconClass }} text-lg leading-none" aria-hidden="true"></i>
        @endif
        @if($leadingShowLabel && $leadingLabel !== '')
          <span>{{ $leadingLabel }}</span>
        @endif
      </{{ $leadingTag }}>
    </div>
  @endif

  <div class="{{ $titleWrapperClass }}">
    <h1 class="{{ $titleClass }}">{{ $title }}</h1>
  </div>

  @if($trailingAction !== null)
    @php
      $trailingTag = ($trailingAction['tag'] ?? 'button') === 'a' ? 'a' : 'button';
      $trailingIconClass = trim((string) ($trailingAction['iconClass'] ?? ''));
      $trailingLabel = trim((string) ($trailingAction['label'] ?? ''));
      $trailingShowLabel = ($trailingAction['showLabel'] ?? false) === true;
      $trailingAttributes = is_array($trailingAction['attributes'] ?? null) ? $trailingAction['attributes'] : [];
      if (! $trailingShowLabel && $trailingLabel !== '' && ! array_key_exists('aria-label', $trailingAttributes)) {
          $trailingAttributes['aria-label'] = $trailingLabel;
      }
    @endphp
    <div class="{{ $trailingWrapperClass }}">
      <{{ $trailingTag }}
        @if($trailingTag === 'a')
          href="{{ (string) ($trailingAction['href'] ?? '#') }}"
        @elseif(! array_key_exists('type', $trailingAttributes))
          type="button"
        @endif
        @foreach($trailingAttributes as $attribute => $value)
          @if(is_bool($value))
            @if($value)
              {{ $attribute }}
            @endif
          @elseif($value !== null && $value !== '')
            {{ $attribute }}="{{ $value }}"
          @endif
        @endforeach
      >
        @if($trailingIconClass !== '')
          <i class="{{ $trailingIconClass }} text-base leading-none" aria-hidden="true"></i>
        @endif
        @if($trailingShowLabel && $trailingLabel !== '')
          <span>{{ $trailingLabel }}</span>
        @endif
      </{{ $trailingTag }}>
    </div>
  @endif
</section>
