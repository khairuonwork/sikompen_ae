<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\KompenResponHubDataQuery;
use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Actions\SiAdminProxy\SiAdminProxyAccess;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubImportTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class KompenResponHubImportRollbackController extends Controller
{
    public function destroy(
        SiAdminProxyAccess $access,
        RecordKompenResponHubActivity $activity,
    ): RedirectResponse {
        $actor = $access->actor(request());

        $hasActiveImportTask = KompenResponHubImportTask::query()
            ->whereIn('status', [
                KompenResponHubImportTask::STATUS_QUEUED,
                KompenResponHubImportTask::STATUS_PROCESSING,
            ])
            ->exists();

        if ($hasActiveImportTask) {
            return to_route('admin.kompen-respon.index', ['tab' => 'imports'])
                ->with('error', 'Tunggu proses upload yang sedang berjalan selesai sebelum menghapus unggahan terakhir.');
        }

        /** @var array{import_id: int, stored_path: string, original_filename: string, periode_semester: string, class_count: int, student_count: int, detail_count: int}|null $rollback */
        $rollback = DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($actor): ?array {
                $latestImport = KompenResponHubImport::query()
                    ->lockForUpdate()
                    ->latest('imported_at')
                    ->latest('id')
                    ->first();

                if ($latestImport === null) {
                    return null;
                }

                KompenResponHubImportAuditLog::create([
                    'event_type' => KompenResponHubImportAuditLog::EVENT_ROLLBACK,
                    'source_import_id' => $latestImport->id,
                    'actor_email' => $actor['email'],
                    'periode_semester' => $latestImport->periode_semester,
                    'original_filename' => $latestImport->original_filename,
                    'class_count' => $latestImport->class_count,
                    'student_count' => $latestImport->student_count,
                    'detail_count' => $latestImport->detail_count,
                    'occurred_at' => now(),
                ]);

                $latestImport->delete();

                return [
                    'import_id' => $latestImport->id,
                    'stored_path' => $latestImport->stored_path,
                    'original_filename' => $latestImport->original_filename,
                    'periode_semester' => $latestImport->periode_semester,
                    'class_count' => $latestImport->class_count,
                    'student_count' => $latestImport->student_count,
                    'detail_count' => $latestImport->detail_count,
                ];
            });

        if ($rollback === null) {
            return to_route('admin.kompen-respon.index', ['tab' => 'imports'])
                ->with('error', 'Belum ada unggahan yang dapat dihapus.');
        }

        Cache::forever(
            KompenResponHubDataQuery::FILTER_OPTIONS_CACHE_VERSION_KEY,
            (int) Cache::get(KompenResponHubDataQuery::FILTER_OPTIONS_CACHE_VERSION_KEY, 1) + 1,
        );
        Storage::disk('local')->delete($rollback['stored_path']);
        $admin = is_int($actor['id'])
            ? KompenResponHubAdmin::query()->find($actor['id'])
            : null;

        $activity->execute(
            'import.rolled_back',
            'import',
            (string) $rollback['import_id'],
            $admin,
            request(),
            null,
            $rollback['periode_semester'],
            null,
            'Unggahan terakhir beserta data terkait dihapus.',
            [
                'class_count' => $rollback['class_count'],
                'student_count' => $rollback['student_count'],
                'detail_count' => $rollback['detail_count'],
                'original_filename' => $rollback['original_filename'],
            ],
        );

        return to_route('admin.kompen-respon.index', ['tab' => 'imports'])
            ->with('success', "Unggahan terakhir {$rollback['original_filename']} beserta data terkait telah dihapus.");
    }
}
