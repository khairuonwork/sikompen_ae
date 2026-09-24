<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\KompenResponHubDataQuery;
use App\Actions\SiAdminProxy\SiAdminProxyAccess;
use App\Http\Requests\KompenResponHubTableRequest;
use App\Http\Resources\KompenResponHubActivityLogResource;
use App\Http\Resources\KompenResponHubDetailResource;
use App\Http\Resources\KompenResponHubExportTaskResource;
use App\Http\Resources\KompenResponHubImportAuditLogResource;
use App\Http\Resources\KompenResponHubImportTaskResource;
use App\Http\Resources\KompenResponHubStudentResource;
use App\Http\Resources\KompenResponHubWarningLetterResource;
use App\Models\KompenResponHubDetail;
use App\Models\KompenResponHubExportTask;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubImportTask;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubStudent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class KompenResponHubController extends Controller
{
    public function __construct(
        private KompenResponHubDataQuery $dataQuery,
        private SiAdminProxyAccess $proxyAccess,
    ) {}

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

        if (! $isAdmin && in_array($activeTab, ['upload', 'imports', 'warnings', 'activity'], true)) {
            $activeTab = 'students';
        }

        return Inertia::render('kompen-respon-hub/index', [
            'activeTab' => $activeTab,
            'isAdmin' => $isAdmin,
            'isProxySession' => $this->proxyAccess->hasValidProxySession($request),
            'filters' => $filters,
            'filterOptions' => $this->dataQuery->filterOptions(),
            'cutoffs' => $isAdmin
                ? KompenResponHubPeriodCutoff::query()
                    ->orderByDesc('deadline_at')
                    ->get(['id', 'periode_semester', 'deadline_at', 'timezone'])
                    ->map(fn (KompenResponHubPeriodCutoff $cutoff): array => [
                        'id' => $cutoff->id,
                        'periode_semester' => $cutoff->periode_semester,
                        'deadline_at' => $cutoff->deadline_at->toIso8601String(),
                        'timezone' => $cutoff->timezone,
                    ])
                    ->all()
                : [],
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
            'exportTasks' => KompenResponHubExportTaskResource::collection(
                KompenResponHubExportTask::query()
                    ->where('request_session_id', $request->session()->getId())
                    ->where('expires_at', '>', now())
                    ->latest('id')
                    ->limit(5)
                    ->get(),
            )->resolve(),
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
            'warnings' => $isAdmin && $activeTab === 'warnings'
                ? $this->resourcePaginator(
                    $this->dataQuery->warnings($filters)->paginate($this->perPage($filters))->withQueryString(),
                    KompenResponHubWarningLetterResource::class,
                )
                : null,
            'temporaryCandidates' => $isAdmin && $activeTab === 'warnings'
                ? $this->resourcePaginator(
                    $this->dataQuery->temporaryWarningCandidates($filters)
                        ->paginate($this->perPage($filters), ['*'], 'candidate_page')
                        ->withQueryString(),
                    KompenResponHubStudentResource::class,
                )
                : null,
            'fixedCandidates' => $isAdmin && $activeTab === 'warnings'
                ? $this->resourcePaginator(
                    $this->dataQuery->fixedWarningCandidates($filters)
                        ->paginate($this->perPage($filters), ['*'], 'fixed_candidate_page')
                        ->withQueryString(),
                    KompenResponHubStudentResource::class,
                )
                : null,
            'activityLogs' => $isAdmin && $activeTab === 'activity'
                ? $this->resourcePaginator(
                    $this->dataQuery->activityLogs($filters)->paginate($this->perPage($filters))->withQueryString(),
                    KompenResponHubActivityLogResource::class,
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
     * @template TModel of KompenResponHubStudent|KompenResponHubDetail|KompenResponHubImportAuditLog|\App\Models\KompenResponHubWarningLetter|\App\Models\KompenResponHubActivityLog
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @param  class-string<KompenResponHubStudentResource|KompenResponHubDetailResource|KompenResponHubImportAuditLogResource|KompenResponHubWarningLetterResource|KompenResponHubActivityLogResource>  $resource
     * @return array<string, mixed>
     */
    private function resourcePaginator(LengthAwarePaginator $paginator, string $resource): array
    {
        return $resource::collection($paginator)->response()->getData(true);
    }
}
