<?php

namespace App\Http\Middleware;

use App\Actions\KompenResponHub\SikompenAdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSikompenAdminAccess
{
    public function __construct(private SikompenAdminAccess $access) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->access->hasAdminAccess($request)) {
            return to_route('login');
        }

        return $next($request);
    }
}
