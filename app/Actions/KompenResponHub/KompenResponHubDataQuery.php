<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActivityLog;
use App\Models\KompenResponHubDetail;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubStudent;
use App\Models\KompenResponHubWarningLetter;
use Illuminate\Database\Eloquent\Builder;
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
