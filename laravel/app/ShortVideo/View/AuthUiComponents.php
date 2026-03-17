<?php

namespace App\ShortVideo\View;

final class AuthUiComponents
{
    public function __construct(private readonly FoundationUiComponents $foundation) {}

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
    ): string
    {
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
        $safeTitle = $this->escape($title);
        $safeNote = $this->escape($note);
        $safeFormAction = $this->escape($formAction);
        $safeFormMethod = $this->escape(strtoupper($formMethod) === 'GET' ? 'GET' : 'POST');
        $statusMarkup = $statusMessage !== null && trim($statusMessage) !== ''
            ? '<div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">'.$this->escape($statusMessage).'</div>'
            : '';
        $errorMarkup = $errorMessage !== null && trim($errorMessage) !== ''
            ? '<div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">'.$this->escape($errorMessage).'</div>'
            : '';

        return <<<HTML
      <section class="w-full max-w-md rounded-[32px] border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
        <h1 class="text-3xl font-semibold tracking-tight text-stone-950">{$safeTitle}</h1>
        <p id="login-placeholder-note" class="mt-3 text-sm leading-6 text-stone-500">
          {$safeNote}
        </p>
        {$statusMarkup}
        {$errorMarkup}

        <form method="{$safeFormMethod}" action="{$safeFormAction}" class="mt-8 grid gap-4" aria-describedby="login-placeholder-note">
          {$hiddenFieldsMarkup}
          {$fieldsMarkup}
          {$actionMarkup}
        </form>
      </section>
HTML;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
