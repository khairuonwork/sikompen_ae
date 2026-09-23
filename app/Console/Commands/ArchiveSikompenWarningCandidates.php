<?php

namespace App\Console\Commands;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubStudent;
use App\Models\KompenResponHubWarningLetter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sikompen:archive-warning-candidates')]
#[Description('Archive outstanding Kompen candidates after each period cutoff.')]
class ArchiveSikompenWarningCandidates extends Command
{
    public function __construct(private RecordKompenResponHubActivity $activity)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $createdCount = 0;

        KompenResponHubPeriodCutoff::query()
            ->where('deadline_at', '<=', now())
            ->orderBy('id')
            ->each(function (KompenResponHubPeriodCutoff $cutoff) use (&$createdCount): void {
                KompenResponHubWarningLetter::query()
                    ->where('cutoff_id', $cutoff->id)
                    ->where('classification', 'temporary')
                    ->chunkById(200, function ($warnings) use ($cutoff): void {
                        foreach ($warnings as $warning) {
                            $before = $warning->only(['classification']);
                            $warning->update(['classification' => 'fixed']);
                            $this->activity->execute(
                                'warning.classification_fixed',
                                'warning_letter',
                                (string) $warning->id,
                                null,
                                null,
                                $warning->nim,
                                $warning->periode_semester,
                                $warning->kelas,
                                'Batas waktu periode telah terlewati.',
                                $before,
                                $warning->only(['classification']),
                                ['cutoff_id' => $cutoff->id],
                            );
                        }
                    });

                KompenResponHubStudent::query()
                    ->where('periode_semester', $cutoff->periode_semester)
                    ->with(['progress', 'summaryOverride'])
                    ->chunkById(200, function ($students) use ($cutoff, &$createdCount): void {
                        foreach ($students as $student) {
                            $snapshot = $this->snapshot($student);
                            if ($snapshot['sisa_hutang_jam'] <= 0) {
                                continue;
                            }

                            $warning = KompenResponHubWarningLetter::query()->firstOrCreate(
                                ['cutoff_id' => $cutoff->id, 'current_student_id' => $student->id],
                                [
                                    'nim' => $student->nim,
                                    'periode_semester' => $student->periode_semester,
                                    'kelas' => $student->kelas,
                                    'nama_mahasiswa' => $student->nama_mahasiswa,
                                    'classification' => 'fixed',
                                    'letter_status' => KompenResponHubWarningLetter::LetterStatusNotCreated,
                                    'resolution' => 'outstanding',
                                    'snapshot' => $snapshot,
                                ],
                            );

                            if ($warning->wasRecentlyCreated) {
                                $createdCount++;
                                $this->activity->execute('warning.archived', 'warning_letter', (string) $warning->id, null, null, $student->nim, $student->periode_semester, $student->kelas, metadata: ['cutoff_id' => $cutoff->id, 'snapshot' => $snapshot]);
                            }
                        }
                    });
            });

        $this->components->info("{$createdCount} kandidat Fixed diarsipkan.");

        return self::SUCCESS;
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
