<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKompenResponHubWorkbookRetentionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'workbook_recycle_bin_retention_days' => ['required', 'integer', Rule::in([7, 14, 30, 60, 90, 180])],
        ];
    }
}
