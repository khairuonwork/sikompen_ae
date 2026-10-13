<?php

use App\Models\KompenResponHubActiveImport;
use App\Models\KompenResponHubActivityLog;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubWarningLetter;

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
        ->and(KompenResponHubImportAuditLog::query()
            ->where('event_type', KompenResponHubImportAuditLog::EVENT_RELEASE)
            ->where('source_import_id', $import->id)
            ->exists())->toBeTrue()
        ->and(KompenResponHubActivityLog::query()
            ->where('event_type', 'import.version_released')
            ->where('subject_reference', (string) $import->id)
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
