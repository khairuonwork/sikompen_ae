<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'nim' => ['bail', 'required', 'string', 'regex:/^[0-9]{9,20}$/'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
