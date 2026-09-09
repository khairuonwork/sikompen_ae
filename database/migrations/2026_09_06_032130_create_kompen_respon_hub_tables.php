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
        $connection = config('kompen-respon-hub.database_connection');

        Schema::connection($connection)->create('kompen_respon_hub_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('periode_semester', 50);
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('file_hash', 64);
            $table->unsignedInteger('class_count');
            $table->unsignedInteger('student_count');
            $table->unsignedInteger('detail_count');
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->index(['periode_semester', 'imported_at']);
        });

        Schema::connection($connection)->create('kompen_respon_hub_students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kompen_respon_hub_import_id')
                ->constrained('kompen_respon_hub_imports')
                ->cascadeOnDelete();
            $table->string('nim', 20);
            $table->string('periode_semester', 50);
            $table->string('nama_mahasiswa', 100);
            $table->string('kelas', 20);
            $table->unsignedTinyInteger('tingkat');
            $table->decimal('total_jam_terlambat', 12, 4)->default(0);
            $table->decimal('total_jam_sakit', 12, 4)->default(0);
            $table->decimal('total_jam_izin', 12, 4)->default(0);
            $table->decimal('total_jam_bolos', 12, 4)->default(0);
            $table->decimal('total_kompensasi_jam', 12, 4)->default(0);
            $table->decimal('total_responsi_jam', 12, 4)->default(0);
            $table->decimal('total_hutang_jam', 12, 4)->default(0);
            $table->decimal('kompensasi_dikerjakan_jam', 12, 4)->default(0);
            $table->decimal('sisa_hutang_jam', 12, 4)->default(0);
            $table->timestamps();

            $table->unique(['nim', 'periode_semester', 'kelas']);
            $table->index(
                ['periode_semester', 'tingkat', 'kelas', 'nama_mahasiswa', 'nim'],
                'kompen_hub_students_filter_idx',
            );
        });

        Schema::connection($connection)->create('kompen_respon_hub_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kompen_respon_hub_student_id')
                ->constrained('kompen_respon_hub_students')
                ->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('mata_kuliah', 100);
            $table->string('nama_dosen', 100);
            $table->string('jenis_pertemuan', 20);
            $table->string('presensi', 20);
            $table->unsignedInteger('menit_keterlambatan')->default(0);
            $table->text('keterangan')->nullable();
            $table->decimal('jam_kompensasi', 12, 4)->default(0);
            $table->decimal('jam_responsi', 12, 4)->default(0);
            $table->timestamps();

            $table->index(
                ['kompen_respon_hub_student_id', 'tanggal'],
                'kompen_hub_details_student_date_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('kompen-respon-hub.database_connection');

        Schema::connection($connection)->dropIfExists('kompen_respon_hub_details');
        Schema::connection($connection)->dropIfExists('kompen_respon_hub_students');
        Schema::connection($connection)->dropIfExists('kompen_respon_hub_imports');
    }
};
