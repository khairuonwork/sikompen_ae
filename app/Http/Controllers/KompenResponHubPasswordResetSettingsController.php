<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Http\Requests\DestroyAdminPasswordResetCodeRequest;
use App\Http\Requests\StoreAdminPasswordResetCodeRequest;
use App\Models\KompenResponHubAdminPasswordResetCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class KompenResponHubPasswordResetSettingsController extends Controller
{
    public function storeCode(
        StoreAdminPasswordResetCodeRequest $request,
        KompenResponHubAdminPasswordResetCode $resetCode,
    ): RedirectResponse {
        $code = (string) random_int(100_000, 999_999);
        $issuedResetCode = DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($request, $resetCode, $code): KompenResponHubAdminPasswordResetCode {
            $pendingResetCode = KompenResponHubAdminPasswordResetCode::query()
                ->lockForUpdate()
                ->findOrFail($resetCode->id);

            abort_if($pendingResetCode->used_at !== null || $pendingResetCode->requested_at === null, 404);

            $pendingResetCode->forceFill([
                'generated_by_admin_id' => $request->user('admin')->id,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now('Asia/Jakarta')->addMinutes(15),
            ])->save();

            return $pendingResetCode;
        });
        $issuedResetCode->load('admin');

        app(RecordKompenResponHubActivity::class)->execute(
            'admin_reset_code_issued',
            'admin_password_reset',
            (string) $issuedResetCode->id,
            $request->user('admin'),
            $request,
            subjectName: $issuedResetCode->admin?->username,
        );

        return to_route('admin.settings')->with('admin_reset_code', $code)->with('success', 'Kode reset admin berhasil dibuat dan berlaku 15 menit.');
    }

    public function destroyCode(
        DestroyAdminPasswordResetCodeRequest $request,
        KompenResponHubAdminPasswordResetCode $resetCode,
    ): RedirectResponse {
        $pendingResetCode = DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($resetCode): KompenResponHubAdminPasswordResetCode {
            $pendingResetCode = KompenResponHubAdminPasswordResetCode::query()
                ->with('admin')
                ->lockForUpdate()
                ->findOrFail($resetCode->id);

            abort_if($pendingResetCode->used_at !== null || $pendingResetCode->requested_at === null, 404);

            $pendingResetCode->delete();

            return $pendingResetCode;
        });

        app(RecordKompenResponHubActivity::class)->execute(
            'admin_reset_request_cancelled',
            'admin_password_reset',
            (string) $pendingResetCode->id,
            $request->user('admin'),
            $request,
            subjectName: $pendingResetCode->admin?->username,
        );

        return to_route('admin.settings')->with('success', 'Permintaan reset admin dibatalkan.');
    }
}
