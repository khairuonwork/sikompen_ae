<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))
            ->dropIfExists('sikompen_proxy_access_logs');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This retired access-log table is intentionally not recreated on rollback.
    }
};
