<?php

use App\Actions\KompenResponHub\ImportKompenResponHubActiveStudents;
use App\Actions\KompenResponHub\ParseKompenResponHubActiveStudentWorkbook;
use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Actions\KompenResponHub\ValidateKompenResponHubWorkbookArchive;
use App\Jobs\ProcessKompenResponHubActiveStudentImport;
use App\Models\KompenResponHubActiveImport;
use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubActiveStudentImport;
use App\Models\KompenResponHubActiveStudentImportReference;
use App\Models\KompenResponHubActiveStudentImportTask;
use App\Models\KompenResponHubActivityLog;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubStudent;
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

test('an administrator can queue an active student list import and select it as the roster source for its period', function () {
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
    expect($task->stored_path)
        ->toStartWith('kompen-respon-hub/imports/active-students/')
        ->and(Storage::disk('local')->exists($task->stored_path))->toBeTrue();

    (new ProcessKompenResponHubActiveStudentImport($task->id))->handle(
        app(ParseKompenResponHubActiveStudentWorkbook::class),
        app(ImportKompenResponHubActiveStudents::class),
        app(RecordKompenResponHubActivity::class),
        app(ValidateKompenResponHubWorkbookArchive::class),
    );

    $task->refresh();

    $import = KompenResponHubActiveStudentImport::query()->latest('id')->firstOrFail();

    expect($task->status)->toBe(KompenResponHubActiveStudentImportTask::StatusCompleted)
        ->and(KompenResponHubActiveStudent::query()->orderBy('nim')->pluck('nim')->all())
        ->toBe(['111111111', '261000001', '261000002'])
        ->and(KompenResponHubImportAuditLog::query()
            ->where('import_type', KompenResponHubImportAuditLog::ImportTypeActiveStudents)
            ->where('original_filename', 'mahasiswa-aktif.xlsx')
            ->exists())->toBeTrue()
        ->and(KompenResponHubActivityLog::query()
            ->where('event_type', 'active_students.imported')
            ->where('reason', 'Versi daftar mahasiswa aktif ditambahkan dari file unggahan.')
            ->where('periode_semester', '2027/2028 Gasal')
            ->exists())->toBeTrue();

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/active-students/imports/{$import->id}/activate")
        ->assertSessionHas('success');

    expect(KompenResponHubActiveStudentImportReference::query()
        ->where('periode_semester', '2027/2028 Gasal')
        ->value('sikompen_active_student_import_id'))
        ->toBe($import->id)
        ->and(KompenResponHubActivityLog::query()
            ->where('event_type', 'active_students.activated')
            ->exists())->toBeTrue();
});

test('an administrator can delete an unused active student roster version', function (): void {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();
    $import = KompenResponHubActiveStudentImport::factory()->create([
        'original_filename' => 'roster-lama.xlsx',
        'stored_path' => 'kompen-respon-hub/imports/active-students/roster-lama.xlsx',
        'periode_semester' => '2027/2028 Gasal',
    ]);
    Storage::disk('local')->put($import->stored_path, 'workbook');
    KompenResponHubActiveStudent::factory()
        ->for($import, 'importBatch')
        ->create();

    $this->actingAs($admin, 'admin')
        ->delete("/admin/kompen-respon/active-students/imports/{$import->id}")
        ->assertRedirect('/admin?tab=files');

    expect(KompenResponHubActiveStudentImport::query()->find($import->id))->toBeNull()
        ->and(KompenResponHubActiveStudent::query()
            ->where('sikompen_active_student_import_id', $import->id)
            ->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists($import->stored_path))->toBeFalse()
        ->and(KompenResponHubImportAuditLog::query()
            ->where('event_type', KompenResponHubImportAuditLog::EVENT_DELETE)
            ->where('import_type', KompenResponHubImportAuditLog::ImportTypeActiveStudents)
            ->where('original_filename', 'roster-lama.xlsx')
            ->exists())->toBeTrue()
        ->and(KompenResponHubActivityLog::query()
            ->where('event_type', 'active_students.deleted')
            ->exists())->toBeTrue();
});

test('an administrator cannot delete an active student roster version in use', function (): void {
    $admin = KompenResponHubAdmin::factory()->create();
    $import = KompenResponHubActiveStudentImport::factory()->create([
        'periode_semester' => '2027/2028 Gasal',
    ]);
    KompenResponHubActiveStudentImportReference::factory()->create([
        'periode_semester' => $import->periode_semester,
        'sikompen_active_student_import_id' => $import->id,
    ]);

    $this->actingAs($admin, 'admin')
        ->delete("/admin/kompen-respon/active-students/imports/{$import->id}")
        ->assertUnprocessable();

    expect(KompenResponHubActiveStudentImport::query()->find($import->id))->not->toBeNull();
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
                activeStudentWorkbookContents(blocks: [[
                    'kelas' => '1AEA1',
                    'students' => [
                        ['261000001', 'Alya Pratama'],
                        ['261000001', 'Alya Pratama'],
                    ],
                ]]),
            ),
        ])
        ->assertRedirect('/admin?tab=upload');

    $task = KompenResponHubActiveStudentImportTask::query()->sole();

    expect($task->status)->toBe(KompenResponHubActiveStudentImportTask::StatusFailed)
        ->and($task->error_message)->toContain('duplikat')
        ->and(KompenResponHubActiveStudent::query()->pluck('nim')->all())
        ->toBe(['111111111']);
});

test('the active student parser accepts repeatable class blocks for one period', function (): void {
    Storage::fake('local');
    $path = 'testing/mahasiswa-aktif-header-bergeser.xlsx';
    Storage::disk('local')->put($path, activeStudentWorkbookContents(
        blocks: [
            [
                'kelas' => '1AEA1',
                'students' => [['261000001', 'Alya Pratama']],
            ],
            [
                'kelas' => '1AEA2',
                'students' => [['261000002', 'Bima Saputra']],
            ],
        ],
        firstMarkerRow: 8,
    ));

    $payload = app(ParseKompenResponHubActiveStudentWorkbook::class)
        ->execute(Storage::disk('local')->path($path));

    expect($payload['errors'])->toBe([])
        ->and($payload['students'])->toHaveCount(2)
        ->and($payload['students'][0]['nim'])->toBe('261000001')
        ->and($payload['students'][1]['kelas'])->toBe('1AEA2')
        ->and($payload['preview']['periode_semester'])->toBe('2027/2028 Gasal');
});

test('active roster sources are selected independently across periods', function (): void {
    $admin = KompenResponHubAdmin::factory()->create();
    $gasalImport = KompenResponHubActiveStudentImport::factory()->create([
        'periode_semester' => '2027/2028 Gasal',
    ]);
    $genapImport = KompenResponHubActiveStudentImport::factory()->create([
        'periode_semester' => '2027/2028 Genap',
    ]);
    KompenResponHubActiveStudent::factory()->for($gasalImport, 'importBatch')->create([
        'kelas' => '1AEA1',
    ]);
    KompenResponHubActiveStudent::factory()->for($genapImport, 'importBatch')->create([
        'kelas' => '1AEA1',
    ]);

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/active-students/imports/{$gasalImport->id}/activate")
        ->assertSessionHas('success');
    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/active-students/imports/{$genapImport->id}/activate")
        ->assertSessionHas('success');

    expect(KompenResponHubActiveStudentImportReference::query()
        ->orderBy('periode_semester')
        ->pluck('periode_semester')
        ->all())->toBe(['2027/2028 Gasal', '2027/2028 Genap']);
});

test('selecting a new active roster replaces the former roster for the same period', function (): void {
    $admin = KompenResponHubAdmin::factory()->create();
    $period = '2027/2028 Gasal';
    $firstImport = KompenResponHubActiveStudentImport::factory()->create(['periode_semester' => $period]);
    $replacementImport = KompenResponHubActiveStudentImport::factory()->create(['periode_semester' => $period]);
    KompenResponHubActiveStudent::factory()->for($firstImport, 'importBatch')->create(['kelas' => '1AEA1']);
    KompenResponHubActiveStudent::factory()->for($replacementImport, 'importBatch')->create(['kelas' => '1AEA2']);

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/active-students/imports/{$firstImport->id}/activate")
        ->assertSessionHas('success');
    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/active-students/imports/{$replacementImport->id}/activate")
        ->assertSessionHas('success');

    expect(KompenResponHubActiveStudentImportReference::query()
        ->where('periode_semester', $period)
        ->count())->toBe(1)
        ->and(KompenResponHubActiveStudentImportReference::query()
            ->where('periode_semester', $period)
            ->value('sikompen_active_student_import_id'))->toBe($replacementImport->id);
});

test('the file list keeps workbook and active student sources independent', function (): void {
    $admin = KompenResponHubAdmin::factory()->create();
    $period = '2027/2028 Gasal';
    $workbookImport = KompenResponHubImport::query()->create([
        'uploaded_by_admin_id' => $admin->id,
        'uploader_name' => $admin->email,
        'uploader_email' => $admin->email,
        'periode_semester' => $period,
        'original_filename' => 'kompen-respon.xlsx',
        'display_filename' => 'kompen-respon.xlsx',
        'stored_path' => 'kompen-respon-hub/imports/kompen-respon.xlsx',
        'file_hash' => str_repeat('a', 64),
        'class_count' => 1,
        'student_count' => 1,
        'detail_count' => 0,
        'imported_at' => now(),
    ]);
    KompenResponHubActiveImport::query()->create([
        'periode_semester' => $period,
        'kompen_respon_hub_import_id' => $workbookImport->id,
        'activated_by_admin_id' => $admin->id,
        'activated_at' => now(),
    ]);
    KompenResponHubStudent::query()->create([
        'kompen_respon_hub_import_id' => $workbookImport->id,
        'nim' => '261000001',
        'periode_semester' => $period,
        'nama_mahasiswa' => 'Alya Pratama',
        'kelas' => '1AEA1',
        'tingkat' => 1,
        'total_jam_terlambat' => 0,
        'total_jam_sakit' => 0,
        'total_jam_izin' => 0,
        'total_jam_bolos' => 0,
        'total_kompensasi_jam' => 0,
        'total_responsi_jam' => 0,
        'total_hutang_jam' => 0,
        'kompensasi_dikerjakan_jam' => 0,
        'sisa_hutang_jam' => 0,
    ]);

    $this->actingAs($admin, 'admin')->get('/admin?tab=files')
        ->assertInertia(fn ($page) => $page
            ->has('activeImportVersions.data', 1)
            ->where('activeStudentImportReferences.meta.total', 0)
            ->missing('filePairingWarnings'));
});

test('the file list paginates active student roster versions and selected roster references independently', function (): void {
    $admin = KompenResponHubAdmin::factory()->create();
    $imports = KompenResponHubActiveStudentImport::factory()->count(16)->create();

    foreach ($imports as $index => $import) {
        KompenResponHubActiveStudentImportReference::factory()->create([
            'periode_semester' => '20'.(27 + $index).'/'.(28 + $index).' Gasal',
            'sikompen_active_student_import_id' => $import->id,
        ]);
    }

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=files&per_page=15&active_student_import_page=99&active_student_import_reference_page=99')
        ->assertInertia(fn ($page) => $page
            ->where('activeStudentImportVersions.meta.page_name', 'active_student_import_page')
            ->where('activeStudentImportVersions.meta.current_page', 2)
            ->has('activeStudentImportVersions.data', 1)
            ->where('activeStudentImportReferences.meta.page_name', 'active_student_import_reference_page')
            ->where('activeStudentImportReferences.meta.current_page', 2)
            ->has('activeStudentImportReferences.data', 1));
});

test('an administrator can select more than five active student roster versions within one minute', function (): void {
    $admin = KompenResponHubAdmin::factory()->create();

    foreach (range(1, 6) as $classNumber) {
        $import = KompenResponHubActiveStudentImport::factory()->create();
        KompenResponHubActiveStudent::factory()
            ->for($import, 'importBatch')
            ->create(['kelas' => "1AEA{$classNumber}"]);

        $this->actingAs($admin, 'admin')
            ->post("/admin/kompen-respon/active-students/imports/{$import->id}/activate")
            ->assertSessionHas('success');
    }
});

test('the active student parser rejects blocks that use different periods', function (): void {
    Storage::fake('local');
    $path = 'testing/mahasiswa-aktif-periode-berbeda.xlsx';
    Storage::disk('local')->put($path, activeStudentWorkbookContents(blocks: [
        [
            'kelas' => '1AEA1',
            'students' => [['261000001', 'Alya Pratama']],
        ],
        [
            'kelas' => '1AEA2',
            'semester' => 'Genap',
            'students' => [['261000002', 'Bima Saputra']],
        ],
    ]));

    $payload = app(ParseKompenResponHubActiveStudentWorkbook::class)
        ->execute(Storage::disk('local')->path($path));

    expect($payload['errors'])->toContain([
        'location' => 'Mahasiswa Aktif',
        'message' => 'Semua blok kelas harus menggunakan periode semester yang sama.',
    ]);
});

test('the active student parser rejects formulas in a class block', function (): void {
    Storage::fake('local');
    $path = 'testing/mahasiswa-aktif-formula.xlsx';
    Storage::disk('local')->put($path, activeStudentWorkbookContents(usesFormula: true));

    $payload = app(ParseKompenResponHubActiveStudentWorkbook::class)
        ->execute(Storage::disk('local')->path($path));

    expect($payload['errors'])->toContain([
        'location' => 'Mahasiswa Aktif!B6',
        'message' => 'Formula tidak diizinkan dalam template daftar mahasiswa aktif.',
    ]);
});

/**
 * @param  list<array{kelas: string, semester?: string, academic_year?: string, students: list<array{0: string, 1: string}>}>  $blocks
 */
function activeStudentWorkbookContents(array $blocks = [
    [
        'kelas' => '1AEA1',
        'students' => [
            ['261000001', 'Alya Pratama'],
            ['261000002', 'Bima Saputra'],
        ],
    ],
], int $firstMarkerRow = 3, bool $usesFormula = false): string
{
    $workbook = new Spreadsheet;
    $guide = $workbook->getActiveSheet();
    $guide->setTitle('Petunjuk');
    $guide->setCellValue('A1', 'Petunjuk');
    $students = $workbook->createSheet();
    $students->setTitle('Mahasiswa Aktif');
    $row = $firstMarkerRow;

    foreach ($blocks as $block) {
        $class = $block['kelas'];
        $semester = $block['semester'] ?? 'Gasal';
        $academicYear = $block['academic_year'] ?? '2027/2028';
        $students->setCellValue("A{$row}", "DAFTAR MAHASISWA AKTIF — {$class}");
        $students->fromArray([
            ['Periode', $semester, $academicYear, 'Kelas', $class, '', 'Tingkat', (int) $class[0]],
            ['No.', 'NIM', 'Nama Mahasiswa'],
        ], null, 'A'.($row + 1));

        foreach ($block['students'] as $index => [$nim, $name]) {
            $students->fromArray([[$index + 1, $nim, $name]], null, 'A'.($row + 3 + $index));
        }

        if ($usesFormula) {
            $students->setCellValue('B'.($row + 3), '=261000001');
        }

        $row += max(7, count($block['students']) + 5);
    }

    $writer = new Xlsx($workbook);
    ob_start();
    $writer->save('php://output');

    return (string) ob_get_clean();
}
test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
