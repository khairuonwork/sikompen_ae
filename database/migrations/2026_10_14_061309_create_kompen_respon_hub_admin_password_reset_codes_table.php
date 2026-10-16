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
        Schema::connection(config('kompen-respon-hub.database_connection'))->create('sikompen_admin_password_reset_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id');
            $table->foreignId('recipient_id');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'expires_at'], 'sikompen_admin_reset_codes_admin_expiry_idx');
            $table->foreign('admin_id', 'skprc_admin_fk')
                ->references('id')
                ->on('sikompen_admins')
                ->cascadeOnDelete();
            $table->foreign('recipient_id', 'skprc_recipient_fk')
                ->references('id')
                ->on('sikompen_admin_password_reset_recipients')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->dropIfExists('sikompen_admin_password_reset_codes');
    }
};
