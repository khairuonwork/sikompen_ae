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
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('kompen_respon_hub_api_sync_runs', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'krh_api_runs_status_created_idx');
            $table->index(['status', 'started_at'], 'krh_api_runs_status_started_idx');
            $table->index(['status', 'completed_at'], 'krh_api_runs_status_completed_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('kompen_respon_hub_api_sync_runs', function (Blueprint $table): void {
            $table->dropIndex('krh_api_runs_status_created_idx');
            $table->dropIndex('krh_api_runs_status_started_idx');
            $table->dropIndex('krh_api_runs_status_completed_idx');
        });
    }
};
