<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Http\Requests\DestroyStudentPasswordResetRequest;
use App\Http\Requests\RefreshStudentPasswordResetRequest;
use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubActiveStudentImportReference;
use App\Models\KompenResponHubStudentPasswordResetRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StudentPasswordResetManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = in_array($request->integer('per_page'), [5, 15, 25, 50, 100], true)
            ? $request->integer('per_page')
            : 15;
        $resetRequests = KompenResponHubStudentPasswordResetRequest::query()
            ->whereNull('fulfilled_at')
            ->latest('requested_at')
            ->paginate($perPage, ['*'], 'student_reset_page');
        $students = $this->activeStudentsByNim(
            $resetRequests->getCollection()
                ->map(static fn (KompenResponHubStudentPasswordResetRequest $resetRequest): string => $resetRequest->nim)
                ->values()
                ->all(),
        );

        return Inertia::render('admin/student-password-resets', [
            'requests' => [
                'data' => $resetRequests->getCollection()
                    ->map(function (KompenResponHubStudentPasswordResetRequest $resetRequest) use ($students): array {
                        $student = $students->get($resetRequest->nim);

                        if (! $student instanceof KompenResponHubActiveStudent) {
                            return [
                                'id' => $resetRequest->id,
                                'nim' => $resetRequest->nim,
                                'name' => 'Mahasiswa tidak ditemukan',
                                'class' => '-',
                                'code' => $resetRequest->reset_code,
                                'requestedAt' => $resetRequest->requested_at->toIso8601String(),
                                'expiresAt' => $resetRequest->expires_at->toIso8601String(),
                                'isExpired' => $resetRequest->expires_at->isPast(),
                            ];
                        }

                        return [
                            'id' => $resetRequest->id,
                            'nim' => $resetRequest->nim,
                            'name' => $student->nama_mahasiswa,
                            'class' => $student->kelas,
                            'code' => $resetRequest->reset_code,
                            'requestedAt' => $resetRequest->requested_at->toIso8601String(),
                            'expiresAt' => $resetRequest->expires_at->toIso8601String(),
                            'isExpired' => $resetRequest->expires_at->isPast(),
                        ];
                    })
                    ->values(),
                'meta' => [
                    'current_page' => $resetRequests->currentPage(),
                    'last_page' => $resetRequests->lastPage(),
                    'per_page' => $resetRequests->perPage(),
                    'total' => $resetRequests->total(),
                    'page_name' => 'student_reset_page',
                ],
                'links' => [
                    'prev' => $resetRequests->previousPageUrl(),
                    'next' => $resetRequests->nextPageUrl(),
                ],
            ],
        ]);
    }

    public function refresh(
        RefreshStudentPasswordResetRequest $request,
        KompenResponHubStudentPasswordResetRequest $studentPasswordReset,
    ): RedirectResponse {
        DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($request, $studentPasswordReset): void {
            $resetRequest = KompenResponHubStudentPasswordResetRequest::query()
                ->lockForUpdate()
                ->findOrFail($studentPasswordReset->id);

            if ($resetRequest->fulfilled_at !== null) {
                return;
            }

            $now = now('Asia/Jakarta');
            $previousExpiry = $resetRequest->expires_at;
            $resetRequest->forceFill([
                'reset_code' => Str::upper(Str::random(8)),
                'attempts' => 0,
                'requested_at' => $now,
                'expires_at' => $now->copy()->addDay(),
            ])->save();

            app(RecordKompenResponHubActivity::class)->execute(
                'student_reset_code_refreshed',
                'student_password_reset',
                (string) $resetRequest->id,
                $request->user('admin'),
                $request,
                nim: $resetRequest->nim,
                subjectName: $resetRequest->nim,
                beforeState: ['expires_at' => $previousExpiry->toIso8601String()],
                afterState: ['expires_at' => $resetRequest->expires_at->toIso8601String()],
            );
        });

        return to_route('admin.student-password-resets.index')
            ->with('success', 'Kode reset mahasiswa telah diperbarui dan berlaku selama 24 jam.');
    }

    public function destroy(
        DestroyStudentPasswordResetRequest $request,
        KompenResponHubStudentPasswordResetRequest $studentPasswordReset,
    ): RedirectResponse {
        DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($request, $studentPasswordReset): void {
            $resetRequest = KompenResponHubStudentPasswordResetRequest::query()
                ->lockForUpdate()
                ->findOrFail($studentPasswordReset->id);

            if ($resetRequest->fulfilled_at !== null) {
                return;
            }

            app(RecordKompenResponHubActivity::class)->execute(
                'student_reset_request_cancelled',
                'student_password_reset',
                (string) $resetRequest->id,
                $request->user('admin'),
                $request,
                nim: $resetRequest->nim,
                subjectName: $resetRequest->nim,
            );

            $resetRequest->delete();
        });

        return to_route('admin.student-password-resets.index')
            ->with('success', 'Permintaan reset mahasiswa telah dihapus.');
    }

    /**
     * @param  array<int, string>  $nims
     * @return Collection<string, KompenResponHubActiveStudent>
     */
    private function activeStudentsByNim(array $nims): Collection
    {
        $studentModel = new KompenResponHubActiveStudent;
        $studentTable = $studentModel->getTable();
        $studentColumns = [
            "{$studentTable}.id",
            "{$studentTable}.nim",
            "{$studentTable}.nama_mahasiswa",
            "{$studentTable}.kelas",
        ];

        if (! KompenResponHubActiveStudentImportReference::query()->exists()) {
            return KompenResponHubActiveStudent::query()
                ->whereIn("{$studentTable}.nim", $nims)
                ->latest("{$studentTable}.id")
                ->get($studentColumns)
                ->unique('nim')
                ->keyBy('nim');
        }

        $referenceTable = (new KompenResponHubActiveStudentImportReference)->getTable();

        return KompenResponHubActiveStudent::query()
            ->join(
                $referenceTable,
                "{$referenceTable}.sikompen_active_student_import_id",
                '=',
                "{$studentTable}.sikompen_active_student_import_id",
            )
            ->whereIn("{$studentTable}.nim", $nims)
            ->latest("{$referenceTable}.activated_at")
            ->get($studentColumns)
            ->unique('nim')
            ->keyBy('nim');
    }
}
