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

    $this->get('/mahasiswa')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('kompen-respon-hub/index')
            ->where('isAdmin', false)
            ->where('activeTab', 'students'),
        );
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
        'email' => 'admin@example.test',
        'password' => 'sangat-aman-untuk-admin',
    ]);

    $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'sangat-aman-untuk-admin',
    ])
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($admin, 'admin');
    $this->get('/admin')->assertOk();
});

test('failed admin logins are rate limited after five attempts', function () {
    $admin = KompenResponHubAdmin::factory()->create([
        'email' => 'admin@example.test',
        'password' => 'sangat-aman-untuk-admin',
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password-yang-salah',
        ])->assertSessionHasErrors('email');
    }

    $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'sangat-aman-untuk-admin',
    ])->assertSessionHasErrors('email');
});
