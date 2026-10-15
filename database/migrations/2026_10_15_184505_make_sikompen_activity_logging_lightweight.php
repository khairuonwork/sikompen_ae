<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = config('kompen-respon-hub.database_connection');
        $schema = Schema::connection($connection);

        if ($schema->hasTable('sikompen_activity_logs') && ! $schema->hasIndex('sikompen_activity_logs', 'sikompen_activity_occurred_id_idx')) {
            $schema->table('sikompen_activity_logs', function (Blueprint $table): void {
                $table->index(['occurred_at', 'id'], 'sikompen_activity_occurred_id_idx');
            });
        }

        if ($schema->hasTable('sikompen_import_audit_logs') && ! $schema->hasIndex('sikompen_import_audit_logs', 'sikompen_import_audit_occurred_id_idx')) {
            $schema->table('sikompen_import_audit_logs', function (Blueprint $table): void {
                $table->index(['occurred_at', 'id'], 'sikompen_import_audit_occurred_id_idx');
            });
        }

        if ($schema->hasTable('sikompen_system_settings')) {
            DB::connection($connection)
                ->table('sikompen_system_settings')
                ->where('activity_log_retention_days', 365)
                ->update(['activity_log_retention_days' => 90]);

            if ($schema->hasColumn('sikompen_system_settings', 'import_audit_log_retention_days')) {
                DB::connection($connection)
                    ->table('sikompen_system_settings')
                    ->where('import_audit_log_retention_days', 365)
                    ->update(['import_audit_log_retention_days' => 90]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection(config('kompen-respon-hub.database_connection'));

        if ($schema->hasTable('sikompen_activity_logs') && $schema->hasIndex('sikompen_activity_logs', 'sikompen_activity_occurred_id_idx')) {
            $schema->table('sikompen_activity_logs', function (Blueprint $table): void {
                $table->dropIndex('sikompen_activity_occurred_id_idx');
            });
        }

        if ($schema->hasTable('sikompen_import_audit_logs') && $schema->hasIndex('sikompen_import_audit_logs', 'sikompen_import_audit_occurred_id_idx')) {
            $schema->table('sikompen_import_audit_logs', function (Blueprint $table): void {
                $table->dropIndex('sikompen_import_audit_occurred_id_idx');
            });
        }
    }
};
