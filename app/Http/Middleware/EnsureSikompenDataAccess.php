<?php

namespace App\Http\Middleware;

use App\Actions\KompenResponHub\SikompenAdminAccess;
use App\Actions\KompenResponHub\SikompenStudentAccess;
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
        if ($this->adminAccess->hasAdminAccess($request) || $this->studentAccess->hasStudentAccess($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(401, 'Autentikasi diperlukan.');
        }

        return to_route('student.login');
    }
}
