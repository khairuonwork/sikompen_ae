<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\EnsureKompenResponHubPeriodIsOpen;
use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Http\Requests\CloseKompenResponHubPeriodRequest;
use App\Http\Requests\DestroyKompenResponHubWarningLetterRequest;
use App\Http\Requests\StoreKompenResponHubDetailOverrideRequest;
use App\Http\Requests\StoreKompenResponHubPeriodCutoffRequest;
use App\Http\Requests\StoreKompenResponHubStudentProgressRequest;
use App\Http\Requests\StoreKompenResponHubStudentSummaryOverrideRequest;
use App\Http\Requests\StoreKompenResponHubWarningLetterRequest;
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

class KompenResponHubLifecycleController extends Controller
{
    public function __construct(
        private RecordKompenResponHubActivity $activity,
        private EnsureKompenResponHubPeriodIsOpen $periodLock,
    ) {}

    public function storeCutoff(StoreKompenResponHubPeriodCutoffRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        abort_unless(KompenResponHubStudent::query()->where('periode_semester', $validated['periode_semester'])->exists(), 422, 'Periode belum memiliki data mahasiswa.');

        $cutoff = KompenResponHubPeriodCutoff::query()->firstOrNew(['periode_semester' => $validated['periode_semester']]);
        abort_if($cutoff->closed_at !== null, 422, 'Periode telah ditutup dan tidak dapat diubah.');
        $before = $cutoff->exists ? $cutoff->only(['deadline_at', 'timezone']) : null;
        $cutoff->fill([
            'deadline_at' => CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $validated['deadline_at'], 'Asia/Jakarta')->utc(),
            'timezone' => 'Asia/Jakarta',
            'updated_by_admin_id' => $request->user('admin')?->id,
        ])->save();

        $this->activity->execute('cutoff.updated', 'period_cutoff', (string) $cutoff->id, $request->user('admin'), $request, period: $cutoff->periode_semester, beforeState: $before, afterState: $cutoff->only(['deadline_at', 'timezone']));
        $this->synchronizeWarningClassification($cutoff, $request);

        return back()->with('success', 'Batas waktu periode berhasil disimpan.');
    }

    public function closeCutoff(
        CloseKompenResponHubPeriodRequest $request,
        KompenResponHubPeriodCutoff $cutoff,
    ): RedirectResponse {
        abort_if($cutoff->closed_at !== null, 422, 'Periode ini sudah ditutup.');
        abort_unless($cutoff->deadline_at->isPast(), 422, 'Periode hanya dapat ditutup setelah batas waktunya terlewati.');

        $cutoff->update([
            'closed_at' => now(),
            'closed_by_admin_id' => $request->user('admin')?->id,
        ]);

        $this->activity->execute(
            'period.closed',
            'period_cutoff',
            (string) $cutoff->id,
            $request->user('admin'),
            $request,
            period: $cutoff->periode_semester,
            afterState: $cutoff->only(['closed_at']),
        );

        return back()->with('success', 'Periode ditutup. Data semester ini kini terkunci dari perubahan manual dan impor baru.');
    }

    public function storeProgress(StoreKompenResponHubStudentProgressRequest $request, KompenResponHubStudent $student): RedirectResponse
    {
        $this->ensurePeriodIsOpen($student->periode_semester);
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
        $this->ensurePeriodIsOpen($student->periode_semester);
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
        $this->ensurePeriodIsOpen($detail->student?->periode_semester);
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

    public function storeWarning(StoreKompenResponHubWarningLetterRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $student = KompenResponHubStudent::query()->with(['progress', 'summaryOverride'])->findOrFail($validated['student_id']);
        $this->ensurePeriodIsOpen($student->periode_semester);
        $cutoff = KompenResponHubPeriodCutoff::query()->where('periode_semester', $student->periode_semester)->first();
        abort_if($cutoff === null, 422, 'Tetapkan batas waktu periode sebelum membuat SP.');
        abort_if($this->studentSnapshot($student)['sisa_hutang_jam'] <= 0, 422, 'SP tidak dapat dibuat karena mahasiswa tidak memiliki sisa jam.');

        $warning = KompenResponHubWarningLetter::query()->firstOrNew(['cutoff_id' => $cutoff->id, 'current_student_id' => $student->id]);
        $before = $warning->exists ? $warning->only(['letter_status', 'resolution', 'reason']) : null;
        $warning->fill([
            ...$this->studentIdentity($student),
            'cutoff_id' => $cutoff->id,
            'current_student_id' => $student->id,
            'nama_mahasiswa' => $student->nama_mahasiswa,
            'classification' => now()->greaterThanOrEqualTo($cutoff->deadline_at) ? 'fixed' : 'temporary',
            'letter_status' => KompenResponHubWarningLetter::LetterStatusDraft,
            'resolution' => 'outstanding',
            'snapshot' => $this->studentSnapshot($student),
            'reason' => $validated['reason'],
            'cancelled_at' => null,
            'created_by_admin_id' => $warning->created_by_admin_id ?? $request->user('admin')?->id,
            'updated_by_admin_id' => $request->user('admin')?->id,
        ])->save();

        $this->activity->execute('warning.drafted', 'warning_letter', (string) $warning->id, $request->user('admin'), $request, $student->nim, $student->periode_semester, $student->kelas, $validated['reason'], $before, $warning->only(['classification', 'letter_status', 'resolution', 'snapshot', 'reason']), subjectName: $student->nama_mahasiswa);

        return back()->with('success', 'Draft SP berhasil dibuat.');
    }

    public function updateWarning(UpdateKompenResponHubWarningLetterRequest $request, KompenResponHubWarningLetter $warning): RedirectResponse
    {
        $this->ensurePeriodIsOpen($warning->periode_semester);
        $validated = $request->validated();
        $before = $warning->only(['letter_status', 'resolution', 'reason', 'issued_at', 'cancelled_at']);
        $warning->fill([
            'letter_status' => $validated['letter_status'],
            'reason' => $validated['reason'],
            'issued_at' => $validated['letter_status'] === KompenResponHubWarningLetter::LetterStatusIssued ? now() : $warning->issued_at,
            'cancelled_at' => $validated['letter_status'] === KompenResponHubWarningLetter::LetterStatusCancelled ? now() : null,
            'updated_by_admin_id' => $request->user('admin')?->id,
        ])->save();

        $this->activity->execute("warning.{$validated['letter_status']}", 'warning_letter', (string) $warning->id, $request->user('admin'), $request, $warning->nim, $warning->periode_semester, $warning->kelas, $validated['reason'], $before, $warning->only(['letter_status', 'resolution', 'reason', 'issued_at', 'cancelled_at']), subjectName: $warning->nama_mahasiswa);

        return back()->with('success', 'Status SP berhasil diperbarui.');
    }

    public function destroyWarning(DestroyKompenResponHubWarningLetterRequest $request, KompenResponHubWarningLetter $warning): RedirectResponse
    {
        $this->ensurePeriodIsOpen($warning->periode_semester);
        $validated = $request->validated();
        $before = $warning->only(['letter_status', 'resolution', 'reason', 'cancelled_at']);
        $warning->update([
            'letter_status' => KompenResponHubWarningLetter::LetterStatusCancelled,
            'reason' => $validated['reason'],
            'cancelled_at' => now(),
            'updated_by_admin_id' => $request->user('admin')?->id,
        ]);

        $this->activity->execute('warning.cancelled', 'warning_letter', (string) $warning->id, $request->user('admin'), $request, $warning->nim, $warning->periode_semester, $warning->kelas, $validated['reason'], $before, $warning->only(['letter_status', 'resolution', 'reason', 'cancelled_at']), subjectName: $warning->nama_mahasiswa);

        return back()->with('success', 'SP dibatalkan. Data kembali ke daftar kandidat dan riwayat pembatalan tetap tersimpan.');
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
    ): void {
        $classification = $cutoff->deadline_at->isPast() ? 'fixed' : 'temporary';

        KompenResponHubWarningLetter::query()
            ->where('cutoff_id', $cutoff->id)
            ->where('classification', '!=', $classification)
            ->each(function (KompenResponHubWarningLetter $warning) use ($classification, $cutoff, $request): void {
                $before = $warning->only(['classification']);
                $warning->update([
                    'classification' => $classification,
                    'updated_by_admin_id' => $request->user('admin')?->id,
                ]);

                $this->activity->execute(
                    "warning.classification_{$classification}",
                    'warning_letter',
                    (string) $warning->id,
                    $request->user('admin'),
                    $request,
                    $warning->nim,
                    $cutoff->periode_semester,
                    $warning->kelas,
                    'Klasifikasi diselaraskan setelah batas waktu periode diperbaiki.',
                    $before,
                    $warning->only(['classification']),
                    subjectName: $warning->nama_mahasiswa,
                );
            });
    }

    private function ensurePeriodIsOpen(?string $period): void
    {
        abort_if($period === null || $this->periodLock->isClosed($period), 422, 'Periode telah ditutup dan data tidak dapat diubah.');
    }
}
