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
        Schema::connection(config('kompen-respon-hub.database_connection'))->create('sikompen_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 80);
            $table->string('subject_type', 80);
            $table->string('subject_reference', 100)->nullable();
            $table->string('nim', 20)->nullable();
            $table->string('periode_semester', 50)->nullable();
            $table->string('kelas', 20)->nullable();
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_admin_id')->nullable();
            $table->string('actor_name', 100)->nullable();
            $table->string('actor_email', 255)->nullable();
            $table->text('reason')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->json('metadata')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['periode_semester', 'occurred_at'], 'sikompen_activity_period_occurred_idx');
            $table->index(['event_type', 'occurred_at'], 'sikompen_activity_event_occurred_idx');
            $table->index(['nim', 'periode_semester', 'kelas'], 'sikompen_activity_identity_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->dropIfExists('sikompen_activity_logs');
    }
};
