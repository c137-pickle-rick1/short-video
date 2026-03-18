@php
  $socialConnectionTabs = is_array($socialConnectionTabs ?? null) ? $socialConnectionTabs : [];
  $orderedTabKeys = array_values(array_filter(['following', 'followers'], static fn (string $key): bool => isset($socialConnectionTabs[$key])));
  $initialTab = $orderedTabKeys[0] ?? null;
@endphp

@if($initialTab !== null)
  <div
    data-profile-social-modal="true"
    class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-gray-950/40 p-4 backdrop-blur-sm sm:p-6"
    hidden
  >
    <div class="flex min-h-full w-full items-start justify-center py-4 sm:items-center sm:py-6">
      <section
        data-profile-social-panel="true"
        class="relative flex min-h-0 max-h-[min(82vh,760px)] w-full max-w-3xl flex-col overflow-hidden rounded-[32px] border border-gray-200 bg-white shadow-glass animate-card-in"
        role="dialog"
        aria-modal="true"
        aria-labelledby="profile-social-modal-title"
        tabindex="-1"
      >
        <button
          type="button"
          data-profile-social-close="true"
          class="absolute right-5 top-5 inline-flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition hover:border-gray-300 hover:text-gray-950 sm:right-6 sm:top-6"
          aria-label="关闭关注列表弹窗"
        >
          <i class="ph ph-x text-xl leading-none" aria-hidden="true"></i>
        </button>

        <div class="border-b border-gray-200 px-6 pt-7 sm:px-7 sm:pt-8">
          <div class="flex flex-wrap items-end gap-5 sm:gap-6">
            @foreach($orderedTabKeys as $tabKey)
              @php
                $tab = $socialConnectionTabs[$tabKey];
                $isActive = $tabKey === $initialTab;
              @endphp
              <button
                type="button"
                data-profile-social-tab-button="{{ $tabKey }}"
                @if($isActive) data-active="true" @else data-active="false" @endif
                @class([
                    'relative pb-4 text-left text-xl font-semibold tracking-tight transition sm:text-2xl',
                    'text-gray-950' => $isActive,
                    'text-gray-400 hover:text-gray-700' => ! $isActive,
                ])
              >
                <span class="absolute inset-x-0 bottom-0 h-1 rounded-full bg-rose-500" @if(! $isActive) hidden @endif aria-hidden="true"></span>
                <span id="{{ $tabKey === $initialTab ? 'profile-social-modal-title' : '' }}">{{ $tab['label'] }} ({{ $tab['count'] }})</span>
              </button>
            @endforeach
          </div>
        </div>

        <div class="min-h-0 flex flex-1 flex-col overflow-hidden">
          @foreach($orderedTabKeys as $tabKey)
            @php
              $tab = $socialConnectionTabs[$tabKey];
              $isActive = $tabKey === $initialTab;
            @endphp
            <div
              data-profile-social-tab-panel="{{ $tabKey }}"
              @if($isActive) data-active="true" @else data-active="false" hidden @endif
              class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-6 py-6 sm:px-7 sm:py-7"
            >
              @if(!empty($tab['items']))
                <div class="divide-y divide-gray-200">
                  @foreach($tab['items'] as $item)
                    @include('shortvideo.partials.profile.social-connection-item', [
                      'item' => $item,
                      'loginUrl' => $loginUrl,
                    ])
                  @endforeach
                </div>

                <p class="py-8 text-center text-sm font-medium text-gray-400">暂时没有更多了</p>
              @else
                <div class="flex h-full items-center justify-center py-4">
                  @include('shortvideo.partials.foundation.empty-state', [
                    'iconClass' => $tab['emptyState']['iconClass'] ?? 'ph ph-users-three',
                    'title' => $tab['emptyState']['title'] ?? '还没有内容',
                    'description' => $tab['emptyState']['description'] ?? '当前标签下还没有可显示的数据。',
                    'containerClass' => 'flex min-h-[26rem] w-full flex-col items-center justify-center px-6 py-12 text-center',
                    'iconShellClass' => 'mx-auto flex items-center justify-center text-[3rem] text-gray-300',
                    'titleClass' => 'mt-4 text-xl font-semibold tracking-tight text-gray-950 sm:text-2xl',
                    'descriptionClass' => 'mt-3 mx-auto w-full max-w-xl text-sm leading-7 text-gray-500 sm:text-base',
                  ])
                </div>
              @endif
            </div>
          @endforeach
        </div>
      </section>
    </div>
  </div>
@endif
