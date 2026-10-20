<?php

use App\Http\Middleware\EnsureSikompenAdminAccess;
use App\Http\Middleware\EnsureSikompenApiClient;
use App\Http\Middleware\EnsureSikompenDataAccess;
use App\Http\Middleware\EnsureSikompenEmergencyAdminAccess;
use App\Http\Middleware\EnsureSikompenStandardAdminAccess;
use App\Http\Middleware\EnsureSikompenStudentAccess;
use App\Http\Middleware\EnsureSikompenStudentPasswordChanged;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = require __DIR__.'/../config/trusted-proxies.php';

        $middleware->trustProxies(
            at: $trustedProxies['cidrs'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'sikompen.admin' => EnsureSikompenAdminAccess::class,
            'sikompen.api-client' => EnsureSikompenApiClient::class,
            'sikompen.data-access' => EnsureSikompenDataAccess::class,
            'sikompen.emergency-admin' => EnsureSikompenEmergencyAdminAccess::class,
            'sikompen.student' => EnsureSikompenStudentAccess::class,
            'sikompen.student-password-changed' => EnsureSikompenStudentPasswordChanged::class,
            'sikompen.standard-admin' => EnsureSikompenStandardAdminAccess::class,
        ]);

        $middleware->redirectGuestsTo(fn (): string => route('login'));
        $middleware->redirectUsersTo(function (Request $request): string {
            if ($request->user('student') !== null) {
                return route('student.kompen-respon.index');
            }

            if ($request->user('admin')?->is_emergency_admin === true) {
                return route('admin.recovery.index');
            }

            return route('admin.kompen-respon.index');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
