<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class UpdateAdminPasswordResetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim($this->string('email')->toString())),
            'otp' => trim($this->string('otp')->toString()),
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['bail', 'required', 'string', 'email:rfc,dns', 'max:255'],
            'otp' => ['bail', 'required', 'string', 'regex:/^[0-9]{6}$/'],
            'password' => ['bail', 'required', 'confirmed', Password::min(12)->mixedCase()->letters()->numbers()->symbols()],
        ];
    }
}
