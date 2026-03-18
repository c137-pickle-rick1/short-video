@php
  $panelOrder = ['login', 'register', 'password_reset'];
  $panelLabels = [
      'login' => '登录',
      'register' => '注册',
      'password_reset' => '忘记密码',
  ];
  $requestedPanel = is_string($initialPanel ?? null) ? $initialPanel : 'login';
  $resolvedPanel = in_array($requestedPanel, $panelOrder, true) ? $requestedPanel : 'login';
  $isStandalone = ($standalone ?? false) === true;
  $isOpen = ($open ?? false) === true;
  $closeUrl = $closeUrl ?? null;
@endphp

<div
  data-auth-modal="true"
  data-auth-default-panel="{{ $resolvedPanel }}"
  data-auth-email-code-action="{{ $sendCodeAction }}"
  data-auth-close-url="{{ is_string($closeUrl) ? $closeUrl : '' }}"
  data-auth-modal-start-open="{{ $isOpen ? 'true' : 'false' }}"
  @class([
    'fixed inset-0 z-50 flex items-center justify-center bg-gray-950/40 p-4 backdrop-blur-sm sm:p-6',
    'hidden' => ! $isOpen,
  ])
  @if(! $isOpen) hidden @endif
>
  <section
    data-auth-modal-panel-shell="true"
    class="relative w-full max-w-xl rounded-[32px] border border-gray-200 bg-white p-6 shadow-glass animate-card-in sm:p-7"
    role="dialog"
    aria-modal="true"
    aria-labelledby="auth-modal-title"
    tabindex="-1"
  >
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-rose-500">Account</p>
        <h1 id="auth-modal-title" class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl">
          @if($isStandalone)
            欢迎回来
          @else
            继续你的 Short Video
          @endif
        </h1>
        <p class="mt-2 text-sm leading-6 text-gray-500">
          登录使用邮箱和密码；注册与忘记密码通过邮箱验证码完成验证。
        </p>
      </div>

      <button
        type="button"
        data-auth-modal-close="true"
        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition hover:border-gray-300 hover:text-gray-950"
        aria-label="关闭认证弹窗"
      >
        <i class="ph ph-x text-xl leading-none" aria-hidden="true"></i>
      </button>
    </div>

    <div class="mt-6 grid grid-cols-3 gap-2 rounded-full bg-stone-100 p-1">
      @foreach($panelOrder as $panel)
        <button
          type="button"
          data-auth-tab="true"
          data-auth-panel-switch="{{ $panel }}"
          @class([
            'inline-flex h-11 items-center justify-center rounded-full px-4 text-sm font-semibold transition',
            'bg-white text-gray-950 shadow-sm' => $resolvedPanel === $panel,
            'text-gray-500 hover:text-gray-900' => $resolvedPanel !== $panel,
          ])
          aria-pressed="{{ $resolvedPanel === $panel ? 'true' : 'false' }}"
        >
          {{ $panelLabels[$panel] }}
        </button>
      @endforeach
    </div>

    <div class="mt-6">
      <section data-auth-panel="login" @if($resolvedPanel !== 'login') class="hidden" hidden @endif>
        <div
          data-auth-status="login"
          @class([
            'rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700',
            'mt-4' => true,
            'hidden' => !is_string($statusMessage ?? null) || trim((string) $statusMessage) === '',
          ])
          @if(!is_string($statusMessage ?? null) || trim((string) $statusMessage) === '') hidden @endif
        >
          {{ $statusMessage }}
        </div>

        <div
          data-auth-error="login"
          @class([
            'rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700',
            'mt-4' => true,
            'hidden' => !is_string($errorMessage ?? null) || trim((string) $errorMessage) === '',
          ])
          @if(!is_string($errorMessage ?? null) || trim((string) $errorMessage) === '') hidden @endif
        >
          {{ $errorMessage }}
        </div>

        <form
          method="POST"
          action="{{ $loginFormAction }}"
          class="mt-6 grid gap-4"
          data-auth-form="login"
        >
          @csrf
          @include('shortvideo.partials.foundation.input-field', [
            'label' => '邮箱',
            'type' => 'email',
            'placeholder' => 'name@example.com',
            'autocomplete' => 'email',
            'name' => 'email',
            'inputId' => 'auth-login-email',
            'value' => $loginEmailValue !== '' ? $loginEmailValue : null,
            'disabled' => false,
            'required' => true,
            'autofocus' => $resolvedPanel === 'login',
            'hasError' => $loginEmailError !== null && trim($loginEmailError) !== '',
            'describedBy' => null,
            'inputClass' => $loginEmailError !== null && trim($loginEmailError) !== ''
              ? 'h-14 rounded-2xl border border-rose-300 bg-rose-50 px-4 text-base text-stone-900 outline-none transition placeholder:text-stone-400 focus:border-rose-400 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400'
              : 'h-14 rounded-2xl border border-stone-200 bg-stone-50 px-4 text-base text-stone-900 outline-none transition placeholder:text-stone-400 focus:border-stone-300 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400',
            'hint' => '请输入你注册时使用的邮箱地址。',
            'hintId' => 'auth-login-email-hint',
            'error' => $loginEmailError,
            'errorId' => 'auth-login-email-error',
          ])
          @include('shortvideo.partials.foundation.input-field', [
            'label' => '密码',
            'type' => 'password',
            'placeholder' => '输入密码',
            'autocomplete' => 'current-password',
            'name' => 'password',
            'inputId' => 'auth-login-password',
            'value' => null,
            'disabled' => false,
            'required' => true,
            'autofocus' => false,
            'hasError' => $passwordError !== null && trim($passwordError) !== '',
            'describedBy' => null,
            'inputClass' => $passwordError !== null && trim($passwordError) !== ''
              ? 'h-14 rounded-2xl border border-rose-300 bg-rose-50 px-4 text-base text-stone-900 outline-none transition placeholder:text-stone-400 focus:border-rose-400 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400'
              : 'h-14 rounded-2xl border border-stone-200 bg-stone-50 px-4 text-base text-stone-900 outline-none transition placeholder:text-stone-400 focus:border-stone-300 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400',
            'hint' => null,
            'hintId' => null,
            'error' => $passwordError,
            'errorId' => 'auth-login-password-error',
          ])
          <button
            type="submit"
            data-auth-submit="login"
            class="mt-2 inline-flex h-14 items-center justify-center rounded-2xl bg-gray-950 px-5 text-base font-semibold text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:bg-gray-300"
          >
            登录
          </button>
        </form>

        <div class="mt-4 flex justify-end">
          <button
            type="button"
            data-auth-panel-switch="password_reset"
            class="text-sm font-medium text-rose-500 transition hover:text-rose-600"
          >
            忘记密码？
          </button>
        </div>
      </section>

      <section data-auth-panel="register" class="hidden" hidden>
        <div data-auth-status="register" class="mt-4 hidden rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700" hidden></div>
        <div data-auth-error="register" class="mt-4 hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700" hidden></div>

        <form method="POST" action="{{ $registerFormAction }}" class="mt-6 grid gap-4" data-auth-form="register">
          @csrf
          @include('shortvideo.partials.foundation.input-field', [
            'label' => '邮箱',
            'type' => 'email',
            'placeholder' => 'name@example.com',
            'autocomplete' => 'email',
            'name' => 'email',
            'inputId' => 'auth-register-email',
            'value' => null,
            'disabled' => false,
            'required' => true,
            'autofocus' => false,
            'hasError' => false,
            'describedBy' => null,
            'inputClass' => 'h-14 rounded-2xl border border-stone-200 bg-stone-50 px-4 text-base text-stone-900 outline-none transition placeholder:text-stone-400 focus:border-stone-300 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400',
            'hint' => '验证码会发送到这个邮箱，60 秒内不可重复发送。',
            'hintId' => 'auth-register-email-hint',
            'error' => null,
            'errorId' => null,
          ])
          <button
            type="button"
            data-auth-send-code-button="register"
            class="inline-flex h-12 items-center justify-center rounded-full border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:text-gray-950 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-400"
          >
            发送验证码
          </button>
          @include('shortvideo.partials.foundation.input-field', [
            'label' => '邮箱验证码',
            'type' => 'text',
            'placeholder' => '输入 6 位验证码',
            'autocomplete' => 'one-time-code',
            'name' => 'code',
            'inputId' => 'auth-register-code',
            'value' => null,
            'disabled' => false,
            'required' => true,
            'autofocus' => false,
            'hasError' => false,
            'describedBy' => null,
            'inputClass' => 'h-14 rounded-2xl border border-stone-200 bg-stone-50 px-4 text-base text-stone-900 outline-none transition placeholder:text-stone-400 focus:border-stone-300 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400',
            'hint' => null,
            'hintId' => null,
            'error' => null,
            'errorId' => null,
          ])
          @include('shortvideo.partials.foundation.input-field', [
            'label' => '密码',
            'type' => 'password',
            'placeholder' => '至少 8 位密码',
            'autocomplete' => 'new-password',
            'name' => 'password',
            'inputId' => 'auth-register-password',
            'value' => null,
            'disabled' => false,
            'required' => true,
            'autofocus' => false,
            'hasError' => false,
            'describedBy' => null,
            'inputClass' => 'h-14 rounded-2xl border border-stone-200 bg-stone-50 px-4 text-base text-stone-900 outline-none transition placeholder:text-stone-400 focus:border-stone-300 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400',
            'hint' => null,
            'hintId' => null,
            'error' => null,
            'errorId' => null,
          ])
          @include('shortvideo.partials.foundation.input-field', [
            'label' => '确认密码',
            'type' => 'password',
            'placeholder' => '再次输入密码',
            'autocomplete' => 'new-password',
            'name' => 'password_confirmation',
            'inputId' => 'auth-register-password-confirmation',
            'value' => null,
            'disabled' => false,
            'required' => true,
            'autofocus' => false,
            'hasError' => false,
            'describedBy' => null,
            'inputClass' => 'h-14 rounded-2xl border border-stone-200 bg-stone-50 px-4 text-base text-stone-900 outline-none transition placeholder:text-stone-400 focus:border-stone-300 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400',
            'hint' => null,
            'hintId' => null,
            'error' => null,
            'errorId' => null,
          ])
          <button
            type="submit"
            data-auth-submit="register"
            class="mt-2 inline-flex h-14 items-center justify-center rounded-2xl bg-gray-950 px-5 text-base font-semibold text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:bg-gray-300"
          >
            注册并登录
          </button>
        </form>
      </section>

      <section data-auth-panel="password_reset" class="hidden" hidden>
        <div data-auth-status="password_reset" class="mt-4 hidden rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700" hidden></div>
        <div data-auth-error="password_reset" class="mt-4 hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700" hidden></div>

        <form method="POST" action="{{ $resetPasswordFormAction }}" class="mt-6 grid gap-4" data-auth-form="password_reset">
          @csrf
          @include('shortvideo.partials.foundation.input-field', [
            'label' => '邮箱',
            'type' => 'email',
            'placeholder' => 'name@example.com',
            'autocomplete' => 'email',
            'name' => 'email',
            'inputId' => 'auth-reset-email',
            'value' => null,
            'disabled' => false,
            'required' => true,
            'autofocus' => false,
            'hasError' => false,
            'describedBy' => null,
            'inputClass' => 'h-14 rounded-2xl border border-stone-200 bg-stone-50 px-4 text-base text-stone-900 outline-none transition placeholder:text-stone-400 focus:border-stone-300 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400',
            'hint' => '输入已注册邮箱后发送验证码。',
            'hintId' => 'auth-reset-email-hint',
            'error' => null,
            'errorId' => null,
          ])
          <button
            type="button"
            data-auth-send-code-button="password_reset"
            class="inline-flex h-12 items-center justify-center rounded-full border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:text-gray-950 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-400"
          >
            发送验证码
          </button>
          @include('shortvideo.partials.foundation.input-field', [
            'label' => '邮箱验证码',
            'type' => 'text',
            'placeholder' => '输入 6 位验证码',
            'autocomplete' => 'one-time-code',
            'name' => 'code',
            'inputId' => 'auth-reset-code',
            'value' => null,
            'disabled' => false,
            'required' => true,
            'autofocus' => false,
            'hasError' => false,
            'describedBy' => null,
            'inputClass' => 'h-14 rounded-2xl border border-stone-200 bg-stone-50 px-4 text-base text-stone-900 outline-none transition placeholder:text-stone-400 focus:border-stone-300 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400',
            'hint' => null,
            'hintId' => null,
            'error' => null,
            'errorId' => null,
          ])
          @include('shortvideo.partials.foundation.input-field', [
            'label' => '新密码',
            'type' => 'password',
            'placeholder' => '至少 8 位密码',
            'autocomplete' => 'new-password',
            'name' => 'password',
            'inputId' => 'auth-reset-password',
            'value' => null,
            'disabled' => false,
            'required' => true,
            'autofocus' => false,
            'hasError' => false,
            'describedBy' => null,
            'inputClass' => 'h-14 rounded-2xl border border-stone-200 bg-stone-50 px-4 text-base text-stone-900 outline-none transition placeholder:text-stone-400 focus:border-stone-300 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400',
            'hint' => null,
            'hintId' => null,
            'error' => null,
            'errorId' => null,
          ])
          @include('shortvideo.partials.foundation.input-field', [
            'label' => '确认新密码',
            'type' => 'password',
            'placeholder' => '再次输入新密码',
            'autocomplete' => 'new-password',
            'name' => 'password_confirmation',
            'inputId' => 'auth-reset-password-confirmation',
            'value' => null,
            'disabled' => false,
            'required' => true,
            'autofocus' => false,
            'hasError' => false,
            'describedBy' => null,
            'inputClass' => 'h-14 rounded-2xl border border-stone-200 bg-stone-50 px-4 text-base text-stone-900 outline-none transition placeholder:text-stone-400 focus:border-stone-300 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400',
            'hint' => null,
            'hintId' => null,
            'error' => null,
            'errorId' => null,
          ])
          <button
            type="submit"
            data-auth-submit="password_reset"
            class="mt-2 inline-flex h-14 items-center justify-center rounded-2xl bg-gray-950 px-5 text-base font-semibold text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:bg-gray-300"
          >
            重置密码
          </button>
        </form>
      </section>
    </div>
  </section>
</div>
