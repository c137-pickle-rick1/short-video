<a
  href="{{ $item['href'] }}"
  @if($item['active']) aria-current="page" @endif
  @class([
    'inline-flex h-12 w-full items-center gap-4 rounded-full px-6 text-left transition-colors',
    'bg-gray-100 text-lg font-semibold text-gray-900 hover:bg-gray-200' => $item['active'],
    'text-lg font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-900' => ! $item['active'],
  ])
>
  {!! $iconMarkup !!}
  <span>{{ $item['label'] }}</span>
</a>
