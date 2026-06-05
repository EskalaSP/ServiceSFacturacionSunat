<?php

/**
 * Seeder de comprobantes ficticios para tests de chatbot.
 * Crea F001-00892 (referenciada en EJEMPLOS-PARTE-02 Esc 2) y B001-04521 (Esc 10).
 *
 * Uso: docker compose exec api-sunat php database/seeders/seed_comprobantes_para_tests.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$existeF = DB::table('invoices')->where('serie', 'F001')->where('correlativo', 892)->exists();
$existeB = DB::table('boletas')->where('serie', 'B001')->where('correlativo', 4521)->exists();

if ($existeF && $existeB) {
    echo "Ya sembrados (F001-892 y B001-4521). Nada que hacer.\n";
    exit(0);
}

DB::transaction(function () use ($existeF, $existeB) {
    $now = '2026-06-04 10:00:00';

    if (! $existeF) {
        $facturaId = DB::table('invoices')->insertGetId([
            'tenant_id' => 1,
            'serie' => 'F001',
            'correlativo' => 892,
            'cod_local' => '0000',
            'fecha_emision' => $now,
            'tipo_operacion' => '0101',
            'tipo_moneda' => 'PEN',
            'forma_pago' => 'Contado',
            'client_tipo_doc' => '6',
            'client_num_doc' => '20567891234',
            'client_razon_social' => 'CONSTRUCTORA LIMA SUR E.I.R.L.',
            'client_direccion' => 'Av. Los Proceres 456, Lima',
            'mto_oper_gravadas' => 955.51,
            'mto_oper_exoneradas' => 0,
            'mto_oper_inafectas' => 0,
            'mto_oper_exportacion' => 0,
            'mto_oper_gratuitas' => 0,
            'mto_igv' => 171.99,
            'mto_base_ivap' => 0,
            'mto_ivap' => 0,
            'mto_isc' => 0,
            'mto_icbper' => 0,
            'total_impuestos' => 171.99,
            'valor_venta' => 955.51,
            'sub_total' => 1127.50,
            'mto_imp_venta' => 1127.50,
            'total_anticipos' => 0,
            'total_descuentos' => 0,
            'sunat_status' => 'aceptado',
            'sunat_code' => '0',
            'sunat_description' => 'La Factura numero F001-892, ha sido aceptada',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $items = [
            ['CEM-PAC', 'CEMENTO PORTLAND TIPO I (42.5 kg)', 'BLS', 15, 28.50, 427.50],
            ['FIE-1/2', 'FIERRO CORRUGADO 1/2 X 9 m',      'NIU', 20, 32.00, 640.00],
            ['CAL-001', 'Cal bolsa',                        'BLS',  5, 12.00,  60.00],
        ];
        $itemColNames = collect(DB::select("SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name='invoice_items'"))
            ->pluck('column_name')->all();
        foreach ($items as [$codigo, $desc, $unidad, $cant, $precio, $subtotal]) {
            $row = [
                'invoice_id' => $facturaId,
                'codigo' => $codigo,
                'descripcion' => $desc,
                'unidad' => $unidad,
                'cantidad' => $cant,
                'mto_valor_unitario' => round($precio / 1.18, 6),
                'mto_precio_unitario' => $precio,
                'mto_valor_venta' => round($subtotal / 1.18, 2),
                'mto_base_igv' => round($subtotal / 1.18, 2),
                'porcentaje_igv' => 18,
                'igv' => round($subtotal - ($subtotal / 1.18), 2),
                'total_impuestos' => round($subtotal - ($subtotal / 1.18), 2),
                'tip_afe_igv' => '10',
            ];
            if (in_array('factor_icbper', $itemColNames, true)) $row['factor_icbper'] = 0;
            if (in_array('icbper', $itemColNames, true)) $row['icbper'] = 0;
            if (in_array('created_at', $itemColNames, true)) { $row['created_at'] = $now; $row['updated_at'] = $now; }
            DB::table('invoice_items')->insert($row);
        }
        echo "Factura F001-00892 sembrada: id={$facturaId}\n";
    }

    if (! $existeB) {
        $boletaId = DB::table('boletas')->insertGetId([
            'tenant_id' => 1,
            'serie' => 'B001',
            'correlativo' => 4521,
            'cod_local' => '0000',
            'fecha_emision' => $now,
            'tipo_operacion' => '0101',
            'tipo_moneda' => 'PEN',
            'forma_pago' => 'Contado',
            'client_tipo_doc' => '0',
            'client_num_doc' => '99999999',
            'client_razon_social' => 'Cliente varios',
            'mto_oper_gravadas' => 54.92,
            'mto_oper_exoneradas' => 0,
            'mto_oper_inafectas' => 0,
            'mto_oper_gratuitas' => 0,
            'mto_igv' => 9.88,
            'mto_isc' => 0,
            'mto_icbper' => 0,
            'total_impuestos' => 9.88,
            'valor_venta' => 54.92,
            'sub_total' => 64.80,
            'mto_imp_venta' => 64.80,
            'total_anticipos' => 0,
            'total_descuentos' => 0,
            'sunat_status' => 'pendiente',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $itemsB = [
            ['ARZ-CS5', 'Arroz Costeño Extra 5kg',           'NIU', 1, 22.00, 22.00],
            ['ACE-PR1', 'Aceite Primor 1L',                  'NIU', 2, 11.50, 23.00],
            ['AZU-RB1', 'Azúcar Rubia 1kg',                  'NIU', 2,  4.20,  8.40],
            ['FID-DV',  'Fideo Don Vittorio Spaghetti 500g', 'NIU', 3,  3.80, 11.40],
        ];
        $bItemColNames = collect(DB::select("SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name='boleta_items'"))
            ->pluck('column_name')->all();
        foreach ($itemsB as [$codigo, $desc, $unidad, $cant, $precio, $subtotal]) {
            $row = [
                'boleta_id' => $boletaId,
                'codigo' => $codigo,
                'descripcion' => $desc,
                'unidad' => $unidad,
                'cantidad' => $cant,
                'mto_valor_unitario' => round($precio / 1.18, 6),
                'mto_precio_unitario' => $precio,
                'mto_valor_venta' => round($subtotal / 1.18, 2),
                'mto_base_igv' => round($subtotal / 1.18, 2),
                'porcentaje_igv' => 18,
                'igv' => round($subtotal - ($subtotal / 1.18), 2),
                'total_impuestos' => round($subtotal - ($subtotal / 1.18), 2),
                'tip_afe_igv' => '10',
            ];
            if (in_array('factor_icbper', $bItemColNames, true)) $row['factor_icbper'] = 0;
            if (in_array('icbper', $bItemColNames, true)) $row['icbper'] = 0;
            if (in_array('created_at', $bItemColNames, true)) { $row['created_at'] = $now; $row['updated_at'] = $now; }
            DB::table('boleta_items')->insert($row);
        }
        echo "Boleta B001-04521 sembrada: id={$boletaId}\n";
    }
});

echo "OK\n";
