<?php

use App\Jobs\ProcessKompenResponHubImport;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportTask;
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
