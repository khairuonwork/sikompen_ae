<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Actions\SiAdminProxy\SiAdminProxyAccess;
use App\Http\Requests\KompenResponHubExportTaskAccessRequest;
use App\Http\Requests\StoreKompenResponHubExportRequest;
use App\Http\Resources\KompenResponHubExportTaskResource;
use App\Jobs\GenerateKompenResponHubExport;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubExportTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KompenResponHubExportController extends Controller
{
    public function store(
        StoreKompenResponHubExportRequest $request,
        SiAdminProxyAccess $access,
        RecordKompenResponHubActivity $activity,
    ): RedirectResponse {
        $actor = $access->hasAdminAccess($request)
            ? $access->actor($request)
            : ['id' => null];
        $validated = $request->validated();
        abort_if($validated['resource'] === 'warnings' && ! $access->hasAdminAccess($request), 403);
        $filters = collect($validated)
            ->only(['nim', 'nama', 'search', 'kelas', 'tingkat', 'periode_semester', 'mata_kuliah', 'nama_dosen'])
            ->filter(fn (mixed $value): bool => filled($value))
            ->all();
        $studentNim = $access->studentNim($request);

        if ($studentNim !== null) {
            $filters['nim'] = $studentNim;
        }

        $task = KompenResponHubExportTask::create([
            'request_session_id' => $request->session()->getId(),
            'access_token' => bin2hex(random_bytes(32)),
            'requested_by_admin_id' => $actor['id'],
            'resource' => $validated['resource'],
            'format' => $validated['format'],
            'filters' => $filters,
            'status' => KompenResponHubExportTask::StatusQueued,
            'progress' => 0,
            'progress_message' => 'Permintaan ekspor masuk ke antrean.',
            'queued_at' => now(),
            'expires_at' => now()->addHours(max(1, (int) config('kompen-respon-hub.retention.export_hours', 24))),
        ]);

        GenerateKompenResponHubExport::dispatch($task->id);

        $admin = is_int($actor['id'])
            ? KompenResponHubAdmin::query()->find($actor['id'])
            : null;
        $activity->execute(
            'export.requested',
            'export',
            (string) $task->id,
            $admin,
            $request,
            period: is_string($filters['periode_semester'] ?? null) ? $filters['periode_semester'] : null,
            metadata: $task->activityMetadata(),
        );

        return back();
    }

    public function show(
        KompenResponHubExportTaskAccessRequest $request,
        KompenResponHubExportTask $exportTask,
    ): JsonResponse {
        $this->authorizeAccess($request, $exportTask);

        return (new KompenResponHubExportTaskResource($exportTask))->response();
    }

    public function download(
        KompenResponHubExportTaskAccessRequest $request,
        KompenResponHubExportTask $exportTask,
    ): StreamedResponse {
        $this->authorizeAccess($request, $exportTask);
        abort_unless($exportTask->status === KompenResponHubExportTask::StatusCompleted, 404, 'File ekspor belum siap.');
        abort_if($exportTask->expires_at?->isPast(), 410, 'File ekspor telah kedaluwarsa. Buat permintaan ekspor baru.');
        abort_unless($exportTask->output_path !== null && Storage::disk('local')->exists($exportTask->output_path), 404, 'File ekspor tidak tersedia.');
        abort_unless($exportTask->download_filename !== null, 404, 'Nama file ekspor tidak tersedia.');

        return Storage::disk('local')->download(
            $exportTask->output_path,
            $exportTask->download_filename,
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }

    public function destroy(
        KompenResponHubExportTaskAccessRequest $request,
        KompenResponHubExportTask $exportTask,
        SiAdminProxyAccess $access,
        RecordKompenResponHubActivity $activity,
    ): RedirectResponse {
        $this->authorizeAccess($request, $exportTask);

        $wasCancelled = KompenResponHubExportTask::query()
            ->whereKey($exportTask->id)
            ->whereIn('status', [
                KompenResponHubExportTask::StatusQueued,
                KompenResponHubExportTask::StatusProcessing,
            ])
            ->update([
                'status' => KompenResponHubExportTask::StatusCancelled,
                'progress' => 100,
                'progress_message' => 'Ekspor dibatalkan oleh pengguna.',
                'error_message' => null,
                'expires_at' => now()->addHours(max(1, (int) config('kompen-respon-hub.retention.export_hours', 24))),
            ]);

        abort_unless($wasCancelled === 1, 409, 'Ekspor ini sudah selesai atau tidak dapat dibatalkan.');

        $exportTask->refresh();
        if ($exportTask->output_path !== null && str_starts_with($exportTask->output_path, 'kompen-respon-hub/exports/')) {
            Storage::disk('local')->delete($exportTask->output_path);
        }

        $actor = $access->hasAdminAccess($request)
            ? $access->actor($request)
            : ['id' => null];
        $admin = is_int($actor['id'])
            ? KompenResponHubAdmin::query()->find($actor['id'])
            : null;
        $activity->execute(
            'export.cancelled',
            'export',
            (string) $exportTask->id,
            $admin,
            $request,
            period: is_string($exportTask->filters['periode_semester'] ?? null)
                ? $exportTask->filters['periode_semester']
                : null,
            metadata: $exportTask->activityMetadata(),
        );

        return back();
    }

    public function dismiss(
        KompenResponHubExportTaskAccessRequest $request,
        KompenResponHubExportTask $exportTask,
    ): RedirectResponse {
        $this->authorizeAccess($request, $exportTask);
        abort_if($exportTask->isActive(), 409, 'Ekspor yang masih diproses tidak dapat ditutup. Gunakan Batalkan bila diperlukan.');

        $dismissedTaskIds = $request->session()->get('sikompen.dismissed_export_task_ids', []);
        $dismissedTaskIds = is_array($dismissedTaskIds) ? $dismissedTaskIds : [];
        $dismissedTaskIds = array_values(array_filter($dismissedTaskIds, static fn (mixed $taskId): bool => is_int($taskId)));
        $dismissedTaskIds[] = $exportTask->id;
        $dismissedTaskIds = array_values(array_unique($dismissedTaskIds));
        $dismissedTaskIds = array_slice($dismissedTaskIds, -50);

        $request->session()->put('sikompen.dismissed_export_task_ids', $dismissedTaskIds);

        return back();
    }

    private function authorizeAccess(KompenResponHubExportTaskAccessRequest $request, KompenResponHubExportTask $exportTask): void
    {
        abort_unless(
            hash_equals($exportTask->access_token, $request->string('token')->toString())
                && hash_equals($exportTask->request_session_id, $request->session()->getId()),
            404,
            'Permintaan ekspor tidak ditemukan.',
        );
    }
}
