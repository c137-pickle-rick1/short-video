<section class="w-full max-w-sm rounded-[28px] border border-gray-200 bg-white p-2">
  <div class="px-3 py-2">
    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">{{ $title }}</p>
  </div>
  <div class="grid gap-1">
    @foreach($items as $item)
      <button
        type="button"
        @class([
          'flex w-full items-start gap-3 rounded-2xl px-3 py-3 text-left transition',
          'text-rose-600 hover:bg-rose-50' => !empty($item['danger']),
          'text-gray-700 hover:bg-gray-50' => empty($item['danger']),
        ])
      >
        <i class="{{ $item['icon'] }} mt-0.5 text-lg leading-none" aria-hidden="true"></i>
        <span class="block min-w-0">
          <span class="block text-sm font-medium">{{ $item['label'] }}</span>
          @if(!empty($item['description']))
            <p class="mt-1 text-xs leading-5 text-gray-500">{{ $item['description'] }}</p>
          @endif
        </span>
      </button>
    @endforeach
  </div>
</section>
