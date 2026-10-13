<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdminPasswordResetRequest;
use App\Http\Requests\UpdateAdminPasswordResetRequest;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubAdminPasswordResetCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminPasswordResetController extends Controller
{
    public function createRequest(): Response
    {
        return Inertia::render('auth/admin-password-reset-request');
    }

    public function storeRequest(StoreAdminPasswordResetRequest $request): RedirectResponse
    {
        $email = $request->string('email')->toString();

        DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($email): void {
            $admin = KompenResponHubAdmin::query()->where('email', $email)->lockForUpdate()->first();

            if ($admin === null) {
                return;
            }

            KompenResponHubAdminPasswordResetCode::query()->updateOrCreate(
                ['admin_id' => $admin->id],
                [
                    'recipient_id' => null,
                    'generated_by_admin_id' => null,
                    'code_hash' => null,
                    'attempts' => 0,
                    'requested_at' => now(),
                    'expires_at' => null,
                    'used_at' => null,
                ],
            );
        });

        return to_route('admin.password-reset.edit', ['email' => $email])->with(
            'success',
            'Jika email terdaftar, permintaan reset dicatat. Hubungi pemegang akun darurat untuk memperoleh kode reset.',
        );
    }

    public function edit(): Response
    {
        return Inertia::render('auth/admin-password-reset');
    }

    public function update(UpdateAdminPasswordResetRequest $request): RedirectResponse
    {
        $email = $request->string('email')->toString();

        DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($request, $email): void {
            $admin = KompenResponHubAdmin::query()->where('email', $email)->lockForUpdate()->first();
            $code = $admin === null ? null : KompenResponHubAdminPasswordResetCode::query()
                ->where('admin_id', $admin->id)->whereNull('used_at')->latest('id')->lockForUpdate()->first();

            if ($admin === null || $code === null || $code->code_hash === null || $code->expires_at === null || $code->expires_at->isPast() || $code->attempts >= 5) {
                throw ValidationException::withMessages(['otp' => 'Kode reset tidak valid atau sudah kedaluwarsa.']);
            }

            if (! Hash::check($request->string('otp')->toString(), $code->code_hash)) {
                $code->increment('attempts');
                throw ValidationException::withMessages(['otp' => 'Kode reset tidak tepat.']);
            }

            $admin->forceFill([
                'password' => $request->string('password')->toString(),
                'session_version' => $admin->session_version + 1,
            ])->save();
            $code->forceFill(['used_at' => now()])->save();
        });

        return to_route('login')->with('success', 'Password admin berhasil diubah. Silakan masuk dengan password baru.');
    }
}
