<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\SikompenStudentAccess;
use App\Http\Requests\StoreStudentPasswordResetRequest;
use App\Http\Requests\UpdateStudentPasswordResetRequest;
use App\Models\KompenResponHubStudentAccount;
use App\Models\KompenResponHubStudentPasswordResetRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StudentPasswordResetController extends Controller
{
    public function createRequest(Request $request): Response
    {
        return Inertia::render('auth/student-password-reset-request', $this->studentSessionProps($request));
    }

    public function storeRequest(StoreStudentPasswordResetRequest $request, SikompenStudentAccess $studentAccess): RedirectResponse
    {
        $nim = $this->requestedNim($request);

        if ($this->canRequestReset($nim, $studentAccess)) {
            DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($nim, $studentAccess): void {
                if (! $this->canRequestReset($nim, $studentAccess)) {
                    return;
                }

                $resetRequest = KompenResponHubStudentPasswordResetRequest::query()->where('nim', $nim)->lockForUpdate()->first();

                if ($resetRequest !== null && $resetRequest->fulfilled_at === null && $resetRequest->expires_at->isFuture()) {
                    return;
                }

                $now = now('Asia/Jakarta');
                $values = [
                    'reset_code' => Str::upper(Str::random(8)),
                    'attempts' => 0,
                    'requested_at' => $now,
                    'expires_at' => $now->copy()->addDay(),
                    'fulfilled_at' => null,
                ];

                if ($resetRequest === null) {
                    KompenResponHubStudentPasswordResetRequest::query()->create(['nim' => $nim, ...$values]);

                    return;
                }

                $resetRequest->forceFill($values)->save();
            });
        }

        return to_route('student.password-reset.edit')->with(
            'success',
            'Jika NIM aktif, permintaan reset telah dicatat. Hubungi admin jurusan untuk memperoleh kode yang berlaku selama 24 jam.',
        );
    }

    public function edit(Request $request): Response
    {
        return Inertia::render('auth/student-password-reset', $this->studentSessionProps($request));
    }

    public function update(UpdateStudentPasswordResetRequest $request, SikompenStudentAccess $studentAccess): RedirectResponse
    {
        $nim = $this->requestedNim($request);

        if (! $this->canRequestReset($nim, $studentAccess)) {
            throw ValidationException::withMessages(['reset_code' => 'Kode reset tidak valid atau sudah kedaluwarsa.']);
        }

        $resetError = DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($request, $nim, $studentAccess): ?string {
            if (! $this->canRequestReset($nim, $studentAccess)) {
                return 'Kode reset tidak valid atau sudah kedaluwarsa.';
            }

            $resetRequest = KompenResponHubStudentPasswordResetRequest::query()
                ->where('nim', $nim)->whereNull('fulfilled_at')->lockForUpdate()->first();

            if ($resetRequest === null || $resetRequest->expires_at->isPast()) {
                return 'Kode reset tidak valid atau sudah kedaluwarsa.';
            }

            if (! hash_equals($resetRequest->reset_code, $request->string('reset_code')->toString())) {
                return 'Kode reset tidak tepat.';
            }

            $account = KompenResponHubStudentAccount::query()
                ->where('nim', $nim)
                ->whereNotNull('password_changed_at')
                ->lockForUpdate()
                ->first();

            if (! $account instanceof KompenResponHubStudentAccount) {
                return 'Kode reset tidak valid atau sudah kedaluwarsa.';
            }

            $account->forceFill([
                'password' => $request->string('password')->toString(),
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
                'session_version' => $account->session_version + 1,
            ])->save();
            $resetRequest->forceFill(['fulfilled_at' => now()])->save();

            return null;
        });

        if ($resetError !== null) {
            throw ValidationException::withMessages(['reset_code' => $resetError]);
        }

        return to_route('student.login')->with('success', 'Password berhasil diubah. Silakan masuk dengan password baru.');
    }

    /** @return array{studentNim: string|null} */
    private function studentSessionProps(Request $request): array
    {
        $student = $request->user('student');

        return [
            'studentNim' => $student instanceof KompenResponHubStudentAccount ? $student->nim : null,
        ];
    }

    private function requestedNim(Request $request): string
    {
        $nim = $request->string('nim')->toString();
        $student = $request->user('student');

        if ($student instanceof KompenResponHubStudentAccount && ! hash_equals($student->nim, $nim)) {
            throw ValidationException::withMessages(['nim' => 'Gunakan NIM dari akun mahasiswa yang sedang masuk.']);
        }

        return $nim;
    }

    private function canRequestReset(string $nim, SikompenStudentAccess $studentAccess): bool
    {
        return $studentAccess->activeStudentExists($nim)
            && KompenResponHubStudentAccount::query()
                ->where('nim', $nim)
                ->whereNotNull('password_changed_at')
                ->exists();
    }
}
