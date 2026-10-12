<?php

use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubImport;
use App\Models\KompenResponHubStudent;
use App\Models\KompenResponHubStudentAccount;
use Illuminate\Support\Facades\Hash;

test('the public student page is available without authentication while the API remains protected', function (): void {
    $this->get('/mahasiswa')->assertOk();
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
    $this->get('/mahasiswa')->assertOk();
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
            ->has('students.data', 2),
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

test('a student loses access when their NIM is no longer in the active roster', function (): void {
    $account = KompenResponHubStudentAccount::factory()->create(['nim' => '261000001']);

    $this->actingAs($account, 'student')
        ->get('/mahasiswa')
        ->assertOk();

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
