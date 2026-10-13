<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\SikompenStudentAccess;
use App\Http\Requests\StoreStudentPasswordResetRequest;
use App\Http\Requests\UpdateStudentPasswordResetRequest;
use App\Models\KompenResponHubStudentAccount;
use App\Models\KompenResponHubStudentPasswordResetRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StudentPasswordResetController extends Controller
{
    public function createRequest(): Response
    {
        return Inertia::render('auth/student-password-reset-request');
    }

    public function storeRequest(StoreStudentPasswordResetRequest $request, SikompenStudentAccess $studentAccess): RedirectResponse
    {
        $nim = $request->string('nim')->toString();

        if ($studentAccess->activeStudentExists($nim)) {
            DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($nim): void {
                $resetRequest = KompenResponHubStudentPasswordResetRequest::query()->where('nim', $nim)->lockForUpdate()->first();

                if ($resetRequest !== null && $resetRequest->fulfilled_at === null && $resetRequest->expires_at->isFuture()) {
                    return;
                }

                $values = [
                    'reset_code' => Str::upper(Str::random(8)),
                    'attempts' => 0,
                    'requested_at' => now(),
                    'expires_at' => now()->addDay(),
                    'fulfilled_at' => null,
                ];

                if ($resetRequest === null) {
                    KompenResponHubStudentPasswordResetRequest::query()->create(['nim' => $nim, ...$values]);

                    return;
                }

                $resetRequest->forceFill($values)->save();
            });
        }

        return to_route('student.password-reset.edit', ['nim' => $nim])->with(
            'success',
            'Jika NIM aktif, permintaan reset telah dicatat. Hubungi admin jurusan untuk memperoleh kode yang berlaku selama 24 jam.',
        );
    }

    public function edit(): Response
    {
        return Inertia::render('auth/student-password-reset');
    }

    public function update(UpdateStudentPasswordResetRequest $request, SikompenStudentAccess $studentAccess): RedirectResponse
    {
        $nim = $request->string('nim')->toString();

        if (! $studentAccess->activeStudentExists($nim)) {
            throw ValidationException::withMessages(['reset_code' => 'Kode reset tidak valid atau sudah kedaluwarsa.']);
        }

        DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($request, $nim): void {
            $resetRequest = KompenResponHubStudentPasswordResetRequest::query()
                ->where('nim', $nim)->whereNull('fulfilled_at')->lockForUpdate()->first();

            if ($resetRequest === null || $resetRequest->expires_at->isPast() || $resetRequest->attempts >= 5) {
                throw ValidationException::withMessages(['reset_code' => 'Kode reset tidak valid atau sudah kedaluwarsa.']);
            }

            if (! hash_equals($resetRequest->reset_code, $request->string('reset_code')->toString())) {
                $resetRequest->increment('attempts');
                throw ValidationException::withMessages(['reset_code' => 'Kode reset tidak tepat.']);
            }

            $account = KompenResponHubStudentAccount::query()->firstOrCreate(['nim' => $nim], ['password' => Hash::make($nim)]);
            $account->forceFill([
                'password' => $request->string('password')->toString(),
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
                'session_version' => $account->session_version + 1,
            ])->save();
            $resetRequest->forceFill(['fulfilled_at' => now()])->save();
        });

        return to_route('student.login')->with('success', 'Password berhasil diubah. Silakan masuk dengan password baru.');
    }
}
