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
        Schema::connection(config('kompen-respon-hub.database_connection'))->create('sikompen_surat_peringatan_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cutoff_id')->unique();
            $table->string('status', 24)->default('finalized');
            $table->unsignedInteger('revision')->default(1);
            $table->string('display_filename', 180);
            $table->string('stored_path', 255);
            $table->string('file_hash', 64);
            $table->unsignedInteger('row_count');
            $table->timestamp('finalized_at');
            $table->unsignedBigInteger('finalized_by_admin_id')->nullable();
            $table->timestamp('correction_started_at')->nullable();
            $table->unsignedBigInteger('correction_started_by_admin_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at'], 'sikompen_sp_artifact_status_updated_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->dropIfExists('sikompen_surat_peringatan_artifacts');
    }
};
