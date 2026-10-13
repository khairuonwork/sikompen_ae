<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubActiveStudentImportReference;
use App\Models\KompenResponHubStudentAccount;
use Illuminate\Http\Request;

class SikompenStudentAccess
{
    public function hasStudentAccess(Request $request): bool
    {
        $student = $this->studentAccount($request);

        return $student !== null && $this->activeStudentExists($student->nim);
    }

    public function studentNim(Request $request): ?string
    {
        return $this->studentAccount($request)?->nim;
    }

    public function activeStudentExists(string $nim): bool
    {
        $students = KompenResponHubActiveStudent::query()->where('nim', $nim);

        if (! KompenResponHubActiveStudentImportReference::query()->exists()) {
            return $students->exists();
        }

        return $students->fromActiveRosterReference()->exists();
    }

    private function studentAccount(Request $request): ?KompenResponHubStudentAccount
    {
        $student = $request->user('student');

        return $student instanceof KompenResponHubStudentAccount ? $student : null;
    }
}
