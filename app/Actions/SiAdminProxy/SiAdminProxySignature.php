<?php

namespace App\Actions\SiAdminProxy;

class SiAdminProxySignature
{
    /**
     * @param  array{email: string, method: string, nonce: string, path: string, role: string, student_nim: string, timestamp: int, user_id: string}  $attributes
     */
    public function create(array $attributes): string
    {
        return hash_hmac('sha256', $this->canonicalRequest($attributes), $this->sharedSecret());
    }

    /**
     * @param  array{email: string, method: string, nonce: string, path: string, role: string, student_nim: string, timestamp: int, user_id: string}  $attributes
     */
    public function canonicalRequest(array $attributes): string
    {
        return implode("\n", [
            strtoupper($attributes['method']),
            $attributes['path'],
            (string) $attributes['timestamp'],
            $attributes['nonce'],
            $attributes['user_id'],
            $attributes['email'],
            $attributes['role'],
            $attributes['student_nim'],
        ]);
    }

    private function sharedSecret(): string
    {
        $sharedSecret = config('si-admin-proxy.shared_secret');

        if (! is_string($sharedSecret) || mb_strlen($sharedSecret) < 32) {
            throw new \LogicException('SI_ADMIN_PROXY_SHARED_SECRET harus diisi minimal 32 karakter.');
        }

        return $sharedSecret;
    }
}
