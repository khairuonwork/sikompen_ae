<?php

namespace App\Http\Controllers;

use App\Actions\SiAdminProxy\SiAdminProxyAccess;
use App\Http\Requests\KompenResponHubExportTaskAccessRequest;
use App\Http\Requests\StoreKompenResponHubExportRequest;
use App\Http\Resources\KompenResponHubExportTaskResource;
use App\Jobs\GenerateKompenResponHubExport;
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
            'expires_at' => now()->addDay(),
        ]);

        GenerateKompenResponHubExport::dispatch($task->id);

        return back()->with('success', 'Permintaan ekspor masuk ke antrean. File akan tersedia di halaman ini setelah selesai dibuat.');
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
