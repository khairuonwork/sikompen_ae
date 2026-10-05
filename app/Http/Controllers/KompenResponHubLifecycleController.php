<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\FinalizeKompenResponHubCutoff;
use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Http\Requests\DestroyKompenResponHubWarningLetterRequest;
use App\Http\Requests\FinalizeKompenResponHubPeriodRequest;
use App\Http\Requests\StoreKompenResponHubDetailOverrideRequest;
use App\Http\Requests\StoreKompenResponHubPeriodCutoffRequest;
use App\Http\Requests\StoreKompenResponHubStudentProgressRequest;
use App\Http\Requests\StoreKompenResponHubStudentSummaryOverrideRequest;
use App\Http\Requests\UpdateKompenResponHubWarningLetterRequest;
use App\Models\KompenResponHubDetail;
use App\Models\KompenResponHubDetailOverride;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubStudent;
use App\Models\KompenResponHubStudentProgress;
use App\Models\KompenResponHubStudentSummaryOverride;
use App\Models\KompenResponHubWarningLetter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class KompenResponHubLifecycleController extends Controller
{
    public function __construct(
        private RecordKompenResponHubActivity $activity,
        private FinalizeKompenResponHubCutoff $finalizeCutoff,
    ) {}

    public function storeCutoff(StoreKompenResponHubPeriodCutoffRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        abort_unless(KompenResponHubStudent::query()->where('periode_semester', $validated['periode_semester'])->exists(), 422, 'Periode belum memiliki data mahasiswa.');

        /** @var array{cutoff: KompenResponHubPeriodCutoff, synchronized_warning_count: int} $result */
        $result = DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($request, $validated): array {
            $cutoff = KompenResponHubPeriodCutoff::query()
                ->where('periode_semester', $validated['periode_semester'])
                ->lockForUpdate()
                ->firstOrNew();
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

        return back()->with('success', "Batas waktu periode berhasil disimpan.{$synchronizationSummary}");
    }

    public function finalizeCutoff(
        FinalizeKompenResponHubPeriodRequest $request,
        KompenResponHubPeriodCutoff $cutoff,
    ): RedirectResponse {
        $finalizedCutoff = null;
        $result = DB::connection(config('kompen-respon-hub.database_connection'))->transaction(function () use ($cutoff, $request, &$finalizedCutoff): array {
            $finalizedCutoff = KompenResponHubPeriodCutoff::query()
                ->lockForUpdate()
                ->findOrFail($cutoff->id);
            abort_unless($finalizedCutoff->deadline_at->isPast(), 422, 'Periode hanya dapat difiksasi setelah batas waktunya terlewati.');

            return $this->finalizeCutoff->execute($finalizedCutoff, $request->user('admin'), $request);
        });

        $this->activity->execute(
            'period.finalized',
            'period_cutoff',
            (string) $finalizedCutoff->id,
            $request->user('admin'),
            $request,
            period: $finalizedCutoff->periode_semester,
            afterState: ['deadline_at' => $finalizedCutoff->deadline_at->toIso8601String()],
            metadata: $result,
        );

        return back()->with('success', 'Periode difiksasi dan dicatat pada riwayat aktivitas. Perbaikan data dan impor versi tetap dapat dilakukan bila diperlukan.');
    }

    public function storeProgress(StoreKompenResponHubStudentProgressRequest $request, KompenResponHubStudent $student): RedirectResponse
    {
        $validated = $request->validated();
        $override = KompenResponHubStudentSummaryOverride::query()->where('current_student_id', $student->id)->first();
        $totalKompen = (float) ($override?->total_kompensasi_jam ?? $student->total_kompensasi_jam);
        $totalResponsi = (float) ($override?->total_responsi_jam ?? $student->total_responsi_jam);

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

        return back()->with('success', 'Progres pengerjaan mahasiswa berhasil diperbarui.');
    }

    public function storeSummaryOverride(StoreKompenResponHubStudentSummaryOverrideRequest $request, KompenResponHubStudent $student): RedirectResponse
    {
        $validated = $request->validated();
        $progress = KompenResponHubStudentProgress::query()->where('current_student_id', $student->id)->first();
        abort_if((float) $validated['total_kompensasi_jam'] < (float) ($progress?->kompensasi_dikerjakan_jam ?? 0) || (float) $validated['total_responsi_jam'] < (float) ($progress?->responsi_dikerjakan_jam ?? 0), 422, 'Total hutang tidak boleh lebih kecil dari jam yang sudah dikerjakan.');

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

        return back()->with('success', 'Koreksi Kompen dan Respon berhasil disimpan.');
    }

    public function storeDetailOverride(StoreKompenResponHubDetailOverrideRequest $request, KompenResponHubDetail $detail): RedirectResponse
    {
        $validated = $request->validated();
        $detail->loadMissing('student');
        $sourceKey = $detail->source_key ?? hash('sha256', "legacy-detail:{$detail->id}");
        if ($detail->source_key === null) {
            $detail->update(['source_key' => $sourceKey]);
        }

        $override = KompenResponHubDetailOverride::query()->firstOrNew(['source_key' => $sourceKey]);
        $before = $override->exists ? $override->only(['override_values', 'reason']) : null;
        $overrideValues = collect($validated)->only(['tanggal', 'mata_kuliah', 'nama_dosen', 'jenis_pertemuan', 'presensi', 'menit_keterlambatan', 'keterangan', 'jam_kompensasi', 'jam_responsi'])->all();
        $override->fill(['override_values' => $overrideValues, 'reason' => $validated['reason'], 'updated_by_admin_id' => $request->user('admin')?->id])->save();

        $this->activity->execute('detail.override_updated', 'detail_override', (string) $override->id, $request->user('admin'), $request, $detail->student?->nim, $detail->student?->periode_semester, $detail->student?->kelas, $validated['reason'], $before, $override->only(['override_values', 'reason']), subjectName: $detail->student?->nama_mahasiswa);

        return back()->with('success', 'Koreksi Detail Kompen berhasil disimpan.');
    }

    public function updateWarning(UpdateKompenResponHubWarningLetterRequest $request, KompenResponHubWarningLetter $warning): RedirectResponse
    {
        $validated = $request->validated();
        $before = $warning->only(['reason']);
        $warning->fill([
            'reason' => $validated['reason'],
            'updated_by_admin_id' => $request->user('admin')?->id,
        ])->save();

        $this->activity->execute('warning.updated', 'warning_letter', (string) $warning->id, $request->user('admin'), $request, $warning->nim, $warning->periode_semester, $warning->kelas, $validated['reason'], $before, $warning->only(['reason']), subjectName: $warning->nama_mahasiswa);

        return back()->with('success', 'Catatan SP berhasil diperbarui.');
    }

    public function destroyWarning(DestroyKompenResponHubWarningLetterRequest $request, KompenResponHubWarningLetter $warning): RedirectResponse
    {
        abort_unless($warning->letter_status === KompenResponHubWarningLetter::LetterStatusIssued, 422, 'Hanya SP aktif yang dapat di-rollback.');

        $validated = $request->validated();
        $before = $warning->only(['letter_status', 'resolution', 'reason', 'cancelled_at']);
        $warning->update([
            'letter_status' => KompenResponHubWarningLetter::LetterStatusCancelled,
            'reason' => $validated['reason'],
            'cancelled_at' => now(),
            'updated_by_admin_id' => $request->user('admin')?->id,
        ]);

        $this->activity->execute('warning.rolled_back', 'warning_letter', (string) $warning->id, $request->user('admin'), $request, $warning->nim, $warning->periode_semester, $warning->kelas, $validated['reason'], $before, $warning->only(['letter_status', 'resolution', 'reason', 'cancelled_at']), subjectName: $warning->nama_mahasiswa);

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
        $totalKompen = (float) ($student->summaryOverride?->total_kompensasi_jam ?? $student->total_kompensasi_jam);
        $totalResponsi = (float) ($student->summaryOverride?->total_responsi_jam ?? $student->total_responsi_jam);
        $workedKompen = (float) ($student->progress?->kompensasi_dikerjakan_jam ?? 0);
        $workedResponsi = (float) ($student->progress?->responsi_dikerjakan_jam ?? 0);

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
}
