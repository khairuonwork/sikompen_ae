<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('sikompen_surat_peringatan_artifacts', function (Blueprint $table): void {
            $table->json('source_provenance')->nullable()->after('row_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('sikompen_surat_peringatan_artifacts', function (Blueprint $table): void {
            $table->dropColumn('source_provenance');
        });
    }
};
