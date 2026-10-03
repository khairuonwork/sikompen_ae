<?php

namespace App\Http\Requests;

use App\Rules\SafeSearchTerm;
use Illuminate\Foundation\Http\FormRequest;

class SearchKompenResponHubStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => is_string($this->query('q'))
                ? trim((string) $this->query('q'))
                : $this->query('q'),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100', new SafeSearchTerm],
        ];
    }
}
