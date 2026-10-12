<?php

namespace App\Actions\KompenResponHub;

use App\Actions\SiAdminProxy\SiAdminProxyAccess;
use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubActiveStudentImportSource;
use App\Models\KompenResponHubStudentAccount;
use Illuminate\Http\Request;

class SikompenStudentAccess
{
    public function __construct(private SiAdminProxyAccess $proxyAccess) {}

    public function hasStudentAccess(Request $request): bool
    {
        if (config('si-admin-proxy.enabled')) {
            return $this->proxyAccess->studentNim($request) !== null;
        }

        $student = $this->studentAccount($request);

        return $student !== null && $this->activeStudentExists($student->nim);
    }

    public function studentNim(Request $request): ?string
    {
        if (config('si-admin-proxy.enabled')) {
            return $this->proxyAccess->studentNim($request);
        }

        return $this->studentAccount($request)?->nim;
    }

    public function activeStudentExists(string $nim): bool
    {
        $students = KompenResponHubActiveStudent::query()->where('nim', $nim);

        if (! KompenResponHubActiveStudentImportSource::query()->exists()) {
            return $students->exists();
        }

        return $students->fromActiveRosterSources()->exists();
    }

    private function studentAccount(Request $request): ?KompenResponHubStudentAccount
    {
        $student = $request->user('student');

        return $student instanceof KompenResponHubStudentAccount ? $student : null;
    }
}
