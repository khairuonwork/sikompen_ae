<?php

use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubAdminPasswordResetCode;
use App\Models\KompenResponHubStudentAccount;
use App\Models\KompenResponHubStudentPasswordResetRequest;
use Database\Seeders\SikompenEmergencyAdminSeeder;
use Illuminate\Support\Facades\Hash;

test('an administrator can request a reset code without disclosing account availability', function (): void {
    $admin = KompenResponHubAdmin::factory()->create(['email' => 'admin@gmail.com']);

    $this->post('/admin/lupa-password', ['email' => $admin->email])
        ->assertRedirect('/admin/reset-password?email=admin%40gmail.com');

    $code = KompenResponHubAdminPasswordResetCode::query()->sole();
    expect($code->admin_id)->toBe($admin->id)->and($code->code_hash)->toBeNull();
});

test('an unknown admin reset request does not disclose account availability', function (): void {
    $this->post('/admin/lupa-password', ['email' => 'unknown@gmail.com'])
        ->assertRedirect('/admin/reset-password?email=unknown%40gmail.com');

    expect(KompenResponHubAdminPasswordResetCode::query()->count())->toBe(0);
});

test('an emergency admin can issue a one-time code for an administrator reset', function (): void {
    $emergencyAdmin = KompenResponHubAdmin::factory()->create(['is_emergency_admin' => true]);
    $admin = KompenResponHubAdmin::factory()->create(['email' => 'admin@gmail.com']);
    $reset = KompenResponHubAdminPasswordResetCode::query()->create(['admin_id' => $admin->id, 'requested_at' => now()]);

    $this->actingAs($emergencyAdmin, 'admin')
        ->post("/admin/settings/admin-password-resets/{$reset->id}/code")
        ->assertRedirect('/admin/settings')
        ->assertSessionHas('admin_reset_code');

    expect($reset->fresh()->code_hash)->not->toBeNull();
});

test('an administrator can reset their password with a valid one-time code', function (): void {
    $admin = KompenResponHubAdmin::factory()->create(['email' => 'admin@gmail.com']);
    KompenResponHubAdminPasswordResetCode::query()->create(['admin_id' => $admin->id, 'code_hash' => Hash::make('123456'), 'requested_at' => now(), 'expires_at' => now()->addMinutes(15)]);

    $this->put('/admin/reset-password', [
        'email' => $admin->email,
        'otp' => '123456',
        'password' => 'Password-Baru#2026',
        'password_confirmation' => 'Password-Baru#2026',
    ])->assertRedirect('/admin/login');

    expect(Hash::check('Password-Baru#2026', $admin->fresh()->password))->toBeTrue();
    expect(KompenResponHubAdminPasswordResetCode::query()->sole()->used_at)->not->toBeNull();
});

test('an active student receives an admin-managed reset code and can use it once', function (): void {
    $nim = '261000001';
    KompenResponHubActiveStudent::factory()->create(['nim' => $nim]);

    $this->post('/mahasiswa/lupa-password', ['nim' => $nim])
        ->assertRedirect('/mahasiswa/reset-password?nim='.$nim);

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
        ['email' => 'darurat-satu@example.test', 'password' => 'Password-Darurat#2026'],
        ['email' => 'darurat-dua@example.test', 'password' => 'Password-Darurat#2026'],
    ]);

    $this->seed(SikompenEmergencyAdminSeeder::class);

    expect(KompenResponHubAdmin::query()->where('is_emergency_admin', true)->count())->toBe(2);
});
