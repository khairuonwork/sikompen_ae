<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKompenResponHubPeriodCutoffRequest extends FormRequest
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
            'periode_semester' => ['required', 'string', 'max:50'],
            'deadline_at' => ['required', 'date_format:Y-m-d\\TH:i', 'after:now'],
        ];
    }
}
