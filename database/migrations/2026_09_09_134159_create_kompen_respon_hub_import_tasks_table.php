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
        Schema::connection(config('kompen-respon-hub.database_connection'))
            ->create('kompen_respon_hub_import_tasks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('uploaded_by_admin_id')
                    ->nullable()
                    ->constrained('kompen_respon_hub_admins', 'id', 'kompen_hub_tasks_admin_id_fk')
                    ->nullOnDelete();
                $table->string('uploader_name', 100);
                $table->string('uploader_email');
                $table->string('original_filename');
                $table->string('stored_path');
                $table->string('file_hash', 64);
                $table->string('status', 20)->default('queued');
                $table->unsignedTinyInteger('progress')->default(0);
                $table->string('progress_message')->default('Menunggu diproses.');
                $table->text('error_message')->nullable();
                $table->foreignId('kompen_respon_hub_import_id')
                    ->nullable()
                    ->constrained('kompen_respon_hub_imports', 'id', 'kompen_hub_tasks_import_id_fk')
                    ->nullOnDelete();
                $table->timestamp('queued_at');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at'], 'kompen_hub_tasks_status_created_idx');
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))
            ->dropIfExists('kompen_respon_hub_import_tasks');
    }
};
