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

        Schema::connection($connection)->table('sikompen_imports', function (Blueprint $table): void {
            $table->string('display_filename')->nullable()->after('original_filename');
        });

        DB::connection($connection)
            ->table('sikompen_imports')
            ->whereNull('display_filename')
            ->update(['display_filename' => DB::raw('original_filename')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))
            ->table('sikompen_imports', function (Blueprint $table): void {
                $table->dropColumn('display_filename');
            });
    }
};
