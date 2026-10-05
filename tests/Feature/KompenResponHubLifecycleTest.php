<?php

use App\Actions\KompenResponHub\KompenResponHubDataQuery;
use App\Models\KompenResponHubActivityLog;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubStudent;
use App\Models\KompenResponHubStudentProgress;
use App\Models\KompenResponHubWarningLetter;
use Carbon\CarbonImmutable;

test('an admin can set a cutoff five minutes from the current Asia Jakarta time', function () {
    $this->travelTo(CarbonImmutable::create(2026, 10, 5, 10, 0, 0, 'Asia/Jakarta'));

    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();

    $this->actingAs($admin, 'admin')
        ->put('/admin/kompen-respon/cutoffs', [
            'periode_semester' => $student->periode_semester,
            'deadline_at' => '2026-10-05T10:05',
        ])
        ->assertRedirect();

    expect(KompenResponHubPeriodCutoff::query()->sole()->deadline_at)
        ->toEqual(CarbonImmutable::create(2026, 10, 5, 10, 5, 0, 'Asia/Jakarta'));
});

test('an admin cannot set a cutoff less than five minutes from the current Asia Jakarta time', function () {
    $this->travelTo(CarbonImmutable::create(2026, 10, 5, 10, 0, 0, 'Asia/Jakarta'));

    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();

    $this->actingAs($admin, 'admin')
        ->put('/admin/kompen-respon/cutoffs', [
            'periode_semester' => $student->periode_semester,
            'deadline_at' => '2026-10-05T10:04',
        ])
        ->assertSessionHasErrors([
            'deadline_at' => 'Batas waktu harus minimal 5 menit dari waktu saat ini (Asia/Jakarta).',
        ]);

    expect(KompenResponHubPeriodCutoff::query()->doesntExist())->toBeTrue();
});

test('an admin can set a cutoff and record bounded student progress', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    $deadline = now('Asia/Jakarta')->addWeek()->setTime(12, 0);

    $this->actingAs($admin, 'admin')
        ->put('/admin/kompen-respon/cutoffs', [
            'periode_semester' => $student->periode_semester,
            'deadline_at' => $deadline->format('Y-m-d\\TH:i'),
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
        ->and(KompenResponHubPeriodCutoff::query()->sole()->deadline_at->setTimezone('Asia/Jakarta')->format('H:i'))->toBe('12:00')
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

test('a passed cutoff does not issue an SP before an admin finalizes it', function () {
    $student = createLifecycleStudent();
    $cutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
    ]);

    expect(KompenResponHubWarningLetter::query()->doesntExist())->toBeTrue()
        ->and(KompenResponHubActivityLog::query()->where('event_type', 'warning.issued')->doesntExist())->toBeTrue();
});

test('a passed cutoff shows outstanding students as candidates without exposing an SP through the API', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    $cutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=warnings')
        ->assertInertia(fn ($page) => $page
            ->where('activeTab', 'warnings')
            ->where('warningCandidates.meta.page_name', 'warning_candidate_page')
            ->has('warningCandidates.data', 1)
            ->where('warningCandidates.data.0.id', $student->id)
            ->has('warnings.data', 0),
        );

    $this->getJson('/api/kompen-respon/students')
        ->assertOk()
        ->assertJsonPath('data.0.progress_status', 'none')
        ->assertJsonPath('data.0.has_active_warning', false);
});

test('a legacy not-created warning remains a candidate after its cutoff passes', function () {
    $student = createLifecycleStudent();
    $cutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
    ]);
    KompenResponHubWarningLetter::create([
        'cutoff_id' => $cutoff->id,
        'current_student_id' => $student->id,
        'nim' => $student->nim,
        'periode_semester' => $student->periode_semester,
        'kelas' => $student->kelas,
        'nama_mahasiswa' => $student->nama_mahasiswa,
        'classification' => 'fixed',
        'letter_status' => KompenResponHubWarningLetter::LetterStatusNotCreated,
        'resolution' => 'outstanding',
        'snapshot' => ['sisa_hutang_jam' => 3.5],
    ]);

    expect(app(KompenResponHubDataQuery::class)
        ->warningCandidates(['periode_semester' => $student->periode_semester])
        ->pluck('id'))
        ->toContain($student->id);
});

test('the warning page keeps candidates scoped to its selected cutoff period', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $gasalStudent = createLifecycleStudent();
    $genapStudent = $gasalStudent->replicate();
    $genapStudent->fill([
        'nim' => '987654321',
        'nama_mahasiswa' => 'Dani Pratama',
        'periode_semester' => '2026/2027 Genap',
        'kelas' => '2AEA1',
        'tingkat' => 2,
    ])->save();

    KompenResponHubPeriodCutoff::create([
        'periode_semester' => $gasalStudent->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
    ]);
    KompenResponHubPeriodCutoff::create([
        'periode_semester' => $genapStudent->periode_semester,
        'deadline_at' => now()->addWeek(),
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=warnings')
        ->assertInertia(fn ($page) => $page
            ->where('filters.periode_semester', '2026/2027 Genap')
            ->has('warningCandidates.data', 0),
        );

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=warnings&periode_semester=2026%2F2027%20Gasal')
        ->assertInertia(fn ($page) => $page
            ->where('filters.periode_semester', '2026/2027 Gasal')
            ->has('warningCandidates.data', 1)
            ->where('warningCandidates.data.0.id', $gasalStudent->id),
        );
});

test('an admin cannot finalize an SP before the cutoff', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    $cutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->addWeek(),
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/cutoffs/{$cutoff->id}/finalize")
        ->assertUnprocessable();

    expect(KompenResponHubWarningLetter::query()->doesntExist())->toBeTrue();
});

test('updating a cutoff recalculates warning classification without cancelling an existing draft', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    $cutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
    ]);
    $warning = KompenResponHubWarningLetter::create([
        'cutoff_id' => $cutoff->id,
        'current_student_id' => $student->id,
        'nim' => $student->nim,
        'periode_semester' => $student->periode_semester,
        'kelas' => $student->kelas,
        'nama_mahasiswa' => $student->nama_mahasiswa,
        'classification' => 'fixed',
        'letter_status' => KompenResponHubWarningLetter::LetterStatusDraft,
        'resolution' => 'outstanding',
        'snapshot' => ['sisa_hutang_jam' => 3.5],
    ]);

    $this->actingAs($admin, 'admin')
        ->put('/admin/kompen-respon/cutoffs', [
            'periode_semester' => $student->periode_semester,
            'deadline_at' => now('Asia/Jakarta')->addWeek()->format('Y-m-d\\TH:i'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Batas waktu periode berhasil disimpan. 1 status SP diselaraskan.');

    expect($warning->fresh())
        ->classification->toBe('temporary')
        ->and($warning->fresh()->letter_status)->toBe(KompenResponHubWarningLetter::LetterStatusDraft)
        ->and(app(KompenResponHubDataQuery::class)->warnings([])->doesntExist())->toBeTrue()
        ->and(KompenResponHubActivityLog::query()
            ->where('event_type', 'warning.classification_temporary')
            ->where('subject_name', $student->nama_mahasiswa)
            ->exists())->toBeTrue();
});

test('completed progress resolves an existing warning without deleting its trace', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    $cutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/cutoffs/{$cutoff->id}/finalize")
        ->assertRedirect();

    $this->actingAs($admin, 'admin')
        ->put("/admin/kompen-respon/students/{$student->id}/progress", [
            'kompensasi_dikerjakan_jam' => 1.5,
            'responsi_dikerjakan_jam' => 2,
            'last_worked_at' => now('Asia/Jakarta')->format('Y-m-d\\TH:i'),
            'reason' => 'Seluruh jam Kompen dan Responsi telah diselesaikan.',
        ])
        ->assertRedirect();

    expect(KompenResponHubWarningLetter::query()->sole())
        ->resolution->toBe('completed')
        ->and(KompenResponHubWarningLetter::query()->sole()->snapshot['sisa_hutang_jam'])->toBe(0)
        ->and(KompenResponHubActivityLog::query()->where('event_type', 'warning.resolution_updated')->exists())->toBeTrue();

    $this->getJson('/api/kompen-respon/students')
        ->assertOk()
        ->assertJsonPath('data.0.progress_status', 'completed');
});

test('students have no status before the cutoff has passed', function () {
    $student = createLifecycleStudent();
    $cutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->addWeek(),
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->getJson('/api/kompen-respon/students')
        ->assertOk()
        ->assertJsonPath('data.0.progress_status', 'none');
});

test('an admin can finalize a passed cutoff into an issued SP', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    $cutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/cutoffs/{$cutoff->id}/finalize")
        ->assertRedirect();

    expect(KompenResponHubWarningLetter::query()->sole())
        ->letter_status->toBe(KompenResponHubWarningLetter::LetterStatusIssued)
        ->and(KompenResponHubWarningLetter::query()->sole()->issued_at)->not->toBeNull()
        ->and(KompenResponHubActivityLog::query()->where('event_type', 'warning.issued')->exists())->toBeTrue()
        ->and(KompenResponHubActivityLog::query()->where('event_type', 'period.finalized')->exists())->toBeTrue();
});

test('an admin can roll back an SP without exposing it through the student API', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    $cutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/cutoffs/{$cutoff->id}/finalize")
        ->assertRedirect();

    $warning = KompenResponHubWarningLetter::query()->sole();

    $this->actingAs($admin, 'admin')
        ->delete("/admin/kompen-respon/warnings/{$warning->id}", [
            'reason' => 'Data surat perlu diperbaiki sebelum diterbitkan.',
        ])
        ->assertRedirect();

    expect($warning->fresh()->letter_status)
        ->toBe(KompenResponHubWarningLetter::LetterStatusCancelled)
        ->and(app(KompenResponHubDataQuery::class)->warnings([])->count())
        ->toBe(0)
        ->and(app(KompenResponHubDataQuery::class)->rolledBackWarnings([])->sole()->id)
        ->toBe($warning->id)
        ->and(KompenResponHubActivityLog::query()
            ->where('event_type', 'warning.rolled_back')
            ->where('subject_name', $student->nama_mahasiswa)
            ->exists())
        ->toBeTrue();

    $this->getJson('/api/kompen-respon/students')
        ->assertOk()
        ->assertJsonPath('data.0.progress_status', 'none')
        ->assertJsonPath('data.0.has_active_warning', false)
        ->assertJsonPath('data.0.warning', null);

    $this->actingAs($admin, 'admin')
        ->delete("/admin/kompen-respon/warnings/{$warning->id}", [
            'reason' => 'Tidak boleh melakukan rollback dua kali.',
        ])
        ->assertUnprocessable();

    expect(KompenResponHubWarningLetter::query()->count())->toBe(1)
        ->and($warning->fresh()->letter_status)
        ->toBe(KompenResponHubWarningLetter::LetterStatusCancelled)
        ->and($warning->fresh()->cancelled_at)->not->toBeNull();
});

test('the warning list exposes only active fixed SP records with outstanding debt', function () {
    $student = createLifecycleStudent();
    $cutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
    ]);
    $warning = KompenResponHubWarningLetter::create([
        'cutoff_id' => $cutoff->id,
        'current_student_id' => $student->id,
        'nim' => $student->nim,
        'periode_semester' => $student->periode_semester,
        'kelas' => $student->kelas,
        'nama_mahasiswa' => $student->nama_mahasiswa,
        'classification' => 'temporary',
        'letter_status' => KompenResponHubWarningLetter::LetterStatusDraft,
        'resolution' => 'outstanding',
        'snapshot' => ['sisa_hutang_jam' => 3.5],
    ]);

    $admin = KompenResponHubAdmin::factory()->create();
    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/cutoffs/{$cutoff->id}/finalize")
        ->assertRedirect();

    expect($warning->fresh())->classification->toBe('fixed')
        ->and(app(KompenResponHubDataQuery::class)->warnings([])->count())->toBe(1)
        ->and(KompenResponHubActivityLog::query()->where('event_type', 'warning.classification_fixed')->exists())->toBeTrue();
});

test('an outstanding fixed issued SP is exposed as an active SP student status', function () {
    $student = createLifecycleStudent();
    $cutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
    ]);

    KompenResponHubWarningLetter::create([
        'cutoff_id' => $cutoff->id,
        'current_student_id' => $student->id,
        'nim' => $student->nim,
        'periode_semester' => $student->periode_semester,
        'kelas' => $student->kelas,
        'nama_mahasiswa' => $student->nama_mahasiswa,
        'classification' => 'fixed',
        'letter_status' => KompenResponHubWarningLetter::LetterStatusIssued,
        'resolution' => 'outstanding',
        'snapshot' => ['sisa_hutang_jam' => 3.5],
    ]);

    $this->getJson('/api/kompen-respon/students')
        ->assertOk()
        ->assertJsonPath('data.0.progress_status', 'warning_active')
        ->assertJsonPath('data.0.has_active_warning', true)
        ->assertJsonPath('data.0.warning.letter_status', 'issued')
        ->assertJsonMissing(['classification' => 'fixed']);
});

test('an admin can finalize an elapsed period and still correct its progress', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    $cutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/cutoffs/{$cutoff->id}/finalize")
        ->assertRedirect();

    expect(KompenResponHubActivityLog::query()->where('event_type', 'period.finalized')->exists())->toBeTrue();

    $this->actingAs($admin, 'admin')
        ->put("/admin/kompen-respon/students/{$student->id}/progress", [
            'kompensasi_dikerjakan_jam' => 1,
            'responsi_dikerjakan_jam' => 0,
            'last_worked_at' => now('Asia/Jakarta')->format('Y-m-d\\TH:i'),
            'reason' => 'Koreksi diperbolehkan setelah periode difiksasi.',
        ])
        ->assertRedirect();

    expect(KompenResponHubStudentProgress::query()->sole()->kompensasi_dikerjakan_jam)->toBe('1.0000');
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
