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
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('sikompen_import_audit_logs', function (Blueprint $table): void {
            $table->index(['periode_semester', 'occurred_at', 'id'], 'sikompen_audit_period_time_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('sikompen_import_audit_logs', function (Blueprint $table): void {
            $table->dropIndex('sikompen_audit_period_time_id_idx');
        });
    }
};
