<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdminLoginRequest;
use App\Models\KompenResponHubAdmin;
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
        $credentials = $request->safe()->only(['username', 'password']);
        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'username' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.RateLimiter::availableIn($throttleKey).' detik.',
            ]);
        }

        if (! Auth::guard('admin')->attempt($credentials)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'username' => 'Username atau password admin tidak tepat.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        /** @var KompenResponHubAdmin $admin */
        $admin = Auth::guard('admin')->user();
        $request->session()->put('admin_session_version', $admin->session_version);

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
        $username = Str::lower($request->string('username')->toString());

        return Str::transliterate("admin-login|{$username}|{$request->ip()}");
    }
}
