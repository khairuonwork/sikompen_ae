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
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('sikompen_surat_peringatan', function (Blueprint $table): void {
            $table->string('cancellation_source', 40)->nullable()->after('cancelled_at');
            $table->index(
                ['cutoff_id', 'letter_status', 'cancellation_source'],
                'sikompen_sp_cutoff_cancellation_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('sikompen_surat_peringatan', function (Blueprint $table): void {
            $table->dropIndex('sikompen_sp_cutoff_cancellation_idx');
            $table->dropColumn('cancellation_source');
        });
    }
};
