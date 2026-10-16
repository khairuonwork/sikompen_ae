<?php

namespace App\Http\Middleware;

use App\Models\KompenResponHubStudentAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSikompenStudentPasswordChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $student = $request->user('student');

        if ($student instanceof KompenResponHubStudentAccount && $student->requiresPasswordChange()) {
            if ($request->expectsJson()) {
                abort(403, 'Ganti password awal sebelum mengakses data.');
            }

            return to_route('student.password.edit');
        }

        return $next($request);
    }
}
