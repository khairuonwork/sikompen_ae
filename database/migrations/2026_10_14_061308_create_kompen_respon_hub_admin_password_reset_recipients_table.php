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
        Schema::connection(config('kompen-respon-hub.database_connection'))->create('sikompen_admin_password_reset_recipients', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 255)->unique();
            $table->foreignId('added_by_admin_id')->nullable();
            $table->foreign('added_by_admin_id', 'skprr_added_by_admin_fk')
                ->references('id')
                ->on('sikompen_admins')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->dropIfExists('sikompen_admin_password_reset_recipients');
    }
};
