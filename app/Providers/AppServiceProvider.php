<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        $this->configureSiAdminProxyUrl();
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

        RateLimiter::for('admin-setup', function (Request $request): Limit {
            return Limit::perMinute(5)->by(Str::transliterate("admin-setup|{$request->ip()}"));
        });

        RateLimiter::for('si-admin-proxy', function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('sikompen-data', function (Request $request): Limit {
            $sessionId = $request->hasSession() ? $request->session()->getId() : 'no-session';

            return Limit::perMinute(30)->by(Str::transliterate("{$sessionId}|{$request->ip()}"));
        });
    }

    /**
     * Force Laravel-generated redirects, pagination links, and asset URLs to
     * use the public Si-Admin prefix instead of the internal Docker hostname.
     */
    protected function configureSiAdminProxyUrl(): void
    {
        if (! config('si-admin-proxy.enabled')) {
            return;
        }

        $publicUrl = config('app.url');

        if (! is_string($publicUrl) || ! filter_var($publicUrl, FILTER_VALIDATE_URL)) {
            throw new \LogicException('APP_URL harus berupa URL publik saat SI_ADMIN_PROXY_ENABLED aktif.');
        }

        $scheme = parse_url($publicUrl, PHP_URL_SCHEME);

        if (! is_string($scheme)) {
            throw new \LogicException('APP_URL harus menyertakan skema HTTP atau HTTPS saat SI_ADMIN_PROXY_ENABLED aktif.');
        }

        URL::useOrigin($publicUrl);
        URL::useAssetOrigin($publicUrl);
        URL::forceScheme($scheme);
    }
}
