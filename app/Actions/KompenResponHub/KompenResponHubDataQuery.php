<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActivityLog;
use App\Models\KompenResponHubDetail;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubStudent;
use App\Models\KompenResponHubStudentProgress;
use App\Models\KompenResponHubStudentSummaryOverride;
use App\Models\KompenResponHubWarningLetter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class KompenResponHubDataQuery
{
    public const FILTER_OPTIONS_CACHE_KEY = 'sikompen:filter-options:v2';

    public const FILTER_OPTIONS_CACHE_VERSION_KEY = 'sikompen:filter-options:version';

    /** @return Builder<KompenResponHubImportAuditLog> */
    public function importAuditLogs(array $filters = []): Builder
    {
        $query = KompenResponHubImportAuditLog::query()
            ->with([
                'sourceImport' => fn (BelongsTo $importQuery): BelongsTo => $importQuery
                    ->select(['id', 'quality_report', 'student_count', 'imported_at'])
                    ->with('activeReference'),
            ])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if (filled($filters['periode_semester'] ?? null)) {
            $query->where('periode_semester', $filters['periode_semester']);
        }

        return $query;
    }

    /** @return Builder<KompenResponHubImport> */
    public function importVersions(array $filters = []): Builder
    {
        $query = KompenResponHubImport::query()
            ->with([
                'activeReference.activatedByAdmin:id,email',
                'uploadedByAdmin:id,email',
            ])
            ->orderByDesc('imported_at')
            ->orderByDesc('id');

        if (filled($filters['periode_semester'] ?? null)) {
            $query->where('periode_semester', $filters['periode_semester']);
        }

        return $query;
    }

    /** @return Builder<KompenResponHubWarningLetter> */
    public function warnings(array $filters, bool $includeCancelled = false): Builder
    {
        $query = KompenResponHubWarningLetter::query()->with('student');

        if (! $includeCancelled) {
            $query->whereIn('letter_status', [
                KompenResponHubWarningLetter::LetterStatusIssued,
            ])->where('classification', 'fixed');
            $query->where('resolution', 'outstanding');
        }

        foreach (['nim', 'kelas', 'periode_semester'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }

        if (filled($filters['tingkat'] ?? null)) {
            $query->whereHas('student', fn (Builder $studentQuery): Builder => $studentQuery->where('tingkat', $filters['tingkat']));
        }

        if (filled($filters['search'] ?? null)) {
            $query->where(function (Builder $warningQuery) use ($filters): void {
                $warningQuery->where('nama_mahasiswa', 'like', $this->containsPattern((string) $filters['search']))
                    ->orWhere('nim', 'like', $this->containsPattern((string) $filters['search']));
            });
        }

        return $query->orderByDesc('id');
    }

    /** @return Builder<KompenResponHubWarningLetter> */
    public function rolledBackWarnings(array $filters): Builder
    {
        return $this->warnings($filters, true)
            ->where('letter_status', KompenResponHubWarningLetter::LetterStatusCancelled);
    }

    /** @return Builder<KompenResponHubStudent> */
    public function warningCandidates(array $filters): Builder
    {
        $studentTable = (new KompenResponHubStudent)->getTable();
        $cutoffTable = (new KompenResponHubPeriodCutoff)->getTable();
        $warningTable = (new KompenResponHubWarningLetter)->getTable();

        return $this->students($filters)
            ->whereExists(function (\Illuminate\Database\Query\Builder $query) use ($cutoffTable, $studentTable): void {
                $query->selectRaw('1')
                    ->from($cutoffTable)
                    ->whereColumn("{$cutoffTable}.periode_semester", "{$studentTable}.periode_semester")
                    ->where('deadline_at', '<=', now());
            })
            ->whereRaw("({$this->effectiveDebtExpression($studentTable)}) > 0")
            ->whereNotExists(function (\Illuminate\Database\Query\Builder $query) use ($cutoffTable, $studentTable, $warningTable): void {
                $query->selectRaw('1')
                    ->from($warningTable)
                    ->join($cutoffTable, "{$cutoffTable}.id", '=', "{$warningTable}.cutoff_id")
                    ->whereColumn("{$warningTable}.current_student_id", "{$studentTable}.id")
                    ->whereColumn("{$cutoffTable}.periode_semester", "{$studentTable}.periode_semester")
                    ->whereIn("{$warningTable}.letter_status", [
                        KompenResponHubWarningLetter::LetterStatusDraft,
                        KompenResponHubWarningLetter::LetterStatusIssued,
                        KompenResponHubWarningLetter::LetterStatusCancelled,
                    ]);
            });
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
        $query = KompenResponHubStudent::query()->with(['progress', 'summaryOverride', 'latestWarning', 'cutoff']);

        foreach (['nim', 'kelas', 'periode_semester', 'tingkat'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }

        if (filled($filters['nama'] ?? null)) {
            $query->where('nama_mahasiswa', 'like', $this->containsPattern((string) $filters['nama']));
        }

        if (filled($filters['search'] ?? null)) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('nama_mahasiswa', 'like', $this->containsPattern((string) $filters['search']))
                    ->orWhere('nim', 'like', $this->containsPattern((string) $filters['search']));
            });
        }

        return $query
            ->orderBy('tingkat')
            ->orderBy('kelas')
            ->orderBy('nama_mahasiswa')
            ->orderBy('nim');
    }

    /** @return Builder<KompenResponHubStudent> */
    public function adminStudentSearch(string $search): Builder
    {
        return $this->students(['search' => $search])
            ->reorder()
            ->orderBy('nama_mahasiswa')
            ->orderBy('nim')
            ->orderByDesc('periode_semester');
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
                    $studentQuery->where('nama_mahasiswa', 'like', $this->containsPattern((string) $filters['nama']));
                }

                if (filled($filters['search'] ?? null)) {
                    $studentQuery->where(function (Builder $query) use ($filters): void {
                        $query->where('nama_mahasiswa', 'like', $this->containsPattern((string) $filters['search']))
                            ->orWhere('nim', 'like', $this->containsPattern((string) $filters['search']));
                    });
                }
            });
        }

        foreach (['mata_kuliah', 'nama_dosen'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, 'like', $this->containsPattern((string) $filters[$field]));
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

    private function containsPattern(string $value): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%';
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
