<?php

namespace App\Http\Requests;

use App\Models\KompenResponHubAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreKompenResponHubAdminSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['username' => Str::lower(trim($this->string('username')->toString()))]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'username' => [
                'bail',
                'required',
                'string',
                'regex:/^[a-z0-9][a-z0-9._@-]{2,63}$/',
                'max:64',
                Rule::unique(KompenResponHubAdmin::class, 'username'),
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
