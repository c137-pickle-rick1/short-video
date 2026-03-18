<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExplorePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:100'],
        ];
    }
}
