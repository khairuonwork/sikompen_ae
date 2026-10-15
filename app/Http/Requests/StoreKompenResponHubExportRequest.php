<?php

namespace App\Http\Requests;

use App\Rules\SafeSearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'resource' => ['required', 'in:students,details,warnings,import_audits'],
            'format' => ['required', 'in:xlsx,pdf'],
            'nim' => ['nullable', 'string', 'regex:/^[0-9]{9,20}$/'],
            'nama' => ['nullable', 'string', 'max:100', new SafeSearchTerm],
            'search' => ['nullable', 'string', 'max:100', new SafeSearchTerm],
            'kelas' => ['nullable', 'string', 'regex:/^[1-4]AE[A-Z][1-9][0-9]*$/'],
            'tingkat' => ['nullable', 'integer', 'between:1,4'],
            'periode_semester' => ['nullable', 'string', 'max:50'],
            'mata_kuliah' => ['nullable', 'string', 'max:100', new SafeSearchTerm],
            'nama_dosen' => ['nullable', 'string', 'max:100', new SafeSearchTerm],
            'import_type' => ['nullable', 'in:kompen_respon,active_students'],
            'import_month' => ['nullable', 'date_format:Y-m'],
            'import_year' => ['nullable', 'integer', 'between:2020,2100'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $tingkat = $this->integer('tingkat');
            $kelas = $this->string('kelas')->toString();

            if ($tingkat !== 0 && $kelas !== '' && ! str_starts_with($kelas, (string) $tingkat)) {
                $validator->errors()->add('kelas', 'Kelas harus sesuai dengan tingkat yang dipilih.');
            }
        }];
    }
}
