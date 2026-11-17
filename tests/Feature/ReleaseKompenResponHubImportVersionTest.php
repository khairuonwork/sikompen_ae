<?php

use App\Actions\KompenResponHub\KompenResponHubDataQuery;
use App\Models\KompenResponHubActiveImport;
use App\Models\KompenResponHubActivityLog;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubStudent;
use App\Models\KompenResponHubWarningLetter;
use App\Models\KompenResponHubWarningPeriodSelection;

test('an admin can release an active workbook without changing finalized warning history', function (): void {
    $admin = KompenResponHubAdmin::factory()->create();
    $import = activeWorkbookImport();
    $cutoff = KompenResponHubPeriodCutoff::query()->create([
        'periode_semester' => $import->periode_semester,
        'deadline_at' => now()->subDay(),
        'timezone' => 'Asia/Jakarta',
    ]);
    $warning = KompenResponHubWarningLetter::query()->create([
        'cutoff_id' => $cutoff->id,
        'nim' => '261000001',
        'periode_semester' => $import->periode_semester,
        'kelas' => '1AEA1',
        'nama_mahasiswa' => 'Alya Pratama',
        'classification' => 'fixed',
        'letter_status' => KompenResponHubWarningLetter::LetterStatusCancelled,
        'resolution' => 'outstanding',
        'snapshot' => ['sisa_hutang_jam' => 1],
        'cancellation_source' => KompenResponHubWarningLetter::CancellationSourceManualRollback,
    ]);

    $this->actingAs($admin, 'admin')
        ->delete("/admin/kompen-respon/imports/{$import->id}/activation")
        ->assertRedirect('/admin?tab=files')
        ->assertSessionHas('success');

    expect(KompenResponHubActiveImport::query()
        ->where('periode_semester', $import->periode_semester)
        ->doesntExist())->toBeTrue()
        ->and($warning->refresh()->letter_status)->toBe(KompenResponHubWarningLetter::LetterStatusCancelled)
        ->and(app(KompenResponHubDataQuery::class)
            ->rolledBackWarnings(['periode_semester' => $import->periode_semester])
            ->pluck('id')
            ->all())->toContain($warning->id)
        ->and(KompenResponHubImportAuditLog::query()
            ->where('event_type', KompenResponHubImportAuditLog::EVENT_RELEASE)
            ->where('source_import_id', $import->id)
            ->exists())->toBeTrue()
        ->and(KompenResponHubActivityLog::query()
            ->where('event_type', 'import.version_released')
            ->where('subject_reference', (string) $import->id)
            ->exists())->toBeTrue();
});

test('an admin cannot release a workbook selected as the active SP period', function (): void {
    $admin = KompenResponHubAdmin::factory()->create();
    $import = activeWorkbookImport();
    $cutoff = KompenResponHubPeriodCutoff::query()->create([
        'periode_semester' => $import->periode_semester,
        'deadline_at' => now()->subDay(),
        'timezone' => 'Asia/Jakarta',
    ]);
    KompenResponHubWarningPeriodSelection::query()->create([
        'selection_key' => KompenResponHubWarningPeriodSelection::ManagedWarningPeriodSelectionKey,
        'sikompen_period_cutoff_id' => $cutoff->id,
        'selected_at' => now(),
    ]);

    $this->actingAs($admin, 'admin')
        ->delete("/admin/kompen-respon/imports/{$import->id}/activation")
        ->assertRedirect('/admin?tab=files')
        ->assertSessionHas('error', 'Workbook tidak dapat dilepas karena periodenya sedang dipilih sebagai periode SP aktif. Pilih periode SP aktif lain terlebih dahulu.');

    expect(KompenResponHubActiveImport::query()
        ->where('kompen_respon_hub_import_id', $import->id)
        ->exists())->toBeTrue();
});

test('an admin cannot release an active workbook while its period has an active warning', function (): void {
    $admin = KompenResponHubAdmin::factory()->create();
    $import = activeWorkbookImport();
    $cutoff = KompenResponHubPeriodCutoff::query()->create([
        'periode_semester' => $import->periode_semester,
        'deadline_at' => now()->subDay(),
        'timezone' => 'Asia/Jakarta',
    ]);
    KompenResponHubWarningLetter::query()->create([
        'cutoff_id' => $cutoff->id,
        'nim' => '261000001',
        'periode_semester' => $import->periode_semester,
        'kelas' => '1AEA1',
        'nama_mahasiswa' => 'Alya Pratama',
        'classification' => 'fixed',
        'letter_status' => KompenResponHubWarningLetter::LetterStatusIssued,
        'resolution' => 'outstanding',
        'snapshot' => ['sisa_hutang_jam' => 1],
    ]);

    $this->actingAs($admin, 'admin')
        ->delete("/admin/kompen-respon/imports/{$import->id}/activation")
        ->assertRedirect('/admin?tab=files')
        ->assertSessionHas('error', 'Workbook tidak dapat dilepas karena periode ini masih memiliki SP aktif. Rollback atau selesaikan SP terlebih dahulu.');

    expect(KompenResponHubActiveImport::query()
        ->where('periode_semester', $import->periode_semester)
        ->where('kompen_respon_hub_import_id', $import->id)
        ->exists())->toBeTrue()
        ->and(KompenResponHubImportAuditLog::query()
            ->where('event_type', KompenResponHubImportAuditLog::EVENT_RELEASE)
            ->doesntExist())->toBeTrue();
});

test('releasing a workbook hides its operational records while preserving the historical rows', function (): void {
    $admin = KompenResponHubAdmin::factory()->create();
    $import = activeWorkbookImport();
    $student = KompenResponHubStudent::query()->create([
        'kompen_respon_hub_import_id' => $import->id,
        'nim' => '261000001',
        'periode_semester' => $import->periode_semester,
        'nama_mahasiswa' => 'Alya Pratama',
        'kelas' => '1AEA1',
        'tingkat' => 1,
        'total_jam_terlambat' => 0,
        'total_jam_sakit' => 0,
        'total_jam_izin' => 0,
        'total_jam_bolos' => 0,
        'total_kompensasi_jam' => 1,
        'total_responsi_jam' => 0,
        'total_hutang_jam' => 1,
        'kompensasi_dikerjakan_jam' => 0,
        'sisa_hutang_jam' => 1,
    ]);

    expect(app(KompenResponHubDataQuery::class)->students([])->pluck('id')->all())->toContain($student->id);

    $this->actingAs($admin, 'admin')
        ->delete("/admin/kompen-respon/imports/{$import->id}/activation")
        ->assertRedirect('/admin?tab=files');

    expect(KompenResponHubStudent::query()->find($student->id))->not->toBeNull()
        ->and(app(KompenResponHubDataQuery::class)->students([])->doesntExist())->toBeTrue();
});

function activeWorkbookImport(string $period = '2026/2027 Gasal'): KompenResponHubImport
{
    $import = KompenResponHubImport::query()->create([
        'periode_semester' => $period,
        'original_filename' => 'kompen-respon.xlsx',
        'display_filename' => 'kompen-respon.xlsx',
        'stored_path' => 'kompen-respon-hub/imports/kompen-respon.xlsx',
        'file_hash' => str_repeat('a', 64),
        'class_count' => 1,
        'student_count' => 1,
        'detail_count' => 1,
        'imported_at' => now(),
    ]);

    KompenResponHubActiveImport::query()->create([
        'periode_semester' => $period,
        'kompen_respon_hub_import_id' => $import->id,
        'activated_at' => now(),
    ]);

    return $import;
}
