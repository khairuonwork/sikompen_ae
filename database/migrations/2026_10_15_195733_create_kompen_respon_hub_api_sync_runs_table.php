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
        Schema::connection(config('kompen-respon-hub.database_connection'))->create('kompen_respon_hub_api_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_client_id')->constrained('kompen_respon_hub_api_clients')->cascadeOnDelete();
            $table->ulid('run_id')->unique();
            $table->string('idempotency_key', 128);
            $table->enum('selector_type', ['kelas', 'periode']);
            $table->string('periode_semester', 32);
            $table->string('kelas', 32)->nullable();
            $table->enum('status', ['queued', 'processing', 'ready', 'failed', 'expired'])->default('queued');
            $table->string('artifact_path')->nullable();
            $table->string('artifact_checksum', 64)->nullable();
            $table->unsignedInteger('record_count')->nullable();
            $table->unsignedBigInteger('artifact_size_bytes')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['api_client_id', 'idempotency_key']);
            $table->index(['status', 'expires_at']);
            $table->index(['periode_semester', 'kelas']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->dropIfExists('kompen_respon_hub_api_sync_runs');
    }
};
