<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateStudentPasswordResetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reset_code' => Str::upper(trim($this->string('reset_code')->toString()))]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'nim' => ['bail', 'required', 'string', 'regex:/^[0-9]{9,20}$/'],
            'reset_code' => ['bail', 'required', 'string', 'regex:/^[A-Z0-9]{8}$/'],
            'password' => ['bail', 'required', 'confirmed', Password::min(12)->mixedCase()->letters()->numbers()->symbols()],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (hash_equals($this->string('nim')->toString(), $this->string('password')->toString())) {
                $validator->errors()->add('password', 'Password baru tidak boleh sama dengan NIM.');
            }
        }];
    }
}
