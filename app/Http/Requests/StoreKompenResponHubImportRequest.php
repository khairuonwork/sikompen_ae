<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKompenResponHubImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uploader_name' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
        ];
    }
}
