<?php

namespace App\Http\Middleware;

use App\Models\KompenResponHubAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSikompenEmergencyAdminAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if (! $admin instanceof KompenResponHubAdmin || ! $admin->is_emergency_admin) {
            abort(403, 'Akses hanya tersedia untuk akun darurat.');
        }

        return $next($request);
    }
}
