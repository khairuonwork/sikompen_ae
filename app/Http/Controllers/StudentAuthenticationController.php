<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\SikompenStudentAccess;
use App\Http\Requests\StoreStudentLoginRequest;
use App\Models\KompenResponHubStudentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StudentAuthenticationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/student-login');
    }

    public function store(
        StoreStudentLoginRequest $request,
        SikompenStudentAccess $studentAccess,
    ): RedirectResponse {
        $nim = $request->string('nim')->toString();
        $throttleKey = $this->throttleKey($request, $nim);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'nim' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.RateLimiter::availableIn($throttleKey).' detik.',
            ]);
        }

        if (! $studentAccess->activeStudentExists($nim)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'nim' => 'NIM atau password tidak tepat.',
            ]);
        }

        $account = KompenResponHubStudentAccount::query()
            ->where('nim', $nim)
            ->first();

        if ($account === null) {
            if (! hash_equals($nim, $request->string('password')->toString())) {
                RateLimiter::hit($throttleKey, 60);

                throw ValidationException::withMessages([
                    'nim' => 'NIM atau password tidak tepat.',
                ]);
            }

            $account = KompenResponHubStudentAccount::query()->firstOrCreate(
                ['nim' => $nim],
                ['password' => Hash::make($nim)],
            );
        }

        if (! Auth::guard('student')->attempt([
            'nim' => $nim,
            'password' => $request->string('password')->toString(),
        ])) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'nim' => 'NIM atau password tidak tepat.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $account->forceFill(['last_login_at' => now()])->save();
        $request->session()->put('student_session_version', $account->session_version);

        return $account->requiresPasswordChange()
            ? to_route('student.password.edit')
            : to_route('student.kompen-respon.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }

    private function throttleKey(Request $request, string $nim): string
    {
        return Str::transliterate("student-login|{$nim}|{$request->ip()}");
    }
}
