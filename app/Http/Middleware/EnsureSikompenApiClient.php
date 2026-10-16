<?php

namespace App\Http\Middleware;

use App\Models\KompenResponHubApiClient;
use Closure;
use Illuminate\Http\Request;
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

        $clientId = $request->header('X-Client-Id');
        $token = $request->bearerToken();

        if (! is_string($clientId) || ! is_string($token) || $clientId === '' || $token === '') {
            return $this->error($requestId, 'unauthenticated', 'Kredensial client API tidak valid.', Response::HTTP_UNAUTHORIZED);
        }

        $client = KompenResponHubApiClient::query()
            ->where('client_id', $clientId)
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $client instanceof KompenResponHubApiClient || ! $client->is_active) {
            return $this->error($requestId, 'unauthenticated', 'Kredensial client API tidak valid.', Response::HTTP_UNAUTHORIZED);
        }

        if (! $client->hasScope('kompen-respon:read')) {
            return $this->error($requestId, 'forbidden', 'Client API tidak memiliki cakupan akses yang diperlukan.', Response::HTTP_FORBIDDEN);
        }

        $ip = $request->ip();

        if (! is_string($ip) || ! $client->allowsIp($ip)) {
            return $this->error($requestId, 'ip_not_allowed', 'Alamat IP peminta tidak diizinkan.', Response::HTTP_FORBIDDEN);
        }

        $client->forceFill(['last_used_at' => now()])->saveQuietly();
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
