<?php

namespace App\ShortVideo\View;

use Illuminate\Contracts\View\Factory as ViewFactory;

final class FoundationUiComponents
{
    public function __construct(private readonly ViewFactory $views) {}

    public function renderButton(
        string $label,
        string $variant = 'primary',
        bool $disabled = false,
        bool $loading = false,
        ?string $leadingIcon = null,
        string $size = 'md',
        string $type = 'button'
    ): string {
        $safeLabel = $this->escape($label);
        $safeType = $this->escape($type);
        $disabledAttr = $disabled || $loading ? ' disabled aria-disabled="true"' : '';
        $busyAttr = $loading ? ' aria-busy="true"' : '';
        $iconMarkup = $leadingIcon !== null && $leadingIcon !== ''
            ? '<i class="'.$this->escape($leadingIcon).' text-lg leading-none" aria-hidden="true"></i>'
            : '';
        $spinnerMarkup = $loading
            ? '<span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-current/30 border-t-current" aria-hidden="true"></span>'
            : '';

        $sizeClass = $size === 'lg'
            ? 'h-14 rounded-2xl px-5 text-base'
            : 'h-12 rounded-full px-4 text-sm';

        $stateClass = match ($variant) {
            'secondary' => $disabled || $loading
                ? 'border border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed'
                : 'border border-gray-200 bg-white text-gray-900 hover:bg-gray-50',
            'ghost' => $disabled || $loading
                ? 'bg-transparent text-gray-400 cursor-not-allowed'
                : 'bg-transparent text-gray-600 hover:bg-gray-100 hover:text-gray-950',
            default => $disabled || $loading
                ? 'bg-gray-300 text-white cursor-not-allowed'
                : 'bg-gray-950 text-white hover:bg-gray-800',
        };

        return $this->renderView('shortvideo.partials.foundation.button', [
            'type' => $safeType,
            'className' => trim("inline-flex items-center justify-center gap-2 font-semibold transition {$sizeClass} {$stateClass}"),
            'disabled' => $disabled || $loading,
            'loading' => $loading,
            'iconMarkup' => $iconMarkup,
            'spinnerMarkup' => $spinnerMarkup,
            'label' => $label,
        ]);
    }

    public function renderIconButton(
        string $icon,
        string $label,
        string $variant = 'outline',
        bool $active = false
    ): string {
        $safeLabel = $this->escape($label);
        $safeIcon = $this->escape($icon);

        $buttonClass = match ($variant) {
            'solid' => $active
                ? 'bg-gray-950 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
            default => $active
                ? 'border border-gray-900 bg-gray-950 text-white'
                : 'border border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-950',
        };

        return $this->renderView('shortvideo.partials.foundation.icon-button', [
            'label' => $safeLabel,
            'icon' => $safeIcon,
            'buttonClass' => trim("inline-flex h-12 w-12 items-center justify-center rounded-full transition {$buttonClass}"),
        ]);
    }

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
        $safeLabel = $this->escape($label);
        $safeType = $this->escape($type);
        $safePlaceholder = $this->escape($placeholder);
        $safeAutocomplete = $this->escape($autocomplete);
        $safeValue = $this->escape($value);
        $safeName = $name !== null ? $this->escape($name) : '';
        $safeInputId = $inputId !== null ? $this->escape($inputId) : '';
        $safeError = $error !== null ? $this->escape($error) : '';
        $valueAttr = $value !== '' ? ' value="'.$safeValue.'"' : '';
        $disabledAttr = $disabled ? ' disabled aria-disabled="true"' : '';
        $nameAttr = $name !== null ? ' name="'.$safeName.'"' : '';
        $idAttr = $inputId !== null ? ' id="'.$safeInputId.'"' : '';
        $requiredAttr = $required ? ' required' : '';
        $autofocusAttr = $autofocus ? ' autofocus' : '';
        $errorAttr = $error !== null && $error !== '' ? ' aria-invalid="true"' : '';
        $describedBy = [];
        $hintId = null;
        if ($hint !== null && $hint !== '') {
            $hintId = $inputId !== null ? $safeInputId.'-hint' : null;
            $describedBy[] = $hintId;
        }
        $errorId = null;
        if ($error !== null && $error !== '') {
            $errorId = $inputId !== null ? $safeInputId.'-error' : null;
            $describedBy[] = $errorId;
        }
        $describedBy = array_values(array_filter($describedBy, static fn (mixed $id): bool => is_string($id) && $id !== ''));
        $inputClass = $error !== null && $error !== ''
            ? 'h-14 rounded-2xl border border-rose-300 bg-rose-50 px-4 text-base text-stone-900 outline-none transition placeholder:text-stone-400 focus:border-rose-400 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400'
            : 'h-14 rounded-2xl border border-stone-200 bg-stone-50 px-4 text-base text-stone-900 outline-none transition placeholder:text-stone-400 focus:border-stone-300 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400';

        return $this->renderView('shortvideo.partials.foundation.input-field', [
            'label' => $safeLabel,
            'type' => $safeType,
            'placeholder' => $safePlaceholder,
            'autocomplete' => $safeAutocomplete,
            'name' => $name !== null ? $safeName : null,
            'inputId' => $inputId !== null ? $safeInputId : null,
            'value' => $value !== '' ? $safeValue : null,
            'disabled' => $disabled,
            'required' => $required,
            'autofocus' => $autofocus,
            'hasError' => $error !== null && $error !== '',
            'describedBy' => $describedBy !== [] ? implode(' ', $describedBy) : null,
            'inputClass' => $inputClass,
            'hint' => $hint,
            'hintId' => $hintId,
            'error' => $safeError !== '' ? $safeError : null,
            'errorId' => $errorId,
        ]);
    }

    /**
     * @param  array<int, array{icon:string,label:string,description?:string,danger?:bool}>  $items
     */
    public function renderMenu(string $title, array $items): string
    {
        return $this->renderView('shortvideo.partials.foundation.menu', [
            'title' => $title,
            'items' => $items,
        ]);
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
