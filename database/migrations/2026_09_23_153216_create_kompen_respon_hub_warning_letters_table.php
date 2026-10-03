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
        Schema::connection(config('kompen-respon-hub.database_connection'))->create('sikompen_surat_peringatan', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cutoff_id')->nullable();
            $table->unsignedBigInteger('current_student_id')->nullable();
            $table->string('nim', 20);
            $table->string('periode_semester', 50);
            $table->string('kelas', 20);
            $table->string('nama_mahasiswa', 100);
            $table->string('classification', 20)->default('fixed');
            $table->string('letter_status', 20)->default('not_created');
            $table->string('resolution', 20)->default('outstanding');
            $table->json('snapshot');
            $table->text('reason')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique(['cutoff_id', 'current_student_id'], 'sikompen_sp_cutoff_student_unique');
            $table->index(['periode_semester', 'classification', 'letter_status'], 'sikompen_sp_period_status_idx');
            $table->index(['nim', 'periode_semester', 'kelas'], 'sikompen_sp_identity_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->dropIfExists('sikompen_surat_peringatan');
    }
};
