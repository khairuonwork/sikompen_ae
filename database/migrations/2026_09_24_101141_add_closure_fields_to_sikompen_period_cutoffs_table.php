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
            $table->timestamp('closed_at')->nullable()->after('deadline_at');
            $table->unsignedBigInteger('closed_by_admin_id')->nullable()->after('updated_by_admin_id');
            $table->index(['closed_at', 'periode_semester'], 'sikompen_cutoff_closed_period_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('sikompen_periode_cutoffs', function (Blueprint $table): void {
            $table->dropIndex('sikompen_cutoff_closed_period_idx');
            $table->dropColumn(['closed_at', 'closed_by_admin_id']);
        });
    }
};
