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
        $connection = config('kompen-respon-hub.database_connection');

        Schema::connection($connection)->table('sikompen_admins', function (Blueprint $table): void {
            $table->boolean('is_emergency_admin')->default(false)->after('session_version');
            $table->index('is_emergency_admin', 'sikompen_admins_emergency_idx');
        });

        Schema::connection($connection)->table('sikompen_admin_password_reset_codes', function (Blueprint $table): void {
            $table->dropForeign(['recipient_id']);
            $table->foreignId('recipient_id')->nullable()->change();
            $table->foreign('recipient_id')->references('id')->on('sikompen_admin_password_reset_recipients')->nullOnDelete();
            $table->string('code_hash')->nullable()->change();
            $table->timestamp('expires_at')->nullable()->change();
            $table->foreignId('generated_by_admin_id')->nullable()->after('admin_id')->constrained('sikompen_admins')->nullOnDelete();
            $table->timestamp('requested_at')->nullable()->after('attempts');
            $table->index(['used_at', 'requested_at'], 'sikompen_admin_reset_codes_pending_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('kompen-respon-hub.database_connection');

        Schema::connection($connection)->table('sikompen_admin_password_reset_codes', function (Blueprint $table): void {
            $table->dropIndex('sikompen_admin_reset_codes_pending_idx');
            $table->dropConstrainedForeignId('generated_by_admin_id');
            $table->dropColumn('requested_at');
        });

        Schema::connection($connection)->table('sikompen_admins', function (Blueprint $table): void {
            $table->dropIndex('sikompen_admins_emergency_idx');
            $table->dropColumn('is_emergency_admin');
        });
    }
};
