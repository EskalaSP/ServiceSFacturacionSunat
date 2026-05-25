<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Omitir si no hay documentos para migrar
        if (! DB::table('documents')->exists()) {
            return;
        }

        // Omitir si las tablas destino ya tienen datos (idempotente)
        if (DB::table('invoices')->exists() || DB::table('boletas')->exists()
            || DB::table('credit_notes')->exists() || DB::table('debit_notes')->exists()) {
            return;
        }

        // Columnas base presentes en TODAS las nuevas tablas
        $baseColumns = [
            'tenant_id', 'client_id', 'serie', 'correlativo', 'cod_local',
            'fecha_emision', 'tipo_moneda',
            'client_tipo_doc', 'client_num_doc', 'client_razon_social',
            'client_direccion', 'mto_oper_gravadas', 'mto_oper_exoneradas',
            'mto_oper_inafectas', 'mto_oper_gratuitas', 'mto_igv', 'mto_isc',
            'mto_icbper', 'total_impuestos', 'valor_venta', 'sub_total',
            'mto_imp_venta', 'total_anticipos', 'total_descuentos', 'leyenda',
            'observacion', 'xml_path', 'cdr_path', 'pdf_path', 'hash_cpe',
            'sunat_status', 'sunat_code', 'sunat_description', 'sunat_notes',
            'ticket', 'sent_at', 'created_at', 'updated_at', 'deleted_at',
        ];

        // Columnas solo en facturas y boletas (no en notas)
        $invoiceBoleta = ['fecha_vencimiento', 'tipo_operacion', 'forma_pago'];

        $invoiceExtra = array_merge($invoiceBoleta, [
            'mto_oper_exportacion', 'mto_base_ivap', 'mto_ivap',
            'cuotas', 'detraccion', 'percepcion', 'anticipos',
            'descuentos_globales', 'guias', 'extras',
        ]);

        $boletaExtra = array_merge($invoiceBoleta, ['cuotas', 'guias']);

        $noteExtra = [
            'doc_afectado_tipo', 'doc_afectado_serie', 'doc_afectado_correlativo',
            'cod_motivo', 'des_motivo', 'guias',
        ];

        $itemColumns = [
            'codigo', 'descripcion', 'unidad', 'cantidad', 'mto_valor_unitario',
            'mto_valor_venta', 'mto_base_igv', 'porcentaje_igv', 'igv',
            'tip_afe_igv', 'isc', 'icbper', 'total_impuestos',
            'mto_precio_unitario', 'descuento', 'created_at', 'updated_at',
        ];

        $typeMap = [
            '01' => ['table' => 'invoices', 'items_table' => 'invoice_items', 'fk' => 'invoice_id', 'extra' => $invoiceExtra],
            '03' => ['table' => 'boletas', 'items_table' => 'boleta_items', 'fk' => 'boleta_id', 'extra' => $boletaExtra],
            '07' => ['table' => 'credit_notes', 'items_table' => 'credit_note_items', 'fk' => 'credit_note_id', 'extra' => $noteExtra],
            '08' => ['table' => 'debit_notes', 'items_table' => 'debit_note_items', 'fk' => 'debit_note_id', 'extra' => $noteExtra],
        ];

        foreach ($typeMap as $tipoDoc => $config) {
            DB::table('documents')
                ->where('tipo_documento', $tipoDoc)
                ->orderBy('id')
                ->chunk(500, function ($documents) use ($config, $baseColumns, $itemColumns) {
                    foreach ($documents as $doc) {
                        $data = [];
                        foreach (array_merge($baseColumns, $config['extra']) as $col) {
                            $data[$col] = $doc->$col ?? null;
                        }

                        $newId = DB::table($config['table'])->insertGetId($data);

                        // Migrar ítems
                        $items = DB::table('document_items')
                            ->where('document_id', $doc->id)
                            ->get();

                        foreach ($items as $item) {
                            $itemData = [$config['fk'] => $newId];
                            foreach ($itemColumns as $col) {
                                $itemData[$col] = $item->$col ?? null;
                            }
                            DB::table($config['items_table'])->insert($itemData);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // Schema::disableForeignKeyConstraints() es portable: en MySQL hace
        // SET FOREIGN_KEY_CHECKS=0; en PostgreSQL setea session_replication_role.
        Schema::disableForeignKeyConstraints();

        $tables = [
            'invoice_items', 'invoices',
            'boleta_items', 'boletas',
            'credit_note_items', 'credit_notes',
            'debit_note_items', 'debit_notes',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();
    }
};
