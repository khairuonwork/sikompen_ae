<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActiveImport;
use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubActiveStudentImportReference;
use App\Models\KompenResponHubStudent;
use Illuminate\Support\Collection;

class BuildKompenResponHubDataConsistencyReport
{
    /**
     * @return list<array{periode_semester: string, status: 'ready'|'roster_missing'|'mismatch', kompen_respon_students: int, roster_students: int, missing_from_roster: list<string>, missing_from_kompen_respon: list<string>}>
     */
    public function execute(): array
    {
        /** @var Collection<int, KompenResponHubActiveImport> $activeImports */
        $activeImports = KompenResponHubActiveImport::query()
            ->orderByDesc('periode_semester')
            ->get();

        $reports = [];

        foreach ($activeImports as $activeImport) {
            $period = $activeImport->periode_semester;
            $studentQuery = KompenResponHubStudent::query()
                ->where('periode_semester', $period);
            $kompenResponCount = (clone $studentQuery)->count();
            $rosterReference = KompenResponHubActiveStudentImportReference::query()
                ->where('periode_semester', $period)
                ->first();

            if ($rosterReference === null) {
                $reports[] = [
                    'periode_semester' => $period,
                    'status' => 'roster_missing',
                    'kompen_respon_students' => $kompenResponCount,
                    'roster_students' => 0,
                    'missing_from_roster' => array_values(KompenResponHubStudent::query()
                        ->where('periode_semester', $period)
                        ->orderBy('nim')
                        ->limit(5)
                        ->pluck('nim')
                        ->map(static fn (mixed $nim): string => (string) $nim)
                        ->all()),
                    'missing_from_kompen_respon' => [],
                ];

                continue;
            }

            $rosterNims = KompenResponHubActiveStudent::query()
                ->where('sikompen_active_student_import_id', $rosterReference->sikompen_active_student_import_id)
                ->pluck('nim');
            $studentNims = (clone $studentQuery)->pluck('nim');
            $missingFromRoster = $studentNims->diff($rosterNims)->values();
            $missingFromKompenRespon = $rosterNims->diff($studentNims)->values();

            if ($missingFromRoster->isEmpty() && $missingFromKompenRespon->isEmpty()) {
                $reports[] = [
                    'periode_semester' => $period,
                    'status' => 'ready',
                    'kompen_respon_students' => $kompenResponCount,
                    'roster_students' => $rosterNims->count(),
                    'missing_from_roster' => [],
                    'missing_from_kompen_respon' => [],
                ];

                continue;
            }

            $reports[] = [
                'periode_semester' => $period,
                'status' => 'mismatch',
                'kompen_respon_students' => $kompenResponCount,
                'roster_students' => $rosterNims->count(),
                'missing_from_roster' => $this->previewNims($missingFromRoster),
                'missing_from_kompen_respon' => $this->previewNims($missingFromKompenRespon),
            ];
        }

        return $reports;
    }

    /**
     * @param  Collection<int, mixed>  $nims
     * @return list<string>
     */
    private function previewNims(Collection $nims): array
    {
        return array_values($nims
            ->take(5)
            ->map(static fn (mixed $nim): string => (string) $nim)
            ->all());
    }
}
