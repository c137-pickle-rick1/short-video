<button
  type="{{ $type }}"
  class="{{ $className }}"
  @if($disabled) disabled aria-disabled="true" @endif
  @if($loading) aria-busy="true" @endif
>
  {!! $spinnerMarkup !!}
  {!! $iconMarkup !!}
  <span>{{ $label }}</span>
</button>
