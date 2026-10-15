<?php

use App\Actions\KompenResponHub\BuildKompenResponHubDataConsistencyReport;
use App\Jobs\ProcessKompenResponHubImport;
use App\Models\KompenResponHubActiveImport;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportTask;
use App\Models\KompenResponHubStudent;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

test('an admin can restore a workbook from the recycle bin without deleting its private file', function (): void {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();
    $path = 'kompen-respon-hub/imports/recycle-bin-source.xlsx';
    Storage::disk('local')->put($path, 'private workbook');
    $import = KompenResponHubImport::query()->create([
        'periode_semester' => '2026/2027 Gasal',
        'original_filename' => 'recycle-bin-source.xlsx',
        'display_filename' => 'recycle-bin-source.xlsx',
        'stored_path' => $path,
        'file_hash' => str_repeat('a', 64),
        'class_count' => 1,
        'student_count' => 1,
        'detail_count' => 0,
        'imported_at' => now(),
    ]);

    $this->actingAs($admin, 'admin')
        ->delete("/admin/kompen-respon/imports/{$import->id}")
        ->assertRedirect('/admin?tab=files')
        ->assertSessionHas('success');

    expect(KompenResponHubImport::query()->find($import->id))->toBeNull()
        ->and(KompenResponHubImport::onlyTrashed()->find($import->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists($path))->toBeTrue();

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/imports/{$import->id}/restore")
        ->assertRedirect('/admin?tab=files')
        ->assertSessionHas('success');

    expect(KompenResponHubImport::query()->find($import->id))->not->toBeNull();
});

test('a failed workbook task removes its private source and instructs the admin to upload again', function (): void {
    Storage::fake('local');
    $path = 'kompen-respon-hub/imports/failed-source.xlsx';
    Storage::disk('local')->put($path, 'private workbook');
    $task = KompenResponHubImportTask::factory()->create([
        'status' => KompenResponHubImportTask::STATUS_PROCESSING,
        'stored_path' => $path,
    ]);

    (new ProcessKompenResponHubImport($task->id))->failed(new RuntimeException('Worker stopped.'));

    expect($task->refresh()->status)->toBe(KompenResponHubImportTask::STATUS_FAILED)
        ->and($task->error_message)->toContain('unggah ulang')
        ->and(Storage::disk('local')->exists($path))->toBeFalse();
});

test('the file list reports roster consistency and exposes recycled versions', function (): void {
    $admin = KompenResponHubAdmin::factory()->create();
    $import = KompenResponHubImport::query()->create([
        'periode_semester' => '2026/2027 Gasal',
        'original_filename' => 'recycled.xlsx',
        'display_filename' => 'recycled.xlsx',
        'stored_path' => 'kompen-respon-hub/imports/recycled.xlsx',
        'file_hash' => str_repeat('b', 64),
        'class_count' => 1,
        'student_count' => 1,
        'detail_count' => 0,
        'imported_at' => now(),
    ]);
    $import->delete();

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=files')
        ->assertInertia(fn ($page) => $page
            ->has('dataConsistencyReport')
            ->has('recycledImportVersions.data', 1)
            ->where('recycledImportVersions.data.0.id', $import->id)
            ->has('workbookRecycleBinRetentionDays'));
});

test('the data consistency report uses an ordered NIM list and a roster missing status', function (): void {
    $period = '2026/2027 Gasal';
    $import = KompenResponHubImport::query()->create([
        'periode_semester' => $period,
        'original_filename' => 'source.xlsx',
        'display_filename' => 'source.xlsx',
        'stored_path' => 'kompen-respon-hub/imports/source.xlsx',
        'file_hash' => str_repeat('c', 64),
        'class_count' => 1,
        'student_count' => 1,
        'detail_count' => 0,
        'imported_at' => now(),
    ]);
    KompenResponHubActiveImport::query()->create([
        'periode_semester' => $period,
        'kompen_respon_hub_import_id' => $import->id,
        'activated_at' => now(),
    ]);
    KompenResponHubStudent::query()->create([
        'kompen_respon_hub_import_id' => $import->id,
        'nim' => '261000001',
        'periode_semester' => $period,
        'nama_mahasiswa' => 'Aditya Pratama',
        'kelas' => '1AEA1',
        'tingkat' => 1,
        'total_jam_terlambat' => '0',
        'total_jam_sakit' => '0',
        'total_jam_izin' => '0',
        'total_jam_bolos' => '0',
        'total_kompensasi_jam' => '1',
        'total_responsi_jam' => '0',
        'total_hutang_jam' => '1',
        'kompensasi_dikerjakan_jam' => '0',
        'sisa_hutang_jam' => '1',
    ]);

    $report = app(BuildKompenResponHubDataConsistencyReport::class)->execute();

    expect($report)->toBe([
        [
            'periode_semester' => $period,
            'status' => 'roster_missing',
            'kompen_respon_students' => 1,
            'roster_students' => 0,
            'missing_from_roster' => ['261000001'],
            'missing_from_kompen_respon' => [],
        ],
    ]);
});
