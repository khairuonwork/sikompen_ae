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

        Schema::connection($connection)->table('kompen_respon_hub_api_sync_runs', function (Blueprint $table): void {
            $table->string('api_client_key', 80)->nullable()->after('api_client_id');
            $table->timestamp('snapshot_at')->nullable()->after('started_at');
            $table->index(['api_client_key', 'status', 'expires_at'], 'krh_api_runs_key_status_exp_idx');
        });

        $database = DB::connection($connection);
        $clientKeys = $database->table('kompen_respon_hub_api_clients')->pluck('client_id', 'id');

        $database->table('kompen_respon_hub_api_sync_runs')
            ->orderBy('id')
            ->eachById(function (object $run) use ($database, $clientKeys): void {
                $clientKey = $clientKeys->get($run->api_client_id);

                if (is_string($clientKey)) {
                    $database->table('kompen_respon_hub_api_sync_runs')
                        ->where('id', $run->id)
                        ->update(['api_client_key' => $clientKey]);
                }
            });

        $database->table('kompen_respon_hub_api_clients')->updateOrInsert(
            ['client_id' => 'configured-runtime'],
            [
                'name' => 'Configured API clients runtime owner',
                'token_hash' => hash('sha256', 'configured-runtime-owner'),
                'scopes' => json_encode([], JSON_THROW_ON_ERROR),
                'allowed_ips' => json_encode([], JSON_THROW_ON_ERROR),
                'rate_limit_per_minute' => 1,
                'is_active' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->table('kompen_respon_hub_api_sync_runs', function (Blueprint $table): void {
            $table->dropIndex('krh_api_runs_key_status_exp_idx');
            $table->dropColumn(['api_client_key', 'snapshot_at']);
        });
    }
};
