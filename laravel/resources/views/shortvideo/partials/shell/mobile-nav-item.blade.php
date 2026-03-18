<a
  href="{{ $item['href'] }}"
  @if($item['active']) aria-current="page" @endif
  @if(($item['authTriggerPanel'] ?? null) !== null) data-auth-modal-trigger="true" data-auth-modal-panel="{{ $item['authTriggerPanel'] }}" @endif
  @class([
    'flex min-h-[52px] flex-col items-center justify-start gap-0.5 px-1 pt-1.5 pb-1 text-center',
    'text-gray-900' => $item['active'],
    'text-gray-500' => ! $item['active'],
  ])
>
  {!! $iconMarkup !!}
  <span class="max-w-full whitespace-nowrap text-[10px] font-medium leading-[1.2]">
    {{ $item['label'] }}
  </span>
</a>
