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
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('sikompen_detail_kompen', function (Blueprint $table): void {
            $table->string('source_key', 64)->nullable()->after('kompen_respon_hub_student_id');
            $table->index(['source_key'], 'sikompen_detail_source_key_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('sikompen_detail_kompen', function (Blueprint $table): void {
            $table->dropIndex('sikompen_detail_source_key_idx');
            $table->dropColumn('source_key');
        });
    }
};
