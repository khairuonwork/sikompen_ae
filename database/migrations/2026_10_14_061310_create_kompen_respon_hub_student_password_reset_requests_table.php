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
        Schema::connection(config('kompen-respon-hub.database_connection'))->create('sikompen_student_password_reset_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('nim', 20)->unique();
            $table->text('reset_code');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('requested_at');
            $table->timestamp('expires_at');
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->index(['fulfilled_at', 'expires_at'], 'sikompen_student_reset_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->dropIfExists('sikompen_student_password_reset_requests');
    }
};
