<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))
            ->table('kompen_respon_hub_imports', function (Blueprint $table): void {
                $table->foreignId('uploaded_by_admin_id')
                    ->nullable()
                    ->constrained('kompen_respon_hub_admins')
                    ->nullOnDelete();
                $table->string('uploader_name', 100)->nullable();
                $table->string('uploader_email')->nullable();
                $table->index(['imported_at', 'id'], 'kompen_hub_imports_latest_idx');
            });
    }

    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))
            ->table('kompen_respon_hub_imports', function (Blueprint $table): void {
                $table->dropIndex('kompen_hub_imports_latest_idx');
                $table->dropForeign(['uploaded_by_admin_id']);
                $table->dropColumn([
                    'uploaded_by_admin_id',
                    'uploader_name',
                    'uploader_email',
                ]);
            });
    }
};
