<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = config('kompen-respon-hub.database_connection');

        Schema::connection($connection)
            ->create('kompen_respon_hub_import_audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('event_type', 20);
                $table->unsignedBigInteger('source_import_id')->nullable();
                $table->string('actor_name', 100)->nullable();
                $table->string('actor_email')->nullable();
                $table->string('periode_semester', 50);
                $table->string('original_filename');
                $table->unsignedInteger('class_count');
                $table->unsignedInteger('student_count');
                $table->unsignedInteger('detail_count');
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->index(['event_type', 'occurred_at'], 'kompen_hub_audit_event_time_idx');
            });

        DB::connection($connection)
            ->table('kompen_respon_hub_imports')
            ->orderBy('id')
            ->eachById(function (object $import) use ($connection): void {
                DB::connection($connection)
                    ->table('kompen_respon_hub_import_audit_logs')
                    ->insert([
                        'event_type' => 'upload',
                        'source_import_id' => $import->id,
                        'actor_name' => $import->uploader_name,
                        'actor_email' => $import->uploader_email,
                        'periode_semester' => $import->periode_semester,
                        'original_filename' => $import->original_filename,
                        'class_count' => $import->class_count,
                        'student_count' => $import->student_count,
                        'detail_count' => $import->detail_count,
                        'occurred_at' => $import->imported_at,
                        'created_at' => $import->created_at,
                        'updated_at' => $import->updated_at,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))
            ->dropIfExists('kompen_respon_hub_import_audit_logs');
    }
};
