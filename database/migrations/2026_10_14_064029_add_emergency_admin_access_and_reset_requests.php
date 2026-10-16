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
        $isSqlite = Schema::connection($connection)->getConnection()->getDriverName() === 'sqlite';

        Schema::connection($connection)->table('sikompen_admins', function (Blueprint $table): void {
            $table->boolean('is_emergency_admin')->default(false);
            $table->index('is_emergency_admin', 'sikompen_admins_emergency_idx');
        });

        Schema::connection($connection)->table('sikompen_admin_password_reset_codes', function (Blueprint $table) use ($isSqlite): void {
            if (! $isSqlite) {
                $table->dropForeign('skprc_recipient_fk');
            }

            $table->foreignId('recipient_id')->nullable()->change();

            if (! $isSqlite) {
                $table->foreign('recipient_id', 'skprc_recipient_null_fk')
                    ->references('id')
                    ->on('sikompen_admin_password_reset_recipients')
                    ->nullOnDelete();
            }
            $table->string('code_hash')->nullable()->change();
            $table->timestamp('expires_at')->nullable()->change();
            $table->foreignId('generated_by_admin_id')->nullable()->after('admin_id');
            $table->foreign('generated_by_admin_id', 'skprc_generated_by_fk')
                ->references('id')
                ->on('sikompen_admins')
                ->nullOnDelete();
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
        $isSqlite = Schema::connection($connection)->getConnection()->getDriverName() === 'sqlite';

        Schema::connection($connection)->table('sikompen_admin_password_reset_codes', function (Blueprint $table) use ($isSqlite): void {
            $table->dropIndex('sikompen_admin_reset_codes_pending_idx');

            if (! $isSqlite) {
                $table->dropForeign('skprc_generated_by_fk');
            }

            $table->dropColumn('generated_by_admin_id');
            $table->dropColumn('requested_at');
        });

        Schema::connection($connection)->table('sikompen_admins', function (Blueprint $table): void {
            $table->dropIndex('sikompen_admins_emergency_idx');
            $table->dropColumn('is_emergency_admin');
        });
    }
};
