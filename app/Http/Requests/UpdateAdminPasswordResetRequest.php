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
            'username' => Str::lower(trim($this->string('username')->toString())),
            'otp' => trim($this->string('otp')->toString()),
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'username' => ['bail', 'required', 'string', 'regex:/^[a-z0-9][a-z0-9._@-]{2,63}$/', 'max:64'],
            'otp' => ['bail', 'required', 'string', 'regex:/^[0-9]{6}$/'],
            'password' => ['bail', 'required', 'confirmed', Password::min(12)->mixedCase()->letters()->numbers()->symbols()],
        ];
    }
}
