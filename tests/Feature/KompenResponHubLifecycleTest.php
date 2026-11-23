<?php

use App\Actions\KompenResponHub\ImportKompenResponHubWorkbook;
use App\Actions\KompenResponHub\KompenResponHubDataQuery;
use App\Http\Resources\KompenResponHubActivityLogResource;
use App\Models\KompenResponHubActiveImport;
use App\Models\KompenResponHubActivityLog;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubDetail;
use App\Models\KompenResponHubDetailOverride;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubStudent;
use App\Models\KompenResponHubStudentProgress;
use App\Models\KompenResponHubStudentSummaryOverride;
use App\Models\KompenResponHubWarningLetter;
use App\Models\KompenResponHubWarningLetterArtifact;
use App\Models\KompenResponHubWarningPeriodSelection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

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
            'responsi_dikerjakan_jam' => 8,
            'last_worked_at' => null,
            'reason' => 'Tidak boleh melampaui total hutang.',
        ])
        ->assertSessionHasErrors(['kompensasi_dikerjakan_jam', 'responsi_dikerjakan_jam', 'last_worked_at']);

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
        ->assertJsonPath('data.0.progress_status', 'awaiting_finalization')
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

test('the warning page uses the persisted selected SP period and only allows list inspection to vary', function () {
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

    $gasalCutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $gasalStudent->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
    ]);
    $genapCutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $genapStudent->periode_semester,
        'deadline_at' => now()->addWeek(),
        'timezone' => 'Asia/Jakarta',
    ]);
    KompenResponHubWarningPeriodSelection::query()->create([
        'selection_key' => KompenResponHubWarningPeriodSelection::ManagedWarningPeriodSelectionKey,
        'sikompen_period_cutoff_id' => $genapCutoff->id,
        'selected_at' => now(),
    ]);

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=warnings')
        ->assertInertia(fn ($page) => $page
            ->where('filters.periode_semester', '2026/2027 Genap')
            ->where('filters.warning_period', '2026/2027 Genap')
            ->has('warningCandidates.data', 0),
        );

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=warnings&warning_period=2026%2F2027%20Gasal')
        ->assertInertia(fn ($page) => $page
            ->where('filters.periode_semester', '2026/2027 Genap')
            ->where('filters.warning_period', '2026/2027 Genap')
            ->has('warningCandidates.data', 0),
        );

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=warnings&list_period=2026%2F2027%20Gasal')
        ->assertInertia(fn ($page) => $page
            ->where('filters.warning_period', '2026/2027 Genap')
            ->where('filters.list_period', '2026/2027 Gasal')
            ->where('filters.periode_semester', '2026/2027 Gasal')
            ->has('warningCandidates.data', 1)
            ->where('warningCandidates.data.0.id', $gasalStudent->id)
            ->has('warningPeriods', 2)
            ->where('warningPeriods.0', '2026/2027 Genap')
            ->where('warningPeriods.1', '2026/2027 Gasal'),
        );

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/cutoffs/{$gasalCutoff->id}/select")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(KompenResponHubWarningPeriodSelection::query()
        ->sole()
        ->sikompen_period_cutoff_id)->toBe($gasalCutoff->id);
});

test('student and detail tables use the managed warning period when opened from Surat Peringatan', function () {
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

    KompenResponHubDetail::create([
        'kompen_respon_hub_student_id' => $gasalStudent->id,
        'tanggal' => '2026-09-01',
        'mata_kuliah' => 'Algoritma',
        'nama_dosen' => 'Ibu Sari',
        'jenis_pertemuan' => 'Luring',
        'presensi' => 'Terlambat',
        'menit_keterlambatan' => 15,
        'jam_kompensasi' => 1,
        'jam_responsi' => 0,
    ]);
    KompenResponHubDetail::create([
        'kompen_respon_hub_student_id' => $genapStudent->id,
        'tanggal' => '2026-09-02',
        'mata_kuliah' => 'Basis Data',
        'nama_dosen' => 'Pak Budi',
        'jenis_pertemuan' => 'Luring',
        'presensi' => 'Terlambat',
        'menit_keterlambatan' => 15,
        'jam_kompensasi' => 1,
        'jam_responsi' => 0,
    ]);

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=students&warning_period=2026%2F2027%20Gasal')
        ->assertInertia(fn ($page) => $page
            ->where('filters.warning_period', '2026/2027 Gasal')
            ->where('filters.periode_semester', '2026/2027 Gasal')
            ->has('students.data', 1)
            ->where('students.data.0.id', $gasalStudent->id),
        );

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=details&warning_period=2026%2F2027%20Gasal')
        ->assertInertia(fn ($page) => $page
            ->where('filters.warning_period', '2026/2027 Gasal')
            ->where('filters.periode_semester', '2026/2027 Gasal')
            ->has('details.data', 1)
            ->where('details.data.0.student_id', $gasalStudent->id),
        );
});

test('an admin can select an active workbook period and finalize its SP immediately', function () {
    Storage::fake('local');

    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();

    $this->actingAs($admin, 'admin')
        ->post('/admin/kompen-respon/warning-periods/select', [
            'periode_semester' => $student->periode_semester,
        ])
        ->assertRedirect('/admin?tab=warnings')
        ->assertSessionHas('success');

    $cutoff = KompenResponHubPeriodCutoff::query()->sole();

    expect($cutoff->periode_semester)->toBe($student->periode_semester)
        ->and(KompenResponHubWarningPeriodSelection::query()->sole()->sikompen_period_cutoff_id)->toBe($cutoff->id);

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/cutoffs/{$cutoff->id}/finalize")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($cutoff->fresh()->closed_at)->not->toBeNull()
        ->and(KompenResponHubWarningLetterArtifact::query()->where('cutoff_id', $cutoff->id)->exists())->toBeTrue();
});

test('selecting an SP semester creates a lifecycle anchor without enabling the cutoff', function (): void {
    $this->travelTo(CarbonImmutable::create(2026, 10, 5, 10, 0, 0, 'Asia/Jakarta'));

    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();

    $this->actingAs($admin, 'admin')
        ->post('/admin/kompen-respon/warning-periods/select', [
            'periode_semester' => $student->periode_semester,
        ])
        ->assertRedirect('/admin?tab=warnings');

    expect(KompenResponHubPeriodCutoff::query()->sole()->deadline_at)
        ->toEqual(CarbonImmutable::create(2026, 10, 5, 10, 5, 0, 'Asia/Jakarta'))
        ->and(KompenResponHubPeriodCutoff::query()->sole()->cutoff_enabled)->toBeFalse();

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=warnings')
        ->assertInertia(fn ($page) => $page
            ->where('warningPeriodOptions.0.status', 'managing')
            ->where('cutoffs.0.cutoff_enabled', false)
            ->where('cutoffs.0.has_passed', false),
        );
});

test('an admin explicitly enables a cutoff for a managed SP semester', function (): void {
    $this->travelTo(CarbonImmutable::create(2026, 10, 5, 10, 0, 0, 'Asia/Jakarta'));

    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();

    $this->actingAs($admin, 'admin')
        ->post('/admin/kompen-respon/warning-periods/select', [
            'periode_semester' => $student->periode_semester,
        ])
        ->assertRedirect('/admin?tab=warnings');

    $this->actingAs($admin, 'admin')
        ->put('/admin/kompen-respon/cutoffs', [
            'periode_semester' => $student->periode_semester,
            'deadline_at' => '2026-10-05T10:05',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Batas waktu periode berhasil disimpan.');

    expect(KompenResponHubPeriodCutoff::query()->sole()->cutoff_enabled)->toBeTrue();

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=warnings')
        ->assertInertia(fn ($page) => $page
            ->where('warningPeriodOptions.0.status', 'cutoff_active')
            ->where('cutoffs.0.cutoff_enabled', true)
            ->where('cutoffs.0.has_passed', false),
        );
});

test('the SP page shows a finalization recipient estimate before the cutoff passes', function (): void {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    $cutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->addWeek(),
        'timezone' => 'Asia/Jakarta',
    ]);
    KompenResponHubWarningPeriodSelection::query()->create([
        'selection_key' => KompenResponHubWarningPeriodSelection::ManagedWarningPeriodSelectionKey,
        'sikompen_period_cutoff_id' => $cutoff->id,
        'selected_at' => now(),
    ]);

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=warnings')
        ->assertInertia(fn ($page) => $page
            ->where('warningCandidates.meta.total', 0)
            ->where('cutoffs.0.has_passed', false)
            ->where('cutoffs.0.projected_recipient_count', 1),
        );
});

test('the SP page exposes when a cutoff has passed using server time', function (): void {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    $cutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
    ]);
    KompenResponHubWarningPeriodSelection::query()->create([
        'selection_key' => KompenResponHubWarningPeriodSelection::ManagedWarningPeriodSelectionKey,
        'sikompen_period_cutoff_id' => $cutoff->id,
        'selected_at' => now(),
    ]);

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=warnings')
        ->assertInertia(fn ($page) => $page
            ->where('cutoffs.0.has_passed', true),
        );
});

test('filtered preview includes detached recipients, respects manual cancellation and matches the final spreadsheet', function (): void {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/warning-periods/select', [
        'periode_semester' => $student->periode_semester,
    ])->assertRedirect();
    $cutoff = KompenResponHubPeriodCutoff::query()->sole();
    $attributes = [
        'cutoff_id' => $cutoff->id, 'periode_semester' => $student->periode_semester,
        'current_student_id' => null, 'kelas' => '2AEA1', 'nama_mahasiswa' => 'Arsip Lama',
        'letter_status' => 'draft', 'classification' => 'temporary', 'resolution' => 'outstanding',
        'snapshot' => ['sisa_hutang_jam' => 2],
    ];
    $orphan = KompenResponHubWarningLetter::create([...$attributes, 'nim' => '999000001']);
    $cancelled = $student->replicate();
    $cancelled->nim = '999000002';
    $cancelled->save();
    KompenResponHubWarningLetter::create([
        ...$attributes, 'nim' => $cancelled->nim, 'current_student_id' => $cancelled->id,
        'letter_status' => 'cancelled', 'cancellation_source' => KompenResponHubWarningLetter::CancellationSourceManualRollback,
    ]);

    $this->get('/admin?tab=warnings&search=arsip&kelas=2AEA1&tingkat=2')
        ->assertInertia(fn ($page) => $page->where('cutoffs.0.projected_recipient_count', 2)
            ->where('warningPreview.meta.page_name', 'warning_preview_page')
            ->where('warningPreview.meta.total', 1)->where('warningPreview.data.0.nim', $orphan->nim)
            ->where('warningCandidates.meta.total', 0));
    expect($orphan->fresh()->letter_status)->toBe('draft');
    $this->post("/admin/kompen-respon/cutoffs/{$cutoff->id}/finalize")->assertSessionHas('success');
    $artifact = KompenResponHubWarningLetterArtifact::query()->sole();
    $sheet = IOFactory::load(Storage::disk('local')->path($artifact->stored_path))->getActiveSheet();
    expect($artifact->row_count)->toBe(2)
        ->and($sheet->getCell('A5')->getValue())->toBe($student->nim)
        ->and($sheet->getCell('A6')->getValue())->toBe($orphan->nim);

    KompenResponHubActiveImport::query()->where('periode_semester', $student->periode_semester)->delete();
    $this->get('/admin?tab=warnings')->assertInertia(fn ($page) => $page
        ->where('warningPeriodOptions.0.status', 'finalized')
        ->where('warningPeriodOptions.0.is_active_workbook', false)
        ->where('cutoffs.0.projected_recipient_count', 2)->where('warningPreview', null));
    $artifact->update(['status' => KompenResponHubWarningLetterArtifact::StatusRevising]);
    $cutoff->refresh()->update(['closed_at' => null]);
    $this->get('/admin?tab=warnings')->assertInertia(fn ($page) => $page
        ->where('warningPeriodOptions.0.status', 'revising')
        ->where('warningPeriodOptions.0.is_active_workbook', false)->where('warningPreview', null));
});

test('SP preview paginates the filtered recipient list without changing the semester total', function (): void {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    foreach (range(1, 6) as $index) {
        $classmate = $student->replicate();
        $classmate->fill(['nim' => (string) (200000000 + $index), 'kelas' => '2AEA1', 'tingkat' => 2])->save();
    }
    $this->actingAs($admin, 'admin')->post('/admin/kompen-respon/warning-periods/select', [
        'periode_semester' => $student->periode_semester,
    ])->assertRedirect();
    $this->get('/admin?tab=warnings&kelas=2AEA1&per_page=5&warning_preview_page=2')
        ->assertInertia(fn ($page) => $page->where('cutoffs.0.projected_recipient_count', 7)
            ->where('warningPreview.meta.total', 6)->where('warningPreview.meta.current_page', 2)
            ->has('warningPreview.data', 1)->where('warningPreview.data.0.nim', '200000006'));
    expect(KompenResponHubWarningLetter::query()->count())->toBe(0);
});

test('an admin cannot select an inactive workbook period for SP', function () {
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->from('/admin?tab=warnings')
        ->post('/admin/kompen-respon/warning-periods/select', [
            'periode_semester' => '2025/2026 Genap',
        ])
        ->assertRedirect('/admin?tab=warnings')
        ->assertSessionHas('error', 'Periode SP hanya dapat dipilih ketika workbook Kompen dan Respon periode tersebut masih digunakan.');

    expect(KompenResponHubPeriodCutoff::query()->doesntExist())->toBeTrue();
});

test('the warning page offers active workbook periods even before SP is initialized', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();

    $this->actingAs($admin, 'admin')
        ->get('/admin?tab=warnings')
        ->assertInertia(fn ($page) => $page
            ->where('selectableWarningPeriods', [$student->periode_semester])
            ->where('warningPeriods', [$student->periode_semester])
            ->has('cutoffs', 0),
        );
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

test('an admin cannot change progress while the SP period is final', function () {
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
            'reason' => 'Perubahan setelah finalisasi harus ditolak.',
        ])
        ->assertUnprocessable();

    expect(KompenResponHubWarningLetter::query()->sole())
        ->resolution->toBe('outstanding')
        ->and(KompenResponHubStudentProgress::query()->doesntExist())->toBeTrue();

    $this->getJson('/api/kompen-respon/students')
        ->assertOk()
        ->assertJsonPath('data.0.progress_status', 'warning_active');
});

test('students with outstanding debt show the in-progress status before the cutoff has passed', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    $cutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->addWeek(),
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->actingAs($admin, 'admin')->getJson('/api/kompen-respon/students')
        ->assertOk()
        ->assertJsonPath('data.0.progress_status', 'in_progress');
});

test('students with outstanding debt show that their cutoff has not been configured', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    createLifecycleStudent();

    $this->actingAs($admin, 'admin')->getJson('/api/kompen-respon/students')
        ->assertOk()
        ->assertJsonPath('data.0.progress_status', 'cutoff_unset');
});

test('a student with no Kompen or Responsi obligation is marked as having no obligation', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    $student->update([
        'total_kompensasi_jam' => 0,
        'total_responsi_jam' => 0,
        'total_hutang_jam' => 0,
        'sisa_hutang_jam' => 0,
    ]);

    $this->actingAs($admin, 'admin')
        ->getJson('/api/kompen-respon/students')
        ->assertOk()
        ->assertJsonPath('data.0.progress_status', 'no_obligation');
});

test('a student with completed work is marked as complete before the cutoff', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    KompenResponHubStudentProgress::create([
        'current_student_id' => $student->id,
        'nim' => $student->nim,
        'periode_semester' => $student->periode_semester,
        'kelas' => $student->kelas,
        'kompensasi_dikerjakan_jam' => 1.5,
        'responsi_dikerjakan_jam' => 2,
        'reason' => 'Seluruh kewajiban telah selesai.',
    ]);

    $this->actingAs($admin, 'admin')->getJson('/api/kompen-respon/students')
        ->assertOk()
        ->assertJsonPath('data.0.progress_status', 'completed');
});

test('a finalized period with outstanding debt but no active SP is marked for review', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
        'closed_at' => now(),
    ]);

    $this->actingAs($admin, 'admin')->getJson('/api/kompen-respon/students')
        ->assertOk()
        ->assertJsonPath('data.0.progress_status', 'needs_review');
});

test('an admin can finalize a passed cutoff into an issued SP', function () {
    Storage::fake('local');
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

    $artifact = KompenResponHubWarningLetterArtifact::query()->sole();
    Storage::disk('local')->assertExists($artifact->stored_path);

    expect($artifact)
        ->status->toBe(KompenResponHubWarningLetterArtifact::StatusFinalized)
        ->revision->toBe(1)
        ->display_filename->toBe('2026-2027_Gasal_list_surat_peringatan.xlsx')
        ->row_count->toBe(1)
        ->and($artifact->source_provenance['kompen_respon_workbook']['import_id'])->toBe($student->kompen_respon_hub_import_id)
        ->and($artifact->source_provenance['kompen_respon_workbook']['display_filename'])->toBe('source.xlsx')
        ->and($cutoff->fresh()->closed_at)->not->toBeNull();

    $worksheet = IOFactory::load(Storage::disk('local')->path($artifact->stored_path))->getActiveSheet();
    expect($worksheet->getCell('A5')->getValue())
        ->toBe($student->nim)
        ->and($worksheet->getCell('B5')->getValue())->toBe($student->nama_mahasiswa);
});

test('an admin cannot finalize a period that is not the selected warning period', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    $selectedCutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
    ]);
    $otherCutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => '2026/2027 Genap',
        'deadline_at' => now()->addWeek(),
        'timezone' => 'Asia/Jakarta',
    ]);
    KompenResponHubWarningPeriodSelection::query()->create([
        'selection_key' => KompenResponHubWarningPeriodSelection::ManagedWarningPeriodSelectionKey,
        'sikompen_period_cutoff_id' => $otherCutoff->id,
        'selected_at' => now(),
    ]);

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/cutoffs/{$selectedCutoff->id}/finalize")
        ->assertUnprocessable();

    expect($selectedCutoff->fresh()->closed_at)->toBeNull()
        ->and(KompenResponHubWarningLetterArtifact::query()->doesntExist())->toBeTrue();
});

test('an admin must explicitly confirm a finalization with no recipients', function () {
    Storage::fake('local');
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    $student->update([
        'total_kompensasi_jam' => 0,
        'total_responsi_jam' => 0,
        'total_hutang_jam' => 0,
        'sisa_hutang_jam' => 0,
    ]);
    $cutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/cutoffs/{$cutoff->id}/finalize")
        ->assertUnprocessable();

    expect(KompenResponHubWarningLetterArtifact::query()->doesntExist())->toBeTrue()
        ->and($cutoff->fresh()->closed_at)->toBeNull();

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/cutoffs/{$cutoff->id}/finalize", [
            'confirm_empty_finalization' => true,
        ])
        ->assertRedirect();

    expect(KompenResponHubWarningLetterArtifact::query()->sole()->row_count)->toBe(0)
        ->and($cutoff->fresh()->closed_at)->not->toBeNull();
});

test('an activity log falls back to an archive workbook label instead of unknown', function () {
    $activity = KompenResponHubActivityLog::query()->create([
        'event_type' => 'import.completed',
        'subject_type' => 'import',
        'subject_reference' => '91',
        'actor_type' => 'system',
        'metadata' => [],
        'occurred_at' => now(),
    ]);

    expect(KompenResponHubActivityLogResource::make($activity)->resolve(request())['subject_label'])
        ->toBe('Workbook: Arsip workbook #91');
});

test('an admin can download a finalized SP artifact from List File', function () {
    Storage::fake('local');
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

    $artifact = KompenResponHubWarningLetterArtifact::query()->sole();

    $this->actingAs($admin, 'admin')
        ->get("/admin/kompen-respon/warning-artifacts/{$artifact->id}/download")
        ->assertOk()
        ->assertDownload('2026-2027_Gasal_list_surat_peringatan.xlsx');
});

test('a corrupted SP artifact is not downloaded', function () {
    Storage::fake('local');
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

    $artifact = KompenResponHubWarningLetterArtifact::query()->sole();
    Storage::disk('local')->put($artifact->stored_path, 'bukan file artifact asli');

    $this->actingAs($admin, 'admin')
        ->get("/admin/kompen-respon/warning-artifacts/{$artifact->id}/download")
        ->assertConflict();
});

test('an admin cannot roll back one SP while the period is final', function () {
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
        ->assertUnprocessable();

    expect($warning->fresh()->letter_status)
        ->toBe(KompenResponHubWarningLetter::LetterStatusIssued)
        ->and(app(KompenResponHubDataQuery::class)->warnings([])->count())->toBe(1);

    $this->getJson('/api/kompen-respon/students')
        ->assertOk()
        ->assertJsonPath('data.0.progress_status', 'warning_active')
        ->assertJsonPath('data.0.has_active_warning', true);
});

test('an admin can cancel a period finalization and preserve its audit trail', function () {
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
        ->delete("/admin/kompen-respon/cutoffs/{$cutoff->id}/finalization", [
            'reason' => '<script>alert(1)</script>',
        ])
        ->assertSessionHasErrors([
            'reason' => 'Kolom reason hanya boleh berisi teks pencarian biasa.',
        ]);

    expect($warning->fresh()->letter_status)
        ->toBe(KompenResponHubWarningLetter::LetterStatusIssued);

    $this->actingAs($admin, 'admin')
        ->delete("/admin/kompen-respon/cutoffs/{$cutoff->id}/finalization", [
            'reason' => 'Data workbook periode ini perlu diperbaiki.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Koreksi dibuka untuk 1 mahasiswa. SP semester ini ditarik dari API. XLSX sebelumnya tetap tersimpan di List File dengan status sedang dibenarkan.');

    expect($warning->fresh()->letter_status)
        ->toBe(KompenResponHubWarningLetter::LetterStatusCancelled)
        ->and($warning->fresh()->cancellation_source)
        ->toBe(KompenResponHubWarningLetter::CancellationSourceFinalizationRollback)
        ->and(app(KompenResponHubDataQuery::class)
            ->warningCandidates(['periode_semester' => $student->periode_semester])
            ->pluck('id'))
        ->toContain($student->id)
        ->and(KompenResponHubActivityLog::query()
            ->where('event_type', 'period.finalization_rolled_back')
            ->where('periode_semester', $student->periode_semester)
            ->exists())
        ->toBeTrue();

    expect(KompenResponHubWarningLetterArtifact::query()->sole())
        ->status->toBe(KompenResponHubWarningLetterArtifact::StatusRevising)
        ->and($cutoff->fresh()->closed_at)->toBeNull();

    $this->getJson('/api/kompen-respon/students')
        ->assertOk()
        ->assertJsonPath('data.0.has_active_warning', false)
        ->assertJsonPath('data.0.progress_status', 'awaiting_finalization');

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/cutoffs/{$cutoff->id}/finalize")
        ->assertRedirect();

    expect($warning->fresh()->letter_status)
        ->toBe(KompenResponHubWarningLetter::LetterStatusIssued)
        ->and($warning->fresh()->cancellation_source)->toBeNull()
        ->and(KompenResponHubWarningLetterArtifact::query()->count())->toBe(2)
        ->and(KompenResponHubWarningLetterArtifact::query()->where('revision', 1)->sole()->status)->toBe(KompenResponHubWarningLetterArtifact::StatusSuperseded)
        ->and(KompenResponHubWarningLetterArtifact::query()->where('revision', 2)->sole()->status)->toBe(KompenResponHubWarningLetterArtifact::StatusFinalized);
});

test('an admin can set a new cutoff after opening a finalized period for correction', function () {
    Storage::fake('local');
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
        ->delete("/admin/kompen-respon/cutoffs/{$cutoff->id}/finalization", [
            'reason' => 'Batas waktu perlu diperbarui selama koreksi.',
        ])
        ->assertRedirect();

    $newDeadline = now('Asia/Jakarta')->addWeek()->startOfMinute();

    $this->actingAs($admin, 'admin')
        ->put('/admin/kompen-respon/cutoffs', [
            'periode_semester' => $student->periode_semester,
            'deadline_at' => $newDeadline->format('Y-m-d\\TH:i'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Batas waktu periode berhasil disimpan.');

    expect($cutoff->fresh()->closed_at)->toBeNull()
        ->and($cutoff->fresh()->deadline_at->toIso8601String())->toBe($newDeadline->toIso8601String())
        ->and(KompenResponHubWarningLetterArtifact::query()->sole()->status)
        ->toBe(KompenResponHubWarningLetterArtifact::StatusRevising);

    $this->actingAs($admin, 'admin')
        ->post("/admin/kompen-respon/cutoffs/{$cutoff->id}/finalize")
        ->assertRedirect();

    expect(KompenResponHubWarningLetterArtifact::query()->where('revision', 2)->sole())
        ->row_count->toBe(1);
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
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    $cutoff = KompenResponHubPeriodCutoff::create([
        'periode_semester' => $student->periode_semester,
        'deadline_at' => now()->subMinute(),
        'timezone' => 'Asia/Jakarta',
        'closed_at' => now(),
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

    $this->actingAs($admin, 'admin')->getJson('/api/kompen-respon/students')
        ->assertOk()
        ->assertJsonPath('data.0.progress_status', 'warning_active')
        ->assertJsonPath('data.0.has_active_warning', true)
        ->assertJsonPath('data.0.warning.letter_status', 'issued')
        ->assertJsonMissing(['classification' => 'fixed']);
});

test('an admin can correct progress after starting a period correction', function () {
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
            'reason' => 'Koreksi sebelum membuka periode harus ditolak.',
        ])
        ->assertUnprocessable();

    $this->actingAs($admin, 'admin')
        ->delete("/admin/kompen-respon/cutoffs/{$cutoff->id}/finalization", [
            'reason' => 'Data progres mahasiswa perlu diperbaiki.',
        ])
        ->assertRedirect();

    $this->actingAs($admin, 'admin')
        ->put("/admin/kompen-respon/students/{$student->id}/progress", [
            'kompensasi_dikerjakan_jam' => 1,
            'responsi_dikerjakan_jam' => 0,
            'last_worked_at' => now('Asia/Jakarta')->format('Y-m-d\\TH:i'),
            'reason' => 'Koreksi diperbolehkan setelah periode dibuka kembali.',
        ])
        ->assertRedirect();

    expect(KompenResponHubStudentProgress::query()->sole()->kompensasi_dikerjakan_jam)->toBe('1.0000');
});

test('replacing a workbook carries lifecycle data when a student changes class', function () {
    $student = createLifecycleStudent();
    $progress = KompenResponHubStudentProgress::query()->create([
        'current_student_id' => $student->id,
        'nim' => $student->nim,
        'periode_semester' => $student->periode_semester,
        'kelas' => $student->kelas,
        'kompensasi_dikerjakan_jam' => 1,
        'responsi_dikerjakan_jam' => 0,
        'reason' => 'Progres tetap dibawa saat pindah kelas.',
    ]);
    $summaryOverride = KompenResponHubStudentSummaryOverride::query()->create([
        'current_student_id' => $student->id,
        'nim' => $student->nim,
        'periode_semester' => $student->periode_semester,
        'kelas' => $student->kelas,
        'total_kompensasi_jam' => 2,
        'total_responsi_jam' => 2,
        'reason' => 'Koreksi total tetap dibawa saat pindah kelas.',
    ]);
    $warning = KompenResponHubWarningLetter::query()->create([
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
    $replacementImport = KompenResponHubImport::query()->create([
        'periode_semester' => $student->periode_semester,
        'original_filename' => 'replacement.xlsx',
        'stored_path' => 'kompen-respon-hub/imports/replacement.xlsx',
        'file_hash' => str_repeat('b', 64),
        'class_count' => 1,
        'student_count' => 1,
        'detail_count' => 0,
        'imported_at' => now(),
    ]);
    $attributes = $student->only([
        'nim',
        'nama_mahasiswa',
        'tingkat',
        'total_jam_terlambat',
        'total_jam_sakit',
        'total_jam_izin',
        'total_jam_bolos',
        'total_kompensasi_jam',
        'total_responsi_jam',
        'total_hutang_jam',
        'kompensasi_dikerjakan_jam',
        'sisa_hutang_jam',
    ]);
    $attributes['kelas'] = '1AEB1';

    KompenResponHubStudent::query()
        ->where('periode_semester', $student->periode_semester)
        ->delete();

    app(ImportKompenResponHubWorkbook::class)->replaceActiveData(
        [
            'preview' => [
                'periode_semester' => $student->periode_semester,
                'classes' => ['1AEB1'],
                'class_count' => 1,
                'student_count' => 1,
                'detail_count' => 0,
            ],
            'students' => [$attributes],
            'details' => [],
        ],
        $student->periode_semester,
        ['1AEB1' => $replacementImport->id],
    );

    $replacementStudent = KompenResponHubStudent::query()->sole();

    expect($replacementStudent->kelas)->toBe('1AEB1')
        ->and($progress->fresh()->kelas)->toBe('1AEB1')
        ->and($progress->fresh()->current_student_id)->toBe($replacementStudent->id)
        ->and($summaryOverride->fresh()->kelas)->toBe('1AEB1')
        ->and($summaryOverride->fresh()->current_student_id)->toBe($replacementStudent->id)
        ->and($warning->fresh()->kelas)->toBe('1AEB1')
        ->and($warning->fresh()->current_student_id)->toBe($replacementStudent->id);
});

test('an admin can remove a detail correction before replacing a workbook', function () {
    $admin = KompenResponHubAdmin::factory()->create();
    $student = createLifecycleStudent();
    $detail = KompenResponHubDetail::query()->create([
        'kompen_respon_hub_student_id' => $student->id,
        'source_key' => 'detail-source-key',
        'tanggal' => now()->toDateString(),
        'mata_kuliah' => 'Pemrograman Web',
        'nama_dosen' => 'Dosen Pengampu',
        'jenis_pertemuan' => 'Teori',
        'presensi' => 'Terlambat',
        'menit_keterlambatan' => 20,
        'jam_kompensasi' => 0.5,
        'jam_responsi' => 0,
    ]);
    KompenResponHubDetailOverride::query()->create([
        'source_key' => $detail->source_key,
        'override_values' => ['menit_keterlambatan' => 10],
        'reason' => 'Koreksi hasil verifikasi.',
        'updated_by_admin_id' => $admin->id,
    ]);

    $this->actingAs($admin, 'admin')
        ->delete("/admin/kompen-respon/details/{$detail->id}/override")
        ->assertRedirect()
        ->assertSessionHas('success', 'Koreksi Detail Kompen dihapus. Nilai kembali mengikuti workbook aktif.');

    expect(KompenResponHubDetailOverride::query()->doesntExist())->toBeTrue()
        ->and(KompenResponHubActivityLog::query()->where('event_type', 'detail.override_removed')->exists())->toBeTrue();
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
    KompenResponHubActiveImport::query()->create([
        'periode_semester' => $import->periode_semester,
        'kompen_respon_hub_import_id' => $import->id,
        'activated_at' => now(),
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
