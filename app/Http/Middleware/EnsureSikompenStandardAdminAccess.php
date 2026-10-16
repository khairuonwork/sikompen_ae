<?php

namespace App\Http\Middleware;

use App\Models\KompenResponHubAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSikompenStandardAdminAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if ($admin instanceof KompenResponHubAdmin && $admin->is_emergency_admin) {
            if ($request->expectsJson()) {
                abort(403, 'Akun darurat tidak dapat mengakses fitur operasional.');
            }

            return to_route('admin.recovery.index');
        }

        return $next($request);
    }
}
