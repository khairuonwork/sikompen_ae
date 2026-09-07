<?php

namespace App\Http\Requests;

use App\Models\KompenResponHubAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreKompenResponHubAdminSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'bail',
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique(KompenResponHubAdmin::class, 'email'),
            ],
            'password' => [
                'bail',
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->letters()->numbers()->symbols(),
            ],
            'activation_code' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function attributes(): array
    {
        return [
            'activation_code' => 'kode aktivasi',
        ];
    }
}
