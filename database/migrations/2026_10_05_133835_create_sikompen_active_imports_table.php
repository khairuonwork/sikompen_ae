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

        Schema::connection($connection)->create('sikompen_active_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('periode_semester', 50)->unique('sikompen_active_import_period_unique');
            $table->foreignId('kompen_respon_hub_import_id')
                ->constrained('sikompen_imports')
                ->cascadeOnDelete();
            $table->foreignId('activated_by_admin_id')
                ->nullable()
                ->constrained('sikompen_admins')
                ->nullOnDelete();
            $table->timestamp('activated_at');
            $table->timestamps();
        });

        $activeImports = DB::connection($connection)
            ->table('sikompen_mahasiswa')
            ->join('sikompen_imports', 'sikompen_imports.id', '=', 'sikompen_mahasiswa.kompen_respon_hub_import_id')
            ->select([
                'sikompen_mahasiswa.periode_semester',
                'sikompen_mahasiswa.kompen_respon_hub_import_id',
                'sikompen_imports.imported_at',
            ])
            ->orderBy('sikompen_mahasiswa.periode_semester')
            ->orderByDesc('sikompen_imports.imported_at')
            ->orderByDesc('sikompen_mahasiswa.kompen_respon_hub_import_id')
            ->get()
            ->groupBy('periode_semester')
            ->filter(fn ($imports): bool => $imports->pluck('kompen_respon_hub_import_id')->unique()->count() === 1)
            ->map(fn ($imports) => $imports->first());

        foreach ($activeImports as $activeImport) {
            DB::connection($connection)->table('sikompen_active_imports')->insert([
                'periode_semester' => $activeImport->periode_semester,
                'kompen_respon_hub_import_id' => $activeImport->kompen_respon_hub_import_id,
                'activated_at' => $activeImport->imported_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('kompen-respon-hub.database_connection'))
            ->dropIfExists('sikompen_active_imports');
    }
};
