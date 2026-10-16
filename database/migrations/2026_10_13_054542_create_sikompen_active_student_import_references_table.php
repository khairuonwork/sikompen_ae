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
        $schema = Schema::connection($connection);

        if (! $schema->hasTable('sikompen_active_student_import_references')) {
            $schema->create('sikompen_active_student_import_references', function (Blueprint $table): void {
                $table->id();
                $table->string('periode_semester', 20);
                $table->foreignId('sikompen_active_student_import_id');
                $table->foreignId('activated_by_admin_id')->nullable();
                $table->timestamp('activated_at');
                $table->timestamps();

                $table->unique('periode_semester', 'sikompen_active_student_reference_period_uq');
                $table->index('sikompen_active_student_import_id', 'sikompen_active_student_reference_import_idx');
            });

            $schema->table('sikompen_active_student_import_references', function (Blueprint $table): void {
                $table->foreign('sikompen_active_student_import_id', 'sikompen_active_student_reference_import_fk')
                    ->references('id')
                    ->on('sikompen_active_student_imports')
                    ->cascadeOnDelete();
                $table->foreign('activated_by_admin_id', 'sikompen_active_student_reference_admin_fk')
                    ->references('id')
                    ->on('sikompen_admins')
                    ->nullOnDelete();
            });
        }

        if (! $schema->hasTable('sikompen_active_student_import_sources')) {
            return;
        }

        $legacySources = DB::connection($connection)
            ->table('sikompen_active_student_import_sources')
            ->whereNotNull('periode_semester')
            ->where('periode_semester', '!=', '')
            ->orderBy('periode_semester')
            ->orderByDesc('activated_at')
            ->get([
                'periode_semester',
                'sikompen_active_student_import_id',
                'activated_by_admin_id',
                'activated_at',
            ])
            ->groupBy('periode_semester');

        foreach ($legacySources as $period => $sources) {
            $importIds = $sources
                ->pluck('sikompen_active_student_import_id')
                ->unique()
                ->values();

            if ($importIds->count() !== 1) {
                continue;
            }

            $latestSource = $sources->first();

            DB::connection($connection)
                ->table('sikompen_active_student_import_references')
                ->updateOrInsert(
                    ['periode_semester' => $period],
                    [
                        'sikompen_active_student_import_id' => $importIds->first(),
                        'activated_by_admin_id' => $latestSource->activated_by_admin_id,
                        'activated_at' => $latestSource->activated_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))
            ->dropIfExists('sikompen_active_student_import_references');
    }
};
