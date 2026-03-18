<div class="mb-4 flex flex-col gap-3 rounded-[28px] border border-gray-200 bg-white/90 px-4 py-4 shadow-sm backdrop-blur-xl sm:px-5 lg:mb-5 lg:flex-row lg:items-center lg:justify-between lg:px-6">
  <div class="min-w-0">
    <p id="feed-summary" class="text-sm font-medium text-gray-700">{{ $feedSummaryText }}</p>
    <p id="feed-status" class="mt-1 text-xs text-gray-500" aria-live="polite">{{ $feedStatusText }}</p>
  </div>

  @if($showSourceFilter)
    <label class="inline-flex items-center gap-3 text-sm text-gray-600">
      <span class="shrink-0 font-medium text-gray-700">来源</span>
      <select
        id="source-filter"
        class="h-11 min-w-[180px] rounded-full border border-gray-200 bg-gray-50 px-4 text-sm text-gray-900 outline-none transition focus:border-gray-300"
      >
        <option value="" @selected($activeSourceHandle === '')>全部来源</option>
        @if($activeSourceHandle !== '')
          <option value="{{ $activeSourceHandle }}" selected>{{ '@'.$activeSourceHandle }}</option>
        @endif
      </select>
    </label>
  @endif
</div>
