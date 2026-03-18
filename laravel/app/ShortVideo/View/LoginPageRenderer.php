<?php

namespace App\ShortVideo\View;

use Illuminate\Contracts\View\Factory as ViewFactory;

final class LoginPageRenderer
{
    public function __construct(private readonly ViewFactory $views) {}

    public function renderDocumentHead(string $pageTitle): string
    {
        return $this->renderView('shortvideo.partials.document-head', [
            'pageTitle' => $pageTitle,
            'includeCsrfToken' => true,
            'includePhosphorStyles' => true,
            'includePlyrStyles' => false,
        ]);
    }

    public function renderAuthModal(
        string $initialPanel = 'login',
        bool $open = false,
        bool $standalone = false,
        string $loginFormAction = '',
        string $loginEmailValue = '',
        ?string $loginEmailError = null,
        ?string $passwordError = null,
        ?string $statusMessage = null,
        ?string $errorMessage = null
    ): string {
        return $this->renderView('shortvideo.partials.auth.modal', [
            'initialPanel' => in_array($initialPanel, ['login', 'register', 'password_reset'], true) ? $initialPanel : 'login',
            'open' => $open,
            'standalone' => $standalone,
            'closeUrl' => $standalone ? url('/') : null,
            'loginFormAction' => $loginFormAction !== '' ? $loginFormAction : route('login.store'),
            'registerFormAction' => route('register.store'),
            'resetPasswordFormAction' => route('password.reset.store'),
            'sendCodeAction' => route('auth.email-codes.store'),
            'loginEmailValue' => $loginEmailValue,
            'loginEmailError' => $loginEmailError,
            'passwordError' => $passwordError,
            'statusMessage' => $statusMessage,
            'errorMessage' => $errorMessage,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderView(string $view, array $data = []): string
    {
        return $this->views->make($view, $data)->render();
    }
}
