<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\BuildKompenResponHubWarningLetterArtifact;
use App\Actions\KompenResponHub\FinalizeKompenResponHubCutoff;
use App\Actions\KompenResponHub\KompenResponHubDataQuery;
use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Actions\KompenResponHub\SelectKompenResponHubWarningPeriod;
use App\Http\Requests\DestroyKompenResponHubWarningLetterRequest;
use App\Http\Requests\FinalizeKompenResponHubPeriodRequest;
use App\Http\Requests\RollbackKompenResponHubPeriodFinalizationRequest;
use App\Http\Requests\SelectKompenResponHubWarningPeriodRequest;
use App\Http\Requests\StoreKompenResponHubDetailOverrideRequest;
use App\Http\Requests\StoreKompenResponHubPeriodCutoffRequest;
use App\Http\Requests\StoreKompenResponHubStudentProgressRequest;
use App\Http\Requests\StoreKompenResponHubStudentSummaryOverrideRequest;
use App\Http\Requests\UpdateKompenResponHubWarningLetterRequest;
use App\Models\KompenResponHubActiveImport;
use App\Models\KompenResponHubActiveStudentImportReference;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubDetail;
use App\Models\KompenResponHubDetailOverride;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubStudent;
use App\Models\KompenResponHubStudentProgress;
use App\Models\KompenResponHubStudentSummaryOverride;
use App\Models\KompenResponHubWarningLetter;
use App\Models\KompenResponHubWarningLetterArtifact;
use App\Models\KompenResponHubWarningPeriodSelection;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class KompenResponHubLifecycleController extends Controller
{
    public function __construct(
        private RecordKompenResponHubActivity $activity,
        private KompenResponHubDataQuery $dataQuery,
        private BuildKompenResponHubWarningLetterArtifact $warningArtifactBuilder,
        private FinalizeKompenResponHubCutoff $finalizeCutoff,
        private SelectKompenResponHubWarningPeriod $warningPeriodSelector,
    ) {}

    public function storeCutoff(StoreKompenResponHubPeriodCutoffRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        abort_unless(
            KompenResponHubStudent::query()
                ->fromActiveImportReference()
                ->where('periode_semester', $validated['periode_semester'])
                ->exists(),
            422,
            'Periode belum memiliki data mahasiswa aktif.',
        );

        /** @var array{cutoff: KompenResponHubPeriodCutoff, synchronized_warning_count: int} $result */
        $result = DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($request, $validated): array {
            $cutoff = KompenResponHubPeriodCutoff::query()
                ->where('periode_semester', $validated['periode_semester'])
                ->lockForUpdate()
                ->firstOrNew();
            abort_if($cutoff->closed_at !== null, 422, 'Periode SP sudah difinalisasi. Mulai koreksi terlebih dahulu sebelum mengubah batas waktu.');
            $before = $cutoff->exists ? $cutoff->only(['deadline_at', 'timezone']) : null;
            $cutoff->fill([
                'periode_semester' => $validated['periode_semester'],
                'deadline_at' => CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $validated['deadline_at'], 'Asia/Jakarta'),
                'timezone' => 'Asia/Jakarta',
                'updated_by_admin_id' => $request->user('admin')?->id,
            ])->save();

            $synchronizedWarningCount = $this->synchronizeWarningClassification($cutoff, $request);
            $classification = $this->candidateClassification($cutoff);

            $this->activity->execute(
                'cutoff.updated',
                'period_cutoff',
                (string) $cutoff->id,
                $request->user('admin'),
                $request,
                period: $cutoff->periode_semester,
                beforeState: $before,
                afterState: $cutoff->only(['deadline_at', 'timezone']),
                metadata: [
                    'warning_classification' => $classification,
                    'synchronized_warning_count' => $synchronizedWarningCount,
                ],
            );

            return [
                'cutoff' => $cutoff,
                'synchronized_warning_count' => $synchronizedWarningCount,
            ];
        });

        $synchronizationSummary = $result['synchronized_warning_count'] > 0
            ? " {$result['synchronized_warning_count']} status SP diselaraskan."
            : '';

        $admin = $request->user('admin');
        $selection = $this->warningPeriodSelector->selectWhenMissing(
            $result['cutoff'],
            $admin instanceof KompenResponHubAdmin ? $admin->id : null,
        );

        if ($selection['changed']) {
            $this->activity->execute(
                'cutoff.selected',
                'period_cutoff',
                (string) $selection['cutoff']->id,
                $admin instanceof KompenResponHubAdmin ? $admin : null,
                $request,
                period: $selection['cutoff']->periode_semester,
                reason: 'Periode SP aktif dipilih otomatis karena belum ada periode yang dikelola.',
            );
        }

        return back()->with('success', "Batas waktu periode berhasil disimpan.{$synchronizationSummary}");
    }

    public function selectCutoff(
        Request $request,
        KompenResponHubPeriodCutoff $cutoff,
    ): RedirectResponse {
        abort_unless(
            KompenResponHubStudent::query()
                ->fromActiveImportReference()
                ->where('periode_semester', $cutoff->periode_semester)
                ->exists(),
            422,
            'Periode SP hanya dapat dipilih ketika workbook Kompen dan Respon periode tersebut masih digunakan.',
        );

        $admin = $request->user('admin');
        $selection = $this->warningPeriodSelector->execute(
            $cutoff,
            $admin instanceof KompenResponHubAdmin ? $admin->id : null,
        );

        if ($selection['changed']) {
            $this->activity->execute(
                'cutoff.selected',
                'period_cutoff',
                (string) $selection['cutoff']->id,
                $admin instanceof KompenResponHubAdmin ? $admin : null,
                $request,
                period: $selection['cutoff']->periode_semester,
                reason: 'Periode dipilih sebagai periode SP aktif.',
            );
        }

        return back()->with('success', "Periode SP aktif sekarang {$selection['cutoff']->periode_semester}.");
    }

    public function selectWarningPeriod(SelectKompenResponHubWarningPeriodRequest $request): RedirectResponse
    {
        $period = $request->string('periode_semester')->toString();
        $admin = $request->user('admin');

        try {
            $selection = $this->warningPeriodSelector->executeForActivePeriod(
                $period,
                $admin instanceof KompenResponHubAdmin ? $admin->id : null,
            );
        } catch (\LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($selection['changed']) {
            $this->activity->execute(
                'cutoff.selected',
                'period_cutoff',
                (string) $selection['cutoff']->id,
                $admin instanceof KompenResponHubAdmin ? $admin : null,
                $request,
                period: $selection['cutoff']->periode_semester,
                reason: 'Periode dipilih sebagai periode SP aktif dari workbook Kompen dan Respon.',
                metadata: ['internal_cutoff_created' => $selection['created']],
            );
        }

        return to_route('admin.kompen-respon.index', ['tab' => 'warnings'])
            ->with('success', "Periode SP aktif sekarang {$selection['cutoff']->periode_semester}. Finalisasi dapat dilakukan langsung tanpa mengatur batas waktu.");
    }

    public function finalizeCutoff(
        FinalizeKompenResponHubPeriodRequest $request,
        KompenResponHubPeriodCutoff $cutoff,
    ): RedirectResponse {
        $finalizedCutoff = null;
        $temporaryArtifactPath = null;
        $storedArtifactPath = null;

        try {
            $result = DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($cutoff, $request, &$finalizedCutoff, &$storedArtifactPath, &$temporaryArtifactPath): array {
                $finalizedCutoff = KompenResponHubPeriodCutoff::query()
                    ->lockForUpdate()
                    ->findOrFail($cutoff->id);
                abort_if($finalizedCutoff->closed_at !== null, 422, 'Periode SP ini sudah final. Mulai koreksi terlebih dahulu sebelum melakukan finalisasi ulang.');

                $selection = KompenResponHubWarningPeriodSelection::query()
                    ->where('selection_key', KompenResponHubWarningPeriodSelection::ManagedWarningPeriodSelectionKey)
                    ->lockForUpdate()
                    ->first();
                $selectionWasInitialized = $selection === null;

                if ($selection === null) {
                    $selection = KompenResponHubWarningPeriodSelection::query()->create([
                        'selection_key' => KompenResponHubWarningPeriodSelection::ManagedWarningPeriodSelectionKey,
                        'sikompen_period_cutoff_id' => $finalizedCutoff->id,
                        'selected_by_admin_id' => $request->user('admin')?->id,
                        'selected_at' => now(),
                    ]);
                }

                abort_unless(
                    $selection->sikompen_period_cutoff_id === $finalizedCutoff->id,
                    422,
                    'Hanya periode SP yang sedang dikelola yang dapat difinalisasi.',
                );

                $activeImport = KompenResponHubActiveImport::query()
                    ->where('periode_semester', $finalizedCutoff->periode_semester)
                    ->lockForUpdate()
                    ->first();
                abort_unless($activeImport instanceof KompenResponHubActiveImport, 422, 'Periode tidak dapat difinalisasi karena workbook Kompen dan Respon aktif belum dipilih.');

                $sourceImport = KompenResponHubImport::query()->find($activeImport->kompen_respon_hub_import_id);
                abort_unless($sourceImport instanceof KompenResponHubImport, 422, 'Periode tidak dapat difinalisasi karena workbook sumber tidak tersedia.');

                $candidateCount = $this->dataQuery
                    ->warningCandidates(['periode_semester' => $finalizedCutoff->periode_semester])
                    ->count();
                $pendingWarningCount = KompenResponHubWarningLetter::query()
                    ->where('cutoff_id', $finalizedCutoff->id)
                    ->where('classification', '!=', 'fixed')
                    ->whereIn('letter_status', [
                        KompenResponHubWarningLetter::LetterStatusDraft,
                        KompenResponHubWarningLetter::LetterStatusIssued,
                    ])
                    ->count();
                $recipientCount = $candidateCount + $pendingWarningCount;
                abort_if(
                    $recipientCount === 0 && ! $request->boolean('confirm_empty_finalization'),
                    422,
                    'Tidak ada mahasiswa yang akan menerima SP. Konfirmasikan finalisasi tanpa penerima untuk membuat artifact resmi kosong.',
                );

                $rosterReference = KompenResponHubActiveStudentImportReference::query()
                    ->with('importBatch')
                    ->where('selection_key', KompenResponHubActiveStudentImportReference::StudentLoginSelectionKey)
                    ->first();
                $sourceProvenance = [
                    'kompen_respon_workbook' => [
                        'import_id' => $sourceImport->id,
                        'display_filename' => $sourceImport->display_filename ?? $sourceImport->original_filename,
                        'file_hash' => $sourceImport->file_hash,
                        'periode_semester' => $sourceImport->periode_semester,
                    ],
                    'active_student_roster' => $rosterReference?->importBatch === null ? null : [
                        'import_id' => $rosterReference->importBatch->id,
                        'original_filename' => $rosterReference->importBatch->original_filename,
                        'file_hash' => $rosterReference->importBatch->file_hash,
                        'periode_semester' => $rosterReference->importBatch->periode_semester,
                    ],
                ];

                $result = $this->finalizeCutoff->execute($finalizedCutoff, $request->user('admin'), $request);
                $warnings = KompenResponHubWarningLetter::query()
                    ->where('cutoff_id', $finalizedCutoff->id)
                    ->where('classification', 'fixed')
                    ->where('letter_status', KompenResponHubWarningLetter::LetterStatusIssued)
                    ->where('resolution', 'outstanding')
                    ->lockForUpdate()
                    ->orderBy('kelas')
                    ->orderBy('nim')
                    ->get();
                $latestArtifact = KompenResponHubWarningLetterArtifact::query()
                    ->where('cutoff_id', $finalizedCutoff->id)
                    ->lockForUpdate()
                    ->orderByDesc('revision')
                    ->first();
                $revision = $latestArtifact instanceof KompenResponHubWarningLetterArtifact
                    ? $latestArtifact->revision + 1
                    : 1;
                $artifactBuild = $this->warningArtifactBuilder->execute($finalizedCutoff, $warnings);
                $temporaryArtifactPath = $artifactBuild['temporary_path'];
                $storedArtifactPath = $this->warningArtifactBuilder->destinationPath(
                    $finalizedCutoff,
                    $revision,
                    $artifactBuild['display_filename'],
                );
                $disk = Storage::disk('local');
                $disk->makeDirectory(dirname($storedArtifactPath));

                if (! $disk->move($temporaryArtifactPath, $storedArtifactPath)) {
                    throw new \RuntimeException('Artifact Surat Peringatan tidak dapat dipindahkan ke penyimpanan resmi.');
                }

                $temporaryArtifactPath = null;
                $fileHash = hash_file('sha256', $disk->path($storedArtifactPath));
                if (! is_string($fileHash)) {
                    throw new \RuntimeException('Checksum artifact Surat Peringatan tidak dapat dibuat.');
                }

                $artifactValues = [
                    'status' => KompenResponHubWarningLetterArtifact::StatusFinalized,
                    'revision' => $revision,
                    'display_filename' => $artifactBuild['display_filename'],
                    'stored_path' => $storedArtifactPath,
                    'file_hash' => $fileHash,
                    'row_count' => $artifactBuild['row_count'],
                    'source_provenance' => $sourceProvenance,
                    'finalized_at' => now(),
                    'finalized_by_admin_id' => $request->user('admin')?->id,
                    'correction_started_at' => null,
                    'correction_started_by_admin_id' => null,
                ];

                KompenResponHubWarningLetterArtifact::query()
                    ->where('cutoff_id', $finalizedCutoff->id)
                    ->where('status', KompenResponHubWarningLetterArtifact::StatusRevising)
                    ->update(['status' => KompenResponHubWarningLetterArtifact::StatusSuperseded]);

                $artifact = KompenResponHubWarningLetterArtifact::query()->create([
                    'cutoff_id' => $finalizedCutoff->id,
                    ...$artifactValues,
                ]);

                $finalizedCutoff->update([
                    'closed_at' => now(),
                    'closed_by_admin_id' => $request->user('admin')?->id,
                ]);

                return [
                    ...$result,
                    'warning_artifact' => [
                        'id' => $artifact->id,
                        'revision' => $artifact->revision,
                        'row_count' => $artifact->row_count,
                        'display_filename' => $artifact->display_filename,
                    ],
                    'candidate_count' => $recipientCount,
                    'selection_was_initialized' => $selectionWasInitialized,
                ];
            });
        } catch (Throwable $exception) {
            if (is_string($temporaryArtifactPath)) {
                Storage::disk('local')->delete($temporaryArtifactPath);
            }

            if (is_string($storedArtifactPath)) {
                Storage::disk('local')->delete($storedArtifactPath);
            }

            throw $exception;
        }

        try {
            $this->activity->execute(
                'period.finalized',
                'period_cutoff',
                (string) $finalizedCutoff->id,
                $request->user('admin'),
                $request,
                period: $finalizedCutoff->periode_semester,
                afterState: [
                    'deadline_at' => $finalizedCutoff->deadline_at->toIso8601String(),
                    'closed_at' => $finalizedCutoff->closed_at?->toIso8601String(),
                ],
                metadata: $result,
            );
        } catch (Throwable $exception) {
            report($exception);
        }

        $emptyFinalizationSummary = $result['candidate_count'] === 0
            ? ' Tidak ada penerima SP; artifact XLSX resmi kosong dibuat sesuai konfirmasi.'
            : '';

        return back()->with('success', "Periode difinalisasi. Artifact XLSX resmi revisi {$result['warning_artifact']['revision']} sudah tersedia di List File.{$emptyFinalizationSummary}");
    }

    public function rollbackCutoffFinalization(
        RollbackKompenResponHubPeriodFinalizationRequest $request,
        KompenResponHubPeriodCutoff $cutoff,
    ): RedirectResponse {
        $reason = $request->validated('reason');
        $rolledBackWarningCount = DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($cutoff, $request, $reason): int {
            $lockedCutoff = KompenResponHubPeriodCutoff::query()
                ->lockForUpdate()
                ->findOrFail($cutoff->id);
            $artifact = KompenResponHubWarningLetterArtifact::query()
                ->where('cutoff_id', $lockedCutoff->id)
                ->lockForUpdate()
                ->orderByDesc('revision')
                ->first();
            abort_unless(
                $lockedCutoff->closed_at !== null
                    && $artifact?->status === KompenResponHubWarningLetterArtifact::StatusFinalized,
                422,
                'Periode SP ini belum memiliki finalisasi resmi yang dapat dikoreksi.',
            );
            $warnings = KompenResponHubWarningLetter::query()
                ->where('cutoff_id', $lockedCutoff->id)
                ->where('classification', 'fixed')
                ->where('letter_status', KompenResponHubWarningLetter::LetterStatusIssued)
                ->lockForUpdate()
                ->get();

            foreach ($warnings as $warning) {
                $before = $warning->only(['letter_status', 'resolution', 'reason', 'cancelled_at', 'cancellation_source']);
                $warning->update([
                    'letter_status' => KompenResponHubWarningLetter::LetterStatusCancelled,
                    'reason' => $reason,
                    'cancelled_at' => now(),
                    'cancellation_source' => KompenResponHubWarningLetter::CancellationSourceFinalizationRollback,
                    'updated_by_admin_id' => $request->user('admin')?->id,
                ]);

                $this->activity->execute(
                    'warning.rolled_back',
                    'warning_letter',
                    (string) $warning->id,
                    $request->user('admin'),
                    $request,
                    $warning->nim,
                    $warning->periode_semester,
                    $warning->kelas,
                    $reason,
                    $before,
                    $warning->only(['letter_status', 'resolution', 'reason', 'cancelled_at', 'cancellation_source']),
                    ['cutoff_id' => $lockedCutoff->id, 'source' => 'period_finalization_rollback'],
                    $warning->nama_mahasiswa,
                );
            }

            $artifact->update([
                'status' => KompenResponHubWarningLetterArtifact::StatusRevising,
                'correction_started_at' => now(),
                'correction_started_by_admin_id' => $request->user('admin')?->id,
            ]);
            $lockedCutoff->update([
                'closed_at' => null,
                'closed_by_admin_id' => null,
            ]);

            $this->activity->execute(
                'period.finalization_rolled_back',
                'period_cutoff',
                (string) $lockedCutoff->id,
                $request->user('admin'),
                $request,
                period: $lockedCutoff->periode_semester,
                reason: $reason,
                metadata: [
                    'rolled_back_warning_count' => $warnings->count(),
                    'warning_artifact_id' => $artifact->id,
                    'warning_artifact_revision' => $artifact->revision,
                    'warning_artifact_status' => $artifact->status,
                ],
            );

            return $warnings->count();
        });

        return back()->with('success', "Koreksi dibuka untuk {$rolledBackWarningCount} mahasiswa. Artifact sebelumnya tetap tersimpan di List File dengan status sedang dibenarkan dan tidak lagi tersedia pada API.");
    }

    public function storeProgress(StoreKompenResponHubStudentProgressRequest $request, KompenResponHubStudent $student): RedirectResponse
    {
        $validated = $request->validated();
        $this->withinEditableWarningPeriod($student->periode_semester, function () use ($request, $student, $validated): void {
            $this->ensureStudentUsesActiveWorkbook($student);
            $override = KompenResponHubStudentSummaryOverride::query()->where('current_student_id', $student->id)->first();
            $totalKompen = $override instanceof KompenResponHubStudentSummaryOverride
                ? (float) $override->total_kompensasi_jam
                : (float) $student->total_kompensasi_jam;
            $totalResponsi = $override instanceof KompenResponHubStudentSummaryOverride
                ? (float) $override->total_responsi_jam
                : (float) $student->total_responsi_jam;

            abort_if((float) $validated['kompensasi_dikerjakan_jam'] > $totalKompen || (float) $validated['responsi_dikerjakan_jam'] > $totalResponsi, 422, 'Jam yang dikerjakan tidak boleh melebihi total hutang efektif.');
            abort_if(((float) $validated['kompensasi_dikerjakan_jam'] + (float) $validated['responsi_dikerjakan_jam']) > 0 && empty($validated['last_worked_at']), 422, 'Tanggal terakhir dikerjakan wajib diisi ketika ada jam yang dikerjakan.');

            $progress = KompenResponHubStudentProgress::query()->firstOrNew($this->studentIdentity($student));
            $before = $progress->exists ? $progress->only(['kompensasi_dikerjakan_jam', 'responsi_dikerjakan_jam', 'last_worked_at', 'reason']) : null;
            $progress->fill([
                ...$this->studentIdentity($student),
                'current_student_id' => $student->id,
                'kompensasi_dikerjakan_jam' => $validated['kompensasi_dikerjakan_jam'],
                'responsi_dikerjakan_jam' => $validated['responsi_dikerjakan_jam'],
                'last_worked_at' => filled($validated['last_worked_at']) ? CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $validated['last_worked_at'], 'Asia/Jakarta')->utc() : null,
                'reason' => $validated['reason'],
                'updated_by_admin_id' => $request->user('admin')?->id,
            ])->save();

            $this->activity->execute('progress.updated', 'student_progress', (string) $progress->id, $request->user('admin'), $request, $student->nim, $student->periode_semester, $student->kelas, $validated['reason'], $before, $progress->only(['kompensasi_dikerjakan_jam', 'responsi_dikerjakan_jam', 'last_worked_at', 'reason']), subjectName: $student->nama_mahasiswa);
            $this->syncWarningResolution($student, $request);
        });

        return back()->with('success', 'Progres pengerjaan mahasiswa berhasil diperbarui.');
    }

    public function storeSummaryOverride(StoreKompenResponHubStudentSummaryOverrideRequest $request, KompenResponHubStudent $student): RedirectResponse
    {
        $validated = $request->validated();
        $this->withinEditableWarningPeriod($student->periode_semester, function () use ($request, $student, $validated): void {
            $this->ensureStudentUsesActiveWorkbook($student);
            $progress = KompenResponHubStudentProgress::query()->where('current_student_id', $student->id)->first();
            $workedKompen = $progress instanceof KompenResponHubStudentProgress
                ? (float) $progress->kompensasi_dikerjakan_jam
                : 0.0;
            $workedResponsi = $progress instanceof KompenResponHubStudentProgress
                ? (float) $progress->responsi_dikerjakan_jam
                : 0.0;

            abort_if((float) $validated['total_kompensasi_jam'] < $workedKompen || (float) $validated['total_responsi_jam'] < $workedResponsi, 422, 'Total hutang tidak boleh lebih kecil dari jam yang sudah dikerjakan.');

            $override = KompenResponHubStudentSummaryOverride::query()->firstOrNew($this->studentIdentity($student));
            $before = $override->exists ? $override->only(['total_kompensasi_jam', 'total_responsi_jam', 'reason']) : null;
            $override->fill([
                ...$this->studentIdentity($student),
                'current_student_id' => $student->id,
                'total_kompensasi_jam' => $validated['total_kompensasi_jam'],
                'total_responsi_jam' => $validated['total_responsi_jam'],
                'reason' => $validated['reason'],
                'updated_by_admin_id' => $request->user('admin')?->id,
            ])->save();

            $this->activity->execute('summary.override_updated', 'student_summary_override', (string) $override->id, $request->user('admin'), $request, $student->nim, $student->periode_semester, $student->kelas, $validated['reason'], $before, $override->only(['total_kompensasi_jam', 'total_responsi_jam', 'reason']), subjectName: $student->nama_mahasiswa);
            $this->syncWarningResolution($student, $request);
        });

        return back()->with('success', 'Koreksi Kompen dan Respon berhasil disimpan.');
    }

    public function storeDetailOverride(StoreKompenResponHubDetailOverrideRequest $request, KompenResponHubDetail $detail): RedirectResponse
    {
        $validated = $request->validated();
        $detail->loadMissing('student');
        abort_unless($detail->student instanceof KompenResponHubStudent, 404);
        $period = $detail->student->periode_semester;
        $this->withinEditableWarningPeriod($period, function () use ($detail, $request, $validated): void {
            $lockedDetail = KompenResponHubDetail::query()
                ->with('student')
                ->lockForUpdate()
                ->findOrFail($detail->id);
            abort_unless($lockedDetail->student instanceof KompenResponHubStudent, 404);
            $this->ensureStudentUsesActiveWorkbook($lockedDetail->student);
            $sourceKey = $lockedDetail->source_key ?? hash('sha256', "legacy-detail:{$lockedDetail->id}");
            if ($lockedDetail->source_key === null) {
                $lockedDetail->update(['source_key' => $sourceKey]);
            }

            $override = KompenResponHubDetailOverride::query()->firstOrNew(['source_key' => $sourceKey]);
            $before = $override->exists ? $override->only(['override_values', 'reason']) : null;
            $overrideValues = collect($validated)->only(['tanggal', 'mata_kuliah', 'nama_dosen', 'jenis_pertemuan', 'presensi', 'menit_keterlambatan', 'keterangan', 'jam_kompensasi', 'jam_responsi'])->all();
            $override->fill(['override_values' => $overrideValues, 'reason' => $validated['reason'], 'updated_by_admin_id' => $request->user('admin')?->id])->save();

            $this->activity->execute('detail.override_updated', 'detail_override', (string) $override->id, $request->user('admin'), $request, $lockedDetail->student->nim, $lockedDetail->student->periode_semester, $lockedDetail->student->kelas, $validated['reason'], $before, $override->only(['override_values', 'reason']), subjectName: $lockedDetail->student->nama_mahasiswa);
        });

        return back()->with('success', 'Koreksi Detail Kompen berhasil disimpan.');
    }

    public function destroyDetailOverride(Request $request, KompenResponHubDetail $detail): RedirectResponse
    {
        $detail->loadMissing('student');
        abort_unless($detail->student instanceof KompenResponHubStudent, 404);

        $this->withinEditableWarningPeriod($detail->student->periode_semester, function () use ($detail, $request): void {
            $lockedDetail = KompenResponHubDetail::query()
                ->with('student')
                ->lockForUpdate()
                ->findOrFail($detail->id);
            abort_unless($lockedDetail->student instanceof KompenResponHubStudent, 404);
            $this->ensureStudentUsesActiveWorkbook($lockedDetail->student);

            $sourceKey = $lockedDetail->source_key ?? hash('sha256', "legacy-detail:{$lockedDetail->id}");
            $override = KompenResponHubDetailOverride::query()
                ->where('source_key', $sourceKey)
                ->lockForUpdate()
                ->first();
            abort_unless($override instanceof KompenResponHubDetailOverride, 422, 'Detail ini tidak memiliki koreksi yang dapat dihapus.');

            $before = $override->only(['override_values', 'reason']);
            $override->delete();

            $this->activity->execute(
                'detail.override_removed',
                'detail_override',
                (string) $lockedDetail->id,
                $request->user('admin'),
                $request,
                $lockedDetail->student->nim,
                $lockedDetail->student->periode_semester,
                $lockedDetail->student->kelas,
                'Koreksi detail dihapus sebelum data sumber diperbarui.',
                $before,
                null,
                subjectName: $lockedDetail->student->nama_mahasiswa,
            );
        });

        return back()->with('success', 'Koreksi Detail Kompen dihapus. Nilai kembali mengikuti workbook aktif.');
    }

    public function updateWarning(UpdateKompenResponHubWarningLetterRequest $request, KompenResponHubWarningLetter $warning): RedirectResponse
    {
        $validated = $request->validated();
        $this->withinEditableWarningPeriod($warning->periode_semester, function () use ($request, $validated, $warning): void {
            $lockedWarning = KompenResponHubWarningLetter::query()->lockForUpdate()->findOrFail($warning->id);
            $before = $lockedWarning->only(['reason']);
            $lockedWarning->fill([
                'reason' => $validated['reason'],
                'updated_by_admin_id' => $request->user('admin')?->id,
            ])->save();

            $this->activity->execute('warning.updated', 'warning_letter', (string) $lockedWarning->id, $request->user('admin'), $request, $lockedWarning->nim, $lockedWarning->periode_semester, $lockedWarning->kelas, $validated['reason'], $before, $lockedWarning->only(['reason']), subjectName: $lockedWarning->nama_mahasiswa);
        });

        return back()->with('success', 'Catatan SP berhasil diperbarui.');
    }

    public function destroyWarning(DestroyKompenResponHubWarningLetterRequest $request, KompenResponHubWarningLetter $warning): RedirectResponse
    {
        $validated = $request->validated();
        $this->withinEditableWarningPeriod($warning->periode_semester, function () use ($request, $validated, $warning): void {
            $lockedWarning = KompenResponHubWarningLetter::query()->lockForUpdate()->findOrFail($warning->id);
            abort_unless($lockedWarning->letter_status === KompenResponHubWarningLetter::LetterStatusIssued, 422, 'Hanya SP aktif yang dapat di-rollback.');

            $before = $lockedWarning->only(['letter_status', 'resolution', 'reason', 'cancelled_at', 'cancellation_source']);
            $lockedWarning->update([
                'letter_status' => KompenResponHubWarningLetter::LetterStatusCancelled,
                'reason' => $validated['reason'],
                'cancelled_at' => now(),
                'cancellation_source' => KompenResponHubWarningLetter::CancellationSourceManualRollback,
                'updated_by_admin_id' => $request->user('admin')?->id,
            ]);

            $this->activity->execute('warning.rolled_back', 'warning_letter', (string) $lockedWarning->id, $request->user('admin'), $request, $lockedWarning->nim, $lockedWarning->periode_semester, $lockedWarning->kelas, $validated['reason'], $before, $lockedWarning->only(['letter_status', 'resolution', 'reason', 'cancelled_at', 'cancellation_source']), subjectName: $lockedWarning->nama_mahasiswa);
        });

        return back()->with('success', 'SP di-rollback. Riwayat surat dan aktivitas tetap tersimpan, tetapi tidak lagi dihitung sebagai SP aktif.');
    }

    /** @return array{nim: string, periode_semester: string, kelas: string} */
    private function studentIdentity(KompenResponHubStudent $student): array
    {
        return ['nim' => $student->nim, 'periode_semester' => $student->periode_semester, 'kelas' => $student->kelas];
    }

    /** @return array<string, string|float|null> */
    private function studentSnapshot(KompenResponHubStudent $student): array
    {
        $totalKompen = (float) $student->effective_total_kompensasi_jam;
        $totalResponsi = (float) $student->effective_total_responsi_jam;
        $workedKompen = (float) $student->effective_kompensasi_dikerjakan_jam;
        $workedResponsi = (float) $student->effective_responsi_dikerjakan_jam;

        return ['total_kompensasi_jam' => $totalKompen, 'total_responsi_jam' => $totalResponsi, 'kompensasi_dikerjakan_jam' => $workedKompen, 'responsi_dikerjakan_jam' => $workedResponsi, 'sisa_hutang_jam' => max(0, $totalKompen + $totalResponsi - $workedKompen - $workedResponsi)];
    }

    private function syncWarningResolution(KompenResponHubStudent $student, StoreKompenResponHubStudentProgressRequest|StoreKompenResponHubStudentSummaryOverrideRequest $request): void
    {
        $student->load(['progress', 'summaryOverride']);
        $resolution = $this->studentSnapshot($student)['sisa_hutang_jam'] <= 0
            ? 'completed'
            : 'outstanding';

        KompenResponHubWarningLetter::query()
            ->where('current_student_id', $student->id)
            ->where('resolution', '!=', $resolution)
            ->each(function (KompenResponHubWarningLetter $warning) use ($student, $request, $resolution): void {
                $before = $warning->only(['resolution', 'snapshot']);
                $warning->update([
                    'resolution' => $resolution,
                    'snapshot' => $this->studentSnapshot($student),
                    'updated_by_admin_id' => $request->user('admin')?->id,
                ]);

                $this->activity->execute(
                    'warning.resolution_updated',
                    'warning_letter',
                    (string) $warning->id,
                    $request->user('admin'),
                    $request,
                    $student->nim,
                    $student->periode_semester,
                    $student->kelas,
                    'Status penyelesaian diselaraskan dengan progres Kompen dan Responsi.',
                    $before,
                    $warning->only(['resolution', 'snapshot']),
                    subjectName: $student->nama_mahasiswa,
                );
            });
    }

    private function synchronizeWarningClassification(
        KompenResponHubPeriodCutoff $cutoff,
        StoreKompenResponHubPeriodCutoffRequest $request,
    ): int {
        $classification = $this->candidateClassification($cutoff);
        $synchronizedWarningCount = 0;

        KompenResponHubWarningLetter::query()
            ->where('cutoff_id', $cutoff->id)
            ->whereIn('letter_status', [
                KompenResponHubWarningLetter::LetterStatusDraft,
                KompenResponHubWarningLetter::LetterStatusIssued,
            ])
            ->where('classification', '!=', $classification)
            ->chunkById(200, function ($warnings) use ($classification, $cutoff, $request, &$synchronizedWarningCount): void {
                foreach ($warnings as $warning) {
                    $before = $warning->only(['classification']);
                    $warning->update([
                        'classification' => $classification,
                        'updated_by_admin_id' => $request->user('admin')?->id,
                    ]);
                    $synchronizedWarningCount++;

                    $this->activity->execute(
                        "warning.classification_{$classification}",
                        'warning_letter',
                        (string) $warning->id,
                        $request->user('admin'),
                        $request,
                        $warning->nim,
                        $cutoff->periode_semester,
                        $warning->kelas,
                        'Status SP diselaraskan setelah batas waktu periode diperbaiki.',
                        $before,
                        $warning->only(['classification']),
                        subjectName: $warning->nama_mahasiswa,
                    );
                }
            });

        return $synchronizedWarningCount;
    }

    private function candidateClassification(KompenResponHubPeriodCutoff $cutoff): string
    {
        return $cutoff->deadline_at->isPast() ? 'fixed' : 'temporary';
    }

    private function ensureStudentUsesActiveWorkbook(KompenResponHubStudent $student): void
    {
        abort_unless(
            KompenResponHubStudent::query()
                ->fromActiveImportReference()
                ->whereKey($student->id)
                ->exists(),
            404,
        );
    }

    private function withinEditableWarningPeriod(string $period, \Closure $callback): mixed
    {
        return DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($callback, $period): mixed {
            $cutoff = KompenResponHubPeriodCutoff::query()
                ->where('periode_semester', $period)
                ->lockForUpdate()
                ->first();

            abort_if(
                $cutoff?->closed_at !== null,
                422,
                'Periode SP sudah final. Mulai koreksi terlebih dahulu sebelum mengubah data.',
            );

            return $callback();
        });
    }
}
