<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdminLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuthenticationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/admin-login');
    }

    public function store(StoreAdminLoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.RateLimiter::availableIn($throttleKey).' detik.',
            ]);
        }

        if (! Auth::guard('admin')->attempt($credentials)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'Email atau password admin tidak tepat.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return to_route('admin.kompen-respon.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }

    private function throttleKey(Request $request): string
    {
        $email = Str::lower($request->string('email')->toString());

        return Str::transliterate("admin-login|{$email}|{$request->ip()}");
    }
}
