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
        Schema::connection(config('kompen-respon-hub.database_connection'))->create('sikompen_warning_period_selections', function (Blueprint $table): void {
            $table->id();
            $table->string('selection_key', 32);
            $table->foreignId('sikompen_period_cutoff_id');
            $table->foreignId('selected_by_admin_id')->nullable();
            $table->timestamp('selected_at');
            $table->timestamps();

            $table->unique('selection_key', 'sikompen_warning_period_selection_key_uq');
            $table->index('sikompen_period_cutoff_id', 'sikompen_warning_period_cutoff_idx');
            $table->foreign('sikompen_period_cutoff_id', 'sikompen_warning_period_cutoff_fk')
                ->references('id')
                ->on('sikompen_periode_cutoffs')
                ->cascadeOnDelete();
            $table->foreign('selected_by_admin_id', 'sikompen_warning_period_admin_fk')
                ->references('id')
                ->on('sikompen_admins')
                ->nullOnDelete();
        });

        $connection = config('kompen-respon-hub.database_connection');
        $latestCutoff = DB::connection($connection)
            ->table('sikompen_periode_cutoffs')
            ->orderByDesc('deadline_at')
            ->orderByDesc('id')
            ->first(['id']);

        if ($latestCutoff !== null) {
            DB::connection($connection)
                ->table('sikompen_warning_period_selections')
                ->insert([
                    'selection_key' => 'warning-period',
                    'sikompen_period_cutoff_id' => $latestCutoff->id,
                    'selected_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))
            ->dropIfExists('sikompen_warning_period_selections');
    }
};
