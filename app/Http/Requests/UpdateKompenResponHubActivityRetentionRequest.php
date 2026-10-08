<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKompenResponHubActivityRetentionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'activity_log_retention_days' => ['required', 'integer', Rule::in([30, 90, 180, 365, 730, 1095])],
        ];
    }
}
