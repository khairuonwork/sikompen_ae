<?php

namespace App\Http\Requests;

use App\Rules\SafeSearchTerm;
use Illuminate\Foundation\Http\FormRequest;

class RollbackKompenResponHubPeriodFinalizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim((string) $this->input('reason'))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000', new SafeSearchTerm],
        ];
    }
}
