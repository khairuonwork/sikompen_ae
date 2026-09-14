<?php

namespace App\Actions\SiAdminProxy;

use App\Models\KompenResponHubAdmin;
use Illuminate\Http\Request;

class SiAdminProxyAccess
{
    /**
     * @return array{email: string, id: ?int}
     */
    public function actor(Request $request): array
    {
        /** @var KompenResponHubAdmin|null $localAdmin */
        $localAdmin = $request->user('admin');

        if ($localAdmin !== null) {
            return [
                'email' => $localAdmin->email,
                'id' => $localAdmin->id,
            ];
        }

        $email = $request->session()->get('si_admin_proxy.email');

        if (! is_string($email) || $email === '') {
            throw new \LogicException('Identitas admin dari Si-Admin tidak tersedia.');
        }

        return [
            'email' => $email,
            'id' => null,
        ];
    }

    public function hasAdminAccess(Request $request): bool
    {
        if ($request->user('admin') !== null) {
            return true;
        }

        $role = $request->session()->get('si_admin_proxy.role');

        return is_string($role)
            && in_array($role, config('si-admin-proxy.admin_roles'), true);
    }
}
