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
        if (config('si-admin-proxy.enabled')) {
            if (! $this->hasValidProxySession($request)) {
                throw new \LogicException('Identitas admin dari Si-Admin tidak tersedia.');
            }

            $email = $request->session()->get('si_admin_proxy.email');

            if (! is_string($email)) {
                throw new \LogicException('Identitas admin dari Si-Admin tidak tersedia.');
            }

            return [
                'email' => $email,
                'id' => null,
            ];
        }

        /** @var KompenResponHubAdmin|null $localAdmin */
        $localAdmin = $request->user('admin');

        if ($localAdmin !== null) {
            return [
                'email' => $localAdmin->email,
                'id' => $localAdmin->id,
            ];
        }

        throw new \LogicException('Identitas admin tidak tersedia.');
    }

    public function hasAdminAccess(Request $request): bool
    {
        if (config('si-admin-proxy.enabled')) {
            if (! $this->hasValidProxySession($request)) {
                return false;
            }

            return in_array(
                $request->session()->get('si_admin_proxy.role'),
                config('si-admin-proxy.admin_roles'),
                true,
            );
        }

        if ($request->user('admin') !== null) {
            return true;
        }

        return false;
    }

    public function hasValidProxySession(Request $request): bool
    {
        $authenticatedAt = $request->session()->get('si_admin_proxy.authenticated_at');
        $email = $request->session()->get('si_admin_proxy.email');
        $role = $request->session()->get('si_admin_proxy.role');
        $userId = $request->session()->get('si_admin_proxy.user_id');

        $sessionAgeInSeconds = is_int($authenticatedAt)
            ? now()->getTimestamp() - $authenticatedAt
            : null;

        return is_int($authenticatedAt)
            && is_int($sessionAgeInSeconds)
            && $sessionAgeInSeconds >= -config('si-admin-proxy.signature_ttl_seconds')
            && $sessionAgeInSeconds <= config('si-admin-proxy.session_max_age_seconds')
            && is_string($email)
            && preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email) === 1
            && is_string($role)
            && in_array($role, config('si-admin-proxy.allowed_roles'), true)
            && is_string($userId)
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $userId) === 1;
    }
}
