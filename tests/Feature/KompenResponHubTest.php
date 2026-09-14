<?php

use App\Actions\KompenResponHub\KompenResponHubDataQuery;
use App\Jobs\ProcessKompenResponHubImport;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubDetail;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubImportAuditLog;
use App\Models\KompenResponHubImportTask;
use App\Models\KompenResponHubStudent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

test('students can download a landscape XLSX limited to the selected uploaded period', function () {
    createStudent();

    $this->get('/mahasiswa/downloads/students')
        ->assertRedirect()
        ->assertSessionHasErrors('periode_semester');

    $response = $this->get('/mahasiswa/downloads/students?periode_semester=2026%2F2027%20Ganjil');

    $response
        ->assertOk()
        ->assertDownload('kompen-dan-respon-2026-2027-ganjil.xlsx')
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
            ->and($worksheet->getCell('B2')->getValue())->toBe('2026/2027 Ganjil')
            ->and($worksheet->getCell('B5')->getValue())->toBe('123456789')
            ->and($worksheet->getStyle('A5')->getNumberFormat()->getFormatCode())->toBe('#,##0')
            ->and($worksheet->getStyle('F5')->getNumberFormat()->getFormatCode())->toBe('#,##0.00');
    } finally {
        unlink($temporaryFile);
    }
});

test('students can download a landscape PDF for detail kompen', function () {
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

    $response = $this->get('/mahasiswa/downloads/details/pdf?periode_semester=2026%2F2027%20Ganjil');

    $response
        ->assertOk()
        ->assertDownload('detail-kompen-2026-2027-ganjil.pdf')
        ->assertHeaderContains('Content-Type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF-');
});

test('an authenticated admin can upload a valid workbook that replaces matching class data', function () {
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

    expect(KompenResponHubStudent::query()->find($oldStudent->id))->toBeNull();

    $student = KompenResponHubStudent::query()->sole();
    expect($student->nim)->toBe('123456789')
        ->and($student->total_hutang_jam)->toBe('3.5000');

    $import = KompenResponHubImport::query()->latest('id')->firstOrFail();
    expect($import->periode_semester)->toBe('2026/2027 Gasal')
        ->and($import->uploaded_by_admin_id)->toBe($admin->id)
        ->and($import->uploader_name)->toBe('Khairul Anwar')
        ->and($import->uploader_email)->toBe($admin->email);

    $this->actingAs($admin, 'admin')->get('/admin?tab=imports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeTab', 'imports')
            ->has('imports.data', 1)
            ->where('imports.data.0.event_type', 'upload')
            ->where('imports.data.0.actor_name', 'Khairul Anwar')
            ->where('imports.data.0.actor_email', $admin->email),
        );

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
        ->assertDownload("sikompen-import-{$import->id}.xlsx");

    $this->actingAs($admin, 'admin')->get('/admin?tab=imports')
        ->assertInertia(fn ($page) => $page
            ->where('imports.data.0.can_download_file', true),
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

test('a completed import clears cached filter options', function () {
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
        ->assertJsonPath('periode_semester', ['2026/2027 Gasal']);
});

test('an admin can delete only the latest upload and its related data is removed', function () {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();
    $olderStudent = createStudent('2025/2026 Gasal');
    $latestStudent = createStudent('2026/2027 Gasal');
    $latestImport = KompenResponHubImport::query()
        ->findOrFail($latestStudent->kompen_respon_hub_import_id);

    Storage::disk('local')->put($latestImport->stored_path, 'workbook');

    KompenResponHubDetail::create([
        'kompen_respon_hub_student_id' => $latestStudent->id,
        'tanggal' => '2026-09-01',
        'mata_kuliah' => 'Algoritma',
        'nama_dosen' => 'Ibu Sari',
        'jenis_pertemuan' => 'Luring',
        'presensi' => 'Terlambat',
        'menit_keterlambatan' => 15,
        'jam_kompensasi' => 1.5,
        'jam_responsi' => 2,
    ]);

    $this->actingAs($admin, 'admin')
        ->delete('/admin/kompen-respon/imports/latest')
        ->assertRedirect('/admin?tab=imports');

    expect(KompenResponHubImport::query()->find($latestImport->id))->toBeNull()
        ->and(KompenResponHubStudent::query()->find($latestStudent->id))->toBeNull()
        ->and(KompenResponHubDetail::query()->where('kompen_respon_hub_student_id', $latestStudent->id)->exists())->toBeFalse()
        ->and(KompenResponHubStudent::query()->find($olderStudent->id))->not->toBeNull();

    Storage::disk('local')->assertMissing($latestImport->stored_path);

    $auditLog = KompenResponHubImportAuditLog::query()->latest('id')->firstOrFail();
    expect($auditLog->event_type)->toBe(KompenResponHubImportAuditLog::EVENT_ROLLBACK)
        ->and($auditLog->source_import_id)->toBe($latestImport->id)
        ->and($auditLog->actor_email)->toBe($admin->email)
        ->and($auditLog->student_count)->toBe(1)
        ->and($auditLog->detail_count)->toBe(0);
});

test('a rollback clears cached filter options', function () {
    Cache::forget(KompenResponHubDataQuery::FILTER_OPTIONS_CACHE_KEY);
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();
    createStudent('2025/2026 Gasal');
    $latestStudent = createStudent('2026/2027 Gasal');
    $latestImport = KompenResponHubImport::query()
        ->findOrFail($latestStudent->kompen_respon_hub_import_id);

    Storage::disk('local')->put($latestImport->stored_path, 'workbook');

    $this->getJson('/api/kompen-respon/filter-options')
        ->assertOk()
        ->assertJsonPath('periode_semester', ['2026/2027 Gasal', '2025/2026 Gasal']);

    $this->actingAs($admin, 'admin')
        ->delete('/admin/kompen-respon/imports/latest')
        ->assertRedirect('/admin?tab=imports');

    $this->getJson('/api/kompen-respon/filter-options')
        ->assertOk()
        ->assertJsonPath('periode_semester', ['2025/2026 Gasal']);
});

test('a guest cannot delete the latest upload', function () {
    $this->delete('/admin/kompen-respon/imports/latest')
        ->assertRedirect('/admin/login');
});

test('an admin cannot delete an upload while another import is still active', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createStudent();

    KompenResponHubImportTask::factory()->create([
        'status' => KompenResponHubImportTask::STATUS_PROCESSING,
    ]);

    $this->actingAs($admin, 'admin')
        ->delete('/admin/kompen-respon/imports/latest')
        ->assertRedirect('/admin?tab=imports')
        ->assertSessionHas('error');

    expect(KompenResponHubStudent::query()->find($student->id))->not->toBeNull();
});

test('an uploaded workbook is queued and its progress remains available outside the upload tab', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->create('kompen-respon.xlsx', 100),
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
        ->and($importTask->error_message)->toContain('Tahun ajaran wajib berformat');
});

test('an admin can import a legacy workbook with its period in one cell', function () {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent(
            'kompen-respon.xlsx',
            workbookContents('2026/2027 Ganjil', ''),
        ),
    ])->assertRedirect('/admin?tab=upload');

    expect(KompenResponHubImport::query()->sole()->periode_semester)->toBe('2026/2027 Gasal');
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

    return KompenResponHubStudent::create([
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
}

function workbookContents(
    string $semester = 'Gasal',
    string $academicYear = '2026/2027',
    string $classCode = '1AEA1',
    int $level = 1,
): string {
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
        [1, '123456789', 'Rina Utami', 0.5, 0, 0, 0, 1.5, 2, 3.5, 0, 3.5],
    ], null, 'A3');
    $summary->setCellValue('A6', 'TEMPLATE BLOK KELAS BARU — SALIN LALU GANTI KODE');

    $details = $workbook->createSheet();
    $details->setTitle('Detail Kompen');
    $details->fromArray([
        ['NO.', 'KELAS', 'NIM', 'NAMA MAHASISWA', 'MATA KULIAH', 'NAMA DOSEN', 'TANGGAL', 'JENIS PERTEMUAN', 'PRESENSI', 'MENIT KETERLAMBATAN', 'KETERANGAN', 'JAM KOMPENSASI', 'JAM RESPONSI'],
        [1, $classCode, '123456789', 'Rina Utami', 'Algoritma', 'Ibu Sari', '2026-09-01', 'Luring', 'Terlambat', 15, 'Macet', 1.5, 2],
    ]);

    $writer = new Xlsx($workbook);
    ob_start();
    $writer->save('php://output');

    return (string) ob_get_clean();
}
