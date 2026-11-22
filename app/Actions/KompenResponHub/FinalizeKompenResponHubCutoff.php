<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubStudent;
use App\Models\KompenResponHubWarningLetter;
use Illuminate\Http\Request;

class FinalizeKompenResponHubCutoff
{
    public function __construct(private RecordKompenResponHubActivity $activity) {}

    /**
     * Issue warning letters from the current outstanding debt snapshot.
     *
     * The caller owns the transaction and marks the cutoff as final only after
     * its official artifact has been stored successfully.
     *
     * @return array{issued_warning_count: int, synchronized_warning_count: int}
     */
    public function execute(
        KompenResponHubPeriodCutoff $cutoff,
        ?KompenResponHubAdmin $actor = null,
        ?Request $request = null,
    ): array {
        $issuedWarningCount = 0;
        $synchronizedWarningCount = 0;

        foreach ($this->finalizationChanges($cutoff, $actor) as $change) {
            $warning = $change['warning'];
            if ($warning->isDirty()) {
                $warning->save();
            }

            if ($change['synchronized']) {
                $synchronizedWarningCount++;
                $this->activity->execute(
                    'warning.classification_fixed',
                    'warning_letter',
                    (string) $warning->id,
                    $actor,
                    $request,
                    $warning->nim,
                    $cutoff->periode_semester,
                    $warning->kelas,
                    'Status SP ditetapkan saat finalisasi semester.',
                    $change['before'],
                    $warning->only(['classification', 'letter_status', 'issued_at']),
                    ['cutoff_id' => $cutoff->id],
                    $warning->nama_mahasiswa,
                );
            }

            if ($change['issued']) {
                $issuedWarningCount++;
                $this->recordIssuedWarning($warning, $cutoff, $actor, $request, $change['before']);
            }
        }

        return ['issued_warning_count' => $issuedWarningCount, 'synchronized_warning_count' => $synchronizedWarningCount];
    }

    public function projectedRecipientCount(KompenResponHubPeriodCutoff $cutoff): int
    {
        return iterator_count($this->projectedRecipients($cutoff));
    }

    /** @return \Generator<int, KompenResponHubWarningLetter> */
    public function projectedRecipients(KompenResponHubPeriodCutoff $cutoff): \Generator
    {
        foreach ($this->finalizationChanges($cutoff) as $change) {
            $warning = $change['warning'];
            $criteria = KompenResponHubWarningLetter::ArtifactRecipientAttributes;
            if ($warning->only(array_keys($criteria)) === $criteria) {
                yield $warning;
            }
        }
    }

    /**
     * Calculate the final state in memory. Only execute() persists changes and records audit events.
     *
     * @return \Generator<int, array{warning: KompenResponHubWarningLetter, before: array<string, mixed>|null, synchronized: bool, issued: bool}>
     */
    private function finalizationChanges(KompenResponHubPeriodCutoff $cutoff, ?KompenResponHubAdmin $actor = null): \Generator
    {
        $existing = KompenResponHubWarningLetter::query()->where('cutoff_id', $cutoff->id);
        $warnings = (clone $existing)->with(['student' => fn ($query) => $query
            ->fromActiveImportReference()->where('periode_semester', $cutoff->periode_semester)
            ->with(['progress', 'summaryOverride'])])->lazyById(200);

        foreach ($warnings as $warning) {
            $before = $warning->only(['classification', 'letter_status', 'resolution', 'snapshot', 'issued_at', 'cancelled_at', 'cancellation_source']);
            $wasIssued = $warning->letter_status === KompenResponHubWarningLetter::LetterStatusIssued;
            $synchronized = $warning->classification !== 'fixed' && ($wasIssued || $warning->letter_status === KompenResponHubWarningLetter::LetterStatusDraft);
            if ($synchronized) {
                $warning->classification = 'fixed';
                $warning->letter_status = KompenResponHubWarningLetter::LetterStatusIssued;
            }

            if ($warning->student !== null) {
                $snapshot = $this->snapshot($warning->student);
                $warning->snapshot = $snapshot;
                $warning->resolution = $snapshot['sisa_hutang_jam'] > 0 ? 'outstanding' : 'completed';
                $manuallyCancelled = $warning->letter_status === KompenResponHubWarningLetter::LetterStatusCancelled
                    && $warning->cancellation_source !== KompenResponHubWarningLetter::CancellationSourceFinalizationRollback;
                if ($warning->resolution === 'outstanding' && ! $manuallyCancelled) {
                    $warning->letter_status = KompenResponHubWarningLetter::LetterStatusIssued;
                }
            }

            $issued = ! $wasIssued && $warning->letter_status === KompenResponHubWarningLetter::LetterStatusIssued;
            if ($issued) {
                $warning->issued_at = now()->toImmutable();
                $warning->cancelled_at = null;
                $warning->cancellation_source = null;
                if (! $synchronized) {
                    $warning->reason = 'SP diterbitkan otomatis saat periode difiksasi.';
                }
            }
            if ($warning->isDirty()) {
                $warning->updated_by_admin_id = $actor?->id;
            }

            yield compact('warning', 'before', 'synchronized', 'issued');
        }

        $students = KompenResponHubStudent::query()->fromActiveImportReference()
            ->where('periode_semester', $cutoff->periode_semester)
            ->whereNotIn('id', $existing->whereNotNull('current_student_id')->select('current_student_id'))
            ->with(['progress', 'summaryOverride'])->lazyById(200);
        foreach ($students as $student) {
            $snapshot = $this->snapshot($student);
            if ($snapshot['sisa_hutang_jam'] <= 0) {
                continue;
            }
            $warning = new KompenResponHubWarningLetter([
                'cutoff_id' => $cutoff->id,
                'current_student_id' => $student->id,
                'nim' => $student->nim,
                'nama_mahasiswa' => $student->nama_mahasiswa,
                'periode_semester' => $student->periode_semester,
                'kelas' => $student->kelas,
                'classification' => 'fixed',
                'letter_status' => KompenResponHubWarningLetter::LetterStatusIssued,
                'resolution' => 'outstanding',
                'snapshot' => $snapshot,
                'reason' => 'SP diterbitkan otomatis saat periode difiksasi.',
                'issued_at' => now(),
                'created_by_admin_id' => $actor?->id,
                'updated_by_admin_id' => $actor?->id,
            ]);

            yield ['warning' => $warning, 'before' => null, 'synchronized' => false, 'issued' => true];
        }
    }

    /** @param array<string, mixed>|null $before */
    private function recordIssuedWarning(
        KompenResponHubWarningLetter $warning,
        KompenResponHubPeriodCutoff $cutoff,
        ?KompenResponHubAdmin $actor,
        ?Request $request,
        ?array $before = null,
    ): void {
        $this->activity->execute(
            'warning.issued',
            'warning_letter',
            (string) $warning->id,
            $actor,
            $request,
            $warning->nim,
            $warning->periode_semester,
            $warning->kelas,
            'SP diterbitkan otomatis saat periode difiksasi.',
            $before,
            $warning->only(['classification', 'letter_status', 'resolution', 'snapshot', 'reason', 'issued_at']),
            ['cutoff_id' => $cutoff->id, 'source' => 'cutoff_finalization'],
            $warning->nama_mahasiswa,
        );
    }

    /** @return array<string, float> */
    private function snapshot(KompenResponHubStudent $student): array
    {
        $totalKompen = (float) $student->effective_total_kompensasi_jam;
        $totalResponsi = (float) $student->effective_total_responsi_jam;
        $workedKompen = (float) $student->effective_kompensasi_dikerjakan_jam;
        $workedResponsi = (float) $student->effective_responsi_dikerjakan_jam;

        return [
            'total_kompensasi_jam' => $totalKompen,
            'total_responsi_jam' => $totalResponsi,
            'kompensasi_dikerjakan_jam' => $workedKompen,
            'responsi_dikerjakan_jam' => $workedResponsi,
            'sisa_hutang_jam' => max(0, $totalKompen + $totalResponsi - $workedKompen - $workedResponsi),
        ];
    }
}
