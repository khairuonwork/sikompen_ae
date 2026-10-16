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

        if (! $schema->hasTable('sikompen_active_student_import_sources')) {
            $schema->create('sikompen_active_student_import_sources', function (Blueprint $table): void {
                $table->id();
                $table->string('kelas', 20);
                $table->foreignId('sikompen_active_student_import_id');
                $table->foreignId('activated_by_admin_id')->nullable();
                $table->timestamp('activated_at');
                $table->timestamps();

                $table->unique('kelas', 'sikompen_active_student_source_kelas_uq');
                $table->index(['sikompen_active_student_import_id', 'kelas'], 'sikompen_active_student_source_import_idx');
            });

            $schema->table('sikompen_active_student_import_sources', function (Blueprint $table): void {
                $table->foreign('sikompen_active_student_import_id', 'sikompen_active_student_source_import_fk')
                    ->references('id')
                    ->on('sikompen_active_student_imports')
                    ->cascadeOnDelete();
                $table->foreign('activated_by_admin_id', 'sikompen_active_student_source_admin_fk')
                    ->references('id')
                    ->on('sikompen_admins')
                    ->nullOnDelete();
            });
        }

        if ($schema->hasIndex('sikompen_active_students', 'sikompen_active_students_nim_unique')) {
            $schema->table('sikompen_active_students', function (Blueprint $table): void {
                $table->dropUnique('sikompen_active_students_nim_unique');
            });
        }

        if (! $schema->hasIndex('sikompen_active_students', 'sikompen_active_students_import_nim_uq')) {
            $schema->table('sikompen_active_students', function (Blueprint $table): void {
                $table->unique(['sikompen_active_student_import_id', 'nim'], 'sikompen_active_students_import_nim_uq');
            });
        }

        if (! $schema->hasColumn('sikompen_system_settings', 'import_audit_log_retention_days')) {
            $schema->table('sikompen_system_settings', function (Blueprint $table): void {
                $table->unsignedSmallInteger('import_audit_log_retention_days')
                    ->default(365)
                    ->after('activity_log_retention_days');
            });
        }

        DB::connection(config('kompen-respon-hub.database_connection'))
            ->table('sikompen_active_students')
            ->select('kelas', 'sikompen_active_student_import_id')
            ->distinct()
            ->orderBy('kelas')
            ->get()
            ->each(function (object $student): void {
                $connection = DB::connection(config('kompen-respon-hub.database_connection'));

                if ($connection->table('sikompen_active_student_import_sources')->where('kelas', $student->kelas)->exists()) {
                    return;
                }

                $connection->table('sikompen_active_student_import_sources')->insert([
                    'kelas' => $student->kelas,
                    'sikompen_active_student_import_id' => $student->sikompen_active_student_import_id,
                    'activated_by_admin_id' => null,
                    'activated_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection(config('kompen-respon-hub.database_connection'));

        if ($schema->hasColumn('sikompen_system_settings', 'import_audit_log_retention_days')) {
            $schema->table('sikompen_system_settings', function (Blueprint $table): void {
                $table->dropColumn('import_audit_log_retention_days');
            });
        }

        if ($schema->hasIndex('sikompen_active_students', 'sikompen_active_students_import_nim_uq')) {
            $schema->table('sikompen_active_students', function (Blueprint $table): void {
                $table->dropUnique('sikompen_active_students_import_nim_uq');
            });
        }

        $schema->dropIfExists('sikompen_active_student_import_sources');
    }
};
