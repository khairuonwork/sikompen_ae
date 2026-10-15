<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Http\Requests\UpdateAdminPasswordRequest;
use App\Http\Requests\UpdateAdminUsernameRequest;
use App\Models\KompenResponHubAdmin;
use Illuminate\Http\RedirectResponse;

class AdminCredentialsController extends Controller
{
    public function updateUsername(
        UpdateAdminUsernameRequest $request,
        RecordKompenResponHubActivity $activity,
    ): RedirectResponse {
        /** @var KompenResponHubAdmin $admin */
        $admin = $request->user('admin');
        $previousUsername = $admin->username;

        $admin->forceFill([
            'username' => $request->string('username')->toString(),
            'session_version' => $admin->session_version + 1,
        ])->save();
        $request->session()->put('admin_session_version', $admin->session_version);

        $activity->execute(
            'admin.username_updated',
            'system_setting',
            (string) $admin->id,
            $admin,
            $request,
            subjectName: 'Username admin',
            reason: 'Username akun admin diperbarui.',
            beforeState: ['username' => $previousUsername],
            afterState: ['username' => $admin->username],
        );

        return to_route('admin.settings')->with('success', 'Username admin berhasil diperbarui. Sesi lain telah dikeluarkan.');
    }

    public function updatePassword(
        UpdateAdminPasswordRequest $request,
        RecordKompenResponHubActivity $activity,
    ): RedirectResponse {
        /** @var KompenResponHubAdmin $admin */
        $admin = $request->user('admin');

        $admin->forceFill([
            'password' => $request->string('password')->toString(),
            'session_version' => $admin->session_version + 1,
        ])->save();
        $request->session()->put('admin_session_version', $admin->session_version);

        $activity->execute(
            'admin.password_updated',
            'system_setting',
            (string) $admin->id,
            $admin,
            $request,
            subjectName: 'Password admin',
            reason: 'Password akun admin diperbarui dan sesi lain dikeluarkan.',
        );

        return to_route('admin.settings')->with('success', 'Password admin berhasil diperbarui. Sesi lain telah dikeluarkan.');
    }
}
