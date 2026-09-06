<?php

namespace App\Http\Controllers;

use App\Http\Requests\KompenResponHubTableRequest;
use App\Http\Resources\KompenResponHubDetailResource;
use App\Http\Resources\KompenResponHubStudentResource;
use App\Models\KompenResponHubDetail;
use App\Models\KompenResponHubStudent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class KompenResponHubController extends Controller
{
    public function index(KompenResponHubTableRequest $request): Response
    {
        $filters = $request->validated();
        $activeTab = $filters['tab'] ?? 'upload';

        return Inertia::render('kompen-respon-hub/index', [
            'activeTab' => $activeTab,
            'filters' => $filters,
            'filterOptions' => $this->filterOptionsData(),
            'students' => $activeTab === 'students'
                ? $this->resourcePaginator(
                    $this->studentQuery($filters)->paginate($this->perPage($filters))->withQueryString(),
                    KompenResponHubStudentResource::class,
                )
                : null,
            'details' => $activeTab === 'details'
                ? $this->resourcePaginator(
                    $this->detailQuery($filters)->paginate($this->perPage($filters))->withQueryString(),
                    KompenResponHubDetailResource::class,
                )
                : null,
        ]);
    }

    public function students(KompenResponHubTableRequest $request): JsonResponse
    {
        $filters = $request->validated();

        return KompenResponHubStudentResource::collection(
            $this->studentQuery($filters)->paginate($this->perPage($filters))->withQueryString(),
        )->response();
    }

    public function details(KompenResponHubTableRequest $request): JsonResponse
    {
        $filters = $request->validated();

        return KompenResponHubDetailResource::collection(
            $this->detailQuery($filters)->paginate($this->perPage($filters))->withQueryString(),
        )->response();
    }

    public function student(KompenResponHubStudent $student): JsonResponse
    {
        $student->load([
            'details' => fn ($query) => $query->orderByDesc('tanggal')->with('student'),
        ]);

        return response()->json([
            'data' => [
                'summary' => new KompenResponHubStudentResource($student),
                'details' => KompenResponHubDetailResource::collection($student->details),
            ],
        ]);
    }

    public function filterOptions(): JsonResponse
    {
        return response()->json($this->filterOptionsData());
    }

    /** @return array{tingkat: Collection<int, int>, kelas: Collection<int, string>, periode_semester: Collection<int, string>} */
    private function filterOptionsData(): array
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
    private function studentQuery(array $filters): Builder
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
    private function detailQuery(array $filters): Builder
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

    /** @param array<string, mixed> $filters */
    private function perPage(array $filters): int
    {
        return (int) ($filters['per_page'] ?? 15);
    }

    /**
     * @param  class-string<KompenResponHubStudentResource|KompenResponHubDetailResource>  $resource
     * @return array<string, mixed>
     */
    private function resourcePaginator(LengthAwarePaginator $paginator, string $resource): array
    {
        return $resource::collection($paginator)->response()->getData(true);
    }
}
