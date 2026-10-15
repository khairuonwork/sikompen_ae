<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\SikompenAdminAccess;
use App\Http\Requests\StoreKompenResponHubAdminSetupRequest;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubAdminPasswordResetCode;
use App\Models\KompenResponHubAdminSetupWindow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KompenResponHubAdminSetupController extends Controller
{
    public function create(): Response
    {
        $hasRegisteredAdmin = KompenResponHubAdmin::query()->exists();
        $setupWindow = $this->setupWindow();

        return Inertia::render('auth/admin-setup', [
            'setupOpen' => $this->isRegistrationOpen($hasRegisteredAdmin, $setupWindow),
            'requiresActivationCode' => $hasRegisteredAdmin,
        ]);
    }

    public function store(StoreKompenResponHubAdminSetupRequest $request): RedirectResponse
    {
        DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($request): void {
            $setupWindow = KompenResponHubAdminSetupWindow::query()
                ->lockForUpdate()
                ->findOrFail(1);
            $hasRegisteredAdmin = KompenResponHubAdmin::query()->lockForUpdate()->exists();

            if (! $this->isRegistrationOpen($hasRegisteredAdmin, $setupWindow)) {
                abort(403, 'Pendaftaran admin sedang tidak dibuka.');
            }

            if ($hasRegisteredAdmin && ! Hash::check($request->string('activation_code')->toString(), $setupWindow->activation_code_hash)) {
                throw ValidationException::withMessages([
                    'activation_code' => 'Kode aktivasi tidak tepat.',
                ]);
            }

            KompenResponHubAdmin::query()->create([
                'username' => Str::lower($request->string('username')->toString()),
                'password' => $request->string('password')->toString(),
            ]);

            $setupWindow->forceFill([
                'activation_code_hash' => null,
                'expires_at' => null,
                'opened_by_admin_id' => null,
            ])->save();
        });

        return to_route('login')->with('success', 'Akun admin berhasil dibuat. Silakan masuk.');
    }

    public function settings(Request $request): Response
    {
        $setupWindow = $this->setupWindow();

        return Inertia::render('admin/settings', [
            'currentAdmin' => [
                'username' => (string) $request->user('admin')?->username,
            ],
            'setupWindow' => [
                'isOpen' => $setupWindow->isOpen(),
                'expiresAt' => $setupWindow->isOpen()
                    ? $setupWindow->expires_at?->toIso8601String()
                    : null,
            ],
            'emergencyAdmins' => KompenResponHubAdmin::query()
                ->where('is_emergency_admin', true)
                ->orderBy('username')
                ->get(['id', 'username'])
                ->map(fn (KompenResponHubAdmin $admin): array => ['id' => $admin->id, 'username' => $admin->username]),
            'canManageAdminResets' => $request->user('admin')?->is_emergency_admin === true,
            'adminPasswordResetRequests' => $request->user('admin')?->is_emergency_admin === true
                ? KompenResponHubAdminPasswordResetCode::query()
                    ->with('admin:id,username')
                    ->whereNull('used_at')
                    ->latest('requested_at')
                    ->get()
                    ->map(fn (KompenResponHubAdminPasswordResetCode $resetCode): array => [
                        'id' => $resetCode->id,
                        'username' => $resetCode->admin->username,
                        'requestedAt' => $resetCode->requested_at?->toIso8601String(),
                        'expiresAt' => $resetCode->expires_at?->toIso8601String(),
                        'isIssued' => $resetCode->code_hash !== null && $resetCode->expires_at?->isFuture(),
                    ])->values()
                : [],
        ]);
    }

    public function enable(Request $request, SikompenAdminAccess $access): RedirectResponse
    {
        $activationCode = Str::upper(Str::random(20));
        $actor = $access->actor($request);

        $this->setupWindow()->forceFill([
            'activation_code_hash' => Hash::make($activationCode),
            'expires_at' => now()->addMinutes(30),
            'opened_by_admin_id' => $actor['id'],
        ])->save();

        return to_route('admin.settings')->with('admin_setup_code', $activationCode);
    }

    public function disable(Request $request): RedirectResponse
    {
        $this->setupWindow()->forceFill([
            'activation_code_hash' => null,
            'expires_at' => null,
            'opened_by_admin_id' => null,
        ])->save();

        $request->session()->forget('admin_setup_code');

        return to_route('admin.settings')->with('success', 'Pendaftaran admin ditutup.');
    }

    private function setupWindow(): KompenResponHubAdminSetupWindow
    {
        return KompenResponHubAdminSetupWindow::query()->findOrFail(1);
    }

    private function isRegistrationOpen(
        bool $hasRegisteredAdmin,
        KompenResponHubAdminSetupWindow $setupWindow,
    ): bool {
        return ! $hasRegisteredAdmin || $setupWindow->isOpen();
    }
}
