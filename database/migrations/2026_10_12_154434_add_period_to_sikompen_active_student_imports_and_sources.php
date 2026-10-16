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
        $schema = Schema::connection(config('kompen-respon-hub.database_connection'));

        if (! $schema->hasColumn('sikompen_active_student_imports', 'periode_semester')) {
            $schema->table('sikompen_active_student_imports', function (Blueprint $table): void {
                $table->string('periode_semester', 20)->nullable()->after('original_filename');
                $table->index(['periode_semester', 'imported_at', 'id'], 'sikompen_active_student_import_period_idx');
            });
        }

        if (! $schema->hasColumn('sikompen_active_student_import_sources', 'periode_semester')) {
            $schema->table('sikompen_active_student_import_sources', function (Blueprint $table): void {
                $table->string('periode_semester', 20)->nullable()->after('id');
            });
        }

        if ($schema->hasIndex('sikompen_active_student_import_sources', 'sikompen_active_student_source_kelas_uq')) {
            $schema->table('sikompen_active_student_import_sources', function (Blueprint $table): void {
                $table->dropUnique('sikompen_active_student_source_kelas_uq');
            });
        }

        if (! $schema->hasIndex('sikompen_active_student_import_sources', 'sikompen_active_student_source_period_kelas_uq')) {
            $schema->table('sikompen_active_student_import_sources', function (Blueprint $table): void {
                $table->unique(['periode_semester', 'kelas'], 'sikompen_active_student_source_period_kelas_uq');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('kompen-respon-hub.database_connection');
        $schema = Schema::connection($connection);

        $hasDuplicateClasses = DB::connection($connection)
            ->table('sikompen_active_student_import_sources')
            ->select('kelas')
            ->groupBy('kelas')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateClasses) {
            throw new LogicException('Rollback tidak aman karena satu kelas sudah memiliki sumber roster untuk lebih dari satu periode.');
        }

        if ($schema->hasIndex('sikompen_active_student_import_sources', 'sikompen_active_student_source_period_kelas_uq')) {
            $schema->table('sikompen_active_student_import_sources', function (Blueprint $table): void {
                $table->dropUnique('sikompen_active_student_source_period_kelas_uq');
            });
        }

        if (! $schema->hasIndex('sikompen_active_student_import_sources', 'sikompen_active_student_source_kelas_uq')) {
            $schema->table('sikompen_active_student_import_sources', function (Blueprint $table): void {
                $table->unique('kelas', 'sikompen_active_student_source_kelas_uq');
            });
        }

        if ($schema->hasColumn('sikompen_active_student_import_sources', 'periode_semester')) {
            $schema->table('sikompen_active_student_import_sources', function (Blueprint $table): void {
                $table->dropColumn('periode_semester');
            });
        }

        if ($schema->hasColumn('sikompen_active_student_imports', 'periode_semester')) {
            $schema->table('sikompen_active_student_imports', function (Blueprint $table): void {
                $table->dropIndex('sikompen_active_student_import_period_idx');
                $table->dropColumn('periode_semester');
            });
        }
    }
};
