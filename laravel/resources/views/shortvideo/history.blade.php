@php
  $historyPagination = is_array($historyPagination ?? null) ? $historyPagination : [];
  $historyPaginationLinks = is_array($historyPagination['links'] ?? null) ? $historyPagination['links'] : [];
  $historyHasAnyRecords = (int) ($historyPagination['totalCount'] ?? 0) > 0;
@endphp

<x-shortvideo.layout.app-shell :shell="$shell">
  <div class="grid gap-5 lg:gap-6 xl:gap-7">
    <x-shortvideo.layout.navigation-bar
      :title="$page['title'] ?? '观看记录'"
      container-class="relative flex h-12 items-center justify-center rounded-full border border-gray-200 bg-white/90 px-1 shadow-sm backdrop-blur-xl"
      title-wrapper-class="min-w-0 px-14 text-center"
      title-class="truncate text-sm font-semibold tracking-[0.08em] text-gray-950 sm:text-base"
      :leading-action="[
        'iconClass' => 'ph ph-arrow-left',
        'label' => '返回上一页',
        'attributes' => [
          'type' => 'button',
          'data-history-back' => 'true',
          'data-fallback-url' => route('profile.me'),
          'class' => 'inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-700 transition hover:text-gray-950',
        ],
      ]"
      :trailing-action="[
        'iconClass' => 'ph ph-trash',
        'label' => '清空记录',
        'showLabel' => true,
        'attributes' => [
          'type' => 'button',
          'data-history-clear-all' => 'true',
          'data-clear-url' => '/api/history',
          'class' => 'inline-flex h-9 items-center justify-center gap-2 rounded-full px-4 text-sm font-semibold text-gray-700 transition hover:text-rose-600 disabled:cursor-not-allowed disabled:text-gray-300',
          'disabled' => ! $historyHasAnyRecords,
        ],
      ]"
    />

    <x-shortvideo.history.panel-content
      :history-pagination="$historyPagination"
      :history-has-items="$historyHasItems"
      :history-items="$historyItems"
      :history-empty-state="$historyEmptyState"
    />
  </div>

  <x-slot:modals>
    @if(($auth['shouldRenderModal'] ?? false) === true)
      <x-shortvideo.auth.modal
        :initial-panel="(string) ($auth['initialPanel'] ?? 'login')"
        :open="($auth['open'] ?? false) === true"
        :standalone="($auth['standalone'] ?? false) === true"
        :close-url="$auth['closeUrl'] ?? null"
        :login-form-action="(string) ($auth['loginFormAction'] ?? '')"
        :register-form-action="(string) ($auth['registerFormAction'] ?? '')"
        :reset-password-form-action="(string) ($auth['resetPasswordFormAction'] ?? '')"
        :send-code-action="(string) ($auth['sendCodeAction'] ?? '')"
        :login-email-value="(string) ($auth['loginEmailValue'] ?? '')"
        :login-email-error="$auth['loginEmailError'] ?? null"
        :password-error="$auth['passwordError'] ?? null"
        :status-message="$auth['statusMessage'] ?? null"
        :error-message="$auth['errorMessage'] ?? null"
      />
    @endif
  </x-slot:modals>

  <x-slot:scripts>
    @vite('laravel/resources/js/pages/history/index.js')
    @if(($auth['shouldRenderModal'] ?? false) === true)
      @vite('laravel/resources/js/pages/auth/modal.js')
    @endif
  </x-slot:scripts>
</x-shortvideo.layout.app-shell>
