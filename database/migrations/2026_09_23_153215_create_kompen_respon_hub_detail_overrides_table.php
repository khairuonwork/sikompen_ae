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
        Schema::connection(config('kompen-respon-hub.database_connection'))->create('sikompen_detail_kompen_overrides', function (Blueprint $table): void {
            $table->id();
            $table->string('source_key', 64)->unique();
            $table->json('override_values');
            $table->text('reason');
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))->dropIfExists('sikompen_detail_kompen_overrides');
    }
};
