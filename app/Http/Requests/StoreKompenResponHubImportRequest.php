<?php

namespace App\Http\Requests;

use App\Rules\SafeSearchTerm;
use Illuminate\Foundation\Http\FormRequest;

class StoreKompenResponHubImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'uploader_name' => ['required', 'string', 'max:100', new SafeSearchTerm],
            'file' => [
                'required',
                'file',
                'mimes:xlsx',
                'max:'.max(1, (int) config('kompen-respon-hub.import.max_upload_kilobytes', 5120)),
            ],
        ];
    }
}
