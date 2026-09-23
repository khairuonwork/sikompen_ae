<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKompenResponHubWarningLetterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'letter_status' => ['required', 'in:draft,issued,cancelled'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
