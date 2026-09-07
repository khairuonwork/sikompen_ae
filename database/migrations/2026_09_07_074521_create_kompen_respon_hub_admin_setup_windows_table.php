<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('kompen-respon-hub.database_connection');

        Schema::connection($connection)->create('kompen_respon_hub_admin_setup_windows', function (Blueprint $table): void {
            $table->id();
            $table->string('activation_code_hash')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('opened_by_admin_id')
                ->nullable()
                ->constrained('kompen_respon_hub_admins')
                ->nullOnDelete();
            $table->timestamps();
        });

        DB::connection($connection)->table('kompen_respon_hub_admin_setup_windows')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))
            ->dropIfExists('kompen_respon_hub_admin_setup_windows');
    }
};
