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
        Schema::connection(config('kompen-respon-hub.database_connection'))->create('sikompen_mahasiswa_progress', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('current_student_id')->nullable();
            $table->string('nim', 20);
            $table->string('periode_semester', 50);
            $table->string('kelas', 20);
            $table->decimal('kompensasi_dikerjakan_jam', 12, 4)->default(0);
            $table->decimal('responsi_dikerjakan_jam', 12, 4)->default(0);
            $table->timestamp('last_worked_at')->nullable();
            $table->text('reason');
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique(['nim', 'periode_semester', 'kelas'], 'sikompen_progress_identity_unique');
            $table->index(['current_student_id'], 'sikompen_progress_student_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->dropIfExists('sikompen_mahasiswa_progress');
    }
};
