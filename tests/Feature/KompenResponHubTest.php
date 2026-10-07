<?php

use App\Actions\KompenResponHub\BuildKompenResponHubExport;
use App\Actions\KompenResponHub\KompenResponHubDataQuery;
use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Jobs\GenerateKompenResponHubExport;
use App\Jobs\ProcessKompenResponHubImport;
use App\Models\KompenResponHubActiveImport;
use App\Models\KompenResponHubActivityLog;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubDetail;
use App\Models\KompenResponHubExportTask;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubImportTask;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubStudent;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

test('the public page and data API do not require a login', function () {
    $student = createStudent();

    $this->get('/kompen-respon')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('kompen-respon-hub/index')
            ->where('activeTab', 'students')
            ->where('isAdmin', false)
            ->where('filterOptions.tingkat', [1])
            ->has('filterOptions.periode_semester', 1),
        );

    $this->getJson('/api/kompen-respon/students?nama=Rina')
        ->assertOk()
        ->assertJsonPath('data.0.nim', $student->nim)
        ->assertJsonPath('data.0.total_hutang_jam', '3.5000');

    $this->get('/admin/login')->assertOk();
});

test('the admin landing page opens the Kompen and Respon table', function () {
    $student = createStudent();
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->get('/admin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeTab', 'students')
            ->has('students.data', 1),
        );

    $this->actingAs($admin, 'admin')->getJson("/admin/kompen-respon/students/{$student->id}/overview")
        ->assertOk()
        ->assertJsonPath('data.summary.nim', $student->nim)
        ->assertJsonPath('data.source.total_kompensasi_jam', '1.5000');
});

test('an admin can search students globally with a validated, limited result set', function () {
    $student = createStudent();
    $admin = KompenResponHubAdmin::factory()->create();

    $this->get('/admin/kompen-respon/student-search?q=Rina')
        ->assertRedirect('/admin/login');

    $this->actingAs($admin, 'admin')
        ->getJson('/admin/kompen-respon/student-search?q=Rina')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $student->id)
        ->assertJsonPath('data.0.nim', $student->nim)
        ->assertJsonPath('data.0.nama_mahasiswa', $student->nama_mahasiswa)
        ->assertJsonMissingPath('data.0.total_hutang_jam');

    $this->actingAs($admin, 'admin')
        ->getJson('/admin/kompen-respon/student-search?q=%3Cscript%3E')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
});

test('an admin student profile includes an interpretable before and after activity history', function () {
    $student = createStudent();
    $admin = KompenResponHubAdmin::factory()->create();

    KompenResponHubActivityLog::create([
        'event_type' => 'progress.updated',
        'subject_type' => 'student_progress',
        'subject_reference' => (string) $student->id,
        'nim' => $student->nim,
        'subject_name' => $student->nama_mahasiswa,
        'periode_semester' => $student->periode_semester,
        'kelas' => $student->kelas,
        'actor_type' => 'admin',
        'actor_admin_id' => $admin->id,
        'actor_name' => $admin->email,
        'actor_email' => $admin->email,
        'reason' => 'Kehadiran diverifikasi.',
        'before_state' => ['kompensasi_dikerjakan_jam' => 1],
        'after_state' => ['kompensasi_dikerjakan_jam' => 2.5],
        'occurred_at' => now(),
    ]);

    $this->actingAs($admin, 'admin')
        ->getJson("/admin/kompen-respon/students/{$student->id}/overview")
        ->assertOk()
        ->assertJsonPath('data.activities.0.event_type', 'progress.updated')
        ->assertJsonPath('data.activities.0.before_state.kompensasi_dikerjakan_jam', 1)
        ->assertJsonPath('data.activities.0.after_state.kompensasi_dikerjakan_jam', 2.5)
        ->assertJsonPath('data.activities.0.reason', 'Kehadiran diverifikasi.');
});

test('the Kompen and Respon table filters students by class and level', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $firstStudent = createStudent();
    $secondStudent = $firstStudent->replicate();
    $secondStudent->fill([
        'nim' => '987654321',
        'nama_mahasiswa' => 'Dani Pratama',
        'kelas' => '2AEA1',
        'tingkat' => 2,
    ])->save();

    KompenResponHubPeriodCutoff::create([
        'periode_semester' => $firstStudent->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->actingAs($admin, 'admin')
        ->get('/admin?tingkat=2&kelas=2AEA1&per_page=25')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.tingkat', 2)
            ->where('filters.kelas', '2AEA1')
            ->where('students.meta.page_name', 'page')
            ->where('students.meta.per_page', 25)
            ->where('activeTab', 'students')
            ->where('students.data.0.nim', '987654321'),
        );
});

test('the dynamic filters only expose uploaded periods and filter their records', function () {
    createStudent();
    $otherImport = KompenResponHubImport::create([
        'periode_semester' => '2026/2027 Genap',
        'original_filename' => 'other-source.xlsx',
        'stored_path' => 'kompen-respon-hub/imports/other-source.xlsx',
        'file_hash' => str_repeat('b', 64),
        'class_count' => 1,
        'student_count' => 1,
        'detail_count' => 0,
        'imported_at' => now(),
    ]);

    KompenResponHubStudent::create([
        'kompen_respon_hub_import_id' => $otherImport->id,
        'nim' => '987654321',
        'periode_semester' => '2026/2027 Genap',
        'nama_mahasiswa' => 'Dani Pratama',
        'kelas' => '2AEB1',
        'tingkat' => 2,
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

    $this->getJson('/api/kompen-respon/filter-options')
        ->assertOk()
        ->assertJsonPath('periode_semester', [
            '2026/2027 Genap',
            '2026/2027 Ganjil',
        ])
        ->assertJsonPath('kelas', ['1AEA1', '2AEB1']);

    $this->getJson('/api/kompen-respon/students?periode_semester=2026%2F2027%20Genap')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nim', '987654321');
});

test('the student API returns the complete kompen and respon payload for one student', function () {
    $student = createStudent();
    KompenResponHubDetail::create([
        'kompen_respon_hub_student_id' => $student->id,
        'tanggal' => '2026-09-01',
        'mata_kuliah' => 'Algoritma',
        'nama_dosen' => 'Ibu Sari',
        'jenis_pertemuan' => 'Luring',
        'presensi' => 'Terlambat',
        'menit_keterlambatan' => 15,
        'jam_kompensasi' => 1.5,
        'jam_responsi' => 2,
    ]);

    $this->getJson("/api/kompen-respon/students/{$student->id}")
        ->assertOk()
        ->assertJsonPath('data.summary.nama_mahasiswa', 'Rina Utami')
        ->assertJsonPath('data.summary.total_kompensasi_jam', '1.5000')
        ->assertJsonPath('data.details.0.mata_kuliah', 'Algoritma')
        ->assertJsonPath('data.details.0.jam_responsi', '2.0000');
});

test('an export without filters is queued and produces a landscape XLSX', function () {
    Storage::fake('local');
    Queue::fake();
    createStudent();

    $this->post('/exports', [
        'resource' => 'students',
        'format' => 'xlsx',
    ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $task = KompenResponHubExportTask::query()->sole();

    expect($task->filters)->toBe([])
        ->and($task->status)->toBe(KompenResponHubExportTask::StatusQueued)
        ->and(KompenResponHubActivityLog::query()->where('event_type', 'export.requested')->exists())->toBeTrue();

    Queue::assertPushed(
        GenerateKompenResponHubExport::class,
        fn (GenerateKompenResponHubExport $job): bool => $job->exportTaskId === $task->id,
    );

    (new GenerateKompenResponHubExport($task->id))->handle(
        app(BuildKompenResponHubExport::class),
        app(RecordKompenResponHubActivity::class),
    );

    $task->refresh();
    expect($task->status)->toBe(KompenResponHubExportTask::StatusCompleted);
    $this->get("/exports/{$task->id}/download?token={$task->access_token}")
        ->assertNotFound();
    $response = $this->withCookie(config('session.cookie'), $task->request_session_id)
        ->get("/exports/{$task->id}/download?token={$task->access_token}");

    $response
        ->assertOk()
        ->assertDownload("kompen-dan-respon-semua-periode-{$task->id}.xlsx")
        ->assertHeaderContains(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

    $temporaryFile = tempnam(sys_get_temp_dir(), 'sikompen-export-');
    file_put_contents($temporaryFile, $response->streamedContent());

    try {
        $worksheet = IOFactory::load($temporaryFile)->getActiveSheet();

        expect($worksheet->getPageSetup()->getOrientation())
            ->toBe(PageSetup::ORIENTATION_LANDSCAPE)
            ->and($worksheet->getCell('A1')->getValue())->toBe('Kompen dan Respon')
            ->and($worksheet->getCell('B2')->getValue())->toBe('Semua Periode')
            ->and($worksheet->getCell('B5')->getValue())->toBe('123456789')
            ->and($worksheet->getStyle('A5')->getNumberFormat()->getFormatCode())->toBe('#,##0')
            ->and($worksheet->getStyle('F5')->getNumberFormat()->getFormatCode())->toBe('#,##0.00');
    } finally {
        unlink($temporaryFile);
    }
});

test('an export produces a landscape PDF for detail kompen', function () {
    Storage::fake('local');
    Queue::fake();
    $student = createStudent();
    KompenResponHubDetail::create([
        'kompen_respon_hub_student_id' => $student->id,
        'tanggal' => '2026-09-01',
        'mata_kuliah' => 'Algoritma',
        'nama_dosen' => 'Ibu Sari',
        'jenis_pertemuan' => 'Luring',
        'presensi' => 'Terlambat',
        'menit_keterlambatan' => 15,
        'jam_kompensasi' => 1.5,
        'jam_responsi' => 2,
    ]);

    $this->post('/exports', [
        'resource' => 'details',
        'format' => 'pdf',
        'periode_semester' => '2026/2027 Ganjil',
    ])->assertRedirect();

    $task = KompenResponHubExportTask::query()->sole();
    (new GenerateKompenResponHubExport($task->id))->handle(
        app(BuildKompenResponHubExport::class),
        app(RecordKompenResponHubActivity::class),
    );

    $task->refresh();
    expect($task->status)->toBe(KompenResponHubExportTask::StatusCompleted);
    $response = $this->withCookie(config('session.cookie'), $task->request_session_id)
        ->get("/exports/{$task->id}/download?token={$task->access_token}");

    $response
        ->assertOk()
        ->assertDownload("detail-kompen-2026-2027-ganjil-{$task->id}.pdf")
        ->assertHeaderContains('Content-Type', 'application/pdf');

    expect($response->streamedContent())->toStartWith('%PDF-');
});

test('an export fails safely when its result exceeds the hard row limit', function () {
    Storage::fake('local');
    Queue::fake();
    config(['kompen-respon-hub.export_max_rows' => 1]);
    $student = createStudent();

    $secondStudent = $student->replicate();
    $secondStudent->fill([
        'nim' => '987654321',
        'nama_mahasiswa' => 'Dani Pratama',
    ])->save();

    $this->post('/exports', [
        'resource' => 'students',
        'format' => 'xlsx',
    ])->assertRedirect();

    $task = KompenResponHubExportTask::query()->sole();

    (new GenerateKompenResponHubExport($task->id))->handle(
        app(BuildKompenResponHubExport::class),
        app(RecordKompenResponHubActivity::class),
    );

    expect($task->refresh()->status)->toBe(KompenResponHubExportTask::StatusFailed)
        ->and($task->error_message)->toContain('maksimal 1 baris')
        ->and(KompenResponHubActivityLog::query()->where('event_type', 'export.failed')->exists())->toBeTrue();
});

test('a student cannot queue an export of warning letters', function () {
    $this->post('/exports', [
        'resource' => 'warnings',
        'format' => 'xlsx',
    ])->assertForbidden();
});

test('an authenticated admin can stage a valid workbook then activate it for its period', function () {
    Storage::fake('local');
    $oldStudent = createStudent('2026/2027 Gasal');
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent(
            'kompen-respon.xlsx',
            workbookContents(),
        ),
    ])->assertRedirect('/admin?tab=upload');

    expect(KompenResponHubStudent::query()->find($oldStudent->id))->not->toBeNull();

    $import = KompenResponHubImport::query()->latest('id')->firstOrFail();
    expect($import->periode_semester)->toBe('2026/2027 Gasal')
        ->and($import->uploaded_by_admin_id)->toBe($admin->id)
        ->and($import->uploader_name)->toBe('Khairul Anwar')
        ->and($import->uploader_email)->toBe($admin->email)
        ->and($import->quality_report['status'])->toBe('passed')
        ->and($import->quality_report['checks'])->toHaveCount(6)
        ->and(KompenResponHubActivityLog::query()->where('event_type', 'import.completed')->exists())->toBeTrue();

    $this->actingAs($admin, 'admin')->get('/admin?tab=imports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeTab', 'imports')
            ->has('imports.data', 1)
            ->where('imports.data.0.event_type', 'upload')
            ->where('imports.data.0.actor_name', 'Khairul Anwar')
            ->where('imports.data.0.actor_email', $admin->email)
            ->where('imports.data.0.version_status', 'stored')
            ->where('imports.data.0.version_imported_at', $import->imported_at->toIso8601String())
            ->where('imports.data.0.quality_report.status', 'passed'),
        );

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/imports/{$import->id}/activate")
        ->assertRedirect('/admin?tab=files');

    $student = KompenResponHubStudent::query()->sole();
    expect($student->nim)->toBe('123456789')
        ->and($student->total_hutang_jam)->toBe('3.5000')
        ->and($student->kompen_respon_hub_import_id)->toBe($import->id);

    $this->getJson('/api/kompen-respon/details?nim=123456789')
        ->assertOk()
        ->assertJsonPath('data.0.nama_dosen', 'Ibu Sari')
        ->assertJsonPath('data.0.jam_kompensasi', '1.5000');
});

test('an admin can download an available workbook from its upload log', function () {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createStudent();
    $import = KompenResponHubImport::query()->findOrFail($student->kompen_respon_hub_import_id);

    Storage::disk('local')->put($import->stored_path, 'workbook contents');
    KompenResponHubImportAuditLog::create([
        'event_type' => KompenResponHubImportAuditLog::EVENT_UPLOAD,
        'source_import_id' => $import->id,
        'periode_semester' => $import->periode_semester,
        'original_filename' => $import->original_filename,
        'class_count' => $import->class_count,
        'student_count' => $import->student_count,
        'detail_count' => $import->detail_count,
        'occurred_at' => $import->imported_at,
    ]);

    $this->actingAs($admin, 'admin')
        ->get("/admin/kompen-respon/imports/{$import->id}/download")
        ->assertDownload('source.xlsx');

    $this->actingAs($admin, 'admin')->get('/admin?tab=imports')
        ->assertInertia(fn ($page) => $page
            ->where('imports.data.0.can_download_file', true),
        );
});

test('an admin can filter upload logs by workbook period, activity year, and activity month', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $matchingAudit = KompenResponHubImportAuditLog::factory()->create([
        'source_import_id' => null,
        'periode_semester' => '2026/2027 Gasal',
        'original_filename' => 'oktober-gasal.xlsx',
        'occurred_at' => CarbonImmutable::create(2026, 10, 4, 9, 30, 0, 'Asia/Jakarta'),
    ]);
    KompenResponHubImportAuditLog::factory()->create([
        'source_import_id' => null,
        'periode_semester' => '2026/2027 Gasal',
        'original_filename' => 'september-gasal.xlsx',
        'occurred_at' => CarbonImmutable::create(2026, 9, 30, 23, 59, 0, 'Asia/Jakarta'),
    ]);
    KompenResponHubImportAuditLog::factory()->create([
        'source_import_id' => null,
        'periode_semester' => '2026/2027 Genap',
        'original_filename' => 'oktober-genap.xlsx',
        'occurred_at' => CarbonImmutable::create(2026, 10, 4, 10, 0, 0, 'Asia/Jakarta'),
    ]);

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=imports&periode_semester=2026%2F2027%20Gasal&import_month=2026-10&import_year=2026')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeTab', 'imports')
            ->where('filters.periode_semester', '2026/2027 Gasal')
            ->where('filters.import_month', '2026-10')
            ->where('filters.import_year', 2026)
            ->where('importAuditPeriods', ['2026/2027 Genap', '2026/2027 Gasal'])
            ->where('importAuditYears', [2026])
            ->has('imports.data', 1)
            ->where('imports.data.0.id', $matchingAudit->id),
        );

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=imports&import_year=2026')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.import_year', 2026)
            ->has('imports.data', 3),
        );

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=imports&import_month=2026-13')
        ->assertSessionHasErrors('import_month');

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=imports&import_month=2026-10&import_year=2025')
        ->assertSessionHasErrors('import_year');
});

test('an admin can rename an inactive workbook version and the audit trail remains interpretable', function () {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();
    $import = KompenResponHubImport::create([
        'periode_semester' => '2026/2027 Genap',
        'original_filename' => 'import-awal.xlsx',
        'display_filename' => 'import-awal.xlsx',
        'stored_path' => 'kompen-respon-hub/imports/import-awal.xlsx',
        'file_hash' => str_repeat('b', 64),
        'class_count' => 1,
        'student_count' => 1,
        'detail_count' => 1,
        'imported_at' => now(),
    ]);
    Storage::disk('local')->put($import->stored_path, 'workbook');

    $this->actingAs($admin, 'admin')
        ->put("/admin/kompen-respon/imports/{$import->id}", [
            'display_filename' => 'rekap-kompen-genap.xlsx',
        ])
        ->assertRedirect('/admin?tab=files');

    expect($import->refresh()->display_filename)->toBe('rekap-kompen-genap.xlsx')
        ->and(KompenResponHubImportAuditLog::query()
            ->where('event_type', KompenResponHubImportAuditLog::EVENT_RENAME)
            ->where('source_import_id', $import->id)
            ->where('original_filename', 'rekap-kompen-genap.xlsx')
            ->exists())->toBeTrue()
        ->and(KompenResponHubActivityLog::query()
            ->where('event_type', 'import.renamed')
            ->where('subject_reference', (string) $import->id)
            ->exists())->toBeTrue();

    $this->actingAs($admin, 'admin')
        ->get("/admin/kompen-respon/imports/{$import->id}/download")
        ->assertDownload('rekap-kompen-genap.xlsx');

    $this->actingAs($admin, 'admin')
        ->put("/admin/kompen-respon/imports/{$import->id}", [
            'display_filename' => '../tidak-valid.xlsx',
        ])
        ->assertSessionHasErrors('display_filename');
});

test('an admin can delete an inactive workbook version while its audit trail remains', function () {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();
    $import = KompenResponHubImport::create([
        'periode_semester' => '2026/2027 Genap',
        'original_filename' => 'salah.xlsx',
        'display_filename' => 'salah.xlsx',
        'stored_path' => 'kompen-respon-hub/imports/salah.xlsx',
        'file_hash' => str_repeat('c', 64),
        'class_count' => 1,
        'student_count' => 1,
        'detail_count' => 1,
        'imported_at' => now(),
    ]);
    Storage::disk('local')->put($import->stored_path, 'workbook');

    $this->actingAs($admin, 'admin')
        ->delete("/admin/kompen-respon/imports/{$import->id}")
        ->assertRedirect('/admin?tab=files');

    expect(KompenResponHubImport::query()->find($import->id))->toBeNull()
        ->and(Storage::disk('local')->exists($import->stored_path))->toBeFalse()
        ->and(KompenResponHubImportAuditLog::query()
            ->where('event_type', KompenResponHubImportAuditLog::EVENT_DELETE)
            ->where('source_import_id', $import->id)
            ->where('original_filename', 'salah.xlsx')
            ->exists())->toBeTrue()
        ->and(KompenResponHubActivityLog::query()
            ->where('event_type', 'import.deleted')
            ->where('subject_reference', (string) $import->id)
            ->exists())->toBeTrue();
});

test('an admin cannot delete the workbook version currently used by a period', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createStudent();

    $this->actingAs($admin, 'admin')
        ->delete("/admin/kompen-respon/imports/{$student->kompen_respon_hub_import_id}")
        ->assertUnprocessable();

    expect(KompenResponHubImport::query()->find($student->kompen_respon_hub_import_id))->not->toBeNull();
});

test('the upload log identifies active data by import version instead of filename', function () {
    $this->travelTo('2026-10-05 12:00:00');

    $admin = KompenResponHubAdmin::factory()->create();
    $student = createStudent();
    $activeImport = KompenResponHubImport::query()->findOrFail($student->kompen_respon_hub_import_id);
    $archivedImport = KompenResponHubImport::create([
        'periode_semester' => $activeImport->periode_semester,
        'original_filename' => $activeImport->original_filename,
        'stored_path' => 'kompen-respon-hub/imports/historical-source.xlsx',
        'file_hash' => str_repeat('b', 64),
        'class_count' => 1,
        'student_count' => 1,
        'detail_count' => 0,
        'imported_at' => now()->subHour(),
    ]);

    KompenResponHubImportAuditLog::create([
        'event_type' => KompenResponHubImportAuditLog::EVENT_UPLOAD,
        'source_import_id' => $archivedImport->id,
        'periode_semester' => $archivedImport->periode_semester,
        'original_filename' => $archivedImport->original_filename,
        'class_count' => $archivedImport->class_count,
        'student_count' => $archivedImport->student_count,
        'detail_count' => $archivedImport->detail_count,
        'occurred_at' => $archivedImport->imported_at,
    ]);
    KompenResponHubImportAuditLog::create([
        'event_type' => KompenResponHubImportAuditLog::EVENT_UPLOAD,
        'source_import_id' => $activeImport->id,
        'periode_semester' => $activeImport->periode_semester,
        'original_filename' => $activeImport->original_filename,
        'class_count' => $activeImport->class_count,
        'student_count' => $activeImport->student_count,
        'detail_count' => $activeImport->detail_count,
        'occurred_at' => $activeImport->imported_at,
    ]);

    $this->actingAs($admin, 'admin')->get('/admin?tab=imports')
        ->assertInertia(fn ($page) => $page
            ->where('imports.data.0.source_import_id', $activeImport->id)
            ->where('imports.data.0.original_filename', 'source.xlsx')
            ->where('imports.data.0.version_status', 'active')
            ->where('imports.data.1.source_import_id', $archivedImport->id)
            ->where('imports.data.1.original_filename', 'source.xlsx')
            ->where('imports.data.1.version_status', 'stored'),
        );

    $this->actingAs($admin, 'admin')->get('/admin?tab=files')
        ->assertInertia(fn ($page) => $page
            ->where('activeImportVersions.0.id', $activeImport->id)
            ->where('activeImportVersions.0.periode_semester', $activeImport->periode_semester)
            ->where('activeImportVersions.0.is_active', true)
            ->where('importVersions.data.0.id', $activeImport->id),
        );
});

test('a guest cannot download an uploaded workbook', function () {
    $student = createStudent();

    $this->get("/admin/kompen-respon/imports/{$student->kompen_respon_hub_import_id}/download")
        ->assertRedirect('/admin/login');
});

test('an admin must enter their name before importing a workbook', function () {
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'file' => UploadedFile::fake()->create('kompen-respon.xlsx'),
    ])->assertSessionHasErrors('uploader_name');
});

test('activating a completed import clears cached filter options', function () {
    Cache::forget(KompenResponHubDataQuery::FILTER_OPTIONS_CACHE_KEY);
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();

    $this->getJson('/api/kompen-respon/filter-options')
        ->assertOk()
        ->assertJsonPath('periode_semester', []);

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent(
            'kompen-respon.xlsx',
            workbookContents(),
        ),
    ])->assertRedirect('/admin?tab=upload');

    $this->getJson('/api/kompen-respon/filter-options')
        ->assertOk()
        ->assertJsonPath('periode_semester', []);

    $import = KompenResponHubImport::query()->sole();
    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/imports/{$import->id}/activate")
        ->assertRedirect('/admin?tab=files');

    $this->getJson('/api/kompen-respon/filter-options')
        ->assertOk()
        ->assertJsonPath('periode_semester', ['2026/2027 Gasal']);
});

test('an admin can switch the active source to a chosen prior workbook version', function () {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent(
            'versi-awal.xlsx',
            workbookContents(compensationHours: 1.5, responseHours: 2),
        ),
    ])->assertRedirect('/admin?tab=upload');

    $previousImport = KompenResponHubImport::query()->sole();

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/imports/{$previousImport->id}/activate")
        ->assertRedirect('/admin?tab=files');

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent(
            'versi-keliru.xlsx',
            workbookContents(compensationHours: 9.25, responseHours: 4.5),
        ),
    ])->assertRedirect('/admin?tab=upload');

    $incorrectImport = KompenResponHubImport::query()->latest('id')->firstOrFail();
    expect(KompenResponHubStudent::query()->sole()->total_hutang_jam)->toBe('3.5000');

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/imports/{$incorrectImport->id}/activate")
        ->assertRedirect('/admin?tab=files');

    expect(KompenResponHubStudent::query()->sole())
        ->kompen_respon_hub_import_id->toBe($incorrectImport->id)
        ->and(KompenResponHubStudent::query()->sole()->total_hutang_jam)->toBe('13.7500')
        ->and(KompenResponHubActiveImport::query()->sole()->kompen_respon_hub_import_id)->toBe($incorrectImport->id)
        ->and(KompenResponHubImportAuditLog::query()->latest('id')->value('event_type'))->toBe(KompenResponHubImportAuditLog::EVENT_ACTIVATE)
        ->and(KompenResponHubActivityLog::query()->where('event_type', 'import.version_activated')->exists())->toBeTrue();
});

test('a guest cannot activate a selected upload version', function () {
    $import = KompenResponHubImport::create([
        'periode_semester' => '2026/2027 Ganjil',
        'original_filename' => 'guest-restore.xlsx',
        'stored_path' => 'kompen-respon-hub/imports/guest-restore.xlsx',
        'file_hash' => str_repeat('f', 64),
        'class_count' => 1,
        'student_count' => 1,
        'detail_count' => 0,
        'imported_at' => now(),
    ]);

    $this->post("/admin/kompen-respon/imports/{$import->id}/activate")
        ->assertRedirect('/admin/login');
});

test('search filters reject HTML syntax and treat SQL wildcards as plain text', function () {
    createStudent();

    $this->getJson('/api/kompen-respon/students?search=%3Cscript%3Ealert(1)%3C%2Fscript%3E')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['search']);

    $this->getJson('/api/kompen-respon/students?search=%25')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('admin login and uploader name reject HTML syntax', function () {
    $admin = KompenResponHubAdmin::factory()->create();

    $this->post('/admin/login', [
        'email' => '<script>alert(1)</script>',
        'password' => 'invalid-password',
    ])->assertSessionHasErrors(['email']);

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => '<script>alert(1)</script>',
        'file' => UploadedFile::fake()->create('kompen-respon.xlsx'),
    ])->assertSessionHasErrors(['uploader_name']);
});

test('an admin cannot delete an upload while another import is still active', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createStudent();

    KompenResponHubImportTask::factory()->create([
        'status' => KompenResponHubImportTask::STATUS_PROCESSING,
    ]);

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/imports/{$student->kompen_respon_hub_import_id}/activate")
        ->assertRedirect('/admin?tab=files')
        ->assertSessionHas('error');

    expect(KompenResponHubStudent::query()->find($student->id))->not->toBeNull();
});

test('an uploaded workbook is queued and its progress remains available outside the upload tab', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent('kompen-respon.xlsx', workbookContents()),
    ])->assertRedirect('/admin?tab=upload');

    $importTask = KompenResponHubImportTask::query()->sole();
    expect($importTask->status)->toBe(KompenResponHubImportTask::STATUS_QUEUED)
        ->and($importTask->progress)->toBe(0)
        ->and($importTask->uploader_name)->toBe('Khairul Anwar');

    Queue::assertPushed(
        ProcessKompenResponHubImport::class,
        fn (ProcessKompenResponHubImport $job): bool => $job->importTaskId === $importTask->id,
    );

    $this->actingAs($admin, 'admin')->get('/admin?tab=details')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeTab', 'details')
            ->has('activeImportTasks', 1)
            ->where('activeImportTasks.0.id', $importTask->id)
            ->where('activeImportTasks.0.status', KompenResponHubImportTask::STATUS_QUEUED),
        );

    $this->actingAs($admin, 'admin')
        ->getJson("/admin/kompen-respon/import-tasks/{$importTask->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $importTask->id)
        ->assertJsonPath('data.progress', 0);
});

test('an import rejects an XLSX archive with too many internal entries before storage', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent(
            'kompen-respon.xlsx',
            workbookContentsWithExtraArchiveEntries(64),
        ),
    ])->assertSessionHasErrors('file');

    expect(KompenResponHubImportTask::query()->doesntExist())->toBeTrue();
});

test('an import rejects workbooks containing formulas before storage', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent(
            'kompen-respon.xlsx',
            workbookContents(formulaCell: 'L4'),
        ),
    ])->assertSessionHasErrors('file');

    expect(KompenResponHubImportTask::query()->doesntExist())->toBeTrue();
});

test('an import accepts worksheets expanded beyond the official template dimensions', function () {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent(
            'kompen-respon.xlsx',
            workbookContents(summaryOutsideTemplateCell: 'A681'),
        ),
    ])->assertRedirect('/admin?tab=upload');

    $importTask = KompenResponHubImportTask::query()->sole();

    expect($importTask->status)->toBe(KompenResponHubImportTask::STATUS_COMPLETED)
        ->and(KompenResponHubImport::query()->exists())->toBeTrue();
});

test('expired import task records are purged while imports and audit logs are retained', function () {
    config(['kompen-respon-hub.retention.import_task_days' => 30]);
    $oldCompletedTask = KompenResponHubImportTask::factory()->create([
        'status' => KompenResponHubImportTask::STATUS_COMPLETED,
        'completed_at' => now()->subDays(31),
    ]);
    $recentFailedTask = KompenResponHubImportTask::factory()->create([
        'status' => KompenResponHubImportTask::STATUS_FAILED,
        'failed_at' => now()->subDays(29),
    ]);
    $import = KompenResponHubImport::create([
        'periode_semester' => '2026/2027 Gasal',
        'original_filename' => 'source.xlsx',
        'stored_path' => 'kompen-respon-hub/imports/source.xlsx',
        'file_hash' => str_repeat('d', 64),
        'class_count' => 1,
        'student_count' => 1,
        'detail_count' => 0,
        'imported_at' => now(),
    ]);

    $this->artisan('sikompen:purge-import-tasks')->assertSuccessful();

    expect(KompenResponHubImportTask::query()->find($oldCompletedTask->id))->toBeNull()
        ->and(KompenResponHubImportTask::query()->find($recentFailedTask->id))->not->toBeNull()
        ->and(KompenResponHubImport::query()->find($import->id))->not->toBeNull()
        ->and(KompenResponHubActivityLog::query()->where('event_type', 'maintenance.import_tasks_purged')->exists())->toBeTrue();
});

test('stalled import and export tasks are marked failed and added to the audit trail', function () {
    config(['kompen-respon-hub.queue.stalled_task_minutes' => 15]);
    $importTask = KompenResponHubImportTask::factory()->create([
        'status' => KompenResponHubImportTask::STATUS_QUEUED,
        'queued_at' => now()->subMinutes(16),
    ]);
    $exportTask = KompenResponHubExportTask::factory()->create([
        'status' => KompenResponHubExportTask::StatusProcessing,
        'started_at' => now()->subMinutes(16),
    ]);

    $this->artisan('sikompen:reconcile-tasks')->assertSuccessful();

    expect($importTask->refresh()->status)->toBe(KompenResponHubImportTask::STATUS_FAILED)
        ->and($exportTask->refresh()->status)->toBe(KompenResponHubExportTask::StatusFailed)
        ->and(KompenResponHubActivityLog::query()->where('event_type', 'import.timed_out')->exists())->toBeTrue()
        ->and(KompenResponHubActivityLog::query()->where('event_type', 'export.timed_out')->exists())->toBeTrue();
});

test('an admin can download the empty Sikompen import template', function () {
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get('/admin/kompen-respon/template')
        ->assertDownload('Template_Impor_Kompen_Respon_Hub.xlsx');
});

test('an invalid academic year marks the queued import as failed', function () {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent(
            'kompen-respon.xlsx',
            workbookContents('Gasal', '2026/2028'),
        ),
    ])->assertRedirect('/admin?tab=upload');

    $importTask = KompenResponHubImportTask::query()->sole();
    expect($importTask->status)->toBe(KompenResponHubImportTask::STATUS_FAILED)
        ->and($importTask->error_message)->toContain('Tahun ajaran wajib berformat')
        ->and(KompenResponHubActivityLog::query()->where('event_type', 'import.failed')->exists())->toBeTrue();
});

test('an import is rejected when summary hours do not match detail totals', function () {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent(
            'kompen-respon.xlsx',
            workbookContents(detailCompensationHours: 1),
        ),
    ])->assertRedirect('/admin?tab=upload');

    $importTask = KompenResponHubImportTask::query()->sole();

    expect($importTask->status)->toBe(KompenResponHubImportTask::STATUS_FAILED)
        ->and($importTask->error_message)->toContain('Total Kompensasi 1.5000 jam tidak sama')
        ->and(KompenResponHubImport::query()->doesntExist())->toBeTrue()
        ->and(KompenResponHubStudent::query()->doesntExist())->toBeTrue();
});

test('an import is rejected when a detail name differs from its summary identity', function () {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent(
            'kompen-respon.xlsx',
            workbookContents(detailName: 'Rina Berbeda'),
        ),
    ])->assertRedirect('/admin?tab=upload');

    $importTask = KompenResponHubImportTask::query()->sole();

    expect($importTask->status)->toBe(KompenResponHubImportTask::STATUS_FAILED)
        ->and($importTask->error_message)->toContain('Nama mahasiswa harus sama');
});

test('an import is rejected when it contains an identical detail twice', function () {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent(
            'kompen-respon.xlsx',
            workbookContents(duplicateDetail: true),
        ),
    ])->assertRedirect('/admin?tab=upload');

    $importTask = KompenResponHubImportTask::query()->sole();

    expect($importTask->status)->toBe(KompenResponHubImportTask::STATUS_FAILED)
        ->and($importTask->error_message)->toContain('Detail duplikat dengan baris 2 ditemukan.');
});

test('an import rejects a legacy combined period value', function () {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent(
            'kompen-respon.xlsx',
            workbookContents('2026/2027 Ganjil', ''),
        ),
    ])->assertRedirect('/admin?tab=upload');

    $importTask = KompenResponHubImportTask::query()->sole();

    expect($importTask->status)->toBe(KompenResponHubImportTask::STATUS_FAILED)
        ->and($importTask->error_message)->toContain('Semester wajib diisi dengan Gasal atau Genap.');
});

test('an import rejects semester names other than Gasal or Genap', function () {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent(
            'kompen-respon.xlsx',
            workbookContents('Ganjil', '2026/2027'),
        ),
    ])->assertRedirect('/admin?tab=upload');

    $importTask = KompenResponHubImportTask::query()->sole();

    expect($importTask->status)->toBe(KompenResponHubImportTask::STATUS_FAILED)
        ->and($importTask->error_message)->toContain('Semester wajib diisi dengan Gasal atau Genap.');
});

test('an admin import reads the class and level metadata from the workbook', function () {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent(
            'kompen-respon.xlsx',
            workbookContents(classCode: '2AEA1', level: 2),
        ),
    ])->assertRedirect('/admin?tab=upload');

    $import = KompenResponHubImport::query()->sole();
    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/imports/{$import->id}/activate")
        ->assertRedirect('/admin?tab=files');

    $student = KompenResponHubStudent::query()->sole();
    expect($student->kelas)->toBe('2AEA1')
        ->and($student->tingkat)->toBe(2);
});

test('a level that differs from its class code marks the queued import as failed', function () {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent(
            'kompen-respon.xlsx',
            workbookContents(classCode: '2AEA1', level: 1),
        ),
    ])->assertRedirect('/admin?tab=upload');

    $importTask = KompenResponHubImportTask::query()->sole();
    expect($importTask->status)->toBe(KompenResponHubImportTask::STATUS_FAILED)
        ->and($importTask->error_message)->toContain('Tingkat harus sama dengan angka awal');
});

function createStudent(string $period = '2026/2027 Ganjil'): KompenResponHubStudent
{
    $import = KompenResponHubImport::create([
        'periode_semester' => $period,
        'original_filename' => 'source.xlsx',
        'stored_path' => 'kompen-respon-hub/imports/source.xlsx',
        'file_hash' => str_repeat('a', 64),
        'class_count' => 1,
        'student_count' => 1,
        'detail_count' => 0,
        'imported_at' => now(),
    ]);

    $student = KompenResponHubStudent::create([
        'kompen_respon_hub_import_id' => $import->id,
        'nim' => '123456789',
        'periode_semester' => $period,
        'nama_mahasiswa' => 'Rina Utami',
        'kelas' => '1AEA1',
        'tingkat' => 1,
        'total_jam_terlambat' => 0.5,
        'total_jam_sakit' => 0,
        'total_jam_izin' => 0,
        'total_jam_bolos' => 0,
        'total_kompensasi_jam' => 1.5,
        'total_responsi_jam' => 2,
        'total_hutang_jam' => 3.5,
        'kompensasi_dikerjakan_jam' => 0,
        'sisa_hutang_jam' => 3.5,
    ]);

    KompenResponHubActiveImport::query()->updateOrCreate(
        ['periode_semester' => $period],
        [
            'kompen_respon_hub_import_id' => $import->id,
            'activated_at' => $import->imported_at,
        ],
    );

    return $student;
}

function workbookContents(
    string $semester = 'Gasal',
    string $academicYear = '2026/2027',
    string $classCode = '1AEA1',
    int $level = 1,
    float $compensationHours = 1.5,
    float $responseHours = 2,
    ?float $detailCompensationHours = null,
    ?float $detailResponseHours = null,
    ?string $detailName = null,
    bool $duplicateDetail = false,
    ?string $summaryOutsideTemplateCell = null,
    ?string $formulaCell = null,
): string {
    $totalHours = $compensationHours + $responseHours;
    $workbook = new Spreadsheet;
    $summary = $workbook->getActiveSheet();
    $summary->setTitle('Kompen dan Respon');
    $summary->setCellValue('A1', "KOMPEN DAN RESPON — {$classCode}");
    $summary->setCellValue('B2', $semester);
    $summary->setCellValue('C2', $academicYear);
    $summary->setCellValue('E2', $classCode);
    $summary->setCellValue('H2', $level);
    $summary->fromArray([
        ['NO.', 'NIM', 'NAMA MAHASISWA', 'T[J]', 'S[J]', 'I[J]', 'B[J]', 'KOMPENSASI[J]', 'RESPONSI[J]', 'TOTAL[J]', 'KOMPENSASI DIKERJAKAN[J]', 'SISA KOMPEN[J]'],
        [1, '123456789', 'Rina Utami', 0.5, 0, 0, 0, $compensationHours, $responseHours, $totalHours, 0, $totalHours],
    ], null, 'A3');
    $summary->setCellValue('A6', 'TEMPLATE BLOK KELAS BARU — SALIN LALU GANTI KODE');

    if ($summaryOutsideTemplateCell !== null) {
        $summary->setCellValue($summaryOutsideTemplateCell, 'Di luar kapasitas template');
    }

    if ($formulaCell !== null) {
        $summary->setCellValue($formulaCell, '=1+1');
    }

    $details = $workbook->createSheet();
    $details->setTitle('Detail Kompen');
    $detailRow = [
        1,
        $classCode,
        '123456789',
        $detailName ?? 'Rina Utami',
        'Algoritma',
        'Ibu Sari',
        '2026-09-01',
        'Luring',
        'Terlambat',
        15,
        'Macet',
        $detailCompensationHours ?? $compensationHours,
        $detailResponseHours ?? $responseHours,
    ];
    $detailRows = [$detailRow];
    if ($duplicateDetail) {
        $duplicateRow = $detailRow;
        $duplicateRow[0] = 2;
        $detailRows[] = $duplicateRow;
    }

    $details->fromArray([
        ['NO.', 'KELAS', 'NIM', 'NAMA MAHASISWA', 'MATA KULIAH', 'NAMA DOSEN', 'TANGGAL', 'JENIS PERTEMUAN', 'PRESENSI', 'MENIT KETERLAMBATAN', 'KETERANGAN', 'JAM KOMPENSASI', 'JAM RESPONSI'],
        ...$detailRows,
    ]);

    $writer = new Xlsx($workbook);
    ob_start();
    $writer->save('php://output');

    return (string) ob_get_clean();
}

function workbookContentsWithExtraArchiveEntries(int $extraEntries): string
{
    $temporaryFile = tempnam(sys_get_temp_dir(), 'sikompen-archive-');

    if ($temporaryFile === false) {
        throw new RuntimeException('Temporary workbook cannot be created.');
    }

    file_put_contents($temporaryFile, workbookContents());
    $archive = new ZipArchive;
    $archive->open($temporaryFile);

    for ($index = 0; $index < $extraEntries; $index++) {
        $archive->addFromString("xl/media/unexpected-{$index}.bin", 'x');
    }

    $archive->close();
    $contents = file_get_contents($temporaryFile);
    unlink($temporaryFile);

    if (! is_string($contents)) {
        throw new RuntimeException('Temporary workbook cannot be read.');
    }

    return $contents;
}
