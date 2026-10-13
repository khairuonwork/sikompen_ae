<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Http\Requests\DestroyKompenResponHubAdminPasswordResetRecipientRequest;
use App\Http\Requests\StoreKompenResponHubAdminPasswordResetRecipientRequest;
use App\Models\KompenResponHubAdminPasswordResetRecipient;
use Illuminate\Http\RedirectResponse;

class KompenResponHubPasswordResetSettingsController extends Controller
{
    public function storeRecipient(StoreKompenResponHubAdminPasswordResetRecipientRequest $request): RedirectResponse
    {
        $recipient = KompenResponHubAdminPasswordResetRecipient::query()->firstOrCreate(
            ['email' => $request->string('email')->toString()],
            ['added_by_admin_id' => $request->user('admin')?->id],
        );

        if ($recipient->wasRecentlyCreated) {
            app(RecordKompenResponHubActivity::class)->execute(
                'admin_reset_recipient_added',
                'admin_password_reset_recipient',
                (string) $recipient->id,
                $request->user('admin'),
                $request,
                subjectName: $recipient->email,
            );
        }

        return to_route('admin.settings')->with('success', 'Email penerima OTP admin disimpan.');
    }

    public function destroyRecipient(
        DestroyKompenResponHubAdminPasswordResetRecipientRequest $request,
        KompenResponHubAdminPasswordResetRecipient $recipient,
    ): RedirectResponse {
        app(RecordKompenResponHubActivity::class)->execute(
            'admin_reset_recipient_removed',
            'admin_password_reset_recipient',
            (string) $recipient->id,
            $request->user('admin'),
            $request,
            subjectName: $recipient->email,
        );
        $recipient->delete();

        return to_route('admin.settings')->with('success', 'Email penerima OTP admin dihapus.');
    }
}
