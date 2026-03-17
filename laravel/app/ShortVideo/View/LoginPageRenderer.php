<?php

namespace App\ShortVideo\View;

final class LoginPageRenderer
{
    public function __construct(private readonly AuthUiComponents $components) {}

    public function renderDocumentHead(string $pageTitle): string
    {
        return <<<HTML
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{$this->escape($pageTitle)}</title>
    <link rel="stylesheet" href="/vendor/fonts/fonts.css" />
    <link rel="stylesheet" href="/styles.css" />
HTML;
    }

    public function renderLoginCard(
        string $buttonLabel = '登录',
        bool $disabled = false,
        bool $loading = false,
        ?string $note = null,
        string $formAction = '',
        string $identifierValue = '',
        ?string $identifierError = null,
        ?string $passwordError = null,
        ?string $statusMessage = null,
        ?string $errorMessage = null
    ): string
    {
        $fieldsMarkup = implode('', [
            $this->components->renderInputField(
                '用户名 / 邮箱 / 手机号',
                'text',
                'name@example.com',
                'username',
                value: $identifierValue,
                disabled: $disabled || $loading,
                name: 'login',
                inputId: 'login-identifier',
                error: $identifierError,
                required: true,
                autofocus: true
            ),
            $this->components->renderInputField(
                '密码',
                'password',
                '输入密码',
                'current-password',
                disabled: $disabled || $loading,
                name: 'password',
                inputId: 'login-password',
                error: $passwordError,
                required: true
            ),
        ]);

        return $this->components->renderAuthCard(
            title: '登录',
            note: $note ?? '使用本地账号登录，支持用户名、邮箱或手机号。',
            fieldsMarkup: $fieldsMarkup,
            actionMarkup: $this->components->renderPrimaryButton($buttonLabel, $disabled, $loading, 'submit'),
            formAction: $formAction,
            hiddenFieldsMarkup: '<input type="hidden" name="_token" value="'.$this->escape(csrf_token()).'" />',
            statusMessage: $statusMessage,
            errorMessage: $errorMessage,
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
