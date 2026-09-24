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

    public const FILTER_OPTIONS_CACHE_VERSION_KEY = 'sikompen:filter-options:version';

    /** @return Builder<KompenResponHubImportAuditLog> */
    public function importAuditLogs(array $filters = []): Builder
    {
        $query = KompenResponHubImportAuditLog::query()
            ->with('sourceImport:id,quality_report')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if (filled($filters['periode_semester'] ?? null)) {
            $query->where('periode_semester', $filters['periode_semester']);
        }

        return $query;
    }

    /** @return Builder<KompenResponHubWarningLetter> */
    public function warnings(array $filters): Builder
    {
        $query = KompenResponHubWarningLetter::query()->with('student');

        foreach (['nim', 'kelas', 'periode_semester'] as $field) {
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
        return $this->warningCandidates($filters, '>');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<KompenResponHubStudent>
     */
    public function fixedWarningCandidates(array $filters): Builder
    {
        return $this->warningCandidates($filters, '<=');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<KompenResponHubStudent>
     */
    private function warningCandidates(array $filters, string $deadlineOperator): Builder
    {
        $studentTable = (new KompenResponHubStudent)->getTable();
        $cutoffTable = (new KompenResponHubPeriodCutoff)->getTable();
        $progressTable = (new KompenResponHubStudentProgress)->getTable();
        $summaryOverrideTable = (new KompenResponHubStudentSummaryOverride)->getTable();

        $effectiveDebt = $this->effectiveDebtExpression($studentTable);

        return $this->students($filters)
            ->whereExists(function (BaseQueryBuilder $query) use ($cutoffTable, $studentTable, $deadlineOperator): void {
                $query->selectRaw('1')
                    ->from($cutoffTable)
                    ->whereColumn("{$cutoffTable}.periode_semester", "{$studentTable}.periode_semester")
                    ->where('deadline_at', $deadlineOperator, now());
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

        if (filled($filters['activity_event'] ?? null)) {
            $query->where('event_type', $filters['activity_event']);
        }

        if (filled($filters['activity_actor'] ?? null)) {
            $query->where('actor_email', $filters['activity_actor']);
        }

        return $query->orderByDesc('occurred_at')->orderByDesc('id');
    }

    /** @return array{tingkat: list<int>, kelas: list<string>, periode_semester: list<string>} */
    public function filterOptions(array $filters = []): array
    {
        $students = KompenResponHubStudent::query();

        if (filled($filters['nim'] ?? null)) {
            $students->where('nim', $filters['nim']);
        }

        $cacheVersion = Cache::get(self::FILTER_OPTIONS_CACHE_VERSION_KEY, 1);
        $filterOptions = Cache::remember(
            self::FILTER_OPTIONS_CACHE_KEY.":{$cacheVersion}:".hash('sha256', (string) ($filters['nim'] ?? 'all')),
            now()->addMinutes(30),
            fn () => [
                'tingkat' => (clone $students)
                    ->distinct()
                    ->orderBy('tingkat')
                    ->pluck('tingkat')
                    ->map(fn (int $tingkat): int => $tingkat)
                    ->values()
                    ->all(),
                'kelas' => (clone $students)
                    ->distinct()
                    ->orderBy('kelas')
                    ->pluck('kelas')
                    ->values()
                    ->all(),
                'periode_semester' => (clone $students)
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
     * @return array{total_students: int, outstanding_students: int, completed_students: int, outstanding_hours: float, warning_count: int, issued_warning_count: int, periods: list<array{periode_semester: string, deadline_at: string|null, status: string}>}
     */
    public function dashboardSummary(array $filters): array
    {
        $studentTable = (new KompenResponHubStudent)->getTable();
        $effectiveDebt = $this->effectiveDebtExpression($studentTable);
        $summary = $this->students($filters)
            ->toBase()
            ->selectRaw('COUNT(*) as total_students')
            ->selectRaw("SUM(CASE WHEN ({$effectiveDebt}) > 0 THEN 1 ELSE 0 END) as outstanding_students")
            ->selectRaw("SUM(CASE WHEN ({$effectiveDebt}) > 0 THEN ({$effectiveDebt}) ELSE 0 END) as outstanding_hours")
            ->first();

        $totalStudents = (int) ($summary->total_students ?? 0);
        $outstandingStudents = (int) ($summary->outstanding_students ?? 0);

        return [
            'total_students' => $totalStudents,
            'outstanding_students' => $outstandingStudents,
            'completed_students' => $totalStudents - $outstandingStudents,
            'outstanding_hours' => round((float) ($summary->outstanding_hours ?? 0), 2),
            'warning_count' => $this->warnings($filters)->count(),
            'issued_warning_count' => $this->warnings($filters)->where('letter_status', KompenResponHubWarningLetter::LetterStatusIssued)->count(),
            'periods' => KompenResponHubPeriodCutoff::query()
                ->when(filled($filters['periode_semester'] ?? null), fn (Builder $query): Builder => $query->where('periode_semester', $filters['periode_semester']))
                ->orderByDesc('deadline_at')
                ->get(['periode_semester', 'deadline_at'])
                ->map(fn (KompenResponHubPeriodCutoff $cutoff): array => [
                    'periode_semester' => $cutoff->periode_semester,
                    'deadline_at' => $cutoff->deadline_at?->toIso8601String(),
                    'status' => $cutoff->deadline_at->isPast() ? 'closed' : 'open',
                ])
                ->all(),
        ];
    }

    /** @return array{temporary: Builder<KompenResponHubStudent>, fixed: Builder<KompenResponHubStudent>, warnings: Builder<KompenResponHubWarningLetter>} */
    public function adminWorklist(array $filters): array
    {
        return [
            'temporary' => $this->temporaryWarningCandidates($filters)->limit(5),
            'fixed' => $this->fixedWarningCandidates($filters)->limit(5),
            'warnings' => $this->warnings($filters)
                ->whereIn('letter_status', [KompenResponHubWarningLetter::LetterStatusDraft, KompenResponHubWarningLetter::LetterStatusIssued])
                ->limit(5),
        ];
    }

    /** @return array{event_types: list<string>, actor_emails: list<string>} */
    public function activityFilterOptions(): array
    {
        return [
            'event_types' => KompenResponHubActivityLog::query()->distinct()->orderBy('event_type')->pluck('event_type')->all(),
            'actor_emails' => KompenResponHubActivityLog::query()->whereNotNull('actor_email')->distinct()->orderBy('actor_email')->pluck('actor_email')->all(),
        ];
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

    private function effectiveDebtExpression(string $studentTable): string
    {
        $progressTable = (new KompenResponHubStudentProgress)->getTable();
        $summaryOverrideTable = (new KompenResponHubStudentSummaryOverride)->getTable();

        return sprintf(
            'COALESCE((SELECT total_kompensasi_jam FROM %1$s WHERE current_student_id = %2$s.id LIMIT 1), %2$s.total_kompensasi_jam) + COALESCE((SELECT total_responsi_jam FROM %1$s WHERE current_student_id = %2$s.id LIMIT 1), %2$s.total_responsi_jam) - COALESCE((SELECT kompensasi_dikerjakan_jam FROM %3$s WHERE current_student_id = %2$s.id LIMIT 1), 0) - COALESCE((SELECT responsi_dikerjakan_jam FROM %3$s WHERE current_student_id = %2$s.id LIMIT 1), 0)',
            $summaryOverrideTable,
            $studentTable,
            $progressTable,
        );
    }
}
