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
        Schema::connection(config('kompen-respon-hub.database_connection'))->create('sikompen_periode_cutoffs', function (Blueprint $table): void {
            $table->id();
            $table->string('periode_semester', 50)->unique();
            $table->timestamp('deadline_at');
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();

            $table->index(['deadline_at', 'periode_semester'], 'sikompen_cutoff_deadline_period_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->dropIfExists('sikompen_periode_cutoffs');
    }
};
