<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Http\Requests\DestroyAdminPasswordResetCodeRequest;
use App\Http\Requests\StoreAdminPasswordResetCodeRequest;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubAdminPasswordResetCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class EmergencyAdminRecoveryController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var KompenResponHubAdmin $admin */
        $admin = $request->user('admin');

        return Inertia::render('admin/emergency-recovery', [
            'currentAdmin' => [
                'username' => $admin->username,
            ],
            'resetRequests' => KompenResponHubAdminPasswordResetCode::query()
                ->with('admin:id,username')
                ->whereNull('used_at')
                ->whereHas('admin', fn ($query) => $query->where('is_emergency_admin', false))
                ->latest('requested_at')
                ->latest('id')
                ->get()
                ->map(fn (KompenResponHubAdminPasswordResetCode $resetCode): array => [
                    'id' => $resetCode->id,
                    'username' => $resetCode->admin->username,
                    'requestedAt' => $resetCode->requested_at?->toIso8601String(),
                    'expiresAt' => $resetCode->expires_at?->toIso8601String(),
                    'isIssued' => $resetCode->code_hash !== null && $resetCode->expires_at?->isFuture(),
                ])->values(),
        ]);
    }

    public function storeCode(
        StoreAdminPasswordResetCodeRequest $request,
        KompenResponHubAdminPasswordResetCode $resetCode,
        RecordKompenResponHubActivity $activity,
    ): RedirectResponse {
        $code = (string) random_int(100_000, 999_999);
        $issuedResetCode = DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($request, $resetCode, $code): KompenResponHubAdminPasswordResetCode {
            $pendingResetCode = KompenResponHubAdminPasswordResetCode::query()
                ->lockForUpdate()
                ->findOrFail($resetCode->id);
            $targetAdmin = KompenResponHubAdmin::query()
                ->lockForUpdate()
                ->findOrFail($pendingResetCode->admin_id);

            abort_if(
                $targetAdmin->is_emergency_admin
                || $pendingResetCode->used_at !== null
                || $pendingResetCode->requested_at === null,
                404,
            );

            $pendingResetCode->forceFill([
                'generated_by_admin_id' => $request->user('admin')->id,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now('Asia/Jakarta')->addMinutes(15),
            ])->save();

            return $pendingResetCode;
        });
        $issuedResetCode->load('admin');

        $activity->execute(
            'admin_reset_code_issued',
            'admin_password_reset',
            (string) $issuedResetCode->id,
            $request->user('admin'),
            $request,
            subjectName: $issuedResetCode->admin?->username,
        );

        return to_route('admin.recovery.index')
            ->with('admin_reset_code', $code)
            ->with('success', 'Kode reset admin berhasil dibuat dan berlaku 15 menit.');
    }

    public function destroyCode(
        DestroyAdminPasswordResetCodeRequest $request,
        KompenResponHubAdminPasswordResetCode $resetCode,
        RecordKompenResponHubActivity $activity,
    ): RedirectResponse {
        $pendingResetCode = DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($resetCode): KompenResponHubAdminPasswordResetCode {
            $pendingResetCode = KompenResponHubAdminPasswordResetCode::query()
                ->with('admin')
                ->lockForUpdate()
                ->findOrFail($resetCode->id);

            abort_if(
                $pendingResetCode->admin?->is_emergency_admin === true
                || $pendingResetCode->used_at !== null
                || $pendingResetCode->requested_at === null,
                404,
            );

            $pendingResetCode->delete();

            return $pendingResetCode;
        });

        $activity->execute(
            'admin_reset_request_cancelled',
            'admin_password_reset',
            (string) $pendingResetCode->id,
            $request->user('admin'),
            $request,
            subjectName: $pendingResetCode->admin?->username,
        );

        return to_route('admin.recovery.index')->with('success', 'Permintaan reset admin dibatalkan.');
    }
}
