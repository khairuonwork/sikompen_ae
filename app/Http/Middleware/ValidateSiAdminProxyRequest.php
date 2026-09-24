<?php

namespace App\Http\Middleware;

use App\Actions\SiAdminProxy\RecordSiAdminProxyAccess;
use App\Actions\SiAdminProxy\SiAdminProxySignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ValidateSiAdminProxyRequest
{
    public function __construct(
        private SiAdminProxySignature $signature,
        private RecordSiAdminProxyAccess $accessRecorder,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('si-admin-proxy.enabled'), 404);

        $attributes = [
            'email' => $this->requiredHeader($request, 'X-Si-Admin-Email', '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'),
            'method' => $request->method(),
            'nonce' => $this->requiredHeader($request, 'X-Si-Admin-Nonce', '/^[A-Za-z0-9_-]{16,128}$/'),
            'path' => $request->getPathInfo(),
            'role' => $this->requiredHeader($request, 'X-Si-Admin-Role', '/^[a-z]+$/'),
            'student_nim' => (string) $request->header('X-Si-Admin-Student-Nim', ''),
            'timestamp' => (int) $this->requiredHeader($request, 'X-Si-Admin-Timestamp', '/^[0-9]{10}$/'),
            'user_id' => $this->requiredHeader($request, 'X-Si-Admin-User-Id', '/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/'),
        ];
        $providedSignature = $this->requiredHeader($request, 'X-Si-Admin-Signature', '/^[a-f0-9]{64}$/');

        abort_unless(in_array($attributes['role'], config('si-admin-proxy.allowed_roles'), true), 403);
        abort_unless(
            $attributes['role'] !== 'mahasiswa' || preg_match('/^[0-9]{9,20}$/', $attributes['student_nim']) === 1,
            403,
        );
        abort_unless(
            $attributes['role'] === 'mahasiswa' || $attributes['student_nim'] === '',
            403,
        );
        abort_unless(
            abs(now()->getTimestamp() - $attributes['timestamp']) <= config('si-admin-proxy.signature_ttl_seconds'),
            403,
        );
        abort_unless(hash_equals($this->signature->create($attributes), $providedSignature), 403);

        $nonceKey = 'sikompen:si-admin-proxy:nonce:'.hash('sha256', $providedSignature);
        abort_unless(
            Cache::add(
                $nonceKey,
                true,
                now()->addSeconds(config('si-admin-proxy.signature_ttl_seconds')),
            ),
            403,
        );

        $this->accessRecorder->record($request, $attributes);

        $request->attributes->set('si_admin_proxy', $attributes);

        return $next($request);
    }

    private function requiredHeader(Request $request, string $name, string $pattern): string
    {
        $value = $request->header($name);

        abort_unless(is_string($value) && preg_match($pattern, $value) === 1, 403);

        return $value;
    }
}
