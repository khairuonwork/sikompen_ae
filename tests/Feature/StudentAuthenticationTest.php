<?php

use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubPeriodCutoff;
use App\Models\KompenResponHubStudent;
use App\Models\KompenResponHubStudentAccount;
use Illuminate\Support\Facades\Hash;

test('student pages require authentication', function (): void {
    $this->get('/mahasiswa')->assertRedirect('/mahasiswa/login');
    $this->get('/kompen-respon')->assertRedirect('/mahasiswa/login');
    $this->getJson('/api/kompen-respon/students')->assertUnauthorized();
});

test('an active student signs in with the initial NIM password and must change it', function (): void {
    KompenResponHubActiveStudent::factory()->create(['nim' => '261000001']);

    $this->post('/mahasiswa/login', [
        'nim' => '261000001',
        'password' => '261000001',
    ])->assertRedirect('/mahasiswa/ganti-password');

    $account = KompenResponHubStudentAccount::query()->sole();

    expect($account->nim)->toBe('261000001')
        ->and(Hash::check('261000001', $account->password))->toBeTrue()
        ->and($account->requiresPasswordChange())->toBeTrue();

    $this->assertAuthenticatedAs($account, 'student');
    $this->get('/mahasiswa')->assertRedirect('/mahasiswa/ganti-password');
    $this->getJson('/api/kompen-respon/students')->assertForbidden();
});

test('a student can only view their own records after changing the initial password', function (): void {
    $ownNim = '261000001';
    $otherNim = '261000002';
    KompenResponHubActiveStudent::factory()->create(['nim' => $ownNim]);
    KompenResponHubActiveStudent::factory()->create(['nim' => $otherNim]);
    $import = KompenResponHubImport::create([
        'periode_semester' => '2026/2027 Ganjil',
        'original_filename' => 'source.xlsx',
        'stored_path' => 'kompen-respon-hub/imports/source.xlsx',
        'file_hash' => str_repeat('a', 64),
        'class_count' => 1,
        'student_count' => 2,
        'detail_count' => 0,
        'imported_at' => now(),
    ]);
    $ownStudent = studentForAuthenticationTest($import->id, $ownNim, 'Rina Utami');
    $otherStudent = studentForAuthenticationTest($import->id, $otherNim, 'Dani Pratama');

    $this->post('/mahasiswa/login', ['nim' => $ownNim, 'password' => $ownNim])
        ->assertRedirect('/mahasiswa/ganti-password');

    $this->put('/mahasiswa/ganti-password', [
        'password' => 'Password-Mahasiswa#2026',
        'password_confirmation' => 'Password-Mahasiswa#2026',
    ])->assertRedirect('/mahasiswa');

    $this->get('/mahasiswa')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('isAdmin', false)
            ->where('hasStudentSession', true)
            ->has('students.data', 1)
            ->where('students.data.0.nim', $ownNim),
        );

    $this->getJson('/api/kompen-respon/students')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nim', $ownNim);

    $this->getJson("/api/kompen-respon/students/{$otherStudent->id}")
        ->assertNotFound();

    $this->getJson("/api/kompen-respon/students/{$ownStudent->id}")
        ->assertOk()
        ->assertJsonPath('data.summary.nim', $ownNim);
});

test('a student cannot keep using the initial NIM as their password', function (): void {
    $account = KompenResponHubStudentAccount::factory()->create([
        'nim' => '261000001',
        'password' => '261000001',
        'password_changed_at' => null,
    ]);
    KompenResponHubActiveStudent::factory()->create(['nim' => $account->nim]);

    $this->actingAs($account, 'student')
        ->put('/mahasiswa/ganti-password', [
            'password' => $account->nim,
            'password_confirmation' => $account->nim,
        ])
        ->assertSessionHasErrors('password');
});

test('a signed-in student profile only shows their own Kompen records for the active warning period', function (): void {
    $ownNim = '261000001';
    $otherNim = '261000002';
    $account = KompenResponHubStudentAccount::factory()->create([
        'nim' => $ownNim,
        'password_changed_at' => now(),
    ]);
    KompenResponHubActiveStudent::factory()->create([
        'nim' => $ownNim,
        'nama_mahasiswa' => 'Rina Utami',
        'kelas' => '1AEA1',
    ]);
    KompenResponHubActiveStudent::factory()->create([
        'nim' => $otherNim,
        'nama_mahasiswa' => 'Dani Pratama',
        'kelas' => '1AEA1',
    ]);
    $import = KompenResponHubImport::create([
        'periode_semester' => '2026/2027 Ganjil',
        'original_filename' => 'source.xlsx',
        'stored_path' => 'kompen-respon-hub/imports/source.xlsx',
        'file_hash' => str_repeat('b', 64),
        'class_count' => 1,
        'student_count' => 2,
        'detail_count' => 0,
        'imported_at' => now(),
    ]);
    studentForAuthenticationTest($import->id, $ownNim, 'Rina Utami');
    studentForAuthenticationTest($import->id, $otherNim, 'Dani Pratama');
    KompenResponHubPeriodCutoff::create([
        'periode_semester' => '2026/2027 Ganjil',
        'deadline_at' => now(),
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->actingAs($account, 'student')
        ->get('/mahasiswa/profil')
        ->assertInertia(fn ($page) => $page
            ->where('profile.nim', $ownNim)
            ->where('profile.nama_mahasiswa', 'Rina Utami')
            ->where('profile.warning_period', '2026/2027 Ganjil')
            ->has('profile.records', 1)
            ->where('profile.records.0.nim', $ownNim)
            ->where('profile.missing_data', []),
        );
});

test('a student profile identifies a missing Kompen and Respon record', function (): void {
    $account = KompenResponHubStudentAccount::factory()->create([
        'nim' => '261000001',
        'password_changed_at' => now(),
    ]);
    KompenResponHubActiveStudent::factory()->create([
        'nim' => $account->nim,
        'nama_mahasiswa' => 'Rina Utami',
        'kelas' => '1AEA1',
    ]);
    KompenResponHubPeriodCutoff::create([
        'periode_semester' => '2026/2027 Ganjil',
        'deadline_at' => now(),
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->actingAs($account, 'student')
        ->get('/mahasiswa/profil')
        ->assertInertia(fn ($page) => $page
            ->where('profile.missing_data', [
                'Data Kompen dan Respon belum tersedia untuk 2026/2027 Ganjil.',
            ]),
        );
});

test('a student profile does not include records outside the active warning period', function (): void {
    $nim = '261000001';
    $account = KompenResponHubStudentAccount::factory()->create([
        'nim' => $nim,
        'password_changed_at' => now(),
    ]);
    KompenResponHubActiveStudent::factory()->create(['nim' => $nim]);
    $gasalImport = KompenResponHubImport::create([
        'periode_semester' => '2026/2027 Ganjil',
        'original_filename' => 'gasal.xlsx',
        'stored_path' => 'kompen-respon-hub/imports/gasal.xlsx',
        'file_hash' => str_repeat('c', 64),
        'class_count' => 1,
        'student_count' => 1,
        'detail_count' => 0,
        'imported_at' => now(),
    ]);
    $genapImport = KompenResponHubImport::create([
        'periode_semester' => '2026/2027 Genap',
        'original_filename' => 'genap.xlsx',
        'stored_path' => 'kompen-respon-hub/imports/genap.xlsx',
        'file_hash' => str_repeat('d', 64),
        'class_count' => 1,
        'student_count' => 1,
        'detail_count' => 0,
        'imported_at' => now(),
    ]);
    studentForAuthenticationTest($gasalImport->id, $nim, 'Rina Utami');
    KompenResponHubStudent::create([
        'kompen_respon_hub_import_id' => $genapImport->id,
        'nim' => $nim,
        'periode_semester' => '2026/2027 Genap',
        'nama_mahasiswa' => 'Rina Utami',
        'kelas' => '1AEA1',
        'tingkat' => 1,
        'total_jam_terlambat' => 0,
        'total_jam_sakit' => 0,
        'total_jam_izin' => 0,
        'total_jam_bolos' => 0,
        'total_kompensasi_jam' => 1,
        'total_responsi_jam' => 0,
        'total_hutang_jam' => 1,
        'kompensasi_dikerjakan_jam' => 0,
        'sisa_hutang_jam' => 1,
    ]);
    KompenResponHubPeriodCutoff::create([
        'periode_semester' => '2026/2027 Ganjil',
        'deadline_at' => now()->subDay(),
        'timezone' => 'Asia/Jakarta',
    ]);
    KompenResponHubPeriodCutoff::create([
        'periode_semester' => '2026/2027 Genap',
        'deadline_at' => now(),
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->actingAs($account, 'student')
        ->get('/mahasiswa/profil')
        ->assertInertia(fn ($page) => $page
            ->where('profile.warning_period', '2026/2027 Genap')
            ->has('profile.records', 1)
            ->where('profile.records.0.periode_semester', '2026/2027 Genap'),
        );
});

test('a student must verify their current password before changing it after first login', function (): void {
    $account = KompenResponHubStudentAccount::factory()->create([
        'nim' => '261000001',
        'password' => 'Password-Lama#2026',
        'password_changed_at' => now(),
    ]);
    KompenResponHubActiveStudent::factory()->create(['nim' => $account->nim]);
    $newPassword = 'Password-Baru#2026';

    $this->actingAs($account, 'student')
        ->put('/mahasiswa/ganti-password', [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($account, 'student')
        ->put('/mahasiswa/ganti-password', [
            'current_password' => 'Password-Tidak-Tepat#2026',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($account, 'student')
        ->put('/mahasiswa/ganti-password', [
            'current_password' => 'Password-Lama#2026',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])
        ->assertRedirect('/mahasiswa');

    expect(Hash::check($newPassword, $account->fresh()->password))->toBeTrue();
});

test('a signed-in student can log out and must authenticate again', function (): void {
    $account = KompenResponHubStudentAccount::factory()->create([
        'nim' => '261000001',
        'password_changed_at' => now(),
    ]);
    KompenResponHubActiveStudent::factory()->create(['nim' => $account->nim]);

    $this->actingAs($account, 'student')
        ->post('/mahasiswa/logout')
        ->assertRedirect('/');

    $this->assertGuest('student');
});

test('a student loses access when their NIM is no longer in the active roster', function (): void {
    $account = KompenResponHubStudentAccount::factory()->create(['nim' => '261000001']);

    $this->actingAs($account, 'student')
        ->get('/mahasiswa')
        ->assertRedirect('/mahasiswa/login');

    $this->actingAs($account, 'student')
        ->getJson('/api/kompen-respon/students')
        ->assertUnauthorized();
});

test('student login is rate limited after repeated failed attempts', function (): void {
    KompenResponHubActiveStudent::factory()->create(['nim' => '261000001']);

    foreach (range(1, 5) as $attempt) {
        $this->post('/mahasiswa/login', [
            'nim' => '261000001',
            'password' => 'password-salah',
        ])->assertSessionHasErrors('nim');
    }

    $this->post('/mahasiswa/login', [
        'nim' => '261000001',
        'password' => '261000001',
    ])->assertSessionHasErrors('nim');
});

test('an incorrect initial password does not provision a student account', function (): void {
    $nim = '261000001';
    KompenResponHubActiveStudent::factory()->create(['nim' => $nim]);

    $this->post('/mahasiswa/login', [
        'nim' => $nim,
        'password' => 'password-salah',
    ])->assertSessionHasErrors('nim');

    $this->assertDatabaseMissing('sikompen_student_accounts', ['nim' => $nim]);
});

function studentForAuthenticationTest(int $importId, string $nim, string $name): KompenResponHubStudent
{
    return KompenResponHubStudent::create([
        'kompen_respon_hub_import_id' => $importId,
        'nim' => $nim,
        'periode_semester' => '2026/2027 Ganjil',
        'nama_mahasiswa' => $name,
        'kelas' => '1AEA1',
        'tingkat' => 1,
        'total_jam_terlambat' => 0,
        'total_jam_sakit' => 0,
        'total_jam_izin' => 0,
        'total_jam_bolos' => 0,
        'total_kompensasi_jam' => 1.5,
        'total_responsi_jam' => 0,
        'total_hutang_jam' => 1.5,
        'kompensasi_dikerjakan_jam' => 0,
        'sisa_hutang_jam' => 1.5,
    ]);
}
