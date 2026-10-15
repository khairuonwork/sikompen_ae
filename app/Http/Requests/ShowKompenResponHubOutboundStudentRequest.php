<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class ShowKompenResponHubOutboundStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'periode' => ['required', 'string', 'regex:/^\d{4}\/\d{4} (Gasal|Genap)$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $periode = $this->input('periode');

        if (is_string($periode)) {
            $this->merge(['periode' => trim($periode)]);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        $requestId = $this->attributes->get('sikompen_api_request_id');

        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'request_id' => is_string($requestId) ? $requestId : (string) Str::ulid(),
            'error' => [
                'code' => 'validation_error',
                'message' => 'Parameter permintaan tidak valid.',
                'fields' => array_keys($validator->errors()->messages()),
            ],
        ], 422, [
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]));
    }
}
