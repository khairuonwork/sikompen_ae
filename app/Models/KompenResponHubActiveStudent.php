<?php

namespace App\Models;

use Database\Factories\KompenResponHubActiveStudentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sikompen_active_student_import_id
 * @property string $nim
 * @property string $nama_mahasiswa
 * @property string $kelas
 * @property-read KompenResponHubActiveStudentImport|null $importBatch
 */
class KompenResponHubActiveStudent extends Model
{
    /** @use HasFactory<KompenResponHubActiveStudentFactory> */
    use HasFactory;

    protected $table = 'sikompen_active_students';

    protected $fillable = [
        'sikompen_active_student_import_id',
        'nim',
        'nama_mahasiswa',
        'kelas',
    ];

    public function getConnectionName(): ?string
    {
        return config('kompen-respon-hub.database_connection');
    }

    /** @return BelongsTo<KompenResponHubActiveStudentImport, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(KompenResponHubActiveStudentImport::class, 'sikompen_active_student_import_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFromActiveRosterSources(Builder $query): Builder
    {
        $studentTable = $this->getTable();
        $sourceTable = (new KompenResponHubActiveStudentImportSource)->getTable();

        return $query->whereExists(function (\Illuminate\Database\Query\Builder $sourceQuery) use ($sourceTable, $studentTable): void {
            $sourceQuery->selectRaw('1')
                ->from($sourceTable)
                ->whereColumn("{$sourceTable}.kelas", "{$studentTable}.kelas")
                ->whereColumn(
                    "{$sourceTable}.sikompen_active_student_import_id",
                    "{$studentTable}.sikompen_active_student_import_id",
                );
        });
    }
}
