<?php

use App\Models\KompenResponHubActivityLog;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubStudent;
use App\Models\KompenResponHubStudentProgress;
use App\Models\KompenResponHubWarningLetter;

test('an admin can set a cutoff and record bounded student progress', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();

    $this->actingAs($admin, 'admin')
        ->put('/admin/kompen-respon/cutoffs', [
            'periode_semester' => $student->periode_semester,
            'deadline_at' => now('Asia/Jakarta')->addWeek()->format('Y-m-d\\TH:i'),
        ])
        ->assertRedirect();

    $this->actingAs($admin, 'admin')
        ->put("/admin/kompen-respon/students/{$student->id}/progress", [
            'kompensasi_dikerjakan_jam' => 1.25,
            'responsi_dikerjakan_jam' => 1.5,
            'last_worked_at' => now('Asia/Jakarta')->subDay()->format('Y-m-d\\TH:i'),
            'reason' => 'Verifikasi pengerjaan laboratorium.',
        ])
        ->assertRedirect();

    expect(KompenResponHubPeriodCutoff::query()->sole()->timezone)->toBe('Asia/Jakarta')
        ->and(KompenResponHubStudentProgress::query()->sole()->kompensasi_dikerjakan_jam)->toBe('1.2500')
        ->and(KompenResponHubActivityLog::query()->where('event_type', 'progress.updated')->exists())->toBeTrue();
});

test('progress cannot exceed the effective debt total', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();

    $this->actingAs($admin, 'admin')
        ->put("/admin/kompen-respon/students/{$student->id}/progress", [
            'kompensasi_dikerjakan_jam' => 9,
            'responsi_dikerjakan_jam' => 0,
            'last_worked_at' => now('Asia/Jakarta')->subDay()->format('Y-m-d\\TH:i'),
            'reason' => 'Tidak boleh melampaui total hutang.',
        ])
        ->assertUnprocessable();

    expect(KompenResponHubStudentProgress::query()->doesntExist())->toBeTrue();
});

test('the scheduled command archives only outstanding students after a cutoff', function () {
    $student = createLifecycleStudent();
    KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->artisan('sikompen:archive-warning-candidates')->assertSuccessful();

    expect(KompenResponHubWarningLetter::query()->sole())
        ->letter_status->toBe(KompenResponHubWarningLetter::LetterStatusNotCreated)
        ->and(KompenResponHubWarningLetter::query()->sole()->classification)->toBe('fixed')
        ->and(KompenResponHubActivityLog::query()->where('event_type', 'warning.archived')->exists())->toBeTrue();
});

test('an admin can prepare a manual SP-1 draft before the cutoff', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->addWeek(),
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->actingAs($admin, 'admin')
        ->post('/admin/kompen-respon/warnings', [
            'student_id' => $student->id,
            'reason' => 'Verifikasi manual untuk kebutuhan surat peringatan.',
        ])
        ->assertRedirect();

    expect(KompenResponHubWarningLetter::query()->sole())
        ->letter_status->toBe(KompenResponHubWarningLetter::LetterStatusDraft)
        ->and(KompenResponHubWarningLetter::query()->sole()->classification)->toBe('temporary');
});

function createLifecycleStudent(): KompenResponHubStudent
{
    $import = KompenResponHubImport::create([
        'periode_semester' => '2026/2027 Gasal',
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
        'periode_semester' => '2026/2027 Gasal',
        'nama_mahasiswa' => 'Rina Utami',
        'kelas' => '1AEA1',
        'tingkat' => 1,
        'total_jam_terlambat' => 0,
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
