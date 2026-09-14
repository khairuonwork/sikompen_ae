<?php

use App\Actions\SiAdminProxy\SiAdminProxySignature;
use App\Models\KompenResponHubAdminSetupWindow;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set([
        'si-admin-proxy.enabled' => true,
        'si-admin-proxy.shared_secret' => str_repeat('a', 64),
        'si-admin-proxy.signature_ttl_seconds' => 60,
    ]);
});

test('the Si-Admin gateway sends admin to the admin panel', function (): void {
    $this->withHeaders(siAdminProxyHeaders('admin'))
        ->get('/si-admin/access')
        ->assertRedirect(route('admin.kompen-respon.index'))
        ->assertSessionHas('si_admin_proxy.role', 'admin');

    $this->get('/admin/kompen-respon')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('kompen-respon-hub/index')
            ->where('isAdmin', true),
        );
});

test('the Si-Admin gateway sends superuser to the access selection page', function (): void {
    $this->withHeaders(siAdminProxyHeaders('superuser'))
        ->get('/si-admin/access')
        ->assertRedirect(route('home'))
        ->assertSessionHas('si_admin_proxy.role', 'superuser');

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->where('isAdminAuthenticated', true),
        );

    $this->get('/admin/kompen-respon')->assertOk();
    $this->get('/mahasiswa')->assertOk();
});

test('the Si-Admin gateway sends mahasiswa to the student page', function (): void {
    $this->withHeaders(siAdminProxyHeaders('mahasiswa'))
        ->get('/si-admin/access')
        ->assertRedirect(route('student.kompen-respon.index'))
        ->assertSessionHas('si_admin_proxy.role', 'mahasiswa');
});

test('a proxy superuser can use an admin action without a local admin record', function (): void {
    $this->withHeaders(siAdminProxyHeaders('superuser'))
        ->get('/si-admin/access')
        ->assertRedirect(route('home'));

    $this->post('/admin/settings/admin-setup')
        ->assertRedirect(route('admin.settings'));

    expect(KompenResponHubAdminSetupWindow::query()->findOrFail(1)->opened_by_admin_id)
        ->toBeNull();
});

test('a direct request cannot enter the admin panel through the proxy access rules', function (): void {
    $this->get('/admin/kompen-respon')
        ->assertRedirect(route('login'));
});

test('the proxy access endpoint rejects a tampered role', function (): void {
    $headers = siAdminProxyHeaders('mahasiswa');
    $headers['X-Si-Admin-Role'] = 'admin';

    $this->withHeaders($headers)
        ->get('/si-admin/access')
        ->assertForbidden();
});

test('the proxy access endpoint rejects a replayed signature', function (): void {
    $headers = siAdminProxyHeaders('superuser');

    $this->withHeaders($headers)
        ->get('/si-admin/access')
        ->assertRedirect(route('home'));

    $this->withHeaders($headers)
        ->get('/si-admin/access')
        ->assertForbidden();
});

test('the proxy access endpoint rejects an expired signature', function (): void {
    $this->withHeaders(siAdminProxyHeaders('admin', now()->subSeconds(61)->getTimestamp()))
        ->get('/si-admin/access')
        ->assertForbidden();
});

test('the proxy access endpoint is hidden until the integration is enabled', function (): void {
    config()->set('si-admin-proxy.enabled', false);

    $this->withHeaders(siAdminProxyHeaders('admin'))
        ->get('/si-admin/access')
        ->assertNotFound();
});

/** @return array<string, string> */
function siAdminProxyHeaders(string $role, ?int $timestamp = null): array
{
    $attributes = [
        'email' => 'superuser@si-admin.test',
        'method' => 'GET',
        'nonce' => Str::lower(Str::random(32)),
        'path' => '/si-admin/access',
        'role' => $role,
        'timestamp' => $timestamp ?? now()->getTimestamp(),
        'user_id' => 'si-admin-123',
    ];

    $signature = app(SiAdminProxySignature::class)->create($attributes);

    return [
        'X-Si-Admin-Email' => $attributes['email'],
        'X-Si-Admin-Nonce' => $attributes['nonce'],
        'X-Si-Admin-Role' => $attributes['role'],
        'X-Si-Admin-Signature' => $signature,
        'X-Si-Admin-Timestamp' => (string) $attributes['timestamp'],
        'X-Si-Admin-User-Id' => $attributes['user_id'],
    ];
}
