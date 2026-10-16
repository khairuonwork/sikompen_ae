<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreAdminPasswordResetRequest extends FormRequest
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
        return ['username' => ['bail', 'required', 'string', 'regex:/^[a-z0-9][a-z0-9._@-]{2,63}$/', 'max:64']];
    }
}
