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
        $displayFilename = $this->input('display_filename');

        if (! is_string($displayFilename)) {
            return;
        }

        $baseFilename = preg_replace('/(?:\.xlsx)+$/iu', '', trim($displayFilename)) ?? '';
        $baseFilename = preg_replace('/\s+/u', '_', $baseFilename) ?? '';
        $baseFilename = preg_replace('/[^\pL\pN._()\-]+/u', '_', $baseFilename) ?? '';
        $baseFilename = preg_replace('/_+/u', '_', $baseFilename) ?? '';
        $baseFilename = trim($baseFilename, '._');

        $this->merge([
            'display_filename' => $baseFilename === '' ? '' : $baseFilename.'.xlsx',
        ]);
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
