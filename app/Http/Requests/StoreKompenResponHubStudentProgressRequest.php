<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKompenResponHubStudentProgressRequest extends FormRequest
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
            'kompensasi_dikerjakan_jam' => ['required', 'numeric', 'between:0,99999999.9999'],
            'responsi_dikerjakan_jam' => ['required', 'numeric', 'between:0,99999999.9999'],
            'last_worked_at' => ['nullable', 'date_format:Y-m-d\\TH:i', 'before_or_equal:now'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
