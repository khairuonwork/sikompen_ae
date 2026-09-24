<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKompenResponHubExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'resource' => ['required', 'in:students,details,warnings'],
            'format' => ['required', 'in:xlsx,pdf'],
            'nim' => ['nullable', 'string', 'regex:/^[0-9]{9,20}$/'],
            'nama' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'kelas' => ['nullable', 'string', 'regex:/^[1-4]AE[A-Z][1-9][0-9]*$/'],
            'tingkat' => ['nullable', 'integer', 'between:1,4'],
            'periode_semester' => ['nullable', 'string', 'max:50'],
            'mata_kuliah' => ['nullable', 'string', 'max:100'],
            'nama_dosen' => ['nullable', 'string', 'max:100'],
        ];
    }
}
