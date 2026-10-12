<?php

namespace App\Http\Requests;

use App\Models\KompenResponHubStudentAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateStudentPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('student') instanceof KompenResponHubStudentAccount;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'password' => [
                'bail',
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->letters()->numbers()->symbols(),
            ],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $student = $this->user('student');
            $password = $this->string('password')->toString();

            if ($student instanceof KompenResponHubStudentAccount && hash_equals($student->nim, $password)) {
                $validator->errors()->add('password', 'Password baru tidak boleh sama dengan NIM.');
            }
        }];
    }
}
