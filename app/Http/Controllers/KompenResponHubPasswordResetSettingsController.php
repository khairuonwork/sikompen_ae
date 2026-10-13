<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Http\Requests\DestroyAdminPasswordResetCodeRequest;
use App\Http\Requests\StoreAdminPasswordResetCodeRequest;
use App\Models\KompenResponHubAdminPasswordResetCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

class KompenResponHubPasswordResetSettingsController extends Controller
{
    public function storeCode(
        StoreAdminPasswordResetCodeRequest $request,
        KompenResponHubAdminPasswordResetCode $resetCode,
    ): RedirectResponse {
        $code = (string) random_int(100_000, 999_999);
        $resetCode->forceFill([
            'generated_by_admin_id' => $request->user('admin')->id,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
            'used_at' => null,
        ])->save();
        app(RecordKompenResponHubActivity::class)->execute(
            'admin_reset_code_issued',
            'admin_password_reset',
            (string) $resetCode->id,
            $request->user('admin'),
            $request,
            subjectName: $resetCode->admin?->email,
        );

        return to_route('admin.settings')->with('admin_reset_code', $code)->with('success', 'Kode reset admin berhasil dibuat dan berlaku 15 menit.');
    }

    public function destroyCode(
        DestroyAdminPasswordResetCodeRequest $request,
        KompenResponHubAdminPasswordResetCode $resetCode,
    ): RedirectResponse {
        app(RecordKompenResponHubActivity::class)->execute(
            'admin_reset_request_cancelled',
            'admin_password_reset',
            (string) $resetCode->id,
            $request->user('admin'),
            $request,
            subjectName: $resetCode->admin?->email,
        );
        $resetCode->delete();

        return to_route('admin.settings')->with('success', 'Permintaan reset admin dibatalkan.');
    }
}
