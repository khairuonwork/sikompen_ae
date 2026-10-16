<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DestroyAdminPasswordResetCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->is_emergency_admin === true;
    }
}
