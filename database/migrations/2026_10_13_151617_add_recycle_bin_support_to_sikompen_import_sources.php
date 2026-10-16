<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('kompen-respon-hub.database_connection'));

        foreach (['sikompen_imports', 'sikompen_active_student_imports'] as $table) {
            if (! $schema->hasColumn($table, 'deleted_at')) {
                $schema->table($table, function (Blueprint $table): void {
                    $table->softDeletes();
                });
            }
        }

        if (! $schema->hasColumn('sikompen_system_settings', 'workbook_recycle_bin_retention_days')) {
            $schema->table('sikompen_system_settings', function (Blueprint $table): void {
                $table->unsignedSmallInteger('workbook_recycle_bin_retention_days')->default(30);
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection(config('kompen-respon-hub.database_connection'));

        if ($schema->hasColumn('sikompen_system_settings', 'workbook_recycle_bin_retention_days')) {
            $schema->table('sikompen_system_settings', function (Blueprint $table): void {
                $table->dropColumn('workbook_recycle_bin_retention_days');
            });
        }

        foreach (['sikompen_imports', 'sikompen_active_student_imports'] as $table) {
            if ($schema->hasColumn($table, 'deleted_at')) {
                $schema->table($table, function (Blueprint $table): void {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
