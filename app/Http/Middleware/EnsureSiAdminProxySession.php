<?php

namespace App\Http\Middleware;

use App\Actions\SiAdminProxy\SiAdminProxyAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiAdminProxySession
{
    public function __construct(private SiAdminProxyAccess $access) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('si-admin-proxy.enabled')) {
            return $next($request);
        }

        if (! $this->access->hasValidProxySession($request)) {
            $request->session()->invalidate();

            abort(403);
        }

        return $next($request);
    }
}
