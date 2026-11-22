<?php

use App\Models\KompenResponHubAdmin;

test('student guide is available before login', function (): void {
    $this->get('/panduan/mahasiswa')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('admin guide requires an authenticated standard admin', function (): void {
    $this->get('/admin/panduan/admin')
        ->assertRedirect('/admin/login');

    $admin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get('/admin/panduan/admin')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
