<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminPasswordResetCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->is_emergency_admin === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }
}
