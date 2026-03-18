<?php

namespace App\ShortVideo\View;

final class AuthViewDataFactory
{
    /**
     * @return array<string, mixed>
     */
    public function makeModalData(
        string $initialPanel = 'login',
        bool $open = false,
        bool $standalone = false,
        ?string $closeUrl = null,
        string $loginFormAction = '',
        string $registerFormAction = '',
        string $resetPasswordFormAction = '',
        string $sendCodeAction = '',
        string $loginEmailValue = '',
        ?string $loginEmailError = null,
        ?string $passwordError = null,
        ?string $statusMessage = null,
        ?string $errorMessage = null
    ): array {
        return [
            'shouldRenderModal' => true,
            'initialPanel' => in_array($initialPanel, ['login', 'register', 'password_reset'], true) ? $initialPanel : 'login',
            'open' => $open,
            'standalone' => $standalone,
            'closeUrl' => $closeUrl,
            'loginFormAction' => $loginFormAction,
            'registerFormAction' => $registerFormAction,
            'resetPasswordFormAction' => $resetPasswordFormAction,
            'sendCodeAction' => $sendCodeAction,
            'loginEmailValue' => $loginEmailValue,
            'loginEmailError' => $loginEmailError,
            'passwordError' => $passwordError,
            'statusMessage' => $statusMessage,
            'errorMessage' => $errorMessage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function empty(): array
    {
        return [
            'shouldRenderModal' => false,
        ];
    }
}
