<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubAdmin;
use Illuminate\Http\Request;

class SikompenAdminAccess
{
    /** @return array{username: string, id: int} */
    public function actor(Request $request): array
    {
        $admin = $request->user('admin');

        if (! $admin instanceof KompenResponHubAdmin) {
            throw new \LogicException('Identitas admin tidak tersedia.');
        }

        return [
            'username' => $admin->username,
            'id' => $admin->id,
        ];
    }

    public function hasAdminAccess(Request $request): bool
    {
        $admin = $request->user('admin');

        return $admin instanceof KompenResponHubAdmin
            && (! $request->session()->has('admin_session_version')
                || $request->session()->get('admin_session_version') === $admin->session_version);
    }
}
