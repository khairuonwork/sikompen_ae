<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKompenResponHubStudentSummaryOverrideRequest extends FormRequest
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
            'total_kompensasi_jam' => ['required', 'numeric', 'between:0,99999999.9999'],
            'total_responsi_jam' => ['required', 'numeric', 'between:0,99999999.9999'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
