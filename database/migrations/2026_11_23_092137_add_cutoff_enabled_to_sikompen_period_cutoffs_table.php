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
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('sikompen_periode_cutoffs', function (Blueprint $table): void {
            // Existing cutoff rows were created before this flag existed and already
            // represent an explicit cutoff. New management-only rows are created by
            // the application with cutoff_enabled set to false.
            $table->boolean('cutoff_enabled')->default(true)->after('deadline_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('sikompen_periode_cutoffs', function (Blueprint $table): void {
            $table->dropColumn('cutoff_enabled');
        });
    }
};
