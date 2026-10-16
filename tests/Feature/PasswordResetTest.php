<?php

use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubActiveStudentImport;
use App\Models\KompenResponHubActiveStudentImportReference;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubAdminPasswordResetCode;
use App\Models\KompenResponHubStudentAccount;
use App\Models\KompenResponHubStudentPasswordResetRequest;
use Database\Seeders\SikompenEmergencyAdminSeeder;
use Illuminate\Support\Facades\Hash;

test('an administrator can request a reset code without disclosing account availability', function (): void {
    $admin = KompenResponHubAdmin::factory()->create(['username' => 'admin-reset']);

    $this->post('/admin/lupa-password', ['username' => $admin->username])
        ->assertRedirect('/admin/reset-password');

    $code = KompenResponHubAdminPasswordResetCode::query()->sole();
    expect($code->admin_id)->toBe($admin->id)->and($code->code_hash)->toBeNull();
});

test('an unknown admin reset request does not disclose account availability', function (): void {
    $this->post('/admin/lupa-password', ['username' => 'admin-tidak-dikenal'])
        ->assertRedirect('/admin/reset-password');

    expect(KompenResponHubAdminPasswordResetCode::query()->count())->toBe(0);
});

test('an emergency admin cannot request a password reset code', function (): void {
    $emergencyAdmin = KompenResponHubAdmin::factory()->create([
        'is_emergency_admin' => true,
        'username' => 'admin-darurat',
    ]);

    $this->post('/admin/lupa-password', ['username' => $emergencyAdmin->username])
        ->assertRedirect('/admin/reset-password');

    expect(KompenResponHubAdminPasswordResetCode::query()->count())->toBe(0);
});

test('an authenticated administrator can request a reset only for their own account', function (): void {
    $admin = KompenResponHubAdmin::factory()->create(['username' => 'admin-saya']);
    $otherAdmin = KompenResponHubAdmin::factory()->create(['username' => 'admin-lain']);

    $this->actingAs($admin, 'admin')
        ->post('/admin/lupa-password', ['username' => $otherAdmin->username])
        ->assertRedirect('/admin/settings')
        ->assertSessionHas('success', 'Permintaan reset dicatat. Hubungi pemegang akun darurat untuk memperoleh kode reset.');

    $reset = KompenResponHubAdminPasswordResetCode::query()->sole();
    expect($reset->admin_id)->toBe($admin->id);
});

test('an emergency admin can issue a one-time code for an administrator reset', function (): void {
    $emergencyAdmin = KompenResponHubAdmin::factory()->create(['is_emergency_admin' => true]);
    $admin = KompenResponHubAdmin::factory()->create(['username' => 'admin-reset']);
    $reset = KompenResponHubAdminPasswordResetCode::query()->create(['admin_id' => $admin->id, 'requested_at' => now()]);

    $this->actingAs($emergencyAdmin, 'admin')
        ->post("/admin/recovery/password-resets/{$reset->id}/code")
        ->assertRedirect('/admin/recovery')
        ->assertSessionHas('admin_reset_code');

    expect($reset->fresh()->code_hash)->not->toBeNull();
});

test('an emergency admin can cancel a pending administrator reset request', function (): void {
    $emergencyAdmin = KompenResponHubAdmin::factory()->create(['is_emergency_admin' => true]);
    $admin = KompenResponHubAdmin::factory()->create(['username' => 'admin-reset']);
    $reset = KompenResponHubAdminPasswordResetCode::query()->create([
        'admin_id' => $admin->id,
        'requested_at' => now(),
    ]);

    $this->actingAs($emergencyAdmin, 'admin')
        ->delete("/admin/recovery/password-resets/{$reset->id}")
        ->assertRedirect('/admin/recovery');

    $this->assertModelMissing($reset);
});

test('a new admin reset request preserves the previously used reset code for audit', function (): void {
    $admin = KompenResponHubAdmin::factory()->create(['username' => 'admin-reset']);
    $usedReset = KompenResponHubAdminPasswordResetCode::query()->create([
        'admin_id' => $admin->id,
        'code_hash' => Hash::make('123456'),
        'requested_at' => now()->subDay(),
        'expires_at' => now()->subDay(),
        'used_at' => now()->subDay(),
    ]);

    $this->post('/admin/lupa-password', ['username' => $admin->username])
        ->assertRedirect('/admin/reset-password');

    expect($usedReset->fresh()->used_at)->not->toBeNull()
        ->and(KompenResponHubAdminPasswordResetCode::query()->count())->toBe(2)
        ->and(KompenResponHubAdminPasswordResetCode::query()->whereNull('used_at')->sole()->code_hash)->toBeNull();
});

test('an emergency admin cannot reissue a code that was already used', function (): void {
    $emergencyAdmin = KompenResponHubAdmin::factory()->create(['is_emergency_admin' => true]);
    $admin = KompenResponHubAdmin::factory()->create(['username' => 'admin-reset']);
    $usedReset = KompenResponHubAdminPasswordResetCode::query()->create([
        'admin_id' => $admin->id,
        'requested_at' => now()->subDay(),
        'expires_at' => now()->subDay(),
        'used_at' => now()->subDay(),
    ]);

    $this->actingAs($emergencyAdmin, 'admin')
        ->post("/admin/recovery/password-resets/{$usedReset->id}/code")
        ->assertNotFound();

    expect($usedReset->fresh()->code_hash)->toBeNull();
});

test('a non-emergency admin cannot issue or cancel administrator reset codes', function (): void {
    $admin = KompenResponHubAdmin::factory()->create();
    $targetAdmin = KompenResponHubAdmin::factory()->create(['username' => 'admin-reset']);
    $reset = KompenResponHubAdminPasswordResetCode::query()->create([
        'admin_id' => $targetAdmin->id,
        'requested_at' => now(),
    ]);

    $this->actingAs($admin, 'admin')
        ->post("/admin/recovery/password-resets/{$reset->id}/code")
        ->assertForbidden();

    $this->actingAs($admin, 'admin')
        ->delete("/admin/recovery/password-resets/{$reset->id}")
        ->assertForbidden();

    $this->assertModelExists($reset);
});

test('an administrator can reset their password with a valid one-time code', function (): void {
    $admin = KompenResponHubAdmin::factory()->create(['username' => 'admin-reset']);
    KompenResponHubAdminPasswordResetCode::query()->create(['admin_id' => $admin->id, 'code_hash' => Hash::make('123456'), 'requested_at' => now(), 'expires_at' => now()->addMinutes(15)]);

    $this->put('/admin/reset-password', [
        'username' => $admin->username,
        'otp' => '123456',
        'password' => 'Password-Baru#2026',
        'password_confirmation' => 'Password-Baru#2026',
    ])->assertRedirect('/admin/login');

    expect(Hash::check('Password-Baru#2026', $admin->fresh()->password))->toBeTrue();
    expect(KompenResponHubAdminPasswordResetCode::query()->sole()->used_at)->not->toBeNull();
});

test('an emergency admin cannot use a password reset code', function (): void {
    $emergencyAdmin = KompenResponHubAdmin::factory()->create([
        'is_emergency_admin' => true,
        'username' => 'admin-darurat',
        'password' => 'Password-Lama#2026',
    ]);
    $resetCode = KompenResponHubAdminPasswordResetCode::query()->create([
        'admin_id' => $emergencyAdmin->id,
        'code_hash' => Hash::make('123456'),
        'requested_at' => now(),
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->from('/admin/reset-password')
        ->put('/admin/reset-password', [
            'username' => $emergencyAdmin->username,
            'otp' => '123456',
            'password' => 'Password-Baru#2026',
            'password_confirmation' => 'Password-Baru#2026',
        ])
        ->assertRedirect('/admin/reset-password')
        ->assertSessionHasErrors('otp');

    expect(Hash::check('Password-Lama#2026', $emergencyAdmin->fresh()->password))->toBeTrue()
        ->and($resetCode->fresh()->used_at)->toBeNull();
});

test('an invalid administrator reset submission records the failed code attempt', function (): void {
    $admin = KompenResponHubAdmin::factory()->create(['username' => 'admin-reset']);
    $resetCode = KompenResponHubAdminPasswordResetCode::query()->create([
        'admin_id' => $admin->id,
        'code_hash' => Hash::make('123456'),
        'requested_at' => now(),
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->from('/admin/reset-password')
        ->put('/admin/reset-password', [
            'username' => $admin->username,
            'otp' => '654321',
            'password' => 'Password-Baru#2026',
            'password_confirmation' => 'Password-Baru#2026',
        ])
        ->assertRedirect('/admin/reset-password')
        ->assertSessionHasErrors('otp');

    expect($resetCode->fresh()->attempts)->toBe(1)
        ->and($resetCode->fresh()->used_at)->toBeNull();
});

test('an active student receives an admin-managed reset code and can use it once', function (): void {
    $nim = '261000001';
    KompenResponHubActiveStudent::factory()->create(['nim' => $nim]);

    $this->post('/mahasiswa/lupa-password', ['nim' => $nim])
        ->assertRedirect('/mahasiswa/reset-password');

    $request = KompenResponHubStudentPasswordResetRequest::query()->sole();
    expect($request->expires_at->isFuture())->toBeTrue();

    $this->put('/mahasiswa/reset-password', [
        'nim' => $nim,
        'reset_code' => $request->reset_code,
        'password' => 'Password-Mahasiswa#2026',
        'password_confirmation' => 'Password-Mahasiswa#2026',
    ])->assertRedirect('/mahasiswa/login');

    expect(Hash::check('Password-Mahasiswa#2026', KompenResponHubStudentAccount::query()->sole()->password))->toBeTrue();
    expect($request->fresh()->fulfilled_at)->not->toBeNull();
});

test('an invalid student reset submission preserves the request and records a failed code attempt', function (): void {
    $nim = '261000001';
    KompenResponHubActiveStudent::factory()->create(['nim' => $nim]);
    $request = KompenResponHubStudentPasswordResetRequest::query()->create([
        'nim' => $nim,
        'reset_code' => 'ABCDEFG1',
        'requested_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $this->from('/mahasiswa/reset-password')
        ->put('/mahasiswa/reset-password', [
            'nim' => $nim,
            'reset_code' => 'SALAH001',
            'password' => 'Password-Mahasiswa#2026',
            'password_confirmation' => 'Password-Mahasiswa#2026',
        ])
        ->assertRedirect('/mahasiswa/reset-password')
        ->assertSessionHasErrors('reset_code');

    expect($request->fresh()->attempts)->toBe(1)
        ->and($request->fresh()->fulfilled_at)->toBeNull();
});

test('an administrator can view student reset requests outside settings', function (): void {
    $this->get('/admin/student-password-resets')->assertRedirect('/admin/login');

    $admin = KompenResponHubAdmin::factory()->create();
    $olderImport = KompenResponHubActiveStudentImport::factory()->create();
    $latestImport = KompenResponHubActiveStudentImport::factory()->create();
    KompenResponHubActiveStudentImportReference::factory()->create([
        'sikompen_active_student_import_id' => $olderImport->id,
        'activated_at' => now()->subDay(),
    ]);
    KompenResponHubActiveStudentImportReference::factory()->create([
        'sikompen_active_student_import_id' => $latestImport->id,
        'periode_semester' => '2027/2028 Genap',
        'activated_at' => now(),
    ]);
    KompenResponHubActiveStudent::factory()->create([
        'sikompen_active_student_import_id' => $olderImport->id,
        'nim' => '261000001',
        'kelas' => '1AEA1',
    ]);
    $student = KompenResponHubActiveStudent::factory()->create([
        'sikompen_active_student_import_id' => $latestImport->id,
        'nim' => '261000001',
        'kelas' => '2AEB2',
    ]);
    KompenResponHubStudentPasswordResetRequest::query()->create([
        'nim' => $student->nim,
        'reset_code' => 'ABCDEFG1',
        'requested_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($admin, 'admin')
        ->get('/admin/student-password-resets')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/student-password-resets')
            ->has('requests.data', 1)
            ->where('requests.data.0.nim', $student->nim)
            ->where('requests.data.0.class', '2AEB2'),
        );
});

test('an administrator can refresh or cancel a student reset request', function (): void {
    $admin = KompenResponHubAdmin::factory()->create();
    $request = KompenResponHubStudentPasswordResetRequest::factory()->create([
        'reset_code' => 'ABCDEFG1',
        'attempts' => 3,
        'expires_at' => now()->addHour(),
    ]);

    $this->actingAs($admin, 'admin')
        ->post("/admin/student-password-resets/{$request->id}/refresh")
        ->assertRedirect('/admin/student-password-resets');

    expect($request->fresh()->reset_code)->not->toBe('ABCDEFG1')
        ->and($request->fresh()->attempts)->toBe(0)
        ->and($request->fresh()->expires_at->isAfter(now()->addHours(23)))->toBeTrue();

    $this->actingAs($admin, 'admin')
        ->delete("/admin/student-password-resets/{$request->id}")
        ->assertRedirect('/admin/student-password-resets');

    expect(KompenResponHubStudentPasswordResetRequest::query()->find($request->id))->toBeNull();
});

test('a pending student reset request retains its code until it expires', function (): void {
    $nim = '261000001';
    KompenResponHubActiveStudent::factory()->create(['nim' => $nim]);
    $existing = KompenResponHubStudentPasswordResetRequest::query()->create([
        'nim' => $nim,
        'reset_code' => 'ABCDEFG1',
        'requested_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $this->post('/mahasiswa/lupa-password', ['nim' => $nim]);

    expect($existing->fresh()->reset_code)->toBe('ABCDEFG1');
});

test('the emergency admin seeder creates the two configured emergency accounts', function (): void {
    config()->set('kompen-respon-hub.emergency_admins', [
        ['username' => 'darurat-satu', 'password' => 'Password-Darurat#2026'],
        ['username' => 'darurat-dua', 'password' => 'Password-Darurat#2026'],
    ]);

    $this->seed(SikompenEmergencyAdminSeeder::class);

    expect(KompenResponHubAdmin::query()->where('is_emergency_admin', true)->count())->toBe(2);
});
