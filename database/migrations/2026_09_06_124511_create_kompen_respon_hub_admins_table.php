<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('kompen-respon-hub.database_connection');

        Schema::connection($connection)->create('kompen_respon_hub_admins', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $connection = config('kompen-respon-hub.database_connection');

        Schema::connection($connection)->dropIfExists('kompen_respon_hub_admins');
    }
};
