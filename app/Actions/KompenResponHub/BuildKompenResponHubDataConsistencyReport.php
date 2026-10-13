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

        return $activeImports
            ->map(function (KompenResponHubActiveImport $activeImport): array {
                $period = $activeImport->periode_semester;
                $studentQuery = KompenResponHubStudent::query()
                    ->where('periode_semester', $period);
                $kompenResponCount = (clone $studentQuery)->count();
                $rosterReference = KompenResponHubActiveStudentImportReference::query()
                    ->where('periode_semester', $period)
                    ->first();

                if ($rosterReference === null) {
                    return $this->reportWithoutRoster($period, $kompenResponCount);
                }

                $rosterNims = KompenResponHubActiveStudent::query()
                    ->where('sikompen_active_student_import_id', $rosterReference->sikompen_active_student_import_id)
                    ->pluck('nim');
                $studentNims = (clone $studentQuery)->pluck('nim');
                $missingFromRoster = $studentNims->diff($rosterNims)->values();
                $missingFromKompenRespon = $rosterNims->diff($studentNims)->values();

                return [
                    'periode_semester' => $period,
                    'status' => $missingFromRoster->isEmpty() && $missingFromKompenRespon->isEmpty() ? 'ready' : 'mismatch',
                    'kompen_respon_students' => $kompenResponCount,
                    'roster_students' => $rosterNims->count(),
                    'missing_from_roster' => $missingFromRoster->take(5)->map(static fn (mixed $nim): string => (string) $nim)->all(),
                    'missing_from_kompen_respon' => $missingFromKompenRespon->take(5)->map(static fn (mixed $nim): string => (string) $nim)->all(),
                ];
            })
            ->all();
    }

    /**
     * @return array{periode_semester: string, status: 'roster_missing', kompen_respon_students: int, roster_students: int, missing_from_roster: list<string>, missing_from_kompen_respon: list<string>}
     */
    private function reportWithoutRoster(string $period, int $kompenResponCount): array
    {
        return [
            'periode_semester' => $period,
            'status' => 'roster_missing',
            'kompen_respon_students' => $kompenResponCount,
            'roster_students' => 0,
            'missing_from_roster' => KompenResponHubStudent::query()
                ->where('periode_semester', $period)
                ->orderBy('nim')
                ->limit(5)
                ->pluck('nim')
                ->map(static fn (mixed $nim): string => (string) $nim)
                ->all(),
            'missing_from_kompen_respon' => [],
        ];
    }
}
