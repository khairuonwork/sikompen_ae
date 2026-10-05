<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KompenResponHubActiveImport extends Model
{
    protected $table = 'sikompen_active_imports';

    protected $fillable = [
        'periode_semester',
        'kompen_respon_hub_import_id',
        'activated_by_admin_id',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'immutable_datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    /** @return BelongsTo<KompenResponHubImport, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubImport::class, 'kompen_respon_hub_import_id');
    }

    /** @return BelongsTo<KompenResponHubAdmin, $this> */
    public function activatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubAdmin::class, 'activated_by_admin_id');
    }
}
