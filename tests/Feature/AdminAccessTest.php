<?php

use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubAdminSetupWindow;
use Illuminate\Support\Facades\Hash;

test('the landing page offers separate admin and student access', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->where('isAdminAuthenticated', false)
            ->where('isInitialAdminSetupAvailable', true)
            ->where('isAdminSetupOpen', false),
        );

    $this->get('/mahasiswa')->assertRedirect('/mahasiswa/login');
});

test('the landing page offers setup when an admin opens registration', function () {
    KompenResponHubAdmin::factory()->create();

    KompenResponHubAdminSetupWindow::query()->findOrFail(1)->forceFill([
        'activation_code_hash' => Hash::make('KODE-AKTIVASI'),
        'expires_at' => now()->addMinutes(30),
    ])->save();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->where('isInitialAdminSetupAvailable', false)
            ->where('isAdminSetupOpen', true),
        );
});

test('guests cannot access admin imports or templates', function () {
    $this->get('/admin')
        ->assertRedirect('/admin/login');

    $this->post('/admin/kompen-respon/imports')
        ->assertRedirect('/admin/login');

    $this->get('/admin/kompen-respon/template')
        ->assertRedirect('/admin/login');
});

test('an admin can log in and receives an authenticated session', function () {
    $admin = KompenResponHubAdmin::factory()->create([
        'username' => 'admin-sikompen',
        'password' => 'sangat-aman-untuk-admin',
    ]);

    $this->post('/admin/login', [
        'username' => $admin->username,
        'password' => 'sangat-aman-untuk-admin',
    ])
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($admin, 'admin');
    $this->get('/admin')->assertOk();
});

test('an emergency admin is directed to the restricted recovery page after login', function (): void {
    $emergencyAdmin = KompenResponHubAdmin::factory()->create([
        'is_emergency_admin' => true,
        'username' => 'admin-darurat',
        'password' => 'Password-Darurat#2026',
    ]);

    $this->post('/admin/login', [
        'username' => $emergencyAdmin->username,
        'password' => 'Password-Darurat#2026',
    ])->assertRedirect('/admin/recovery');

    $this->assertAuthenticatedAs($emergencyAdmin, 'admin');
});

test('an emergency admin can only access the recovery area', function (): void {
    $emergencyAdmin = KompenResponHubAdmin::factory()->create(['is_emergency_admin' => true]);

    $this->actingAs($emergencyAdmin, 'admin')
        ->get('/admin/recovery')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/emergency-recovery'));

    $this->actingAs($emergencyAdmin, 'admin')
        ->get('/admin')
        ->assertRedirect('/admin/recovery');

    $this->actingAs($emergencyAdmin, 'admin')
        ->get('/admin/settings')
        ->assertRedirect('/admin/recovery');

    $this->actingAs($emergencyAdmin, 'admin')
        ->get('/admin/student-password-resets')
        ->assertRedirect('/admin/recovery');

    $this->actingAs($emergencyAdmin, 'admin')
        ->post('/exports')
        ->assertForbidden();
});

test('a regular admin cannot access the recovery area', function (): void {
    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get('/admin/recovery')
        ->assertForbidden();
});

test('failed admin logins are rate limited after five attempts', function () {
    $admin = KompenResponHubAdmin::factory()->create([
        'username' => 'admin-sikompen',
        'password' => 'sangat-aman-untuk-admin',
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->post('/admin/login', [
            'username' => $admin->username,
            'password' => 'password-yang-salah',
        ])->assertSessionHasErrors('username');
    }

    $this->post('/admin/login', [
        'username' => $admin->username,
        'password' => 'sangat-aman-untuk-admin',
    ])->assertSessionHasErrors('username');
});

test('an authenticated admin can update their username and invalidate other sessions', function () {
    $admin = KompenResponHubAdmin::factory()->create([
        'username' => 'admin-lama',
        'password' => 'Password-Lama#2026',
    ]);
    $previousSessionVersion = $admin->session_version;

    $this->actingAs($admin, 'admin')
        ->put('/admin/settings/username', [
            'username' => 'admin-baru',
            'current_password' => 'Password-Lama#2026',
        ])
        ->assertRedirect('/admin/settings');

    expect($admin->fresh()->username)->toBe('admin-baru')
        ->and($admin->fresh()->session_version)->toBe($previousSessionVersion + 1);
});

test('an authenticated admin can update their password with the current password', function () {
    $admin = KompenResponHubAdmin::factory()->create([
        'password' => 'Password-Lama#2026',
    ]);
    $previousSessionVersion = $admin->session_version;

    $this->actingAs($admin, 'admin')
        ->put('/admin/settings/password', [
            'current_password' => 'Password-Lama#2026',
            'password' => 'Password-Baru#2026',
            'password_confirmation' => 'Password-Baru#2026',
        ])
        ->assertRedirect('/admin/settings');

    expect(Hash::check('Password-Baru#2026', $admin->fresh()->password))->toBeTrue()
        ->and($admin->fresh()->session_version)->toBe($previousSessionVersion + 1);
});

test('an emergency admin cannot change their fallback credentials in the application', function () {
    $emergencyAdmin = KompenResponHubAdmin::factory()->create([
        'is_emergency_admin' => true,
        'username' => 'admin-darurat',
        'password' => 'Password-Lama#2026',
    ]);

    $this->actingAs($emergencyAdmin, 'admin')
        ->put('/admin/settings/username', [
            'username' => 'admin-berubah',
            'current_password' => 'Password-Lama#2026',
        ])
        ->assertRedirect('/admin/recovery');

    $this->actingAs($emergencyAdmin, 'admin')
        ->put('/admin/settings/password', [
            'current_password' => 'Password-Lama#2026',
            'password' => 'Password-Baru#2026',
            'password_confirmation' => 'Password-Baru#2026',
        ])
        ->assertRedirect('/admin/recovery');

    expect($emergencyAdmin->fresh()->username)->toBe('admin-darurat')
        ->and(Hash::check('Password-Lama#2026', $emergencyAdmin->fresh()->password))->toBeTrue();
});
