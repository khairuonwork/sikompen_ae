<?php

namespace App\Http\Middleware;

use App\Actions\KompenResponHub\SikompenStudentAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSikompenStudentAccess
{
    public function __construct(private SikompenStudentAccess $studentAccess) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->studentAccess->hasStudentAccess($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(401, 'Autentikasi mahasiswa diperlukan.');
        }

        return to_route('student.login');
    }
}
