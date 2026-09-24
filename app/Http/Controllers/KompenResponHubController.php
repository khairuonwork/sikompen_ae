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
use App\Models\KompenResponHubActivityLog;
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
        $filters = $this->filtersForRequest($request);

        return KompenResponHubStudentResource::collection(
            $this->dataQuery->students($filters)->paginate($this->perPage($filters))->withQueryString(),
        )->response();
    }

    public function details(KompenResponHubTableRequest $request): JsonResponse
    {
        $filters = $this->filtersForRequest($request);

        return KompenResponHubDetailResource::collection(
            $this->dataQuery->details($filters)->paginate($this->perPage($filters))->withQueryString(),
        )->response();
    }

    public function student(KompenResponHubStudent $student): JsonResponse
    {
        $studentNim = $this->proxyAccess->studentNim(request());
        abort_if($studentNim !== null && $student->nim !== $studentNim, 404);

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

    public function adminStudentOverview(KompenResponHubStudent $student): JsonResponse
    {
        $student->load([
            'importBatch:id,original_filename,imported_at',
            'progress',
            'summaryOverride',
            'latestWarning',
            'details' => fn ($query) => $query->orderByDesc('tanggal')->with(['override', 'student']),
        ]);

        $warnings = $this->dataQuery->warnings([
            'nim' => $student->nim,
            'periode_semester' => $student->periode_semester,
            'kelas' => $student->kelas,
        ])->get();

        $activities = KompenResponHubActivityLog::query()
            ->where('nim', $student->nim)
            ->where('periode_semester', $student->periode_semester)
            ->where('kelas', $student->kelas)
            ->latest('occurred_at')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => [
                'summary' => new KompenResponHubStudentResource($student),
                'source' => [
                    'import_filename' => $student->importBatch?->original_filename,
                    'imported_at' => $student->importBatch?->imported_at?->toIso8601String(),
                    'total_kompensasi_jam' => $student->total_kompensasi_jam,
                    'total_responsi_jam' => $student->total_responsi_jam,
                    'sisa_hutang_jam' => $student->sisa_hutang_jam,
                ],
                'details' => KompenResponHubDetailResource::collection($student->details),
                'warnings' => KompenResponHubWarningLetterResource::collection($warnings),
                'activities' => KompenResponHubActivityLogResource::collection($activities),
            ],
        ]);
    }

    public function filterOptions(KompenResponHubTableRequest $request): JsonResponse
    {
        return response()->json($this->dataQuery->filterOptions($this->filtersForRequest($request)));
    }

    private function index(KompenResponHubTableRequest $request, bool $isAdmin): Response
    {
        $filters = $this->filtersForRequest($request);
        $activeTab = $filters['tab'] ?? ($isAdmin ? 'dashboard' : 'students');
        $hasActiveImportTask = $isAdmin && KompenResponHubImportTask::query()
            ->whereIn('status', [
                KompenResponHubImportTask::STATUS_QUEUED,
                KompenResponHubImportTask::STATUS_PROCESSING,
            ])
            ->exists();

        if (! $isAdmin && in_array($activeTab, ['dashboard', 'upload', 'imports', 'warnings', 'activity'], true)) {
            $activeTab = 'students';
        }

        return Inertia::render('kompen-respon-hub/index', [
            'activeTab' => $activeTab,
            'isAdmin' => $isAdmin,
            'isProxySession' => $this->proxyAccess->hasValidProxySession($request),
            'filters' => $filters,
            'filterOptions' => $this->dataQuery->filterOptions(),
            'activityFilterOptions' => $isAdmin && $activeTab === 'activity'
                ? $this->dataQuery->activityFilterOptions()
                : ['event_types' => [], 'actor_emails' => []],
            'dashboard' => $isAdmin && $activeTab === 'dashboard'
                ? $this->dashboardData($filters)
                : null,
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
                    $this->dataQuery->importAuditLogs($filters)->paginate($this->perPage($filters))->withQueryString(),
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

    /** @return array<string, mixed> */
    private function filtersForRequest(KompenResponHubTableRequest $request): array
    {
        $filters = $request->validated();
        $studentNim = $this->proxyAccess->studentNim($request);

        if ($studentNim !== null) {
            $filters['nim'] = $studentNim;
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function dashboardData(array $filters): array
    {
        $worklist = $this->dataQuery->adminWorklist($filters);

        return [
            'summary' => $this->dataQuery->dashboardSummary($filters),
            'worklist' => [
                'temporary_candidates' => KompenResponHubStudentResource::collection($worklist['temporary']->get())->resolve(),
                'fixed_candidates' => KompenResponHubStudentResource::collection($worklist['fixed']->get())->resolve(),
                'warnings_to_follow_up' => KompenResponHubWarningLetterResource::collection($worklist['warnings']->get())->resolve(),
            ],
        ];
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
