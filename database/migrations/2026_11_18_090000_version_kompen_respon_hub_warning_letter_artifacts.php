<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('sikompen_surat_peringatan_artifacts', function (Blueprint $table): void {
            $table->dropUnique('sikompen_surat_peringatan_artifacts_cutoff_id_unique');
            $table->unique(['cutoff_id', 'revision'], 'sikompen_sp_artifact_cutoff_revision_unique');
        });
    }

    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('sikompen_surat_peringatan_artifacts', function (Blueprint $table): void {
            $table->dropUnique('sikompen_sp_artifact_cutoff_revision_unique');
            $table->unique('cutoff_id');
        });
    }
};
