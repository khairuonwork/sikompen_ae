<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetryKompenResponHubTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'task_type' => ['required', 'in:kompen_respon,active_students,export'],
            'task_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
