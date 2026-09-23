<?php

namespace App\Http\Requests;

use App\Models\KompenResponHubStudent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKompenResponHubWarningLetterRequest extends FormRequest
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
        $student = new KompenResponHubStudent;

        return [
            'student_id' => [
                'required',
                'integer',
                Rule::exists($student->getConnectionName().'.'.$student->getTable(), 'id'),
            ],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
