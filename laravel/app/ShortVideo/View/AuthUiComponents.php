<?php

namespace App\ShortVideo\View;

use Illuminate\Contracts\View\Factory as ViewFactory;

final class AuthUiComponents
{
    public function __construct(
        private readonly FoundationUiComponents $foundation,
        private readonly ViewFactory $views
    ) {}

    public function renderInputField(
        string $label,
        string $type,
        string $placeholder,
        string $autocomplete,
        string $value = '',
        ?string $hint = null,
        bool $disabled = false,
        ?string $name = null,
        ?string $inputId = null,
        ?string $error = null,
        bool $required = false,
        bool $autofocus = false
    ): string {
        return $this->foundation->renderInputField(
            $label,
            $type,
            $placeholder,
            $autocomplete,
            $value,
            $hint,
            $disabled,
            $name,
            $inputId,
            $error,
            $required,
            $autofocus
        );
    }

    public function renderPrimaryButton(
        string $label,
        bool $disabled = false,
        bool $loading = false,
        string $type = 'button'
    ): string {
        return '<div class="mt-2">'.
            $this->foundation->renderButton($label, 'primary', $disabled, $loading, null, 'lg', $type).
            '</div>';
    }

    public function renderAuthCard(
        string $title,
        string $note,
        string $fieldsMarkup,
        string $actionMarkup,
        string $formAction = '',
        string $formMethod = 'POST',
        string $hiddenFieldsMarkup = '',
        ?string $statusMessage = null,
        ?string $errorMessage = null
    ): string {
        return $this->renderView('shortvideo.partials.auth.card', [
            'title' => $title,
            'note' => $note,
            'formAction' => $formAction,
            'formMethod' => strtoupper($formMethod) === 'GET' ? 'GET' : 'POST',
            'hiddenFieldsMarkup' => $hiddenFieldsMarkup,
            'fieldsMarkup' => $fieldsMarkup,
            'actionMarkup' => $actionMarkup,
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
