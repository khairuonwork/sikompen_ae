<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('kompen-respon-hub.database_connection');

        Schema::connection($connection)->table('sikompen_admins', function (Blueprint $table): void {
            $table->renameColumn('email', 'username');
        });

        Schema::connection($connection)->table('sikompen_imports', function (Blueprint $table): void {
            $table->renameColumn('uploader_email', 'uploader_username');
        });

        Schema::connection($connection)->table('sikompen_import_tasks', function (Blueprint $table): void {
            $table->renameColumn('uploader_email', 'uploader_username');
        });

        Schema::connection($connection)->table('sikompen_active_student_imports', function (Blueprint $table): void {
            $table->renameColumn('uploader_email', 'uploader_username');
        });

        Schema::connection($connection)->table('sikompen_active_student_import_tasks', function (Blueprint $table): void {
            $table->renameColumn('uploader_email', 'uploader_username');
        });

        Schema::connection($connection)->table('sikompen_import_audit_logs', function (Blueprint $table): void {
            $table->renameColumn('actor_email', 'actor_username');
        });

        Schema::connection($connection)->table('sikompen_activity_logs', function (Blueprint $table): void {
            $table->renameColumn('actor_email', 'actor_username');
        });
    }

    public function down(): void
    {
        $connection = config('kompen-respon-hub.database_connection');

        Schema::connection($connection)->table('sikompen_activity_logs', function (Blueprint $table): void {
            $table->renameColumn('actor_username', 'actor_email');
        });

        Schema::connection($connection)->table('sikompen_import_audit_logs', function (Blueprint $table): void {
            $table->renameColumn('actor_username', 'actor_email');
        });

        Schema::connection($connection)->table('sikompen_active_student_import_tasks', function (Blueprint $table): void {
            $table->renameColumn('uploader_username', 'uploader_email');
        });

        Schema::connection($connection)->table('sikompen_active_student_imports', function (Blueprint $table): void {
            $table->renameColumn('uploader_username', 'uploader_email');
        });

        Schema::connection($connection)->table('sikompen_import_tasks', function (Blueprint $table): void {
            $table->renameColumn('uploader_username', 'uploader_email');
        });

        Schema::connection($connection)->table('sikompen_imports', function (Blueprint $table): void {
            $table->renameColumn('uploader_username', 'uploader_email');
        });

        Schema::connection($connection)->table('sikompen_admins', function (Blueprint $table): void {
            $table->renameColumn('username', 'email');
        });
    }
};
