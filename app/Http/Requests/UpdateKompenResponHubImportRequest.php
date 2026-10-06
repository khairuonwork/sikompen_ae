<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKompenResponHubImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('display_filename'))) {
            $this->merge([
                'display_filename' => trim((string) $this->input('display_filename')),
            ]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'display_filename' => [
                'required',
                'string',
                'max:180',
                'regex:/^[\\pL\\pN][\\pL\\pN ._()\\-]*\\.xlsx$/u',
            ],
        ];
    }
}
