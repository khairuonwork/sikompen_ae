<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubStudent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RestoreKompenResponHubImportVersion
{
    public function __construct(
        private ParseKompenResponHubWorkbook $parser,
        private ImportKompenResponHubWorkbook $importer,
        private EnsureKompenResponHubPeriodIsOpen $periodLock,
    ) {}

    /**
     * Restore the live classes from the workbook versions that the target
     * import replaced. The old import records and XLSX files remain intact so
     * the audit trail stays downloadable and repeatable.
     *
     * @return array{rolled_back_import_id: int, periode_semester: string, restored_imports: list<array{id: int, original_filename: string}>, restored: bool}
     */
    public function execute(
        KompenResponHubImport $targetImport,
        string $actorName,
        string $actorEmail,
    ): array {
        if ($this->periodLock->isClosed($targetImport->periode_semester)) {
            throw new \LogicException('Periode telah ditutup dan versi impor tidak dapat diubah.');
        }

        $activeClasses = KompenResponHubStudent::query()
            ->where('kompen_respon_hub_import_id', $targetImport->id)
            ->distinct()
            ->orderBy('kelas')
            ->pluck('kelas')
            ->all();

        if ($activeClasses === []) {
            throw new \LogicException('Versi impor ini sudah tidak menjadi data aktif.');
        }

        $previousImportsByClass = $this->previousImportsByClass($targetImport, $activeClasses);
        $payload = $previousImportsByClass === []
            ? null
            : $this->restorePayload($targetImport, $previousImportsByClass);

        return DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($targetImport, $actorName, $actorEmail, $activeClasses, $previousImportsByClass, $payload): array {
                $lockedTarget = KompenResponHubImport::query()
                    ->lockForUpdate()
                    ->findOrFail($targetImport->id);

                $liveClasses = KompenResponHubStudent::query()
                    ->where('kompen_respon_hub_import_id', $lockedTarget->id)
                    ->distinct()
                    ->orderBy('kelas')
                    ->pluck('kelas')
                    ->all();

                if ($liveClasses !== $activeClasses) {
                    throw new \LogicException('Data aktif berubah saat rollback diproses. Muat ulang halaman lalu coba kembali.');
                }

                if ($payload === null) {
                    KompenResponHubStudent::query()
                        ->where('kompen_respon_hub_import_id', $lockedTarget->id)
                        ->delete();
                } else {
                    KompenResponHubStudent::query()
                        ->where('kompen_respon_hub_import_id', $lockedTarget->id)
                        ->delete();

                    $this->importer->replaceActiveData(
                        $payload,
                        $lockedTarget->periode_semester,
                        $previousImportsByClass,
                        deleteCurrentData: false,
                    );
                }

                $restoredImports = KompenResponHubImport::query()
                    ->whereKey(array_values(array_unique($previousImportsByClass)))
                    ->orderBy('id')
                    ->get(['id', 'original_filename'])
                    ->map(fn (KompenResponHubImport $import): array => [
                        'id' => $import->id,
                        'original_filename' => $import->original_filename,
                    ])
                    ->all();

                KompenResponHubImportAuditLog::create([
                    'event_type' => KompenResponHubImportAuditLog::EVENT_ROLLBACK,
                    'source_import_id' => $lockedTarget->id,
                    'actor_name' => $actorName,
                    'actor_email' => $actorEmail,
                    'periode_semester' => $lockedTarget->periode_semester,
                    'original_filename' => $lockedTarget->original_filename,
                    'class_count' => $lockedTarget->class_count,
                    'student_count' => $lockedTarget->student_count,
                    'detail_count' => $lockedTarget->detail_count,
                    'metadata' => [
                        'restored' => $restoredImports !== [],
                        'restored_imports' => $restoredImports,
                    ],
                    'occurred_at' => now(),
                ]);

                return [
                    'rolled_back_import_id' => $lockedTarget->id,
                    'periode_semester' => $lockedTarget->periode_semester,
                    'restored_imports' => $restoredImports,
                    'restored' => $restoredImports !== [],
                ];
            });
    }

    /**
     * @param  list<string>  $activeClasses
     * @return array<string, int>
     */
    private function previousImportsByClass(
        KompenResponHubImport $targetImport,
        array $activeClasses,
    ): array {
        $replacedImports = $targetImport->replaced_imports ?? [];

        return collect($replacedImports)
            ->only($activeClasses)
            ->filter(fn (mixed $importId): bool => filter_var($importId, FILTER_VALIDATE_INT) !== false)
            ->map(fn (mixed $importId): int => (int) $importId)
            ->all();
    }

    /**
     * @param  array<string, int>  $previousImportsByClass
     * @return array{preview: array{periode_semester: string, classes: list<string>, class_count: int, student_count: int, detail_count: int}, students: list<array<string, mixed>>, details: list<array<string, mixed>>}
     */
    private function restorePayload(
        KompenResponHubImport $targetImport,
        array $previousImportsByClass,
    ): array {
        $sourceImports = KompenResponHubImport::query()
            ->whereKey(array_values(array_unique($previousImportsByClass)))
            ->get()
            ->keyBy('id');
        $payloadsByImportId = [];
        $students = [];
        $details = [];

        foreach ($previousImportsByClass as $class => $sourceImportId) {
            /** @var KompenResponHubImport|null $sourceImport */
            $sourceImport = $sourceImports->get($sourceImportId);

            if ($sourceImport === null) {
                throw new \LogicException("Versi sebelumnya untuk kelas {$class} tidak ditemukan.");
            }

            if (! str_starts_with($sourceImport->stored_path, 'kompen-respon-hub/imports/') || ! Storage::disk('local')->exists($sourceImport->stored_path)) {
                throw new \LogicException("File workbook versi sebelumnya untuk kelas {$class} tidak tersedia.");
            }

            if (! isset($payloadsByImportId[$sourceImportId])) {
                $sourcePayload = $this->parser->execute(Storage::disk('local')->path($sourceImport->stored_path));

                if ($sourcePayload['errors'] !== []) {
                    throw new \LogicException("Workbook versi sebelumnya {$sourceImport->original_filename} tidak dapat dipulihkan karena tidak lagi valid.");
                }

                if ($sourcePayload['preview']['periode_semester'] !== $targetImport->periode_semester) {
                    throw new \LogicException("Workbook versi sebelumnya {$sourceImport->original_filename} memiliki periode yang berbeda.");
                }

                $payloadsByImportId[$sourceImportId] = $sourcePayload;
            }

            $sourcePayload = $payloadsByImportId[$sourceImportId];
            if (! in_array($class, $sourcePayload['preview']['classes'], true)) {
                throw new \LogicException("Workbook versi sebelumnya {$sourceImport->original_filename} tidak memuat kelas {$class}.");
            }

            $students = [...$students, ...array_values(array_filter(
                $sourcePayload['students'],
                fn (array $student): bool => $student['kelas'] === $class,
            ))];
            $details = [...$details, ...array_values(array_filter(
                $sourcePayload['details'],
                fn (array $detail): bool => $detail['kelas'] === $class,
            ))];
        }

        return [
            'preview' => [
                'periode_semester' => $targetImport->periode_semester,
                'classes' => array_keys($previousImportsByClass),
                'class_count' => count($previousImportsByClass),
                'student_count' => count($students),
                'detail_count' => count($details),
            ],
            'students' => $students,
            'details' => $details,
        ];
    }
}
