<?php

namespace App\Http\Requests;

use App\Rules\SafeSearchTerm;
use Illuminate\Foundation\Http\FormRequest;

class KompenResponHubTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(
            collect(['nim', 'nama', 'search', 'kelas', 'periode_semester', 'mata_kuliah', 'nama_dosen', 'tab', 'activity_event', 'activity_actor'])
                ->mapWithKeys(fn (string $key): array => [
                    $key => is_string($this->query($key))
                        ? trim((string) $this->query($key))
                        : $this->query($key),
                ])
                ->all(),
        );
    }

    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'in:dashboard,upload,students,details,imports,warnings,activity'],
            'nim' => ['nullable', 'string', 'regex:/^[0-9]{9,20}$/'],
            'nama' => ['nullable', 'string', 'max:100', new SafeSearchTerm],
            'search' => ['nullable', 'string', 'max:100', new SafeSearchTerm],
            'kelas' => ['nullable', 'string', 'regex:/^[1-4]AE[A-Z][1-9][0-9]*$/'],
            'tingkat' => ['nullable', 'integer', 'between:1,4'],
            'periode_semester' => ['nullable', 'string', 'max:50'],
            'mata_kuliah' => ['nullable', 'string', 'max:100', new SafeSearchTerm],
            'nama_dosen' => ['nullable', 'string', 'max:100', new SafeSearchTerm],
            'activity_event' => ['nullable', 'string', 'max:80'],
            'activity_actor' => ['nullable', 'email:rfc,dns', 'max:255'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
