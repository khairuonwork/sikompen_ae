<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\KompenResponHubDataQuery;
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
    public function destroy(): RedirectResponse
    {
        /** @var KompenResponHubAdmin $admin */
        $admin = request()->user('admin');

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

        /** @var array{stored_path: string, original_filename: string}|null $rollback */
        $rollback = DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($admin): ?array {
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
                    'actor_email' => $admin->email,
                    'periode_semester' => $latestImport->periode_semester,
                    'original_filename' => $latestImport->original_filename,
                    'class_count' => $latestImport->class_count,
                    'student_count' => $latestImport->student_count,
                    'detail_count' => $latestImport->detail_count,
                    'occurred_at' => now(),
                ]);

                $latestImport->delete();

                return [
                    'stored_path' => $latestImport->stored_path,
                    'original_filename' => $latestImport->original_filename,
                ];
            });

        if ($rollback === null) {
            return to_route('admin.kompen-respon.index', ['tab' => 'imports'])
                ->with('error', 'Belum ada unggahan yang dapat dihapus.');
        }

        Cache::forget(KompenResponHubDataQuery::FILTER_OPTIONS_CACHE_KEY);
        Storage::disk('local')->delete($rollback['stored_path']);

        return to_route('admin.kompen-respon.index', ['tab' => 'imports'])
            ->with('success', "Unggahan terakhir {$rollback['original_filename']} beserta data terkait telah dihapus.");
    }
}
