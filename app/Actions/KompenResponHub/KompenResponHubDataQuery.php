<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubDetail;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubStudent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class KompenResponHubDataQuery
{
    public function imports(): Builder
    {
        return KompenResponHubImport::query()
            ->orderByDesc('imported_at')
            ->orderByDesc('id');
    }

    /** @return array{tingkat: Collection<int, int>, kelas: Collection<int, string>, periode_semester: Collection<int, string>} */
    public function filterOptions(): array
    {
        return [
            'tingkat' => KompenResponHubStudent::query()
                ->distinct()
                ->orderBy('tingkat')
                ->pluck('tingkat')
                ->map(fn (int $tingkat): int => $tingkat)
                ->values(),
            'kelas' => KompenResponHubStudent::query()
                ->distinct()
                ->orderBy('kelas')
                ->pluck('kelas')
                ->values(),
            'periode_semester' => KompenResponHubStudent::query()
                ->distinct()
                ->orderByDesc('periode_semester')
                ->pluck('periode_semester')
                ->values(),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function students(array $filters): Builder
    {
        $query = KompenResponHubStudent::query();

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

    /** @param array<string, mixed> $filters */
    public function details(array $filters): Builder
    {
        $query = KompenResponHubDetail::query()
            ->with('student')
            ->where(function (Builder $query): void {
                $query->where('jam_kompensasi', '>', 0)
                    ->orWhere('jam_responsi', '>', 0);
            });

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

        foreach (['mata_kuliah', 'nama_dosen'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, 'like', "%{$filters[$field]}%");
            }
        }

        return $query->orderByDesc('tanggal')->orderByDesc('id');
    }
}
