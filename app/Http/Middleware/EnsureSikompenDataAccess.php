<?php

namespace App\Http\Middleware;

use App\Actions\KompenResponHub\SikompenStudentAccess;
use App\Actions\SiAdminProxy\SiAdminProxyAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSikompenDataAccess
{
    public function __construct(
        private SiAdminProxyAccess $proxyAccess,
        private SikompenStudentAccess $studentAccess,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->proxyAccess->hasAdminAccess($request) || $this->studentAccess->hasStudentAccess($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(401, 'Autentikasi diperlukan.');
        }

        if (config('si-admin-proxy.enabled')) {
            abort(403);
        }

        return to_route('student.login');

    }
}
