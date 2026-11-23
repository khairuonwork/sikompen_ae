<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Actions\KompenResponHub\SikompenAdminAccess;
use App\Http\Requests\PermanentlyDestroyKompenResponHubWarningLetterArtifactRequest;
use App\Http\Requests\UpdateKompenResponHubImportRequest;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubWarningLetter;
use App\Models\KompenResponHubWarningLetterArtifact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KompenResponHubWarningLetterArtifactController extends Controller
{
    public function update(
        UpdateKompenResponHubImportRequest $request,
        SikompenAdminAccess $access,
        RecordKompenResponHubActivity $activity,
        KompenResponHubWarningLetterArtifact $artifact,
    ): RedirectResponse {
        $actor = $access->actor($request);
        $admin = KompenResponHubAdmin::query()->find($actor['id']);
        $displayFilename = $request->string('display_filename')->toString();
        $previousFilename = $artifact->display_filename;

        $artifact->update(['display_filename' => $displayFilename]);

        $activity->execute(
            'warning_artifact.renamed',
            'warning_letter_artifact',
            (string) $artifact->id,
            $admin,
            $request,
            period: $artifact->cutoff?->periode_semester,
            reason: 'Nama tampilan artifact Surat Peringatan diperbarui.',
            beforeState: ['display_filename' => $previousFilename],
            afterState: ['display_filename' => $displayFilename],
            subjectName: $displayFilename,
        );

        return to_route('admin.kompen-respon.index', ['tab' => 'files'])
            ->with('success', "Nama artifact diperbarui menjadi {$displayFilename}.");
    }

    public function download(KompenResponHubWarningLetterArtifact $artifact): StreamedResponse
    {
        $disk = Storage::disk('local');

        abort_unless($disk->exists($artifact->stored_path), 404, 'Artifact Surat Peringatan tidak ditemukan pada penyimpanan.');
        $fileHash = hash_file('sha256', $disk->path($artifact->stored_path));
        abort_unless(
            is_string($fileHash) && hash_equals($artifact->file_hash, $fileHash),
            409,
            'Artifact Surat Peringatan tidak lolos pemeriksaan integritas.',
        );

        return $disk->download($artifact->stored_path, $artifact->display_filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function destroy(
        PermanentlyDestroyKompenResponHubWarningLetterArtifactRequest $request,
        SikompenAdminAccess $access,
        RecordKompenResponHubActivity $activity,
        KompenResponHubWarningLetterArtifact $artifact,
    ): RedirectResponse {
        $actor = $access->actor($request);
        $admin = KompenResponHubAdmin::query()->find($actor['id']);

        /** @var array{id: int, cutoff_id: int, periode_semester: string, display_filename: string, revision: int, reopened_for_correction: bool, cancelled_warning_count: int} $deletedArtifact */
        $deletedArtifact = DB::connection(config('kompen-respon-hub.database_connection'))
            ->transaction(function () use ($artifact, $actor): array {
                $lockedArtifact = KompenResponHubWarningLetterArtifact::query()
                    ->lockForUpdate()
                    ->findOrFail($artifact->id);
                $cutoff = KompenResponHubPeriodCutoff::query()
                    ->lockForUpdate()
                    ->findOrFail($lockedArtifact->cutoff_id);
                $latestArtifact = KompenResponHubWarningLetterArtifact::query()
                    ->where('cutoff_id', $cutoff->id)
                    ->lockForUpdate()
                    ->orderByDesc('revision')
                    ->first();
                $isLatestArtifact = $latestArtifact?->id === $lockedArtifact->id;
                $reopenedForCorrection = $isLatestArtifact && $cutoff->closed_at !== null;
                $cancelledWarningCount = 0;

                if ($reopenedForCorrection) {
                    $warnings = KompenResponHubWarningLetter::query()
                        ->where('cutoff_id', $cutoff->id)
                        ->where('classification', 'fixed')
                        ->where('letter_status', KompenResponHubWarningLetter::LetterStatusIssued)
                        ->where('resolution', 'outstanding')
                        ->lockForUpdate()
                        ->get();

                    foreach ($warnings as $warning) {
                        $warning->update([
                            'letter_status' => KompenResponHubWarningLetter::LetterStatusCancelled,
                            'reason' => 'Finalisasi dibuka kembali karena artifact XLSX dihapus.',
                            'cancelled_at' => now(),
                            'cancellation_source' => KompenResponHubWarningLetter::CancellationSourceArtifactDeletion,
                            'updated_by_admin_id' => $actor['id'],
                        ]);
                    }

                    $cancelledWarningCount = $warnings->count();
                    $cutoff->update([
                        'closed_at' => null,
                        'closed_by_admin_id' => null,
                    ]);
                }

                $disk = Storage::disk('local');
                if (str_starts_with($lockedArtifact->stored_path, 'kompen-respon-hub/warning-artifacts/')
                    && $disk->exists($lockedArtifact->stored_path)) {
                    abort_unless(
                        $disk->delete($lockedArtifact->stored_path),
                        500,
                        'Artifact XLSX tidak dapat dihapus dari penyimpanan.',
                    );
                }

                $snapshot = [
                    'id' => $lockedArtifact->id,
                    'cutoff_id' => $cutoff->id,
                    'periode_semester' => $cutoff->periode_semester,
                    'display_filename' => $lockedArtifact->display_filename,
                    'revision' => $lockedArtifact->revision,
                    'reopened_for_correction' => $reopenedForCorrection,
                    'cancelled_warning_count' => $cancelledWarningCount,
                ];

                $lockedArtifact->delete();

                return $snapshot;
            });

        $activity->execute(
            'warning_artifact.permanently_deleted',
            'warning_letter_artifact',
            (string) $deletedArtifact['id'],
            $admin,
            $request,
            period: $deletedArtifact['periode_semester'],
            reason: 'Artifact Surat Peringatan dihapus permanen dari List File.',
            beforeState: [
                'display_filename' => $deletedArtifact['display_filename'],
                'revision' => $deletedArtifact['revision'],
            ],
            metadata: [
                'cutoff_id' => $deletedArtifact['cutoff_id'],
                'reopened_for_correction' => $deletedArtifact['reopened_for_correction'],
                'cancelled_warning_count' => $deletedArtifact['cancelled_warning_count'],
            ],
            subjectName: $deletedArtifact['display_filename'],
        );

        $reopenSummary = $deletedArtifact['reopened_for_correction']
            ? " Finalisasi periode dibuka kembali untuk koreksi; {$deletedArtifact['cancelled_warning_count']} SP ditarik dan perlu difinalisasi ulang."
            : '';

        return to_route('admin.kompen-respon.index', ['tab' => 'files'])
            ->with('success', "Artifact {$deletedArtifact['display_filename']} dihapus permanen.{$reopenSummary}");
    }
}
