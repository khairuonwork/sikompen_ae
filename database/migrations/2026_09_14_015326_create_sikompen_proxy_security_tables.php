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
        $schema = Schema::connection(config('kompen-respon-hub.database_connection'));

        $schema->create('sikompen_cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        $schema->create('sikompen_cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });

        $schema->create('sikompen_proxy_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('si_admin_user_id', 64);
            $table->string('email');
            $table->string('role', 20);
            $table->char('nonce_hash', 64)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('accessed_at');
            $table->timestamps();

            $table->index(['si_admin_user_id', 'accessed_at'], 'sikompen_proxy_user_accessed_idx');
            $table->index(['role', 'accessed_at'], 'sikompen_proxy_role_accessed_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection(config('kompen-respon-hub.database_connection'));

        $schema->dropIfExists('sikompen_proxy_access_logs');
        $schema->dropIfExists('sikompen_cache_locks');
        $schema->dropIfExists('sikompen_cache');
    }
};
