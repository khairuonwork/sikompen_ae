<?php

namespace App\Http\Requests;

use App\Models\KompenResponHubAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateAdminUsernameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') instanceof KompenResponHubAdmin;
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
                Rule::unique(KompenResponHubAdmin::class, 'username')->ignore($this->user('admin')?->id),
            ],
            'current_password' => ['bail', 'required', 'string', 'current_password:admin'],
        ];
    }
}
