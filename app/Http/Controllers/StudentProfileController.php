<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\KompenResponHubDataQuery;
use App\Http\Resources\KompenResponHubStudentResource;
use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubStudent;
use App\Models\KompenResponHubStudentAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class StudentProfileController extends Controller
{
    public function show(Request $request, KompenResponHubDataQuery $dataQuery): Response
    {
        /** @var KompenResponHubStudentAccount $account */
        $account = $request->user('student');
        $warningPeriod = $dataQuery->selectedWarningPeriod();
        $rosterStudent = $this->activeRosterStudent($account->nim);
        $studentRecords = $warningPeriod === null
            ? collect()
            : $dataQuery->students([
                'nim' => $account->nim,
                'periode_semester' => $warningPeriod,
            ])->get();
        $studentRecord = $studentRecords->first();

        $studentName = filled($studentRecord?->nama_mahasiswa)
            ? $studentRecord->nama_mahasiswa
            : $rosterStudent?->nama_mahasiswa;

        return Inertia::render('mahasiswa/profile', [
            'profile' => [
                'nim' => $account->nim,
                'nama_mahasiswa' => $studentName,
                'warning_period' => $warningPeriod,
                'records' => KompenResponHubStudentResource::collection($studentRecords)->resolve(),
                'missing_data' => $this->missingData($studentRecords, $warningPeriod, $rosterStudent),
            ],
        ]);
    }

    private function activeRosterStudent(string $nim): ?KompenResponHubActiveStudent
    {
        $query = KompenResponHubActiveStudent::query()
            ->where('nim', $nim)
            ->orderByDesc('id');

        $query->fromActiveRosterReference();

        return $query->first();
    }

    /**
     * @param  Collection<int, KompenResponHubStudent>  $studentRecords
     * @return list<string>
     */
    private function missingData(
        Collection $studentRecords,
        ?string $warningPeriod,
        ?KompenResponHubActiveStudent $rosterStudent,
    ): array {
        $missing = [];

        if ($warningPeriod === null) {
            $missing[] = 'Admin belum memilih periode Surat Peringatan aktif.';
        }

        if ($studentRecords->isEmpty() && $warningPeriod !== null) {
            $missing[] = "Belum ada catatan Kompen dan Respon untuk NIM ini pada periode SP {$warningPeriod}.";
        }

        $student = $studentRecords->first();
        $studentName = $student?->nama_mahasiswa ?? $rosterStudent?->nama_mahasiswa;
        $studentClass = $student?->kelas ?? $rosterStudent?->kelas;

        if (blank($studentName)) {
            $missing[] = 'Nama mahasiswa belum tersedia pada roster aktif maupun catatan Kompen dan Respon.';
        }

        if (blank($studentClass)) {
            $missing[] = 'Kelas mahasiswa belum tersedia pada roster aktif maupun catatan Kompen dan Respon.';
        }

        return array_values(array_unique($missing));
    }
}
