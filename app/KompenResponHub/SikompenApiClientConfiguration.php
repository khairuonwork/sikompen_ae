<?php

namespace App\KompenResponHub;

use InvalidArgumentException;

final readonly class SikompenApiClientConfiguration
{
    /**
     * @param  list<string>  $allowedIps
     * @param  list<string>  $scopes
     */
    private function __construct(
        public string $key,
        private string $tokenHash,
        public array $allowedIps,
        public array $scopes,
        public int $rateLimitPerMinute,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromDefinition(array $definition): self
    {
        $key = $definition['key'] ?? null;
        $token = $definition['token'] ?? null;
        $allowedIps = $definition['allowed_ips'] ?? [];
        $scopes = $definition['scopes'] ?? ['kompen-respon:read'];
        $rateLimit = $definition['rate_limit_per_minute'] ?? 30;

        if (! is_string($key) || ! preg_match('/^[a-z0-9][a-z0-9-]{2,79}$/', $key)) {
            throw new InvalidArgumentException('Key client API tidak valid.');
        }

        if (! is_string($token) || strlen($token) < 32) {
            throw new InvalidArgumentException('Token client API harus berisi minimal 32 karakter.');
        }

        if (! is_array($allowedIps) || ! self::isStringList($allowedIps)) {
            throw new InvalidArgumentException('Allowlist IP client API harus berupa daftar IP atau CIDR.');
        }

        foreach ($allowedIps as $allowedIp) {
            if (! self::isValidIpRule($allowedIp)) {
                throw new InvalidArgumentException('Allowlist IP client API tidak valid.');
            }
        }

        if (! is_array($scopes) || ! self::isStringList($scopes)) {
            throw new InvalidArgumentException('Scope client API tidak valid.');
        }

        if (! is_int($rateLimit) || $rateLimit < 1 || $rateLimit > 600) {
            throw new InvalidArgumentException('Rate limit client API harus berada antara 1 dan 600.');
        }

        return new self(
            key: $key,
            tokenHash: hash('sha256', $token),
            allowedIps: $allowedIps,
            scopes: $scopes,
            rateLimitPerMinute: $rateLimit,
        );
    }

    /** @return list<self> */
    public static function all(): array
    {
        $definitions = config('kompen-respon-hub.api_sync.clients', []);

        if (! is_array($definitions)) {
            throw new InvalidArgumentException('Konfigurasi client API harus berupa daftar.');
        }

        $clients = [];
        $keys = [];

        foreach ($definitions as $definition) {
            if (! is_array($definition)) {
                throw new InvalidArgumentException('Setiap client API harus berupa objek konfigurasi.');
            }

            $client = self::fromDefinition($definition);

            if (in_array($client->key, $keys, true)) {
                throw new InvalidArgumentException('Key client API tidak boleh duplikat.');
            }

            $clients[] = $client;
            $keys[] = $client->key;
        }

        return $clients;
    }

    public static function forBearerToken(string $token): ?self
    {
        $tokenHash = hash('sha256', $token);

        foreach (self::all() as $client) {
            if (hash_equals($client->tokenHash, $tokenHash)) {
                return $client;
            }
        }

        return null;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function requiresIpAllowlist(): bool
    {
        return $this->allowedIps !== [];
    }

    public function allowsIp(string $ip): bool
    {
        foreach ($this->allowedIps as $allowedIp) {
            if (self::matchesIpRule($ip, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $values
     *
     * @phpstan-assert-if-true list<string> $values
     */
    private static function isStringList(array $values): bool
    {
        if (! array_is_list($values)) {
            return false;
        }

        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                return false;
            }
        }

        return true;
    }

    private static function isValidIpRule(string $rule): bool
    {
        [$ip, $prefix] = str_contains($rule, '/') ? explode('/', $rule, 2) : [$rule, null];

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if ($prefix === null) {
            return true;
        }

        $maxPrefix = str_contains($ip, ':') ? 128 : 32;

        return ctype_digit($prefix) && (int) $prefix <= $maxPrefix;
    }

    private static function matchesIpRule(string $ip, string $allowedIp): bool
    {
        if (! str_contains($allowedIp, '/')) {
            return hash_equals($allowedIp, $ip);
        }

        [$network, $prefix] = explode('/', $allowedIp, 2);
        $ipBinary = inet_pton($ip);
        $networkBinary = inet_pton($network);

        if ($ipBinary === false || $networkBinary === false || strlen($ipBinary) !== strlen($networkBinary) || ! ctype_digit($prefix)) {
            return false;
        }

        $bits = (int) $prefix;
        $maxBits = strlen($ipBinary) * 8;

        if ($bits > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($networkBinary, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($ipBinary[$fullBytes]) & $mask) === (ord($networkBinary[$fullBytes]) & $mask);
    }
}
