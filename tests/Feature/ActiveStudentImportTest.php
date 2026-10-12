<?php

use App\Actions\KompenResponHub\ImportKompenResponHubActiveStudents;
use App\Actions\KompenResponHub\ParseKompenResponHubActiveStudentWorkbook;
use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Actions\KompenResponHub\ValidateKompenResponHubWorkbookArchive;
use App\Jobs\ProcessKompenResponHubActiveStudentImport;
use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubActiveStudentImportTask;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubImportAuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

test('an administrator can download the active student list template', function () {
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get('/admin/kompen-respon/active-students/template')
        ->assertDownload('Template_Daftar_Mahasiswa_Aktif_Sikompen.xlsx');
});

test('an administrator can queue an active student list import and replace the master list after validation', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = KompenResponHubAdmin::factory()->create();
    KompenResponHubActiveStudent::factory()->create([
        'nim' => '111111111',
        'nama_mahasiswa' => 'Data Lama',
        'kelas' => '1AEA1',
    ]);

    $this->actingAs($admin, 'admin')
        ->post('/admin/kompen-respon/active-students/imports', [
            'uploader_name' => 'Admin Akademik',
            'file' => UploadedFile::fake()->createWithContent(
                'mahasiswa-aktif.xlsx',
                activeStudentWorkbookContents(),
            ),
        ])
        ->assertRedirect('/admin?tab=upload');

    $task = KompenResponHubActiveStudentImportTask::query()->sole();
    Queue::assertPushed(
        ProcessKompenResponHubActiveStudentImport::class,
        fn (ProcessKompenResponHubActiveStudentImport $job): bool => $job->importTaskId === $task->id,
    );

    (new ProcessKompenResponHubActiveStudentImport($task->id))->handle(
        app(ParseKompenResponHubActiveStudentWorkbook::class),
        app(ImportKompenResponHubActiveStudents::class),
        app(RecordKompenResponHubActivity::class),
        app(ValidateKompenResponHubWorkbookArchive::class),
    );

    $task->refresh();

    expect($task->status)->toBe(KompenResponHubActiveStudentImportTask::StatusCompleted)
        ->and(KompenResponHubActiveStudent::query()->pluck('nim')->all())
        ->toBe(['261000001', '261000002'])
        ->and(KompenResponHubImportAuditLog::query()
            ->where('import_type', KompenResponHubImportAuditLog::ImportTypeActiveStudents)
            ->where('original_filename', 'mahasiswa-aktif.xlsx')
            ->exists())->toBeTrue();
});

test('a duplicate NIM leaves the existing active student master list unchanged', function () {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();
    KompenResponHubActiveStudent::factory()->create([
        'nim' => '111111111',
        'nama_mahasiswa' => 'Data Lama',
        'kelas' => '1AEA1',
    ]);

    $this->actingAs($admin, 'admin')
        ->post('/admin/kompen-respon/active-students/imports', [
            'uploader_name' => 'Admin Akademik',
            'file' => UploadedFile::fake()->createWithContent(
                'duplikat.xlsx',
                activeStudentWorkbookContents([
                    ['261000001', 'Alya Pratama', '1AEA1'],
                    ['261000001', 'Alya Pratama', '1AEA1'],
                ]),
            ),
        ])
        ->assertRedirect('/admin?tab=upload');

    $task = KompenResponHubActiveStudentImportTask::query()->sole();

    expect($task->status)->toBe(KompenResponHubActiveStudentImportTask::StatusFailed)
        ->and($task->error_message)->toContain('duplikat')
        ->and(KompenResponHubActiveStudent::query()->pluck('nim')->all())
        ->toBe(['111111111']);
});

/**
 * @param  list<array{0: string, 1: string, 2: string}>  $rows
 */
function activeStudentWorkbookContents(array $rows = [
    ['261000001', 'Alya Pratama', '1AEA1'],
    ['261000002', 'Bima Saputra', '1AEA1'],
]): string
{
    $workbook = new Spreadsheet;
    $guide = $workbook->getActiveSheet();
    $guide->setTitle('Petunjuk');
    $guide->setCellValue('A1', 'Petunjuk');
    $students = $workbook->createSheet();
    $students->setTitle('Mahasiswa Aktif');
    $students->fromArray([
        ['NIM', 'Nama Mahasiswa', 'Kelas'],
        ...$rows,
    ], null, 'A5');

    $writer = new Xlsx($workbook);
    ob_start();
    $writer->save('php://output');

    return (string) ob_get_clean();
}
test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
