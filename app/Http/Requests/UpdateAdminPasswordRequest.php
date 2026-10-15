<?php

namespace App\Http\Requests;

use App\Models\KompenResponHubAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateAdminPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') instanceof KompenResponHubAdmin;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'current_password' => ['bail', 'required', 'string', 'current_password:admin'],
            'password' => ['bail', 'required', 'confirmed', Password::min(12)->mixedCase()->letters()->numbers()->symbols()],
        ];
    }
}
