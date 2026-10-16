<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateStudentPasswordRequest;
use App\Models\KompenResponHubStudentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentPasswordController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var KompenResponHubStudentAccount $student */
        $student = $request->user('student');

        return Inertia::render('auth/student-password', [
            'nim' => $student->nim,
            'requiresCurrentPassword' => ! $student->requiresPasswordChange(),
        ]);
    }

    public function update(UpdateStudentPasswordRequest $request): RedirectResponse
    {
        /** @var KompenResponHubStudentAccount $student */
        $student = $request->user('student');
        $student->update([
            'password' => $request->string('password')->toString(),
            'password_changed_at' => now(),
            'session_version' => $student->session_version + 1,
        ]);
        $request->session()->regenerate();
        $request->session()->put('student_session_version', $student->session_version);

        return to_route('student.kompen-respon.index')
            ->with('success', 'Password berhasil diperbarui. Data Kompen/Respon Anda kini dapat diakses.');
    }
}
