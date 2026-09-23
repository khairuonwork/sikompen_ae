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
        Schema::connection(config('kompen-respon-hub.database_connection'))->create('sikompen_mahasiswa_summary_overrides', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('current_student_id')->nullable();
            $table->string('nim', 20);
            $table->string('periode_semester', 50);
            $table->string('kelas', 20);
            $table->decimal('total_kompensasi_jam', 12, 4);
            $table->decimal('total_responsi_jam', 12, 4);
            $table->text('reason');
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique(['nim', 'periode_semester', 'kelas'], 'sikompen_summary_override_identity_unique');
            $table->index(['current_student_id'], 'sikompen_summary_override_student_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->dropIfExists('sikompen_mahasiswa_summary_overrides');
    }
};
