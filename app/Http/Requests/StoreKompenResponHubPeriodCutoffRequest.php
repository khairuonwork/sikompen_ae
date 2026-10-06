<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreKompenResponHubPeriodCutoffRequest extends FormRequest
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
            'periode_semester' => ['required', 'string', 'max:50'],
            'deadline_at' => ['required', 'date_format:Y-m-d\\TH:i'],
        ];
    }

    /**
     * Validate the operational lead time after the input format is valid.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('deadline_at')) {
                    return;
                }

                $deadline = CarbonImmutable::createFromFormat(
                    'Y-m-d\\TH:i',
                    $this->string('deadline_at')->toString(),
                    'Asia/Jakarta',
                );
                $minimumDeadline = now('Asia/Jakarta')->startOfMinute()->addMinutes(5);

                if ($deadline->lessThan($minimumDeadline)) {
                    $validator->errors()->add(
                        'deadline_at',
                        'Batas waktu harus minimal 5 menit dari waktu saat ini (Asia/Jakarta).',
                    );
                }
            },
        ];
    }
}
