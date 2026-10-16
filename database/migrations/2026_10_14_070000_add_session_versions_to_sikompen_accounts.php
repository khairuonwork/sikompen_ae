<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('kompen-respon-hub.database_connection');

        Schema::connection($connection)->table('sikompen_admins', function (Blueprint $table): void {
            $table->unsignedInteger('session_version')->default(1)->after('password');
        });

        Schema::connection($connection)->table('sikompen_student_accounts', function (Blueprint $table): void {
            $table->unsignedInteger('session_version')->default(1)->after('password');
        });
    }

    public function down(): void
    {
        $connection = config('kompen-respon-hub.database_connection');

        Schema::connection($connection)->table('sikompen_admins', function (Blueprint $table): void {
            $table->dropColumn('session_version');
        });

        Schema::connection($connection)->table('sikompen_student_accounts', function (Blueprint $table): void {
            $table->dropColumn('session_version');
        });
    }
};
