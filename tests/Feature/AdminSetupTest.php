<?php

use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubAdminSetupWindow;
use Illuminate\Support\Facades\Hash;

test('the first admin can be created from the initial setup page', function () {
    $this->get('/admin/setup')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/admin-setup')
            ->where('setupOpen', true)
            ->where('requiresActivationCode', false),
        );

    $this->post('/admin/setup', [
        'username' => 'admin-pertama',
        'password' => 'Password!YangAman123',
        'password_confirmation' => 'Password!YangAman123',
    ])->assertRedirect('/admin/login');

    $this->assertDatabaseHas('sikompen_admins', [
        'username' => 'admin-pertama',
    ]);

    $this->get('/admin/setup')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('setupOpen', false)
            ->where('requiresActivationCode', true),
        );

    $this->post('/admin/login', [
        'username' => 'admin-pertama',
        'password' => 'Password!YangAman123',
    ])
        ->assertRedirect('/admin');

    $this->get('/admin')->assertOk();
});

test('emergency fallback accounts do not close initial regular admin setup', function (): void {
    KompenResponHubAdmin::factory()->count(2)->create([
        'is_emergency_admin' => true,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('isInitialAdminSetupAvailable', true)
            ->where('isAdminSetupOpen', false),
        );

    $this->get('/admin/setup')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('setupOpen', true)
            ->where('requiresActivationCode', false),
        );

    $this->post('/admin/setup', [
        'username' => 'admin-reguler',
        'password' => 'Password!YangAman123',
        'password_confirmation' => 'Password!YangAman123',
    ])->assertRedirect('/admin/login');

    expect(KompenResponHubAdmin::query()->where('is_emergency_admin', false)->count())->toBe(1)
        ->and(KompenResponHubAdmin::query()->where('is_emergency_admin', true)->count())->toBe(2);
});

test('a guest cannot create an admin after the initial setup has closed', function () {
    KompenResponHubAdmin::factory()->create();

    $this->post('/admin/setup', [
        'username' => 'admin-tak-terduga',
        'password' => 'Password!YangAman123',
        'password_confirmation' => 'Password!YangAman123',
    ])->assertForbidden();

    expect(KompenResponHubAdmin::query()->count())->toBe(1);
});

test('an authenticated admin can open a one-account registration window', function () {
    $admin = KompenResponHubAdmin::factory()->create();

    $response = $this->actingAs($admin, 'admin')
        ->post('/admin/settings/admin-setup')
        ->assertRedirect('/admin/settings')
        ->assertSessionHas('admin_setup_code');

    $activationCode = $response->getSession()->get('admin_setup_code');
    $setupWindow = KompenResponHubAdminSetupWindow::query()->findOrFail(1);

    expect($setupWindow->isOpen())->toBeTrue()
        ->and(Hash::check($activationCode, $setupWindow->activation_code_hash))->toBeTrue();

    $this->post('/admin/setup', [
        'username' => 'admin-baru',
        'password' => 'Password!YangAman123',
        'password_confirmation' => 'Password!YangAman123',
        'activation_code' => $activationCode,
    ])->assertRedirect('/admin/login');

    $setupWindow->refresh();

    expect(KompenResponHubAdmin::query()->count())->toBe(2)
        ->and($setupWindow->isOpen())->toBeFalse()
        ->and($setupWindow->activation_code_hash)->toBeNull();
});

test('an invalid or expired activation code cannot create an admin', function () {
    KompenResponHubAdmin::factory()->create();

    $setupWindow = KompenResponHubAdminSetupWindow::query()->findOrFail(1);
    $setupWindow->forceFill([
        'activation_code_hash' => Hash::make('KODE-AKTIVASI-YANG-BENAR'),
        'expires_at' => now()->addMinutes(30),
    ])->save();

    $this->post('/admin/setup', [
        'username' => 'admin-baru',
        'password' => 'Password!YangAman123',
        'password_confirmation' => 'Password!YangAman123',
        'activation_code' => 'KODE-YANG-SALAH',
    ])->assertSessionHasErrors('activation_code');

    $setupWindow->forceFill([
        'expires_at' => now()->subMinute(),
    ])->save();

    $this->post('/admin/setup', [
        'username' => 'admin-baru',
        'password' => 'Password!YangAman123',
        'password_confirmation' => 'Password!YangAman123',
        'activation_code' => 'KODE-AKTIVASI-YANG-BENAR',
    ])->assertForbidden();

    expect(KompenResponHubAdmin::query()->count())->toBe(1);
});

test('only an authenticated admin can manage the registration window', function () {
    $this->get('/admin/settings')->assertRedirect('/admin/login');
    $this->post('/admin/settings/admin-setup')->assertRedirect('/admin/login');
    $this->delete('/admin/settings/admin-setup')->assertRedirect('/admin/login');
});
