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
        $connection = config('kompen-respon-hub.database_connection');

        Schema::connection($connection)->create('sikompen_active_student_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('uploaded_by_admin_id')
                ->nullable()
                ->constrained('sikompen_admins')
                ->nullOnDelete();
            $table->string('uploader_name', 100);
            $table->string('uploader_email')->nullable();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('file_hash', 64);
            $table->unsignedInteger('student_count');
            $table->unsignedInteger('class_count');
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->index(['imported_at', 'id'], 'sikompen_active_student_import_date_idx');
        });

        Schema::connection($connection)->create('sikompen_active_students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sikompen_active_student_import_id')
                ->constrained('sikompen_active_student_imports')
                ->cascadeOnDelete();
            $table->string('nim', 20);
            $table->string('nama_mahasiswa', 100);
            $table->string('kelas', 20);
            $table->timestamps();

            $table->unique('nim', 'sikompen_active_students_nim_unique');
            $table->index(['kelas', 'nama_mahasiswa', 'nim'], 'sikompen_active_students_filter_idx');
        });

        Schema::connection($connection)->create('sikompen_active_student_import_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('uploaded_by_admin_id')
                ->nullable()
                ->constrained('sikompen_admins')
                ->nullOnDelete();
            $table->string('uploader_name', 100);
            $table->string('uploader_email')->nullable();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('file_hash', 64);
            $table->string('status', 20);
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('progress_message');
            $table->text('error_message')->nullable();
            $table->foreignId('sikompen_active_student_import_id')
                ->nullable()
                ->constrained('sikompen_active_student_imports')
                ->nullOnDelete();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id'], 'sikompen_active_student_task_status_idx');
        });

        Schema::connection($connection)->table('sikompen_import_audit_logs', function (Blueprint $table): void {
            $table->string('import_type', 30)
                ->default('kompen_respon')
                ->after('event_type');
            $table->foreignId('source_active_student_import_id')
                ->nullable()
                ->after('source_import_id')
                ->constrained('sikompen_active_student_imports')
                ->nullOnDelete();
            $table->string('periode_semester', 50)->nullable()->change();
            $table->index(['import_type', 'occurred_at', 'id'], 'sikompen_audit_type_occurred_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('kompen-respon-hub.database_connection');

        Schema::connection($connection)->table('sikompen_import_audit_logs', function (Blueprint $table): void {
            $table->dropIndex('sikompen_audit_type_occurred_idx');
            $table->dropConstrainedForeignId('source_active_student_import_id');
            $table->dropColumn('import_type');
            $table->string('periode_semester', 50)->nullable(false)->change();
        });

        Schema::connection($connection)->dropIfExists('sikompen_active_student_import_tasks');
        Schema::connection($connection)->dropIfExists('sikompen_active_students');
        Schema::connection($connection)->dropIfExists('sikompen_active_student_imports');
    }
};
