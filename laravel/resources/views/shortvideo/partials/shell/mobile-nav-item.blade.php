<a
  href="{{ $item['href'] }}"
  @if($item['active']) aria-current="page" @endif
  @class([
    'flex flex-col items-center justify-center gap-1 py-2 text-center text-xs font-medium',
    'text-gray-900' => $item['active'],
    'text-gray-500' => ! $item['active'],
  ])
>
  {!! $iconMarkup !!}
  {{ $item['label'] }}
</a>
