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
     * Finalize the current outstanding debt as issued warning letters without
     * locking future corrections, imports, or cutoff changes.
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

        KompenResponHubWarningLetter::query()
            ->where('cutoff_id', $cutoff->id)
            ->whereIn('letter_status', [
                KompenResponHubWarningLetter::LetterStatusDraft,
                KompenResponHubWarningLetter::LetterStatusIssued,
            ])
            ->where('classification', '!=', 'fixed')
            ->chunkById(200, function ($warnings) use ($cutoff, $actor, $request, &$issuedWarningCount, &$synchronizedWarningCount): void {
                foreach ($warnings as $warning) {
                    $before = $warning->only(['classification', 'letter_status', 'issued_at']);
                    $updates = [
                        'classification' => 'fixed',
                        'updated_by_admin_id' => $actor?->id,
                    ];

                    $wasDraft = $warning->letter_status === KompenResponHubWarningLetter::LetterStatusDraft;
                    if ($wasDraft) {
                        $updates['letter_status'] = KompenResponHubWarningLetter::LetterStatusIssued;
                        $updates['issued_at'] = now();
                    }

                    $warning->update($updates);
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
                        'SP diselaraskan saat periode difiksasi.',
                        $before,
                        $warning->only(['classification', 'letter_status', 'issued_at']),
                        ['cutoff_id' => $cutoff->id],
                        $warning->nama_mahasiswa,
                    );

                    if ($wasDraft) {
                        $issuedWarningCount++;
                        $this->recordIssuedWarning($warning, $cutoff, $actor, $request, $before);
                    }
                }
            });

        KompenResponHubStudent::query()
            ->where('periode_semester', $cutoff->periode_semester)
            ->with(['progress', 'summaryOverride'])
            ->chunkById(200, function ($students) use ($cutoff, $actor, $request, &$issuedWarningCount): void {
                foreach ($students as $student) {
                    $snapshot = $this->snapshot($student);
                    $isOutstanding = $snapshot['sisa_hutang_jam'] > 0;
                    $warning = KompenResponHubWarningLetter::query()
                        ->where('cutoff_id', $cutoff->id)
                        ->where('current_student_id', $student->id)
                        ->first();

                    if ($warning === null && ! $isOutstanding) {
                        continue;
                    }

                    if ($warning === null) {
                        $warning = KompenResponHubWarningLetter::query()->create([
                            'cutoff_id' => $cutoff->id,
                            'current_student_id' => $student->id,
                            'nim' => $student->nim,
                            'periode_semester' => $student->periode_semester,
                            'kelas' => $student->kelas,
                            'nama_mahasiswa' => $student->nama_mahasiswa,
                            'classification' => 'fixed',
                            'letter_status' => KompenResponHubWarningLetter::LetterStatusIssued,
                            'resolution' => 'outstanding',
                            'snapshot' => $snapshot,
                            'reason' => 'SP diterbitkan otomatis saat periode difiksasi.',
                            'issued_at' => now(),
                            'created_by_admin_id' => $actor?->id,
                            'updated_by_admin_id' => $actor?->id,
                        ]);
                        $issuedWarningCount++;
                        $this->recordIssuedWarning($warning, $cutoff, $actor, $request);

                        continue;
                    }

                    $resolution = $isOutstanding ? 'outstanding' : 'completed';
                    $needsIssuance = $isOutstanding
                        && in_array($warning->letter_status, [
                            KompenResponHubWarningLetter::LetterStatusNotCreated,
                            KompenResponHubWarningLetter::LetterStatusDraft,
                        ], true);
                    $isPeriodFinalizationRollback = $warning->letter_status === KompenResponHubWarningLetter::LetterStatusCancelled
                        && $warning->cancellation_source === KompenResponHubWarningLetter::CancellationSourceFinalizationRollback;
                    $needsReissuance = $isOutstanding && $isPeriodFinalizationRollback;
                    if ($warning->snapshot !== $snapshot || $warning->resolution !== $resolution || $needsIssuance || $needsReissuance) {
                        $before = $warning->only(['letter_status', 'resolution', 'snapshot', 'issued_at', 'cancelled_at', 'cancellation_source']);
                        $updates = [
                            'snapshot' => $snapshot,
                            'resolution' => $resolution,
                            'updated_by_admin_id' => $actor?->id,
                        ];

                        if ($needsIssuance || $needsReissuance) {
                            $updates['letter_status'] = KompenResponHubWarningLetter::LetterStatusIssued;
                            $updates['issued_at'] = now();
                            $updates['reason'] = 'SP diterbitkan otomatis saat periode difiksasi.';
                            $updates['cancelled_at'] = null;
                            $updates['cancellation_source'] = null;
                        }

                        $warning->update($updates);

                        if ($needsIssuance || $needsReissuance) {
                            $issuedWarningCount++;
                            $this->recordIssuedWarning($warning, $cutoff, $actor, $request, $before);
                        }
                    }
                }
            });

        return [
            'issued_warning_count' => $issuedWarningCount,
            'synchronized_warning_count' => $synchronizedWarningCount,
        ];
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
        $totalKompen = (float) ($student->summaryOverride?->total_kompensasi_jam ?? $student->total_kompensasi_jam);
        $totalResponsi = (float) ($student->summaryOverride?->total_responsi_jam ?? $student->total_responsi_jam);
        $workedKompen = (float) ($student->progress?->kompensasi_dikerjakan_jam ?? 0);
        $workedResponsi = (float) ($student->progress?->responsi_dikerjakan_jam ?? 0);

        return [
            'total_kompensasi_jam' => $totalKompen,
            'total_responsi_jam' => $totalResponsi,
            'kompensasi_dikerjakan_jam' => $workedKompen,
            'responsi_dikerjakan_jam' => $workedResponsi,
            'sisa_hutang_jam' => max(0, $totalKompen + $totalResponsi - $workedKompen - $workedResponsi),
        ];
    }
}
