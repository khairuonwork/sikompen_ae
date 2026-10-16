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
        $schema = Schema::connection(config('kompen-respon-hub.database_connection'));
        $hasForeignKey = static fn (string $table, string $column): bool => collect($schema->getForeignKeys($table))
            ->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === [$column]);
        $hasIndex = static fn (string $table, string $index): bool => collect($schema->getIndexes($table))
            ->contains(fn (array $existingIndex): bool => $existingIndex['name'] === $index);

        if (! $schema->hasTable('sikompen_active_student_imports')) {
            $schema->create('sikompen_active_student_imports', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('uploaded_by_admin_id')->nullable();
                $table->string('uploader_name', 100);
                $table->string('uploader_email')->nullable();
                $table->string('original_filename');
                $table->string('stored_path');
                $table->string('file_hash', 64);
                $table->unsignedInteger('student_count');
                $table->unsignedInteger('class_count');
                $table->timestamp('imported_at');
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('sikompen_active_students')) {
            $schema->create('sikompen_active_students', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('sikompen_active_student_import_id');
                $table->string('nim', 20);
                $table->string('nama_mahasiswa', 100);
                $table->string('kelas', 20);
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('sikompen_active_student_import_tasks')) {
            $schema->create('sikompen_active_student_import_tasks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('uploaded_by_admin_id')->nullable();
                $table->string('uploader_name', 100);
                $table->string('uploader_email')->nullable();
                $table->string('original_filename');
                $table->string('stored_path');
                $table->string('file_hash', 64);
                $table->string('status', 20);
                $table->unsignedTinyInteger('progress')->default(0);
                $table->string('progress_message');
                $table->text('error_message')->nullable();
                $table->foreignId('sikompen_active_student_import_id')->nullable();
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $hasForeignKey('sikompen_active_student_imports', 'uploaded_by_admin_id')) {
            $schema->table('sikompen_active_student_imports', function (Blueprint $table): void {
                $table->foreign('uploaded_by_admin_id', 'sikompen_active_imp_admin_fk')
                    ->references('id')
                    ->on('sikompen_admins')
                    ->nullOnDelete();
            });
        }

        if (! $hasForeignKey('sikompen_active_students', 'sikompen_active_student_import_id')) {
            $schema->table('sikompen_active_students', function (Blueprint $table): void {
                $table->foreign('sikompen_active_student_import_id', 'sikompen_active_student_import_fk')
                    ->references('id')
                    ->on('sikompen_active_student_imports')
                    ->cascadeOnDelete();
            });
        }

        if (! $hasForeignKey('sikompen_active_student_import_tasks', 'uploaded_by_admin_id')) {
            $schema->table('sikompen_active_student_import_tasks', function (Blueprint $table): void {
                $table->foreign('uploaded_by_admin_id', 'sikompen_active_task_admin_fk')
                    ->references('id')
                    ->on('sikompen_admins')
                    ->nullOnDelete();
            });
        }

        if (! $hasForeignKey('sikompen_active_student_import_tasks', 'sikompen_active_student_import_id')) {
            $schema->table('sikompen_active_student_import_tasks', function (Blueprint $table): void {
                $table->foreign('sikompen_active_student_import_id', 'sikompen_active_task_import_fk')
                    ->references('id')
                    ->on('sikompen_active_student_imports')
                    ->nullOnDelete();
            });
        }

        if (! $hasIndex('sikompen_active_student_imports', 'sikompen_active_student_import_date_idx')) {
            $schema->table('sikompen_active_student_imports', function (Blueprint $table): void {
                $table->index(['imported_at', 'id'], 'sikompen_active_student_import_date_idx');
            });
        }

        if (! $hasIndex('sikompen_active_students', 'sikompen_active_students_nim_unique')) {
            $schema->table('sikompen_active_students', function (Blueprint $table): void {
                $table->unique('nim', 'sikompen_active_students_nim_unique');
            });
        }

        if (! $hasIndex('sikompen_active_students', 'sikompen_active_students_filter_idx')) {
            $schema->table('sikompen_active_students', function (Blueprint $table): void {
                $table->index(['kelas', 'nama_mahasiswa', 'nim'], 'sikompen_active_students_filter_idx');
            });
        }

        if (! $hasIndex('sikompen_active_student_import_tasks', 'sikompen_active_student_task_status_idx')) {
            $schema->table('sikompen_active_student_import_tasks', function (Blueprint $table): void {
                $table->index(['status', 'id'], 'sikompen_active_student_task_status_idx');
            });
        }

        if (! $schema->hasColumn('sikompen_import_audit_logs', 'import_type')) {
            $schema->table('sikompen_import_audit_logs', function (Blueprint $table): void {
                $table->string('import_type', 30)
                    ->default('kompen_respon')
                    ->after('event_type');
            });
        }

        if (! $schema->hasColumn('sikompen_import_audit_logs', 'source_active_student_import_id')) {
            $schema->table('sikompen_import_audit_logs', function (Blueprint $table): void {
                $table->foreignId('source_active_student_import_id')
                    ->nullable()
                    ->after('source_import_id');
            });
        }

        if (! $hasForeignKey('sikompen_import_audit_logs', 'source_active_student_import_id')) {
            $schema->table('sikompen_import_audit_logs', function (Blueprint $table): void {
                $table->foreign('source_active_student_import_id', 'sikompen_audit_active_imp_fk')
                    ->references('id')
                    ->on('sikompen_active_student_imports')
                    ->nullOnDelete();
            });
        }

        $schema->table('sikompen_import_audit_logs', function (Blueprint $table): void {
            $table->string('periode_semester', 50)->nullable()->change();
        });

        if (! $hasIndex('sikompen_import_audit_logs', 'sikompen_audit_type_occurred_idx')) {
            $schema->table('sikompen_import_audit_logs', function (Blueprint $table): void {
                $table->index(['import_type', 'occurred_at', 'id'], 'sikompen_audit_type_occurred_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection(config('kompen-respon-hub.database_connection'));

        $schema->table('sikompen_import_audit_logs', function (Blueprint $table): void {
            $table->dropIndex('sikompen_audit_type_occurred_idx');
            $table->dropForeign('sikompen_audit_active_imp_fk');
            $table->dropColumn('source_active_student_import_id');
            $table->dropColumn('import_type');
            $table->string('periode_semester', 50)->nullable(false)->change();
        });

        $schema->dropIfExists('sikompen_active_student_import_tasks');
        $schema->dropIfExists('sikompen_active_students');
        $schema->dropIfExists('sikompen_active_student_imports');
    }
};
