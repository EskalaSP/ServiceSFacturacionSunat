<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite (tests) no soporta particionamiento ni INFORMATION_SCHEMA — skip la migración completa
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // ─── Particionar documents por fecha_emision ───
        // MySQL no soporta FK en tablas particionadas. Eliminamos FK constraints
        // y mantenemos integridad referencial a nivel de aplicación (ya garantizada por Actions).

        if (Schema::hasTable('documents') && ! $this->isPartitioned('documents')) {
            // 1. Eliminar FKs que referencian documents — si existen
            $this->dropForeignIfExists('document_items', 'document_items_document_id_foreign');
            $this->dropForeignIfExists('dispatch_guides', 'dispatch_guides_document_id_foreign');

            // 2. Eliminar FKs de documents — si existen
            $this->dropForeignIfExists('documents', 'documents_tenant_id_foreign');
            $this->dropForeignIfExists('documents', 'documents_client_id_foreign');

            // 3. Ajustar unique constraint para incluir fecha_emision
            if ($this->indexExists('documents', 'documents_tenant_tipo_serie_corr_unique')) {
                DB::statement('ALTER TABLE documents DROP INDEX documents_tenant_tipo_serie_corr_unique');
            }
            if (! $this->indexExists('documents', 'documents_tenant_tipo_serie_corr_fecha_unique')) {
                DB::statement('ALTER TABLE documents ADD UNIQUE INDEX documents_tenant_tipo_serie_corr_fecha_unique (tenant_id, tipo_documento, serie, correlativo, fecha_emision)');
            }

            // 4. Ajustar PK para incluir fecha_emision
            DB::statement('ALTER TABLE documents DROP PRIMARY KEY, ADD PRIMARY KEY (id, fecha_emision)');
            DB::statement('ALTER TABLE documents MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

            // 5. Particionar
            DB::statement('ALTER TABLE documents PARTITION BY RANGE (YEAR(fecha_emision) * 100 + MONTH(fecha_emision)) (
                PARTITION p202601 VALUES LESS THAN (202602),
                PARTITION p202602 VALUES LESS THAN (202603),
                PARTITION p202603 VALUES LESS THAN (202604),
                PARTITION p202604 VALUES LESS THAN (202605),
                PARTITION p202605 VALUES LESS THAN (202606),
                PARTITION p202606 VALUES LESS THAN (202607),
                PARTITION p202607 VALUES LESS THAN (202608),
                PARTITION p202608 VALUES LESS THAN (202609),
                PARTITION p202609 VALUES LESS THAN (202610),
                PARTITION p202610 VALUES LESS THAN (202611),
                PARTITION p202611 VALUES LESS THAN (202612),
                PARTITION p202612 VALUES LESS THAN (202701),
                PARTITION p_future VALUES LESS THAN MAXVALUE
            )');
        }

        // ─── Particionar api_logs por created_at ───

        if (! $this->isPartitioned('api_logs')) {
            // 1. Eliminar FK de api_logs — si existe
            $this->dropForeignIfExists('api_logs', 'api_logs_tenant_id_foreign');

            // 2. Convertir created_at de TIMESTAMP a DATETIME (TIMESTAMP no permite particionamiento)
            DB::statement('ALTER TABLE api_logs MODIFY created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

            // 3. Ajustar PK para incluir created_at
            DB::statement('ALTER TABLE api_logs DROP PRIMARY KEY, ADD PRIMARY KEY (id, created_at)');
            DB::statement('ALTER TABLE api_logs MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

            // 4. Particionar usando RANGE COLUMNS (funciona con DATETIME)
            DB::statement("ALTER TABLE api_logs PARTITION BY RANGE COLUMNS(created_at) (
                PARTITION p202601 VALUES LESS THAN ('2026-02-01'),
                PARTITION p202602 VALUES LESS THAN ('2026-03-01'),
                PARTITION p202603 VALUES LESS THAN ('2026-04-01'),
                PARTITION p202604 VALUES LESS THAN ('2026-05-01'),
                PARTITION p202605 VALUES LESS THAN ('2026-06-01'),
                PARTITION p202606 VALUES LESS THAN ('2026-07-01'),
                PARTITION p202607 VALUES LESS THAN ('2026-08-01'),
                PARTITION p202608 VALUES LESS THAN ('2026-09-01'),
                PARTITION p202609 VALUES LESS THAN ('2026-10-01'),
                PARTITION p202610 VALUES LESS THAN ('2026-11-01'),
                PARTITION p202611 VALUES LESS THAN ('2026-12-01'),
                PARTITION p202612 VALUES LESS THAN ('2027-01-01'),
                PARTITION p_future VALUES LESS THAN (MAXVALUE)
            )");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Remover particiones de api_logs y restaurar
        if ($this->isPartitioned('api_logs')) {
            DB::statement('ALTER TABLE api_logs REMOVE PARTITIONING');
            DB::statement('ALTER TABLE api_logs DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
            DB::statement('ALTER TABLE api_logs MODIFY created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
            Schema::table('api_logs', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            });
        }

        // Remover particiones de documents y restaurar
        if (Schema::hasTable('documents') && $this->isPartitioned('documents')) {
            DB::statement('ALTER TABLE documents REMOVE PARTITIONING');
            DB::statement('ALTER TABLE documents DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
            DB::statement('ALTER TABLE documents DROP INDEX documents_tenant_tipo_serie_corr_fecha_unique');
            DB::statement('ALTER TABLE documents ADD UNIQUE INDEX documents_tenant_tipo_serie_corr_unique (tenant_id, tipo_documento, serie, correlativo)');
            Schema::table('documents', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
            });

            if (Schema::hasTable('document_items')) {
                Schema::table('document_items', function (Blueprint $table) {
                    $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
                });
            }

            Schema::table('dispatch_guides', function (Blueprint $table) {
                $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();
            });
        }
    }

    private function isPartitioned(string $table): bool
    {
        // SQLite (tests) no soporta particionamiento — se considera no particionado
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        $result = DB::select(
            'SELECT PARTITION_NAME FROM INFORMATION_SCHEMA.PARTITIONS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND PARTITION_NAME IS NOT NULL LIMIT 1',
            [config('database.connections.mysql.database'), $table]
        );

        return ! empty($result);
    }

    private function dropForeignIfExists(string $table, string $foreignKey): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $exists = DB::select(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [config('database.connections.mysql.database'), $table, $foreignKey]
        );

        if (! empty($exists)) {
            Schema::table($table, function (Blueprint $t) use ($foreignKey) {
                $t->dropForeign($foreignKey);
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        $exists = DB::select(
            'SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [config('database.connections.mysql.database'), $table, $indexName]
        );

        return ! empty($exists);
    }
};
