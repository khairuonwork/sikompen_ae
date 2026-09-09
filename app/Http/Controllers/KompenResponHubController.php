<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\KompenResponHubDataQuery;
use App\Http\Requests\KompenResponHubTableRequest;
use App\Http\Resources\KompenResponHubDetailResource;
use App\Http\Resources\KompenResponHubImportAuditLogResource;
use App\Http\Resources\KompenResponHubImportTaskResource;
use App\Http\Resources\KompenResponHubStudentResource;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportTask;
use App\Models\KompenResponHubStudent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class KompenResponHubController extends Controller
{
    public function __construct(private KompenResponHubDataQuery $dataQuery) {}

    public function studentIndex(KompenResponHubTableRequest $request): Response
    {
        return $this->index($request, false);
    }

    public function adminIndex(KompenResponHubTableRequest $request): Response
    {
        return $this->index($request, true);
    }

    public function students(KompenResponHubTableRequest $request): JsonResponse
    {
        $filters = $request->validated();

        return KompenResponHubStudentResource::collection(
            $this->dataQuery->students($filters)->paginate($this->perPage($filters))->withQueryString(),
        )->response();
    }

    public function details(KompenResponHubTableRequest $request): JsonResponse
    {
        $filters = $request->validated();

        return KompenResponHubDetailResource::collection(
            $this->dataQuery->details($filters)->paginate($this->perPage($filters))->withQueryString(),
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
        return response()->json($this->dataQuery->filterOptions());
    }

    private function index(KompenResponHubTableRequest $request, bool $isAdmin): Response
    {
        $filters = $request->validated();
        $activeTab = $filters['tab'] ?? ($isAdmin ? 'upload' : 'students');
        $hasActiveImportTask = $isAdmin && KompenResponHubImportTask::query()
            ->whereIn('status', [
                KompenResponHubImportTask::STATUS_QUEUED,
                KompenResponHubImportTask::STATUS_PROCESSING,
            ])
            ->exists();

        if (! $isAdmin && in_array($activeTab, ['upload', 'imports'], true)) {
            $activeTab = 'students';
        }

        return Inertia::render('kompen-respon-hub/index', [
            'activeTab' => $activeTab,
            'isAdmin' => $isAdmin,
            'filters' => $filters,
            'filterOptions' => $this->dataQuery->filterOptions(),
            'activeImportTasks' => $isAdmin
                ? KompenResponHubImportTaskResource::collection(
                    KompenResponHubImportTask::query()
                        ->whereIn('status', [
                            KompenResponHubImportTask::STATUS_QUEUED,
                            KompenResponHubImportTask::STATUS_PROCESSING,
                        ])
                        ->latest('id')
                        ->limit(3)
                        ->get(),
                )->resolve()
                : [],
            'students' => $activeTab === 'students'
                ? $this->resourcePaginator(
                    $this->dataQuery->students($filters)->paginate($this->perPage($filters))->withQueryString(),
                    KompenResponHubStudentResource::class,
                )
                : null,
            'details' => $activeTab === 'details'
                ? $this->resourcePaginator(
                    $this->dataQuery->details($filters)->paginate($this->perPage($filters))->withQueryString(),
                    KompenResponHubDetailResource::class,
                )
                : null,
            'imports' => $isAdmin && $activeTab === 'imports'
                ? $this->resourcePaginator(
                    $this->dataQuery->importAuditLogs()->paginate($this->perPage($filters))->withQueryString(),
                    KompenResponHubImportAuditLogResource::class,
                )
                : null,
            'canRollbackLatestImport' => $isAdmin
                && ! $hasActiveImportTask
                && KompenResponHubImport::query()->exists(),
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function perPage(array $filters): int
    {
        return (int) ($filters['per_page'] ?? 15);
    }

    /**
     * @param  class-string<KompenResponHubStudentResource|KompenResponHubDetailResource|KompenResponHubImportAuditLogResource>  $resource
     * @return array<string, mixed>
     */
    private function resourcePaginator(LengthAwarePaginator $paginator, string $resource): array
    {
        return $resource::collection($paginator)->response()->getData(true);
    }
}
