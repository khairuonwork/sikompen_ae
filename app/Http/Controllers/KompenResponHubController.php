<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\KompenResponHubDataQuery;
use App\Actions\SiAdminProxy\SiAdminProxyAccess;
use App\Http\Requests\KompenResponHubTableRequest;
use App\Http\Requests\SearchKompenResponHubStudentRequest;
use App\Http\Resources\KompenResponHubActivityLogResource;
use App\Http\Resources\KompenResponHubDetailResource;
use App\Http\Resources\KompenResponHubExportTaskResource;
use App\Http\Resources\KompenResponHubImportAuditLogResource;
use App\Http\Resources\KompenResponHubImportTaskResource;
use App\Http\Resources\KompenResponHubImportVersionResource;
use App\Http\Resources\KompenResponHubStudentResource;
use App\Http\Resources\KompenResponHubStudentSearchResource;
use App\Http\Resources\KompenResponHubWarningLetterResource;
use App\Models\KompenResponHubActivityLog;
use App\Models\KompenResponHubDetail;
use App\Models\KompenResponHubExportTask;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubImportTask;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubStudent;
use App\Models\KompenResponHubSystemSetting;
use App\Models\KompenResponHubWarningLetter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
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
            'progress',
            'summaryOverride',
            'latestWarning',
            'cutoff',
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
            'cutoff',
            'details' => fn ($query) => $query->orderByDesc('tanggal')->with(['override', 'student']),
        ]);

        $warnings = $this->dataQuery->warnings([
            'nim' => $student->nim,
            'periode_semester' => $student->periode_semester,
            'kelas' => $student->kelas,
        ], true)->get();

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

    public function adminStudentSearch(SearchKompenResponHubStudentRequest $request): JsonResponse
    {
        return KompenResponHubStudentSearchResource::collection(
            $this->dataQuery
                ->adminStudentSearch($request->string('q')->toString())
                ->limit(10)
                ->get(),
        )->response();
    }

    public function filterOptions(KompenResponHubTableRequest $request): JsonResponse
    {
        return response()->json($this->dataQuery->filterOptions($this->filtersForRequest($request)));
    }

    private function index(KompenResponHubTableRequest $request, bool $isAdmin): Response
    {
        $filters = $this->filtersForRequest($request);
        $filterOptions = $this->dataQuery->filterOptions();
        $activeTab = $filters['tab'] ?? 'students';
        if (! $isAdmin && in_array($activeTab, ['upload', 'files', 'imports', 'warnings', 'activity'], true)) {
            $activeTab = 'students';
        }

        $cutoffs = $isAdmin
            ? KompenResponHubPeriodCutoff::query()
                ->orderByDesc('deadline_at')
                ->get(['id', 'periode_semester', 'deadline_at', 'timezone'])
            : collect();

        if ($isAdmin && $activeTab === 'warnings') {
            $filters = $this->filtersForWarningPeriod(
                $filters,
                $cutoffs,
                $filterOptions['periode_semester'],
            );
        }

        return Inertia::render('kompen-respon-hub/index', [
            'activeTab' => $activeTab,
            'isAdmin' => $isAdmin,
            'isProxySession' => $this->proxyAccess->hasValidProxySession($request),
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'warningPeriods' => $isAdmin && $activeTab === 'warnings'
                ? $filterOptions['periode_semester']
                : [],
            'importAuditPeriods' => $isAdmin && $activeTab === 'imports'
                ? $this->dataQuery->importAuditPeriods()
                : [],
            'importAuditYears' => $isAdmin && $activeTab === 'imports'
                ? $this->dataQuery->importAuditYears()
                : [],
            'activityFilterOptions' => $isAdmin && $activeTab === 'activity'
                ? $this->dataQuery->activityFilterOptions()
                : ['event_types' => [], 'actor_emails' => []],
            'cutoffs' => $isAdmin
                ? $cutoffs->map(fn (KompenResponHubPeriodCutoff $cutoff): array => [
                    'id' => $cutoff->id,
                    'periode_semester' => $cutoff->periode_semester,
                    'deadline_at' => $cutoff->deadline_at
                        ->setTimezone($cutoff->timezone)
                        ->toIso8601String(),
                    'timezone' => $cutoff->timezone,
                ])
                    ->all()
                : [],
            'managedPeriodHasActiveWarnings' => $isAdmin && $activeTab === 'warnings'
                ? KompenResponHubWarningLetter::query()
                    ->where('periode_semester', $filters['warning_period'] ?? null)
                    ->where('classification', 'fixed')
                    ->where('letter_status', KompenResponHubWarningLetter::LetterStatusIssued)
                    ->where('resolution', 'outstanding')
                    ->exists()
                : false,
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
                    ->when(
                        $this->dismissedExportTaskIds($request),
                        fn ($query, array $taskIds) => $query->whereNotIn('id', $taskIds),
                    )
                    ->latest('id')
                    ->limit(5)
                    ->get(),
            )->resolve(),
            'exportStalledAfterMinutes' => max(15, (int) config('kompen-respon-hub.queue.stalled_task_minutes', 15)),
            'activityRetentionDays' => $isAdmin
                ? KompenResponHubSystemSetting::current()->activity_log_retention_days
                : null,
            'students' => $activeTab === 'students'
                ? $this->resourcePaginator(
                    $this->dataQuery->students($filters)->paginate($this->perPage($filters))->withQueryString(),
                    KompenResponHubStudentResource::class,
                    'page',
                )
                : null,
            'details' => $activeTab === 'details'
                ? $this->resourcePaginator(
                    $this->dataQuery->details($filters)->paginate($this->perPage($filters))->withQueryString(),
                    KompenResponHubDetailResource::class,
                    'page',
                )
                : null,
            'imports' => $isAdmin && $activeTab === 'imports'
                ? $this->resourcePaginator(
                    $this->dataQuery->importAuditLogs($filters)->paginate($this->perPage($filters))->withQueryString(),
                    KompenResponHubImportAuditLogResource::class,
                    'page',
                )
                : null,
            'importVersions' => $isAdmin && $activeTab === 'files'
                ? $this->resourcePaginator(
                    $this->dataQuery->importVersions($filters)->paginate($this->perPage($filters))->withQueryString(),
                    KompenResponHubImportVersionResource::class,
                    'page',
                )
                : null,
            'activeImportVersions' => $isAdmin && $activeTab === 'files'
                ? KompenResponHubImportVersionResource::collection(
                    $this->dataQuery->activeImportVersions()->get(),
                )->resolve()
                : [],
            'warnings' => $isAdmin && $activeTab === 'warnings'
                ? $this->resourcePaginator(
                    $this->dataQuery->warnings($filters)->paginate($this->perPage($filters))->withQueryString(),
                    KompenResponHubWarningLetterResource::class,
                    'page',
                )
                : null,
            'warningCandidates' => $isAdmin && $activeTab === 'warnings'
                ? $this->resourcePaginator(
                    $this->dataQuery->warningCandidates($filters)->paginate($this->perPage($filters), ['*'], 'warning_candidate_page')->withQueryString(),
                    KompenResponHubStudentResource::class,
                    'warning_candidate_page',
                )
                : null,
            'rolledBackWarnings' => $isAdmin && $activeTab === 'warnings'
                ? $this->resourcePaginator(
                    $this->dataQuery->rolledBackWarnings($filters)->paginate($this->perPage($filters), ['*'], 'warning_history_page')->withQueryString(),
                    KompenResponHubWarningLetterResource::class,
                    'warning_history_page',
                )
                : null,
            'activityLogs' => $isAdmin && $activeTab === 'activity'
                ? $this->resourcePaginator(
                    $this->dataQuery->activityLogs($filters)->paginate($this->perPage($filters))->withQueryString(),
                    KompenResponHubActivityLogResource::class,
                    'page',
                )
                : null,
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function perPage(array $filters): int
    {
        return (int) ($filters['per_page'] ?? 15);
    }

    /** @return list<int> */
    private function dismissedExportTaskIds(KompenResponHubTableRequest $request): array
    {
        $dismissedTaskIds = $request->session()->get('sikompen.dismissed_export_task_ids', []);

        if (! is_array($dismissedTaskIds)) {
            return [];
        }

        return array_values(array_filter($dismissedTaskIds, static fn (mixed $taskId): bool => is_int($taskId)));
    }

    /** @return array<string, mixed> */
    private function filtersForRequest(KompenResponHubTableRequest $request): array
    {
        $filters = $request->validated();

        if (filled($filters['tingkat'] ?? null)) {
            $filters['tingkat'] = (int) $filters['tingkat'];
        }

        if (filled($filters['import_year'] ?? null)) {
            $filters['import_year'] = (int) $filters['import_year'];
        }

        $requestedPerPage = (int) ($filters['per_page'] ?? 15);
        $filters['per_page'] = in_array($requestedPerPage, [15, 25, 50, 100], true)
            ? $requestedPerPage
            : 15;

        $studentNim = $this->proxyAccess->studentNim($request);

        if ($studentNim !== null) {
            $filters['nim'] = $studentNim;
        }

        return $filters;
    }

    /**
     * Surat Peringatan has one managed period for finalization and a separate
     * optional inspection period for the displayed list.
     *
     * @param  array<string, mixed>  $filters
     * @param  Collection<int, KompenResponHubPeriodCutoff>  $cutoffs
     * @param  list<string>  $warningPeriods
     * @return array<string, mixed>
     */
    private function filtersForWarningPeriod(array $filters, Collection $cutoffs, array $warningPeriods): array
    {
        if ($warningPeriods === []) {
            unset($filters['periode_semester']);
            unset($filters['warning_period']);
            unset($filters['list_period']);

            return $filters;
        }

        $requestedWarningPeriod = $filters['warning_period'] ?? $filters['periode_semester'] ?? null;
        $hasRequestedWarningPeriod = filled($requestedWarningPeriod)
            && in_array($requestedWarningPeriod, $warningPeriods, true);

        $firstCutoff = $cutoffs->first();
        $warningPeriod = $hasRequestedWarningPeriod
            ? $requestedWarningPeriod
            : ($firstCutoff instanceof KompenResponHubPeriodCutoff
                ? $firstCutoff->periode_semester
                : $warningPeriods[0]);
        $requestedListPeriod = $filters['list_period'] ?? $warningPeriod;
        $hasRequestedListPeriod = filled($requestedListPeriod)
            && in_array($requestedListPeriod, $warningPeriods, true);

        $filters['warning_period'] = $warningPeriod;
        $filters['list_period'] = $hasRequestedListPeriod
            ? $requestedListPeriod
            : $warningPeriod;
        $filters['periode_semester'] = $filters['list_period'];

        if ($filters['list_period'] === $filters['warning_period']) {
            unset($filters['list_period']);
        }

        return $filters;
    }

    /**
     * @template TModel of KompenResponHubStudent|KompenResponHubDetail|KompenResponHubImportAuditLog|\App\Models\KompenResponHubImport|\App\Models\KompenResponHubWarningLetter|\App\Models\KompenResponHubActivityLog
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @param  class-string<KompenResponHubStudentResource|KompenResponHubDetailResource|KompenResponHubImportAuditLogResource|KompenResponHubImportVersionResource|KompenResponHubWarningLetterResource|KompenResponHubActivityLogResource>  $resource
     * @return array<string, mixed>
     */
    private function resourcePaginator(LengthAwarePaginator $paginator, string $resource, string $pageName): array
    {
        $pagination = $resource::collection($paginator)->response()->getData(true);
        $pagination['meta']['page_name'] = $pageName;

        return $pagination;
    }
}
