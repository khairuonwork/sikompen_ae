<?php

use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubDetail;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubStudent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

test('the public page and data API do not require a login', function () {
    $student = createStudent();

    $this->get('/kompen-respon')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('kompen-respon-hub/index')
            ->where('activeTab', 'students')
            ->where('isAdmin', false)
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

test('students can download an XLSX limited to the selected uploaded period', function () {
    createStudent();

    $this->get('/mahasiswa/downloads/students')
        ->assertRedirect()
        ->assertSessionHasErrors('periode_semester');

    $this->get('/mahasiswa/downloads/students?periode_semester=2026%2F2027%20Ganjil')
        ->assertOk()
        ->assertDownload('kompen-dan-respon-2026-2027-ganjil.xlsx');
});

test('an authenticated admin can upload a valid workbook that replaces matching class data', function () {
    Storage::fake('local');
    $oldStudent = createStudent();
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'uploader_name' => 'Khairul Anwar',
        'file' => UploadedFile::fake()->createWithContent(
            'kompen-respon.xlsx',
            workbookContents(),
        ),
    ])->assertRedirect('/admin/kompen-respon');

    expect(KompenResponHubStudent::query()->find($oldStudent->id))->toBeNull();

    $student = KompenResponHubStudent::query()->sole();
    expect($student->nim)->toBe('123456789')
        ->and($student->total_hutang_jam)->toBe('3.5000');

    $import = KompenResponHubImport::query()->latest('id')->firstOrFail();
    expect($import->uploaded_by_admin_id)->toBe($admin->id)
        ->and($import->uploader_name)->toBe('Khairul Anwar')
        ->and($import->uploader_email)->toBe($admin->email);

    $this->actingAs($admin, 'admin')->get('/admin/kompen-respon?tab=imports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeTab', 'imports')
            ->has('imports.data', 2)
            ->where('imports.data.0.uploader_name', 'Khairul Anwar')
            ->where('imports.data.0.uploader_email', $admin->email),
        );

    $this->getJson('/api/kompen-respon/details?nim=123456789')
        ->assertOk()
        ->assertJsonPath('data.0.nama_dosen', 'Ibu Sari')
        ->assertJsonPath('data.0.jam_kompensasi', '1.5000');
});

test('an admin must enter their name before importing a workbook', function () {
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/imports', [
        'file' => UploadedFile::fake()->create('kompen-respon.xlsx'),
    ])->assertSessionHasErrors('uploader_name');
});

function createStudent(): KompenResponHubStudent
{
    $import = KompenResponHubImport::create([
        'periode_semester' => '2026/2027 Ganjil',
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
        'periode_semester' => '2026/2027 Ganjil',
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

function workbookContents(): string
{
    $workbook = new Spreadsheet;
    $summary = $workbook->getActiveSheet();
    $summary->setTitle('Kompen dan Respon');
    $summary->setCellValue('A1', 'KOMPEN DAN RESPON — 1AEA1');
    $summary->setCellValue('B2', '2026/2027 Ganjil');
    $summary->fromArray([
        ['NO.', 'NIM', 'NAMA MAHASISWA', 'T[J]', 'S[J]', 'I[J]', 'B[J]', 'KOMPENSASI[J]', 'RESPONSI[J]', 'TOTAL[J]', 'KOMPENSASI DIKERJAKAN[J]', 'SISA KOMPEN[J]'],
        [1, '123456789', 'Rina Utami', 0.5, 0, 0, 0, 1.5, 2, 3.5, 0, 3.5],
    ], null, 'A3');

    $details = $workbook->createSheet();
    $details->setTitle('Detail Kompen');
    $details->fromArray([
        ['NO.', 'KELAS', 'NIM', 'NAMA MAHASISWA', 'MATA KULIAH', 'NAMA DOSEN', 'TANGGAL', 'JENIS PERTEMUAN', 'PRESENSI', 'MENIT KETERLAMBATAN', 'KETERANGAN', 'JAM KOMPENSASI', 'JAM RESPONSI'],
        [1, '1AEA1', '123456789', 'Rina Utami', 'Algoritma', 'Ibu Sari', '2026-09-01', 'Luring', 'Terlambat', 15, 'Macet', 1.5, 2],
    ]);

    $writer = new Xlsx($workbook);
    ob_start();
    $writer->save('php://output');

    return (string) ob_get_clean();
}
