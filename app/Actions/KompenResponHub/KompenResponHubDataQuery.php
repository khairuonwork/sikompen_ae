<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActivityLog;
use App\Models\KompenResponHubDetail;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubStudent;
use App\Models\KompenResponHubStudentProgress;
use App\Models\KompenResponHubStudentSummaryOverride;
use App\Models\KompenResponHubWarningLetter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Support\Facades\Cache;

class KompenResponHubDataQuery
{
    public const FILTER_OPTIONS_CACHE_KEY = 'sikompen:filter-options:v2';

    /** @return Builder<KompenResponHubImportAuditLog> */
    public function importAuditLogs(): Builder
    {
        return KompenResponHubImportAuditLog::query()
            ->with('sourceImport:id')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    /** @return Builder<KompenResponHubWarningLetter> */
    public function warnings(array $filters): Builder
    {
        $query = KompenResponHubWarningLetter::query()->with('student');

        foreach (['kelas', 'periode_semester'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }

        if (filled($filters['search'] ?? null)) {
            $query->where(function (Builder $warningQuery) use ($filters): void {
                $warningQuery->where('nama_mahasiswa', 'like', "%{$filters['search']}%")
                    ->orWhere('nim', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderByDesc('id');
    }

    /**
     * Return students with remaining debt while their applicable period is
     * still open. These records are candidates only: creating a draft SP-1
     * remains an explicit administrative action.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<KompenResponHubStudent>
     */
    public function temporaryWarningCandidates(array $filters): Builder
    {
        $studentTable = (new KompenResponHubStudent)->getTable();
        $cutoffTable = (new KompenResponHubPeriodCutoff)->getTable();
        $progressTable = (new KompenResponHubStudentProgress)->getTable();
        $summaryOverrideTable = (new KompenResponHubStudentSummaryOverride)->getTable();

        $effectiveDebt = sprintf(
            'COALESCE((SELECT total_kompensasi_jam FROM %1$s WHERE current_student_id = %2$s.id LIMIT 1), %2$s.total_kompensasi_jam) + COALESCE((SELECT total_responsi_jam FROM %1$s WHERE current_student_id = %2$s.id LIMIT 1), %2$s.total_responsi_jam) - COALESCE((SELECT kompensasi_dikerjakan_jam FROM %3$s WHERE current_student_id = %2$s.id LIMIT 1), 0) - COALESCE((SELECT responsi_dikerjakan_jam FROM %3$s WHERE current_student_id = %2$s.id LIMIT 1), 0)',
            $summaryOverrideTable,
            $studentTable,
            $progressTable,
        );

        return $this->students($filters)
            ->whereExists(function (BaseQueryBuilder $query) use ($cutoffTable, $studentTable): void {
                $query->selectRaw('1')
                    ->from($cutoffTable)
                    ->whereColumn("{$cutoffTable}.periode_semester", "{$studentTable}.periode_semester")
                    ->where('deadline_at', '>', now());
            })
            ->whereRaw("({$effectiveDebt}) > 0");
    }

    /** @return Builder<KompenResponHubActivityLog> */
    public function activityLogs(array $filters): Builder
    {
        $query = KompenResponHubActivityLog::query();

        if (filled($filters['periode_semester'] ?? null)) {
            $query->where('periode_semester', $filters['periode_semester']);
        }

        return $query->orderByDesc('occurred_at')->orderByDesc('id');
    }

    /** @return array{tingkat: list<int>, kelas: list<string>, periode_semester: list<string>} */
    public function filterOptions(): array
    {
        $filterOptions = Cache::remember(
            self::FILTER_OPTIONS_CACHE_KEY,
            now()->addMinutes(30),
            fn (): array => [
                'tingkat' => KompenResponHubStudent::query()
                    ->distinct()
                    ->orderBy('tingkat')
                    ->pluck('tingkat')
                    ->map(fn (int $tingkat): int => $tingkat)
                    ->values()
                    ->all(),
                'kelas' => KompenResponHubStudent::query()
                    ->distinct()
                    ->orderBy('kelas')
                    ->pluck('kelas')
                    ->values()
                    ->all(),
                'periode_semester' => KompenResponHubStudent::query()
                    ->distinct()
                    ->orderByDesc('periode_semester')
                    ->pluck('periode_semester')
                    ->values()
                    ->all(),
            ],
        );

        return $filterOptions;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<KompenResponHubStudent>
     */
    public function students(array $filters): Builder
    {
        $query = KompenResponHubStudent::query()->with(['progress', 'summaryOverride', 'latestWarning']);

        foreach (['nim', 'kelas', 'periode_semester', 'tingkat'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }

        if (filled($filters['nama'] ?? null)) {
            $query->where('nama_mahasiswa', 'like', "%{$filters['nama']}%");
        }

        if (filled($filters['search'] ?? null)) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('nama_mahasiswa', 'like', "%{$filters['search']}%")
                    ->orWhere('nim', 'like', "%{$filters['search']}%");
            });
        }

        return $query
            ->orderBy('tingkat')
            ->orderBy('kelas')
            ->orderBy('nama_mahasiswa')
            ->orderBy('nim');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<KompenResponHubDetail>
     */
    public function details(array $filters): Builder
    {
        $query = KompenResponHubDetail::query()
            ->with(['student.progress', 'student.summaryOverride', 'override'])
            ->where(function (Builder $query): void {
                $query->where('jam_kompensasi', '>', 0)
                    ->orWhere('jam_responsi', '>', 0);
            });

        if ($this->hasStudentFilters($filters)) {
            $query->whereHas('student', function (Builder $studentQuery) use ($filters): void {
                foreach (['nim', 'kelas', 'periode_semester', 'tingkat'] as $field) {
                    if (filled($filters[$field] ?? null)) {
                        $studentQuery->where($field, $filters[$field]);
                    }
                }

                if (filled($filters['nama'] ?? null)) {
                    $studentQuery->where('nama_mahasiswa', 'like', "%{$filters['nama']}%");
                }

                if (filled($filters['search'] ?? null)) {
                    $studentQuery->where(function (Builder $query) use ($filters): void {
                        $query->where('nama_mahasiswa', 'like', "%{$filters['search']}%")
                            ->orWhere('nim', 'like', "%{$filters['search']}%");
                    });
                }
            });
        }

        foreach (['mata_kuliah', 'nama_dosen'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, 'like', "%{$filters[$field]}%");
            }
        }

        return $query->orderByDesc('tanggal')->orderByDesc('id');
    }

    /** @param array<string, mixed> $filters */
    private function hasStudentFilters(array $filters): bool
    {
        foreach (['nim', 'nama', 'search', 'kelas', 'periode_semester', 'tingkat'] as $field) {
            if (filled($filters[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
