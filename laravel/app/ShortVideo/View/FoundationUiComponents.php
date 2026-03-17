<?php

namespace App\ShortVideo\View;

final class FoundationUiComponents
{
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

        return <<<HTML
          <button
            type="{$safeType}"
            class="inline-flex items-center justify-center gap-2 font-semibold transition {$sizeClass} {$stateClass}"{$disabledAttr}{$busyAttr}
          >
            {$spinnerMarkup}
            {$iconMarkup}
            <span>{$safeLabel}</span>
          </button>
HTML;
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

        return <<<HTML
          <button
            type="button"
            aria-label="{$safeLabel}"
            title="{$safeLabel}"
            class="inline-flex h-12 w-12 items-center justify-center rounded-full transition {$buttonClass}"
          >
            <i class="{$safeIcon} text-xl leading-none" aria-hidden="true"></i>
          </button>
HTML;
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
        $hintMarkup = '';
        if ($hint !== null && $hint !== '') {
            $hintId = $inputId !== null ? $safeInputId.'-hint' : '';
            $describedBy[] = $hintId;
            $hintMarkup = '<p'.($hintId !== '' ? ' id="'.$hintId.'"' : '').' class="text-sm leading-6 text-stone-500">'.$this->escape($hint).'</p>';
        }
        $errorMarkup = '';
        if ($error !== null && $error !== '') {
            $errorId = $inputId !== null ? $safeInputId.'-error' : '';
            $describedBy[] = $errorId;
            $errorMarkup = '<p'.($errorId !== '' ? ' id="'.$errorId.'"' : '').' class="text-sm font-medium leading-6 text-rose-600">'.$safeError.'</p>';
        }
        $describedBy = array_values(array_filter($describedBy, static fn (string $id): bool => $id !== ''));
        $describedByAttr = $describedBy !== [] ? ' aria-describedby="'.$this->escape(implode(' ', $describedBy)).'"' : '';
        $inputClass = $error !== null && $error !== ''
            ? 'h-14 rounded-2xl border border-rose-300 bg-rose-50 px-4 text-base text-stone-900 outline-none transition placeholder:text-stone-400 focus:border-rose-400 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400'
            : 'h-14 rounded-2xl border border-stone-200 bg-stone-50 px-4 text-base text-stone-900 outline-none transition placeholder:text-stone-400 focus:border-stone-300 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400';

        return <<<HTML
          <label class="grid gap-2">
            <span class="text-sm font-medium text-stone-700">{$safeLabel}</span>
            <input
              type="{$safeType}"
              placeholder="{$safePlaceholder}"
              autocomplete="{$safeAutocomplete}"{$nameAttr}{$idAttr}{$valueAttr}{$disabledAttr}{$requiredAttr}{$autofocusAttr}{$errorAttr}{$describedByAttr}
              class="{$inputClass}"
            />
            {$hintMarkup}
            {$errorMarkup}
          </label>
HTML;
    }

    /**
     * @param  array<int, array{icon:string,label:string,description?:string,danger?:bool}>  $items
     */
    public function renderMenu(string $title, array $items): string
    {
        $safeTitle = $this->escape($title);
        $itemsMarkup = implode('', array_map(function (array $item): string {
            $safeIcon = $this->escape($item['icon']);
            $safeLabel = $this->escape($item['label']);
            $description = $item['description'] ?? '';
            $safeDescription = $this->escape($description);
            $toneClass = ! empty($item['danger'])
                ? 'text-rose-600 hover:bg-rose-50'
                : 'text-gray-700 hover:bg-gray-50';
            $descriptionMarkup = $description !== ''
                ? '<p class="mt-1 text-xs leading-5 text-gray-500">'.$safeDescription.'</p>'
                : '';

            return <<<HTML
              <button
                type="button"
                class="flex w-full items-start gap-3 rounded-2xl px-3 py-3 text-left transition {$toneClass}"
              >
                <i class="{$safeIcon} mt-0.5 text-lg leading-none" aria-hidden="true"></i>
                <span class="block min-w-0">
                  <span class="block text-sm font-medium">{$safeLabel}</span>
                  {$descriptionMarkup}
                </span>
              </button>
HTML;
        }, $items));

        return <<<HTML
          <section class="w-full max-w-sm rounded-[28px] border border-gray-200 bg-white p-2">
            <div class="px-3 py-2">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">{$safeTitle}</p>
            </div>
            <div class="grid gap-1">
              {$itemsMarkup}
            </div>
          </section>
HTML;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
