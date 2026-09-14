<?php

use App\Actions\SiAdminProxy\SiAdminProxySignature;
use App\Models\KompenResponHubAdmin;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set([
        'si-admin-proxy.enabled' => true,
        'si-admin-proxy.shared_secret' => str_repeat('a', 64),
        'si-admin-proxy.signature_ttl_seconds' => 60,
        'si-admin-proxy.session_max_age_seconds' => 7200,
    ]);
});

afterEach(function (): void {
    config()->set('si-admin-proxy.enabled', false);
    URL::useOrigin(null);
    URL::useAssetOrigin(null);
    URL::forceScheme(null);
});

test('the Si-Admin gateway sends admin to the admin panel', function (): void {
    $this->withHeaders(siAdminProxyHeaders('admin'))
        ->get('/si-admin/access')
        ->assertRedirect(route('admin.kompen-respon.index'))
        ->assertSessionHas('si_admin_proxy.role', 'admin');

    $this->assertDatabaseHas('sikompen_proxy_access_logs', [
        'email' => 'superuser@si-admin.test',
        'role' => 'admin',
        'si_admin_user_id' => 'si-admin-123',
    ]);

    $this->get(route('admin.kompen-respon.index'))
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

    $this->get(route('admin.kompen-respon.index'))->assertOk();
    $this->get('/mahasiswa')->assertOk();
});

test('the Si-Admin gateway sends mahasiswa to the student page', function (): void {
    $this->withHeaders(siAdminProxyHeaders('mahasiswa'))
        ->get('/si-admin/access')
        ->assertRedirect(route('student.kompen-respon.index'))
        ->assertSessionHas('si_admin_proxy.role', 'mahasiswa');
});

test('a proxy superuser cannot open a local admin setup window', function (): void {
    $this->withHeaders(siAdminProxyHeaders('superuser'))
        ->get('/si-admin/access')
        ->assertRedirect(route('home'));

    $this->post('/admin/settings/admin-setup')
        ->assertNotFound();
});

test('proxy mode returns 403 for direct web and API requests without a proxy session', function (): void {
    $this->get(route('admin.kompen-respon.index'))
        ->assertForbidden();

    $this->get('/mahasiswa')->assertForbidden();
    $this->getJson('/api/kompen-respon/students')->assertForbidden();
});

test('proxy mode returns 404 for standalone authentication routes', function (): void {
    $this->get('/admin/login')->assertNotFound();
    $this->get('/admin/setup')->assertNotFound();
});

test('a local admin session cannot elevate a proxy mahasiswa session', function (): void {
    $localAdmin = KompenResponHubAdmin::factory()->create();

    $this->actingAs($localAdmin, 'admin')
        ->withHeaders(siAdminProxyHeaders('mahasiswa'))
        ->get('/si-admin/access')
        ->assertRedirect(route('student.kompen-respon.index'));

    $this->get(route('admin.kompen-respon.index'))->assertForbidden();
});

test('proxy mode returns 403 for an expired proxy session', function (): void {
    $this->withSession([
        'si_admin_proxy.authenticated_at' => now()->subSeconds(7201)->getTimestamp(),
        'si_admin_proxy.email' => 'superuser@si-admin.test',
        'si_admin_proxy.role' => 'admin',
        'si_admin_proxy.user_id' => 'si-admin-123',
    ])->get('/mahasiswa')->assertForbidden();
});

test('proxy mode generates public URLs with the Sikompen path prefix', function (): void {
    config()->set([
        'app.url' => 'https://si-admin.test/sikompen',
        'si-admin-proxy.enabled' => true,
    ]);

    (new AppServiceProvider(app()))->boot();

    expect(route('admin.kompen-respon.index'))->toBe('https://si-admin.test/sikompen/admin');

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

test('the proxy access endpoint rejects a nonce reused with a different valid timestamp', function (): void {
    $nonce = Str::lower(Str::random(32));

    $this->withHeaders(siAdminProxyHeaders('admin', now()->subSeconds(10)->getTimestamp(), $nonce))
        ->get('/si-admin/access')
        ->assertRedirect(route('admin.kompen-respon.index'));

    $this->withHeaders(siAdminProxyHeaders('admin', now()->getTimestamp(), $nonce))
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
function siAdminProxyHeaders(string $role, ?int $timestamp = null, ?string $nonce = null): array
{
    $attributes = [
        'email' => 'superuser@si-admin.test',
        'method' => 'GET',
        'nonce' => $nonce ?? Str::lower(Str::random(32)),
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
