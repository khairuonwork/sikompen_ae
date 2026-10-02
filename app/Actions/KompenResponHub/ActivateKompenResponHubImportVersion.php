<?php

namespace App\Actions\KompenResponHub;

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
        private EnsureKompenResponHubPeriodIsOpen $periodLock,
    ) {}

    /**
     * Restore a selected historical workbook as the active version for only
     * the classes contained by that workbook.
     *
     * @return array{import_id: int, periode_semester: string, original_filename: string, classes: list<string>, displaced_imports: list<array{id: int, original_filename: string}>}
     */
    public function execute(
        KompenResponHubImport $import,
        string $actorName,
        string $actorEmail,
    ): array {
        if ($this->periodLock->isClosed($import->periode_semester)) {
            throw new \LogicException('Periode telah ditutup dan versi impor tidak dapat dipulihkan.');
        }

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
            ->transaction(function () use ($actorEmail, $actorName, $classes, $import, $payload): array {
                $lockedImport = KompenResponHubImport::query()
                    ->lockForUpdate()
                    ->findOrFail($import->id);

                if ($this->periodLock->isClosed($lockedImport->periode_semester)) {
                    throw new \LogicException('Periode telah ditutup dan versi impor tidak dapat dipulihkan.');
                }

                $activeImportIdsByClass = KompenResponHubStudent::query()
                    ->where('periode_semester', $lockedImport->periode_semester)
                    ->whereIn('kelas', $classes)
                    ->lockForUpdate()
                    ->get(['kelas', 'kompen_respon_hub_import_id'])
                    ->groupBy('kelas')
                    ->map(fn ($students): int => (int) $students->first()->kompen_respon_hub_import_id)
                    ->all();

                if ($activeImportIdsByClass !== [] && collect($classes)->every(
                    fn (string $class): bool => ($activeImportIdsByClass[$class] ?? null) === $lockedImport->id,
                )) {
                    throw new \LogicException('Versi file yang dipilih sudah menjadi data aktif.');
                }

                $displacedImports = KompenResponHubImport::query()
                    ->whereKey(array_values(array_unique($activeImportIdsByClass)))
                    ->orderBy('id')
                    ->get(['id', 'original_filename'])
                    ->map(fn (KompenResponHubImport $activeImport): array => [
                        'id' => $activeImport->id,
                        'original_filename' => $activeImport->original_filename,
                    ])
                    ->all();

                $this->importer->replaceActiveData(
                    $payload,
                    $lockedImport->periode_semester,
                    array_fill_keys($classes, $lockedImport->id),
                );

                KompenResponHubImportAuditLog::create([
                    'event_type' => KompenResponHubImportAuditLog::EVENT_RESTORE,
                    'source_import_id' => $lockedImport->id,
                    'actor_name' => $actorName,
                    'actor_email' => $actorEmail,
                    'periode_semester' => $lockedImport->periode_semester,
                    'original_filename' => $lockedImport->original_filename,
                    'class_count' => $payload['preview']['class_count'],
                    'student_count' => $payload['preview']['student_count'],
                    'detail_count' => $payload['preview']['detail_count'],
                    'metadata' => [
                        'restored_classes' => $classes,
                        'displaced_imports' => $displacedImports,
                    ],
                    'occurred_at' => now(),
                ]);

                return [
                    'import_id' => $lockedImport->id,
                    'periode_semester' => $lockedImport->periode_semester,
                    'original_filename' => $lockedImport->original_filename,
                    'classes' => $classes,
                    'displaced_imports' => $displacedImports,
                ];
            });
    }
}
