<x-shortvideo.layout.app-shell
  :shell="$shell"
  body-class="min-h-screen bg-stone-100 text-stone-950 antialiased"
  :wrap-main-content="false"
>
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
    @vite('laravel/resources/js/pages/auth/login.js')
  </x-slot:scripts>
</x-shortvideo.layout.app-shell>
