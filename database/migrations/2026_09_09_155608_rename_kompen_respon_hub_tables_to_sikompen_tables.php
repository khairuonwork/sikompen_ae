<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private const TABLE_RENAMES = [
        'kompen_respon_hub_admins' => 'sikompen_admins',
        'kompen_respon_hub_admin_setup_windows' => 'sikompen_admin_setup_windows',
        'kompen_respon_hub_imports' => 'sikompen_imports',
        'kompen_respon_hub_students' => 'sikompen_mahasiswa',
        'kompen_respon_hub_details' => 'sikompen_detail_kompen',
        'kompen_respon_hub_import_tasks' => 'sikompen_import_tasks',
        'kompen_respon_hub_import_audit_logs' => 'sikompen_import_audit_logs',
        'kompen_respon_hub_sessions' => 'sikompen_sessions',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $schema = Schema::connection(config('kompen-respon-hub.database_connection'));

        if (! $this->usesSqlite($schema)) {
            $this->dropForeignKeys($schema, false);
        }

        $this->renameTables($schema, self::TABLE_RENAMES);

        if (! $this->usesSqlite($schema)) {
            $this->createForeignKeys($schema, true);
        }

        $schema->table('sikompen_detail_kompen', function (Blueprint $table): void {
            $table->index(['tanggal', 'id'], 'sikompen_detail_date_id_idx');
        });

        $schema->table('sikompen_import_audit_logs', function (Blueprint $table): void {
            $table->index(['occurred_at', 'id'], 'sikompen_audit_occurred_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection(config('kompen-respon-hub.database_connection'));

        $schema->table('sikompen_detail_kompen', function (Blueprint $table): void {
            $table->dropIndex('sikompen_detail_date_id_idx');
        });

        $schema->table('sikompen_import_audit_logs', function (Blueprint $table): void {
            $table->dropIndex('sikompen_audit_occurred_id_idx');
        });

        if (! $this->usesSqlite($schema)) {
            $this->dropForeignKeys($schema, true);
        }

        $this->renameTables($schema, array_flip(self::TABLE_RENAMES));

        if (! $this->usesSqlite($schema)) {
            $this->createForeignKeys($schema, false);
        }
    }

    /** @param array<string, string> $tableRenames */
    private function renameTables(Builder $schema, array $tableRenames): void
    {
        foreach ($tableRenames as $from => $to) {
            if ($schema->hasTable($from) && ! $schema->hasTable($to)) {
                $schema->rename($from, $to);
            }
        }
    }

    private function usesSqlite(Builder $schema): bool
    {
        return $schema->getConnection()->getDriverName() === 'sqlite';
    }

    private function dropForeignKeys(Builder $schema, bool $usesSikompenTables): void
    {
        $tables = $usesSikompenTables
            ? [
                'imports' => 'sikompen_imports',
                'mahasiswa' => 'sikompen_mahasiswa',
                'detail' => 'sikompen_detail_kompen',
                'setup' => 'sikompen_admin_setup_windows',
                'tasks' => 'sikompen_import_tasks',
            ]
            : [
                'imports' => 'kompen_respon_hub_imports',
                'mahasiswa' => 'kompen_respon_hub_students',
                'detail' => 'kompen_respon_hub_details',
                'setup' => 'kompen_respon_hub_admin_setup_windows',
                'tasks' => 'kompen_respon_hub_import_tasks',
            ];

        $schema->table($tables['imports'], function (Blueprint $table) use ($usesSikompenTables): void {
            $table->dropForeign($usesSikompenTables ? 'sikompen_import_admin_fk' : ['uploaded_by_admin_id']);
        });

        $schema->table($tables['mahasiswa'], function (Blueprint $table) use ($usesSikompenTables): void {
            $table->dropForeign($usesSikompenTables ? 'sikompen_mahasiswa_import_fk' : ['kompen_respon_hub_import_id']);
        });

        $schema->table($tables['detail'], function (Blueprint $table) use ($usesSikompenTables): void {
            $table->dropForeign($usesSikompenTables ? 'sikompen_detail_mahasiswa_fk' : ['kompen_respon_hub_student_id']);
        });

        $schema->table($tables['setup'], function (Blueprint $table) use ($usesSikompenTables): void {
            $table->dropForeign($usesSikompenTables ? 'sikompen_setup_admin_fk' : ['opened_by_admin_id']);
        });

        $schema->table($tables['tasks'], function (Blueprint $table) use ($usesSikompenTables): void {
            $table->dropForeign($usesSikompenTables ? 'sikompen_tasks_admin_fk' : 'kompen_hub_tasks_admin_id_fk');
            $table->dropForeign($usesSikompenTables ? 'sikompen_tasks_import_fk' : 'kompen_hub_tasks_import_id_fk');
        });
    }

    private function createForeignKeys(Builder $schema, bool $usesSikompenTables): void
    {
        $tables = $usesSikompenTables
            ? [
                'admins' => 'sikompen_admins',
                'imports' => 'sikompen_imports',
                'mahasiswa' => 'sikompen_mahasiswa',
                'detail' => 'sikompen_detail_kompen',
                'setup' => 'sikompen_admin_setup_windows',
                'tasks' => 'sikompen_import_tasks',
            ]
            : [
                'admins' => 'kompen_respon_hub_admins',
                'imports' => 'kompen_respon_hub_imports',
                'mahasiswa' => 'kompen_respon_hub_students',
                'detail' => 'kompen_respon_hub_details',
                'setup' => 'kompen_respon_hub_admin_setup_windows',
                'tasks' => 'kompen_respon_hub_import_tasks',
            ];
        $prefix = $usesSikompenTables ? 'sikompen' : 'kompen_hub';

        $schema->table($tables['imports'], function (Blueprint $table) use ($tables, $prefix): void {
            $table->foreign('uploaded_by_admin_id', "{$prefix}_import_admin_fk")
                ->references('id')
                ->on($tables['admins'])
                ->nullOnDelete();
        });

        $schema->table($tables['mahasiswa'], function (Blueprint $table) use ($tables, $prefix): void {
            $table->foreign('kompen_respon_hub_import_id', "{$prefix}_mahasiswa_import_fk")
                ->references('id')
                ->on($tables['imports'])
                ->cascadeOnDelete();
        });

        $schema->table($tables['detail'], function (Blueprint $table) use ($tables, $prefix): void {
            $table->foreign('kompen_respon_hub_student_id', "{$prefix}_detail_mahasiswa_fk")
                ->references('id')
                ->on($tables['mahasiswa'])
                ->cascadeOnDelete();
        });

        $schema->table($tables['setup'], function (Blueprint $table) use ($tables, $prefix): void {
            $table->foreign('opened_by_admin_id', "{$prefix}_setup_admin_fk")
                ->references('id')
                ->on($tables['admins'])
                ->nullOnDelete();
        });

        $schema->table($tables['tasks'], function (Blueprint $table) use ($tables, $prefix): void {
            $table->foreign('uploaded_by_admin_id', "{$prefix}_tasks_admin_fk")
                ->references('id')
                ->on($tables['admins'])
                ->nullOnDelete();
            $table->foreign('kompen_respon_hub_import_id', "{$prefix}_tasks_import_fk")
                ->references('id')
                ->on($tables['imports'])
                ->nullOnDelete();
        });
    }
};
