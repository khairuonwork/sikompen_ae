<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubApiClientFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $client_id
 * @property string $name
 * @property string $token_hash
 * @property array<int, string> $scopes
 * @property array<int, string> $allowed_ips
 * @property int $rate_limit_per_minute
 * @property bool $is_active
 * @property CarbonInterface|null $last_used_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, KompenResponHubApiSyncRun> $syncRuns
 */
class KompenResponHubApiClient extends Model
{
    /** @use HasFactory<KompenResponHubApiClientFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'token_hash',
        'scopes',
        'allowed_ips',
        'rate_limit_per_minute',
        'is_active',
        'last_used_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'allowed_ips' => 'array',
            'rate_limit_per_minute' => 'integer',
            'is_active' => 'boolean',
            'last_used_at' => 'immutable_datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    /** @return HasMany<KompenResponHubApiSyncRun, $this> */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(KompenResponHubApiSyncRun::class, 'api_client_id');
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    public function allowsIp(string $ip): bool
    {
        foreach ($this->allowed_ips ?? [] as $allowedIp) {
            if ($this->matchesIpRule($ip, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    private function matchesIpRule(string $ip, string $allowedIp): bool
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

        if ($bits < 0 || $bits > $maxBits) {
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
