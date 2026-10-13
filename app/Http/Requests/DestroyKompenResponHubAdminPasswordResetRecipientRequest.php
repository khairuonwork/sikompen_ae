<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DestroyKompenResponHubAdminPasswordResetRecipientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }
}
