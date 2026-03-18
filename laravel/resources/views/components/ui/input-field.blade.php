@props([
  'label' => '',
  'type' => 'text',
  'placeholder' => '',
  'autocomplete' => 'off',
  'name' => null,
  'inputId' => null,
  'value' => null,
  'disabled' => false,
  'required' => false,
  'autofocus' => false,
  'hasError' => false,
  'describedBy' => null,
  'inputClass' => '',
  'hint' => null,
  'hintId' => null,
  'error' => null,
  'errorId' => null,
])

<label class="grid gap-2">
  <span class="text-sm font-medium text-stone-700">{{ $label }}</span>
  <input
    type="{{ $type }}"
    placeholder="{{ $placeholder }}"
    autocomplete="{{ $autocomplete }}"
    @if($name !== null) name="{{ $name }}" @endif
    @if($inputId !== null) id="{{ $inputId }}" @endif
    @if($value !== null) value="{{ $value }}" @endif
    @if($disabled) disabled aria-disabled="true" @endif
    @if($required) required @endif
    @if($autofocus) autofocus @endif
    @if($hasError) aria-invalid="true" @endif
    @if($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
    class="{{ $inputClass }}"
  />
  @if($hint !== null && $hint !== '')
    <p @if($hintId !== null) id="{{ $hintId }}" @endif class="text-sm leading-6 text-stone-500">{{ $hint }}</p>
  @endif
  @if($error !== null && $error !== '')
    <p @if($errorId !== null) id="{{ $errorId }}" @endif class="text-sm font-medium leading-6 text-rose-600">{{ $error }}</p>
  @endif
</label>
