<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKompenResponHubDetailOverrideRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date'],
            'mata_kuliah' => ['required', 'string', 'max:100'],
            'nama_dosen' => ['required', 'string', 'max:100'],
            'jenis_pertemuan' => ['required', 'string', 'max:20'],
            'presensi' => ['required', 'string', 'max:20'],
            'menit_keterlambatan' => ['required', 'integer', 'between:0,1440'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'jam_kompensasi' => ['required', 'numeric', 'between:0,99999999.9999'],
            'jam_responsi' => ['required', 'numeric', 'between:0,99999999.9999'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
