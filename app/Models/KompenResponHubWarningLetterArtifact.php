<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\KompenResponHubWarningLetterArtifactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $cutoff_id
 * @property string $status
 * @property int $revision
 * @property string $display_filename
 * @property string $stored_path
 * @property string $file_hash
 * @property int $row_count
 * @property array<string, mixed>|null $source_provenance
 * @property CarbonImmutable $finalized_at
 * @property int|null $finalized_by_admin_id
 * @property CarbonImmutable|null $correction_started_at
 * @property int|null $correction_started_by_admin_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read KompenResponHubPeriodCutoff|null $cutoff
 */
class KompenResponHubWarningLetterArtifact extends Model
{
    /** @use HasFactory<KompenResponHubWarningLetterArtifactFactory> */
    use HasFactory;

    public const StatusFinalized = 'finalized';

    public const StatusRevising = 'revising';

    public const StatusSuperseded = 'superseded';

    protected $table = 'sikompen_surat_peringatan_artifacts';

    protected $fillable = [
        'cutoff_id',
        'status',
        'revision',
        'display_filename',
        'stored_path',
        'file_hash',
        'row_count',
        'source_provenance',
        'finalized_at',
        'finalized_by_admin_id',
        'correction_started_at',
        'correction_started_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'finalized_at' => 'immutable_datetime',
            'correction_started_at' => 'immutable_datetime',
            'source_provenance' => 'array',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    /** @return BelongsTo<KompenResponHubPeriodCutoff, $this> */
    public function cutoff(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubPeriodCutoff::class, 'cutoff_id');
    }
}
