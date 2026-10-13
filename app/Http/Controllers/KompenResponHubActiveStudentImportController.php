<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\ActivateKompenResponHubActiveStudentImport;
use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Actions\KompenResponHub\SikompenAdminAccess;
use App\Actions\KompenResponHub\ValidateKompenResponHubWorkbookArchive;
use App\Http\Requests\DestroyKompenResponHubActiveStudentImportRequest;
use App\Http\Requests\RestoreKompenResponHubActiveStudentImportRequest;
use App\Http\Requests\StoreKompenResponHubActiveStudentImportRequest;
use App\Http\Resources\KompenResponHubActiveStudentImportTaskResource;
use App\Jobs\ProcessKompenResponHubActiveStudentImport;
use App\Models\KompenResponHubActiveStudentImport;
use App\Models\KompenResponHubActiveStudentImportTask;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubImportAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KompenResponHubActiveStudentImportController extends Controller
{
    public function downloadTemplate(): BinaryFileResponse
    {
        $path = storage_path('app/private/kompen-respon-hub/Template_Daftar_Mahasiswa_Aktif_Sikompen.xlsx');

        abort_unless(file_exists($path), 404, 'Template daftar mahasiswa aktif tidak ditemukan.');

        return response()->download($path, 'Template_Daftar_Mahasiswa_Aktif_Sikompen.xlsx');
    }

    public function downloadUploadedWorkbook(KompenResponHubActiveStudentImport $import): StreamedResponse
    {
        abort_unless(
            str_starts_with($import->stored_path, 'kompen-respon-hub/imports/active-students/')
                && Storage::disk('local')->exists($import->stored_path),
            404,
            'File unggahan tidak tersedia.',
        );

        return Storage::disk('local')->download(
            $import->stored_path,
            $this->safeDownloadFilename($import),
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function store(
        StoreKompenResponHubActiveStudentImportRequest $request,
        SikompenAdminAccess $access,
        ValidateKompenResponHubWorkbookArchive $archiveValidator,
    ): RedirectResponse {
        $actor = $access->actor($request);
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => 'Daftar mahasiswa aktif wajib diunggah dalam format XLSX.',
            ]);
        }

        try {
            $archiveValidator->execute($file->getPathname());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        $storedPath = $file->store('kompen-respon-hub/imports/active-students');

        if ($storedPath === false) {
            throw ValidationException::withMessages([
                'file' => 'Daftar mahasiswa aktif tidak dapat disimpan. Coba unggah kembali.',
            ]);
        }

        $task = KompenResponHubActiveStudentImportTask::create([
            'uploaded_by_admin_id' => $actor['id'],
            'uploader_name' => $request->string('uploader_name')->trim()->toString(),
            'uploader_email' => $actor['email'],
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'file_hash' => hash_file('sha256', Storage::disk('local')->path($storedPath)),
            'status' => KompenResponHubActiveStudentImportTask::StatusQueued,
            'progress' => 0,
            'progress_message' => 'Daftar mahasiswa aktif diterima dan menunggu diproses.',
            'queued_at' => now(),
        ]);

        ProcessKompenResponHubActiveStudentImport::dispatch($task->id);

        return to_route('admin.kompen-respon.index', ['tab' => 'upload'])
            ->with('success', 'Daftar mahasiswa aktif diterima. Proses impor berjalan di latar belakang.');
    }

    public function showTask(KompenResponHubActiveStudentImportTask $task): JsonResponse
    {
        return (new KompenResponHubActiveStudentImportTaskResource($task))->response();
    }

    public function destroy(
        DestroyKompenResponHubActiveStudentImportRequest $request,
        SikompenAdminAccess $access,
        RecordKompenResponHubActivity $activity,
        KompenResponHubActiveStudentImport $import,
    ): RedirectResponse {
        $actor = $access->actor($request);
        $admin = $this->adminForActor($actor['id']);

        /** @var array{id: int, original_filename: string, periode_semester: string|null, class_count: int, student_count: int} $deletedImport */
        $deletedImport = DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($admin, $actor, $import): array {
                $lockedImport = KompenResponHubActiveStudentImport::query()
                    ->lockForUpdate()
                    ->findOrFail($import->id);

                abort_if(
                    $lockedImport->activeReference()->lockForUpdate()->exists(),
                    422,
                    'Daftar mahasiswa aktif yang sedang digunakan tidak dapat dihapus. Pilih file lain untuk periode terkait terlebih dahulu.',
                );

                $snapshot = [
                    'id' => $lockedImport->id,
                    'original_filename' => $lockedImport->original_filename,
                    'periode_semester' => $lockedImport->periode_semester,
                    'class_count' => $lockedImport->class_count,
                    'student_count' => $lockedImport->student_count,
                ];

                KompenResponHubImportAuditLog::create([
                    'event_type' => KompenResponHubImportAuditLog::EVENT_DELETE,
                    'import_type' => KompenResponHubImportAuditLog::ImportTypeActiveStudents,
                    'source_active_student_import_id' => $lockedImport->id,
                    'actor_name' => $admin instanceof KompenResponHubAdmin ? $admin->email : $actor['email'],
                    'actor_email' => $actor['email'],
                    'periode_semester' => $lockedImport->periode_semester,
                    'original_filename' => $lockedImport->original_filename,
                    'class_count' => $lockedImport->class_count,
                    'student_count' => $lockedImport->student_count,
                    'detail_count' => 0,
                    'metadata' => [
                        'original_filename' => $lockedImport->original_filename,
                        'moved_to_recycle_bin' => true,
                    ],
                    'occurred_at' => now(),
                ]);

                $lockedImport->delete();

                return $snapshot;
            });

        $activity->execute(
            eventType: 'active_students.deleted',
            subjectType: 'active_student_import',
            subjectReference: (string) $deletedImport['id'],
            actor: $admin,
            request: $request,
            reason: 'Versi daftar mahasiswa aktif dipindahkan ke recycle bin.',
            beforeState: [
                'original_filename' => $deletedImport['original_filename'],
                'student_count' => $deletedImport['student_count'],
                'class_count' => $deletedImport['class_count'],
            ],
            subjectName: $deletedImport['original_filename'],
            period: $deletedImport['periode_semester'],
        );

        return to_route('admin.kompen-respon.index', ['tab' => 'files'])
            ->with('success', "Versi {$deletedImport['original_filename']} dipindahkan ke recycle bin. File dapat dipulihkan sebelum masa retensi berakhir.");
    }

    public function restore(
        RestoreKompenResponHubActiveStudentImportRequest $request,
        SikompenAdminAccess $access,
        RecordKompenResponHubActivity $activity,
        string $import,
    ): RedirectResponse {
        $actor = $access->actor($request);
        $admin = $this->adminForActor($actor['id']);

        /** @var KompenResponHubActiveStudentImport $restoredImport */
        $restoredImport = DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($actor, $admin, $import): KompenResponHubActiveStudentImport {
                $trashedImport = KompenResponHubActiveStudentImport::withTrashed()
                    ->lockForUpdate()
                    ->findOrFail((int) $import);

                abort_unless($trashedImport->trashed(), 422, 'Versi roster ini tidak berada di recycle bin.');
                abort_unless(Storage::disk('local')->exists($trashedImport->stored_path), 422, 'File roster sudah tidak tersedia dan tidak dapat dipulihkan.');

                $trashedImport->restore();

                KompenResponHubImportAuditLog::create([
                    'event_type' => KompenResponHubImportAuditLog::EVENT_RESTORE,
                    'import_type' => KompenResponHubImportAuditLog::ImportTypeActiveStudents,
                    'source_active_student_import_id' => $trashedImport->id,
                    'actor_name' => $admin instanceof KompenResponHubAdmin ? $admin->email : $actor['email'],
                    'actor_email' => $actor['email'],
                    'periode_semester' => $trashedImport->periode_semester,
                    'original_filename' => $trashedImport->original_filename,
                    'class_count' => $trashedImport->class_count,
                    'student_count' => $trashedImport->student_count,
                    'detail_count' => 0,
                    'metadata' => ['restored_from_recycle_bin' => true],
                    'occurred_at' => now(),
                ]);

                return $trashedImport;
            });

        $activity->execute(
            'active_students.restored_from_recycle_bin',
            'active_student_import',
            (string) $restoredImport->id,
            $admin,
            $request,
            reason: 'Versi daftar mahasiswa aktif dipulihkan dari recycle bin.',
            subjectName: $restoredImport->original_filename,
            period: $restoredImport->periode_semester,
        );

        return to_route('admin.kompen-respon.index', ['tab' => 'files'])
            ->with('success', "Versi {$restoredImport->original_filename} dipulihkan dari recycle bin.");
    }

    public function activate(
        Request $request,
        KompenResponHubActiveStudentImport $import,
        SikompenAdminAccess $access,
        ActivateKompenResponHubActiveStudentImport $activator,
        RecordKompenResponHubActivity $activity,
    ): RedirectResponse {
        $actor = $access->actor($request);
        $admin = is_int($actor['id'])
            ? KompenResponHubAdmin::query()->find($actor['id'])
            : null;
        try {
            $result = $activator->execute(
                $import,
                $admin instanceof KompenResponHubAdmin ? $admin->email : $actor['email'],
                $actor['email'],
                $admin?->id,
            );
        } catch (\LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $activity->execute(
            eventType: 'active_students.activated',
            subjectType: 'active_student_import',
            subjectReference: (string) $result['import_id'],
            actor: $admin,
            request: $request,
            reason: "Daftar mahasiswa aktif {$result['periode_semester']} dipilih sebagai satu-satunya roster aktif periode tersebut.",
            metadata: ['classes' => $result['classes'], 'original_filename' => $result['original_filename'], 'periode_semester' => $result['periode_semester']],
            subjectName: $result['original_filename'],
            period: $result['periode_semester'],
        );

        return back()->with('success', "Daftar mahasiswa aktif {$result['periode_semester']} sekarang digunakan untuk seluruh kelas di dalam file.");
    }

    private function safeDownloadFilename(KompenResponHubActiveStudentImport $import): string
    {
        if (preg_match('/^[\pL\pN][\pL\pN ._()\-]*\.xlsx$/u', $import->original_filename) !== 1) {
            return "sikompen-daftar-mahasiswa-aktif-{$import->id}.xlsx";
        }

        return $import->original_filename;
    }

    private function adminForActor(int|string|null $actorId): ?KompenResponHubAdmin
    {
        return is_int($actorId)
            ? KompenResponHubAdmin::query()->find($actorId)
            : null;
    }
}
