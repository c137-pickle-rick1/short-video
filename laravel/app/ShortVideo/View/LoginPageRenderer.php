<?php

namespace App\ShortVideo\View;

use Illuminate\Contracts\View\Factory as ViewFactory;

final class LoginPageRenderer
{
    public function __construct(
        private readonly AuthUiComponents $components,
        private readonly ViewFactory $views
    ) {}

    public function renderDocumentHead(string $pageTitle): string
    {
        return $this->renderView('shortvideo.partials.document-head', [
            'pageTitle' => $pageTitle,
            'includeCsrfToken' => false,
            'includePhosphorStyles' => false,
            'includePlyrStyles' => false,
        ]);
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
    ): string {
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderView(string $view, array $data = []): string
    {
        return $this->views->make($view, $data)->render();
    }
}
