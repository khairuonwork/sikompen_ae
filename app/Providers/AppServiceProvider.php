<?php

namespace App\Providers;

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
            $email = Str::lower($request->string('email')->toString());

            return Limit::perMinute(10)->by(Str::transliterate("{$email}|{$request->ip()}"));
        });

        RateLimiter::for('student-login', function (Request $request): Limit {
            $nim = $request->string('nim')->toString();

            return Limit::perMinute(10)->by(Str::transliterate("{$nim}|{$request->ip()}"));
        });

        RateLimiter::for('admin-setup', function (Request $request): Limit {
            return Limit::perMinute(5)->by(Str::transliterate("admin-setup|{$request->ip()}"));
        });

        RateLimiter::for('sikompen-data', function (Request $request): Limit {
            $sessionId = $request->hasSession() ? $request->session()->getId() : 'no-session';

            return Limit::perMinute(30)->by(Str::transliterate("{$sessionId}|{$request->ip()}"));
        });
    }
}
