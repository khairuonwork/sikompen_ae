<?php

namespace App\Http\Controllers;

use App\Actions\SiAdminProxy\SiAdminProxyAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SiAdminProxyAccessController extends Controller
{
    public function __invoke(Request $request, SiAdminProxyAccess $access): RedirectResponse
    {
        /** @var array{email: string, nonce: string, role: string, student_nim: string, timestamp: int, user_id: string} $proxyIdentity */
        $proxyIdentity = $request->attributes->get('si_admin_proxy');

        $request->session()->regenerate();
        $request->session()->put([
            'si_admin_proxy.authenticated_at' => $proxyIdentity['timestamp'],
            'si_admin_proxy.email' => $proxyIdentity['email'],
            'si_admin_proxy.role' => $proxyIdentity['role'],
            'si_admin_proxy.user_id' => $proxyIdentity['user_id'],
            'si_admin_proxy.student_nim' => $proxyIdentity['student_nim'],
        ]);

        if ($proxyIdentity['role'] === 'superuser') {
            return to_route('home');
        }

        if ($access->hasAdminAccess($request)) {
            return to_route('admin.kompen-respon.index');
        }

        return to_route('student.kompen-respon.index');
    }
}
