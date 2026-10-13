<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubAdmin;
use Illuminate\Http\Request;

class SikompenAdminAccess
{
    /** @return array{email: string, id: int} */
    public function actor(Request $request): array
    {
        $admin = $request->user('admin');

        if (! $admin instanceof KompenResponHubAdmin) {
            throw new \LogicException('Identitas admin tidak tersedia.');
        }

        return [
            'email' => $admin->email,
            'id' => $admin->id,
        ];
    }

    public function hasAdminAccess(Request $request): bool
    {
        return $request->user('admin') instanceof KompenResponHubAdmin;
    }
}
