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
        Schema::connection(config('kompen-respon-hub.database_connection'))->create('kompen_respon_hub_api_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('client_id', 80)->unique();
            $table->string('name', 120);
            $table->string('token_hash', 64)->unique();
            $table->json('scopes');
            $table->json('allowed_ips');
            $table->unsignedSmallInteger('rate_limit_per_minute')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->dropIfExists('kompen_respon_hub_api_clients');
    }
};
