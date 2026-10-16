<?php

namespace App\Http\Middleware;

use App\Actions\KompenResponHub\SikompenAdminAccess;
use App\Actions\KompenResponHub\SikompenStudentAccess;
use App\Models\KompenResponHubAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSikompenDataAccess
{
    public function __construct(
        private SikompenAdminAccess $adminAccess,
        private SikompenStudentAccess $studentAccess,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if ($admin instanceof KompenResponHubAdmin && $admin->is_emergency_admin) {
            abort(403, 'Akun darurat tidak dapat mengakses data operasional.');
        }

        if ($this->adminAccess->hasAdminAccess($request) || $this->studentAccess->hasStudentAccess($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(401, 'Autentikasi diperlukan.');
        }

        return to_route('student.login');
    }
}
