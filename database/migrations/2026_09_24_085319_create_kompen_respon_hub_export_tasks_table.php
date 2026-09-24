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
        Schema::connection(config('kompen-respon-hub.database_connection'))->create('sikompen_export_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('request_session_id', 100);
            $table->string('access_token', 64)->unique();
            $table->unsignedBigInteger('requested_by_admin_id')->nullable();
            $table->string('resource', 20);
            $table->string('format', 10);
            $table->json('filters');
            $table->string('status', 20)->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('progress_message', 255);
            $table->string('output_path')->nullable();
            $table->string('download_filename')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['request_session_id', 'created_at'], 'sikompen_export_session_created_idx');
            $table->index(['status', 'created_at'], 'sikompen_export_status_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->dropIfExists('sikompen_export_tasks');
    }
};
