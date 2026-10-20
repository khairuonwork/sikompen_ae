<?php

namespace App\Http\Middleware;

use App\KompenResponHub\SikompenApiClientConfiguration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureSikompenApiClient
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::ulid();
        $request->attributes->set('sikompen_api_request_id', $requestId);

        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return $this->error($requestId, 'unauthenticated', 'Kredensial client API tidak valid.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $client = SikompenApiClientConfiguration::forBearerToken($token);
        } catch (\InvalidArgumentException $exception) {
            Log::error('Konfigurasi client API Sikompen tidak valid.', [
                'exception' => $exception::class,
            ]);

            return $this->error($requestId, 'service_unavailable', 'Konfigurasi provider API belum siap.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (! $client instanceof SikompenApiClientConfiguration) {
            return $this->error($requestId, 'unauthenticated', 'Kredensial client API tidak valid.', Response::HTTP_UNAUTHORIZED);
        }

        if (! $client->hasScope('kompen-respon:read')) {
            return $this->error($requestId, 'forbidden', 'Client API tidak memiliki cakupan akses yang diperlukan.', Response::HTTP_FORBIDDEN);
        }

        if ($client->requiresIpAllowlist()) {
            $ip = $request->ip();

            if (! is_string($ip) || ! $client->allowsIp($ip)) {
                return $this->error($requestId, 'ip_not_allowed', 'Alamat IP peminta tidak diizinkan.', Response::HTTP_FORBIDDEN);
            }
        }

        $request->attributes->set('sikompen_api_client', $client);

        return $next($request);
    }

    private function error(string $requestId, string $code, string $message, int $status): Response
    {
        return response()->json([
            'status' => 'error',
            'request_id' => $requestId,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status, [
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
