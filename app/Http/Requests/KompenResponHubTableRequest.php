<?php

namespace App\Http\Requests;

use App\Rules\SafeSearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class KompenResponHubTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(
            collect(['nim', 'nama', 'search', 'kelas', 'periode_semester', 'warning_period', 'list_period', 'import_month', 'import_year', 'mata_kuliah', 'nama_dosen', 'tab', 'activity_event', 'activity_actor'])
                ->mapWithKeys(fn (string $key): array => [
                    $key => is_string($this->query($key))
                        ? trim((string) $this->query($key))
                        : $this->query($key),
                ])
                ->all(),
        );
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'in:upload,students,details,files,imports,warnings,activity'],
            'nim' => ['nullable', 'string', 'regex:/^[0-9]{9,20}$/'],
            'nama' => ['nullable', 'string', 'max:100', new SafeSearchTerm],
            'search' => ['nullable', 'string', 'max:100', new SafeSearchTerm],
            'kelas' => ['nullable', 'string', 'regex:/^[1-4]AE[A-Z][1-9][0-9]*$/'],
            'tingkat' => ['nullable', 'integer', 'between:1,4'],
            'periode_semester' => ['nullable', 'string', 'max:50'],
            'warning_period' => ['nullable', 'string', 'max:50'],
            'list_period' => ['nullable', 'string', 'max:50'],
            'import_month' => ['nullable', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'import_year' => ['nullable', 'integer', 'between:2000,2100'],
            'mata_kuliah' => ['nullable', 'string', 'max:100', new SafeSearchTerm],
            'nama_dosen' => ['nullable', 'string', 'max:100', new SafeSearchTerm],
            'activity_event' => ['nullable', 'string', 'max:80'],
            'activity_actor' => ['nullable', 'email:rfc,dns', 'max:255'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $month = $this->string('import_month')->toString();
            $year = $this->integer('import_year');

            if ($month !== '' && $year !== 0 && (int) substr($month, 0, 4) !== $year) {
                $validator->errors()->add('import_year', 'Tahun aktivitas harus sesuai dengan bulan aktivitas.');
            }
        }];
    }
}
