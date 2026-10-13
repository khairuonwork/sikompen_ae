<?php

use App\Mail\AdminPasswordResetCodeMail;
use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubAdminPasswordResetCode;
use App\Models\KompenResponHubAdminPasswordResetRecipient;
use App\Models\KompenResponHubStudentAccount;
use App\Models\KompenResponHubStudentPasswordResetRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('an allowlisted administrator can request an emailed reset OTP', function (): void {
    Mail::fake();
    $admin = KompenResponHubAdmin::factory()->create(['email' => 'admin@gmail.com']);
    KompenResponHubAdminPasswordResetRecipient::query()->create(['email' => $admin->email]);

    $this->post('/admin/lupa-password', ['email' => $admin->email])
        ->assertRedirect('/admin/reset-password?email=admin%40gmail.com');

    $code = KompenResponHubAdminPasswordResetCode::query()->sole();
    expect($code->admin_id)->toBe($admin->id)->and($code->expires_at->isFuture())->toBeTrue();
    Mail::assertQueued(AdminPasswordResetCodeMail::class);
});

test('a non allowlisted admin reset request does not disclose account availability', function (): void {
    Mail::fake();

    $this->post('/admin/lupa-password', ['email' => 'unknown@gmail.com'])
        ->assertRedirect('/admin/reset-password?email=unknown%40gmail.com');

    expect(KompenResponHubAdminPasswordResetCode::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

test('an administrator can reset their password with a valid one-time OTP', function (): void {
    $admin = KompenResponHubAdmin::factory()->create(['email' => 'admin@gmail.com']);
    $recipient = KompenResponHubAdminPasswordResetRecipient::query()->create(['email' => $admin->email]);
    KompenResponHubAdminPasswordResetCode::query()->create([
        'admin_id' => $admin->id,
        'recipient_id' => $recipient->id,
        'code_hash' => Hash::make('123456'),
        'expires_at' => now()->addMinutes(15),
    ]);

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
