<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\KompenResponHubSystemSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $activity_log_retention_days
 * @property int $import_audit_log_retention_days
 * @property int $workbook_recycle_bin_retention_days
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class KompenResponHubSystemSetting extends Model
{
    /** @use HasFactory<KompenResponHubSystemSettingFactory> */
    use HasFactory;

    public const DefaultActivityLogRetentionDays = 90;

    public const DefaultImportAuditLogRetentionDays = 90;

    public const DefaultWorkbookRecycleBinRetentionDays = 30;

    protected $table = 'sikompen_system_settings';

    protected $fillable = [
        'activity_log_retention_days',
        'import_audit_log_retention_days',
        'workbook_recycle_bin_retention_days',
    ];

    protected function casts(): array
    {
        return [
            'activity_log_retention_days' => 'integer',
            'import_audit_log_retention_days' => 'integer',
            'workbook_recycle_bin_retention_days' => 'integer',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    public static function current(): self
    {
        static::query()->insertOrIgnore([
            'id' => 1,
            'activity_log_retention_days' => self::DefaultActivityLogRetentionDays,
            'import_audit_log_retention_days' => self::DefaultImportAuditLogRetentionDays,
            'workbook_recycle_bin_retention_days' => self::DefaultWorkbookRecycleBinRetentionDays,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return static::query()->findOrFail(1);
    }
}
