<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActiveImport;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubStudent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ActivateKompenResponHubImportVersion
{
    public function __construct(
        private ParseKompenResponHubWorkbook $parser,
        private ImportKompenResponHubWorkbook $importer,
    ) {}

    /**
     * Activate one workbook as the sole active source for its period.
     *
     * @return array{import_id: int, periode_semester: string, original_filename: string, classes: list<string>, previous_import: array{id: int, original_filename: string}|null}
     */
    public function execute(
        KompenResponHubImport $import,
        string $actorName,
        string $actorEmail,
        ?int $actorAdminId = null,
    ): array {
        if (! str_starts_with($import->stored_path, 'kompen-respon-hub/imports/') || ! Storage::disk('local')->exists($import->stored_path)) {
            throw new \LogicException('File workbook versi yang dipilih tidak tersedia.');
        }

        $payload = $this->parser->execute(Storage::disk('local')->path($import->stored_path));

        if ($payload['errors'] !== []) {
            throw new \LogicException('File workbook versi yang dipilih tidak lagi valid untuk dipulihkan.');
        }

        if ($payload['preview']['periode_semester'] !== $import->periode_semester) {
            throw new \LogicException('File workbook versi yang dipilih memiliki periode yang tidak sesuai.');
        }

        $classes = $payload['preview']['classes'];

        if ($classes === []) {
            throw new \LogicException('File workbook versi yang dipilih tidak memuat kelas untuk dipulihkan.');
        }

        return DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($actorAdminId, $actorEmail, $actorName, $classes, $import, $payload): array {
                $lockedImport = KompenResponHubImport::query()
                    ->lockForUpdate()
                    ->findOrFail($import->id);

                KompenResponHubImport::query()
                    ->where('periode_semester', $lockedImport->periode_semester)
                    ->lockForUpdate()
                    ->get(['id']);

                $activeImport = KompenResponHubActiveImport::query()
                    ->where('periode_semester', $lockedImport->periode_semester)
                    ->lockForUpdate()
                    ->with('importBatch:id,original_filename')
                    ->first();

                if ($activeImport?->kompen_respon_hub_import_id === $lockedImport->id) {
                    throw new \LogicException('Versi file yang dipilih sudah menjadi data aktif.');
                }

                $previousImport = $activeImport?->importBatch === null ? null : [
                    'id' => $activeImport->importBatch->id,
                    'original_filename' => $activeImport->importBatch->original_filename,
                ];

                KompenResponHubStudent::query()
                    ->where('periode_semester', $lockedImport->periode_semester)
                    ->delete();

                $this->importer->replaceActiveData(
                    $payload,
                    $lockedImport->periode_semester,
                    array_fill_keys($classes, $lockedImport->id),
                    deleteCurrentData: false,
                );

                KompenResponHubActiveImport::query()->updateOrCreate(
                    ['periode_semester' => $lockedImport->periode_semester],
                    [
                        'kompen_respon_hub_import_id' => $lockedImport->id,
                        'activated_by_admin_id' => $actorAdminId,
                        'activated_at' => now(),
                    ],
                );

                KompenResponHubImportAuditLog::create([
                    'event_type' => KompenResponHubImportAuditLog::EVENT_ACTIVATE,
                    'source_import_id' => $lockedImport->id,
                    'actor_name' => $actorName,
                    'actor_email' => $actorEmail,
                    'periode_semester' => $lockedImport->periode_semester,
                    'original_filename' => $lockedImport->original_filename,
                    'class_count' => $payload['preview']['class_count'],
                    'student_count' => $payload['preview']['student_count'],
                    'detail_count' => $payload['preview']['detail_count'],
                    'metadata' => [
                        'activated_classes' => $classes,
                        'previous_import' => $previousImport,
                    ],
                    'occurred_at' => now(),
                ]);

                return [
                    'import_id' => $lockedImport->id,
                    'periode_semester' => $lockedImport->periode_semester,
                    'original_filename' => $lockedImport->original_filename,
                    'classes' => $classes,
                    'previous_import' => $previousImport,
                ];
            });
    }
}
