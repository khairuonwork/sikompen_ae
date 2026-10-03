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
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('sikompen_imports', function (Blueprint $table): void {
            $table->json('quality_report')->nullable()->after('detail_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('sikompen_imports', function (Blueprint $table): void {
            $table->dropColumn('quality_report');
        });
    }
};
