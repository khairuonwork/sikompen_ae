<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubActiveImport;
use App\Models\KompenResponHubDetail;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubStudent;
use App\Models\KompenResponHubStudentProgress;
use App\Models\KompenResponHubStudentSummaryOverride;
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
     * @return array{import_id: int, periode_semester: string, original_filename: string, classes: list<string>, previous_import: array{id: int, original_filename: string}|null, class_moves: list<array{nim: string, from: string, to: string}>, detail_override_reconciliation: array{preserved_count: int, unmatched_count: 0}}
     */
    public function execute(
        KompenResponHubImport $import,
        string $actorName,
        string $actorUsername,
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
            ->transaction(function () use ($actorAdminId, $actorUsername, $actorName, $classes, $import, $payload): array {
                $lockedImport = KompenResponHubImport::query()
                    ->lockForUpdate()
                    ->findOrFail($import->id);

                KompenResponHubImport::query()
                    ->where('periode_semester', $lockedImport->periode_semester)
                    ->lockForUpdate()
                    ->get(['id']);

                $cutoff = KompenResponHubPeriodCutoff::query()
                    ->where('periode_semester', $lockedImport->periode_semester)
                    ->lockForUpdate()
                    ->first();

                if ($cutoff?->closed_at !== null) {
                    throw new \LogicException('Workbook periode ini terkunci karena SP sudah difinalisasi. Mulai koreksi periode SP terlebih dahulu.');
                }

                $activeImport = KompenResponHubActiveImport::query()
                    ->where('periode_semester', $lockedImport->periode_semester)
                    ->lockForUpdate()
                    ->with('importBatch:id,original_filename')
                    ->first();

                if ($activeImport?->kompen_respon_hub_import_id === $lockedImport->id) {
                    throw new \LogicException('Versi file yang dipilih sudah menjadi data aktif.');
                }

                $incomingNims = array_values(collect($payload['students'])
                    ->map(static fn (array $student): string => (string) $student['nim'])
                    ->all());
                $duplicateIncomingNims = collect($incomingNims)
                    ->countBy()
                    ->filter(static fn (int $count): bool => $count > 1)
                    ->keys()
                    ->values();
                if ($duplicateIncomingNims->isNotEmpty()) {
                    throw new \LogicException('Aktivasi dibatalkan karena NIM berikut muncul pada lebih dari satu kelas: '.$duplicateIncomingNims->take(5)->implode(', ').'. Perbaiki workbook sebelum melanjutkan.');
                }

                $this->assertLifecycleRecordsAreUnambiguous(
                    $lockedImport->periode_semester,
                    $incomingNims,
                );
                $detailOverrideReconciliation = $this->reconcileDetailOverrides(
                    $lockedImport->periode_semester,
                    $payload['details'],
                );

                $previousImport = $activeImport?->importBatch === null ? null : [
                    'id' => $activeImport->importBatch->id,
                    'original_filename' => $activeImport->importBatch->original_filename,
                ];

                $currentClassesByNim = KompenResponHubStudent::query()
                    ->where('periode_semester', $lockedImport->periode_semester)
                    ->pluck('kelas', 'nim');
                $classMoves = array_values(collect($payload['students'])
                    ->map(function (array $student) use ($currentClassesByNim): ?array {
                        $nim = (string) $student['nim'];
                        $currentClass = $currentClassesByNim->get($nim);
                        $nextClass = (string) $student['kelas'];

                        if (! is_string($currentClass) || $currentClass === $nextClass) {
                            return null;
                        }

                        return ['nim' => $nim, 'from' => $currentClass, 'to' => $nextClass];
                    })
                    ->filter()
                    ->all());

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
                    'actor_username' => $actorUsername,
                    'periode_semester' => $lockedImport->periode_semester,
                    'original_filename' => $lockedImport->original_filename,
                    'class_count' => $payload['preview']['class_count'],
                    'student_count' => $payload['preview']['student_count'],
                    'detail_count' => $payload['preview']['detail_count'],
                    'metadata' => [
                        'activated_classes' => $classes,
                        'previous_import' => $previousImport,
                        'class_moves' => $classMoves,
                        'detail_override_reconciliation' => $detailOverrideReconciliation,
                    ],
                    'occurred_at' => now(),
                ]);

                return [
                    'import_id' => $lockedImport->id,
                    'periode_semester' => $lockedImport->periode_semester,
                    'original_filename' => $lockedImport->original_filename,
                    'classes' => $classes,
                    'previous_import' => $previousImport,
                    'class_moves' => $classMoves,
                    'detail_override_reconciliation' => $detailOverrideReconciliation,
                ];
            });
    }

    /** @param list<string> $incomingNims */
    private function assertLifecycleRecordsAreUnambiguous(string $period, array $incomingNims): void
    {
        foreach ([
            KompenResponHubStudentProgress::class => 'progres pengerjaan',
            KompenResponHubStudentSummaryOverride::class => 'koreksi total jam',
        ] as $model => $label) {
            $duplicateNims = $model::query()
                ->where('periode_semester', $period)
                ->whereIn('nim', $incomingNims)
                ->selectRaw('nim')
                ->groupBy('nim')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('nim')
                ->map(static fn (mixed $nim): string => (string) $nim)
                ->values();

            if ($duplicateNims->isNotEmpty()) {
                throw new \LogicException("Aktivasi dibatalkan karena riwayat {$label} ganda untuk NIM: ".$duplicateNims->take(5)->implode(', ').'. Rapikan riwayat tersebut sebelum mengganti workbook.');
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $nextDetails
     * @return array{preserved_count: int, unmatched_count: 0}
     */
    private function reconcileDetailOverrides(string $period, array $nextDetails): array
    {
        $currentOverrideKeys = KompenResponHubDetail::query()
            ->whereHas('student', static function ($query) use ($period): void {
                $query->where('periode_semester', $period);
            })
            ->whereHas('override')
            ->pluck('source_key')
            ->filter(static fn (mixed $sourceKey): bool => is_string($sourceKey) && $sourceKey !== '')
            ->values();
        $nextDetailKeys = collect($nextDetails)
            ->map(static fn (array $detail): string => ImportKompenResponHubWorkbook::detailSourceKey($detail))
            ->flip();
        $unmatchedKeys = $currentOverrideKeys
            ->reject(static fn (string $sourceKey): bool => $nextDetailKeys->has($sourceKey))
            ->values();

        if ($unmatchedKeys->isNotEmpty()) {
            throw new \LogicException('Aktivasi dibatalkan karena '.count($unmatchedKeys).' koreksi Detail Kompen tidak lagi cocok dengan workbook baru. Hapus atau selesaikan koreksi tersebut pada workbook aktif, lalu terapkan ulang setelah workbook baru digunakan.');
        }

        return [
            'preserved_count' => $currentOverrideKeys->count(),
            'unmatched_count' => 0,
        ];
    }
}
