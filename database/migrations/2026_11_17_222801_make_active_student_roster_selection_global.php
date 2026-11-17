<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('kompen-respon-hub.database_connection');
        $schema = Schema::connection($connection);

        if (! $schema->hasTable('sikompen_active_student_import_references')) {
            return;
        }

        if (! $schema->hasColumn('sikompen_active_student_import_references', 'selection_key')) {
            $schema->table('sikompen_active_student_import_references', function (Blueprint $table): void {
                $table->string('selection_key', 32)->default('student-login');
            });
        }

        $references = DB::connection($connection)
            ->table('sikompen_active_student_import_references')
            ->orderByDesc('activated_at')
            ->orderByDesc('id')
            ->get(['id']);
        $selectedReference = $references->first();

        if ($selectedReference !== null) {
            DB::connection($connection)
                ->table('sikompen_active_student_import_references')
                ->where('id', '!=', $selectedReference->id)
                ->delete();
        }

        if ($schema->hasIndex('sikompen_active_student_import_references', 'sikompen_active_student_reference_period_uq')) {
            $schema->table('sikompen_active_student_import_references', function (Blueprint $table): void {
                $table->dropUnique('sikompen_active_student_reference_period_uq');
            });
        }

        if (! $schema->hasIndex('sikompen_active_student_import_references', 'sikompen_active_student_reference_selection_uq')) {
            $schema->table('sikompen_active_student_import_references', function (Blueprint $table): void {
                $table->unique('selection_key', 'sikompen_active_student_reference_selection_uq');
            });
        }
    }

    public function down(): void
    {
        $connection = config('kompen-respon-hub.database_connection');
        $schema = Schema::connection($connection);

        if (! $schema->hasTable('sikompen_active_student_import_references')) {
            return;
        }

        if ($schema->hasIndex('sikompen_active_student_import_references', 'sikompen_active_student_reference_selection_uq')) {
            $schema->table('sikompen_active_student_import_references', function (Blueprint $table): void {
                $table->dropUnique('sikompen_active_student_reference_selection_uq');
            });
        }

        if (! $schema->hasIndex('sikompen_active_student_import_references', 'sikompen_active_student_reference_period_uq')) {
            $schema->table('sikompen_active_student_import_references', function (Blueprint $table): void {
                $table->unique('periode_semester', 'sikompen_active_student_reference_period_uq');
            });
        }

        if ($schema->hasColumn('sikompen_active_student_import_references', 'selection_key')) {
            $schema->table('sikompen_active_student_import_references', function (Blueprint $table): void {
                $table->dropColumn('selection_key');
            });
        }
    }
};
