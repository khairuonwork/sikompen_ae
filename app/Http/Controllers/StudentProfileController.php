<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\KompenResponHubDataQuery;
use App\Http\Resources\KompenResponHubStudentResource;
use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubActiveStudentImportSource;
use App\Models\KompenResponHubPeriodCutoff;
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
        $warningPeriod = $this->activeWarningPeriod();
        $studentRecords = $warningPeriod === null
            ? collect()
            : $dataQuery->students([
                'nim' => $account->nim,
                'periode_semester' => $warningPeriod,
            ])->get();
        $studentRecord = $studentRecords->first();
        $studentName = null;

        if ($studentRecord !== null) {
            $studentName = $studentRecord->nama_mahasiswa;
        } else {
            $studentName = $this->activeRosterStudentName($account->nim);
        }

        return Inertia::render('mahasiswa/profile', [
            'profile' => [
                'nim' => $account->nim,
                'nama_mahasiswa' => $studentName,
                'warning_period' => $warningPeriod,
                'records' => KompenResponHubStudentResource::collection($studentRecords)->resolve(),
                'missing_data' => $this->missingData($studentRecords, $warningPeriod),
            ],
        ]);
    }

    private function activeWarningPeriod(): ?string
    {
        return KompenResponHubPeriodCutoff::query()
            ->orderByDesc('deadline_at')
            ->value('periode_semester');
    }

    private function activeRosterStudentName(string $nim): ?string
    {
        $query = KompenResponHubActiveStudent::query()
            ->where('nim', $nim)
            ->orderByDesc('id');

        if (KompenResponHubActiveStudentImportSource::query()->exists()) {
            $query->fromActiveRosterSources();
        }

        return $query->value('nama_mahasiswa');
    }

    /**
     * @param  Collection<int, KompenResponHubStudent>  $studentRecords
     * @return list<string>
     */
    private function missingData(Collection $studentRecords, ?string $warningPeriod): array
    {
        $missing = [];

        if ($warningPeriod === null) {
            $missing[] = 'Periode Surat Peringatan aktif belum ditetapkan.';
        }

        if ($studentRecords->isEmpty()) {
            $periodLabel = $warningPeriod ?? 'periode Surat Peringatan aktif';
            $missing[] = "Data Kompen dan Respon belum tersedia untuk {$periodLabel}.";
        }

        foreach ($studentRecords as $student) {
            if (blank($student->nama_mahasiswa)) {
                $missing[] = "Nama mahasiswa belum tersedia pada data Kompen dan Respon periode {$student->periode_semester}.";
            }

            if (blank($student->kelas)) {
                $missing[] = "Kelas belum tersedia pada data Kompen dan Respon periode {$student->periode_semester}.";
            }
        }

        return array_values(array_unique($missing));
    }
}
