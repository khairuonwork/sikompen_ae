<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreKompenResponHubApiSyncRunRequest extends FormRequest
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
            'selector' => ['required', 'array:type,periode,kelas'],
            'selector.type' => ['required', 'string', Rule::in(['kelas', 'periode'])],
            'selector.periode' => ['required', 'string', 'regex:/^\d{4}\/\d{4} (Gasal|Genap)$/'],
            'selector.kelas' => ['nullable', 'required_if:selector.type,kelas', 'string', 'regex:/^[1-4][A-Z]{2,8}[1-9][0-9]*$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $selector = $this->input('selector');

        if (! is_array($selector)) {
            return;
        }

        $this->merge([
            'selector' => [
                ...$selector,
                'type' => is_string($selector['type'] ?? null) ? strtolower(trim($selector['type'])) : null,
                'periode' => is_string($selector['periode'] ?? null) ? trim($selector['periode']) : null,
                'kelas' => is_string($selector['kelas'] ?? null) ? strtoupper(trim($selector['kelas'])) : null,
            ],
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        $requestId = $this->attributes->get('sikompen_api_request_id');

        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'request_id' => is_string($requestId) ? $requestId : (string) Str::ulid(),
            'error' => [
                'code' => 'validation_error',
                'message' => 'Payload permintaan tidak valid.',
                'fields' => array_keys($validator->errors()->messages()),
            ],
        ], 422, [
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]));
    }
}
