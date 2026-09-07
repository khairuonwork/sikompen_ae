<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('kompen-respon-hub.database_connection');

        Schema::connection($connection)->create('kompen_respon_hub_sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        $connection = config('kompen-respon-hub.database_connection');

        Schema::connection($connection)->dropIfExists('kompen_respon_hub_sessions');
    }
};
