<?php

namespace App\Providers;

use App\KompenResponHub\SikompenApiClientConfiguration;
use App\Models\KompenResponHubAdmin;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('admin-login', function (Request $request): Limit {
            $username = Str::lower($request->string('username')->toString());

            return Limit::perMinute(10)->by(Str::transliterate("{$username}|{$request->ip()}"));
        });

        RateLimiter::for('student-login', function (Request $request): Limit {
            $nim = $request->string('nim')->toString();

            return Limit::perMinute(10)->by(Str::transliterate("{$nim}|{$request->ip()}"));
        });

        RateLimiter::for('admin-setup', function (Request $request): Limit {
            return Limit::perMinute(5)->by(Str::transliterate("admin-setup|{$request->ip()}"));
        });

        RateLimiter::for('admin-password-reset-request', function (Request $request): Limit {
            $admin = $request->user('admin');
            $username = $admin instanceof KompenResponHubAdmin
                ? $admin->username
                : Str::lower($request->string('username')->toString());

            return Limit::perMinute(3)->by(Str::transliterate("admin-reset-request|{$username}|{$request->ip()}"));
        });

        RateLimiter::for('admin-password-reset-confirm', function (Request $request): Limit {
            $username = Str::lower($request->string('username')->toString());

            return Limit::perMinute(5)->by(Str::transliterate("admin-reset-confirm|{$username}|{$request->ip()}"));
        });

        RateLimiter::for('student-password-reset-request', function (Request $request): Limit {
            return Limit::perMinute(3)->by(Str::transliterate("student-reset-request|{$request->string('nim')->toString()}|{$request->ip()}"));
        });

        RateLimiter::for('student-password-reset-confirm', function (Request $request): Limit {
            return Limit::perMinute(5)->by(Str::transliterate("student-reset-confirm|{$request->string('nim')->toString()}|{$request->ip()}"));
        });

        RateLimiter::for('sikompen-data', function (Request $request): Limit {
            $sessionId = $request->hasSession() ? $request->session()->getId() : 'no-session';

            return Limit::perMinute(30)->by(Str::transliterate("{$sessionId}|{$request->ip()}"));
        });

        RateLimiter::for('sikompen-api', function (Request $request): Limit {
            $client = $request->attributes->get('sikompen_api_client');
            $limit = $client instanceof SikompenApiClientConfiguration ? $client->rateLimitPerMinute : 10;
            $key = $client instanceof SikompenApiClientConfiguration ? $client->key : 'unknown';

            return Limit::perMinute($limit)->by(Str::transliterate("sikompen-api|{$key}|{$request->ip()}"));
        });

        RateLimiter::for('sikompen-api-auth', function (Request $request): Limit {
            return Limit::perMinute(60)->by(Str::transliterate("sikompen-api-auth|{$request->ip()}"));
        });
    }
}
