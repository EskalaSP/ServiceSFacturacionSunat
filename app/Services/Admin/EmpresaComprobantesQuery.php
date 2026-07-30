<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Unifica TODOS los tipos de comprobante/documento de una empresa (tenant) en una
 * sola consulta mediante UNION ALL de columnas normalizadas. Permite filtrar,
 * ordenar, buscar y paginar de forma nativa en BD (sin cargar todo en memoria,
 * sin N+1). Cada tabla aporta un SELECT con el MISMO orden de columnas.
 *
 * Tipos incluidos: 01 factura, 03 boleta, 07 NC, 08 ND, 09/31 guía,
 * 20 retención, 40 percepción, RC resumen diario, RA comunicación de baja,
 * COT cotización, NV nota de venta.
 */
class EmpresaComprobantesQuery
{
    /** Columnas de salida (mismo orden en cada SELECT del UNION). */
    private const COLS = 'tipo, id, serie, correlativo, numero, cliente, cliente_doc, moneda, total, subtotal, igv, estado, fecha_emision, fecha_envio, sucursal_id, observacion, has_xml, has_cdr, has_pdf';

    /** Etiquetas legibles por tipo. */
    public const TIPOS = [
        '01' => 'Factura',
        '03' => 'Boleta',
        '07' => 'Nota de crédito',
        '08' => 'Nota de débito',
        '09' => 'Guía de remisión',
        '31' => 'Guía transportista',
        '20' => 'Retención',
        '40' => 'Percepción',
        'RC' => 'Resumen diario',
        'RA' => 'Comunicación de baja',
        'COT' => 'Cotización',
        'NV' => 'Nota de venta',
    ];

    /**
     * `SERIE-00000001` a partir de serie + correlativo, según el motor.
     *
     * `correlativo` es un entero. MySQL lo castea solo dentro de LPAD; Postgres
     * no tiene `lpad(integer, integer, text)` y responde
     * "SQLSTATE[42883] function lpad(integer, integer, unknown) does not exist".
     *
     * Tampoco sirve un CAST único: en MySQL el tipo texto es CHAR, pero en
     * Postgres `CAST(x AS CHAR)` significa `char(1)` y TRUNCA el correlativo a
     * un solo carácter — peor que el error, porque no falla, corrompe.
     */
    private function expresionNumero(): string
    {
        $tipoTexto = DB::connection()->getDriverName() === 'pgsql' ? 'VARCHAR' : 'CHAR';

        return "CONCAT(serie, '-', LPAD(CAST(correlativo AS {$tipoTexto}), 8, '0'))";
    }

    /**
     * Construye el UNION ALL normalizado para un tenant.
     */
    public function baseUnion(int $tenantId): Builder
    {
        $numeroCore = $this->expresionNumero();

        // Cada bandera se normaliza a 1/0. Escribir `(xml_path IS NOT NULL)` da
        // boolean en Postgres e integer en MySQL, y las ramas que no tienen la
        // columna pasan un 0 literal: unir boolean con integer revienta en
        // Postgres con "UNION types boolean and integer cannot be matched".
        // null = esta rama nunca tiene ese archivo.
        $bandera = fn (?string $condicion): string => $condicion === null
            ? '0'
            : "CASE WHEN {$condicion} THEN 1 ELSE 0 END";

        $flags = fn (?string $xml, ?string $cdr, ?string $pdf): string => $bandera($xml).' AS has_xml, '
            .$bandera($cdr).' AS has_cdr, '
            .$bandera($pdf).' AS has_pdf';

        // 4 comprobantes electrónicos "core" (mismo esquema vía HasDocumentFields).
        $core = function (string $tipo, string $tabla) use ($numeroCore, $flags, $tenantId) {
            return DB::table($tabla)
                ->selectRaw(
                    "'$tipo' AS tipo, id, serie, correlativo, $numeroCore AS numero, "
                    .'client_razon_social AS cliente, client_num_doc AS cliente_doc, tipo_moneda AS moneda, '
                    .'mto_imp_venta AS total, sub_total AS subtotal, mto_igv AS igv, '
                    .'sunat_status AS estado, fecha_emision, sent_at AS fecha_envio, sucursal_id, observacion, '
                    .$flags('xml_path IS NOT NULL', 'cdr_path IS NOT NULL', 'pdf_path IS NOT NULL')
                )
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at');
        };

        $q = $core('01', 'invoices');
        $q->unionAll($core('03', 'boletas'));
        $q->unionAll($core('07', 'credit_notes'));
        $q->unionAll($core('08', 'debit_notes'));

        // Guías de remisión (esquema propio; XML/CDR inline o en disco).
        $q->unionAll(
            DB::table('dispatch_guides')
                ->selectRaw(
                    "tipo_documento AS tipo, id, serie, correlativo, $numeroCore AS numero, "
                    .'destinatario_razon_social AS cliente, destinatario_num_doc AS cliente_doc, NULL AS moneda, '
                    .'NULL AS total, NULL AS subtotal, NULL AS igv, '
                    .'sunat_status AS estado, fecha_emision, sent_at AS fecha_envio, sucursal_id, observacion, '
                    .$flags('xml_path IS NOT NULL OR xml_content IS NOT NULL', 'cdr_path IS NOT NULL OR cdr_content IS NOT NULL', 'pdf_path IS NOT NULL')
                )
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
        );

        // Retención (20).
        $q->unionAll(
            DB::table('retentions')
                ->selectRaw(
                    "'20' AS tipo, id, serie, correlativo, $numeroCore AS numero, "
                    ."proveedor_razon_social AS cliente, proveedor_num_doc AS cliente_doc, 'PEN' AS moneda, "
                    .'imp_retenido AS total, NULL AS subtotal, NULL AS igv, '
                    .'sunat_status AS estado, fecha_emision, sent_at AS fecha_envio, NULL AS sucursal_id, observacion, '
                    .$flags('xml_path IS NOT NULL', 'cdr_path IS NOT NULL', 'pdf_path IS NOT NULL')
                )
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
        );

        // Percepción (40).
        $q->unionAll(
            DB::table('perceptions')
                ->selectRaw(
                    "'40' AS tipo, id, serie, correlativo, $numeroCore AS numero, "
                    ."cliente_razon_social AS cliente, cliente_num_doc AS cliente_doc, 'PEN' AS moneda, "
                    .'imp_percibido AS total, NULL AS subtotal, NULL AS igv, '
                    .'sunat_status AS estado, fecha_emision, sent_at AS fecha_envio, NULL AS sucursal_id, observacion, '
                    .$flags('xml_path IS NOT NULL', 'cdr_path IS NOT NULL', 'pdf_path IS NOT NULL')
                )
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
        );

        // Resumen diario de boletas (RC).
        $q->unionAll(
            DB::table('summaries')
                ->selectRaw(
                    "'RC' AS tipo, id, NULL AS serie, correlativo, identifier AS numero, "
                    .'NULL AS cliente, NULL AS cliente_doc, NULL AS moneda, NULL AS total, NULL AS subtotal, NULL AS igv, '
                    .'sunat_status AS estado, fecha_referencia AS fecha_emision, fecha_envio, NULL AS sucursal_id, NULL AS observacion, '
                    .$flags('xml_path IS NOT NULL', 'cdr_path IS NOT NULL', null)
                )
                ->where('tenant_id', $tenantId)
        );

        // Comunicación de baja (RA).
        $q->unionAll(
            DB::table('voided_documents')
                ->selectRaw(
                    "'RA' AS tipo, id, NULL AS serie, correlativo, identifier AS numero, "
                    .'NULL AS cliente, NULL AS cliente_doc, NULL AS moneda, NULL AS total, NULL AS subtotal, NULL AS igv, '
                    .'sunat_status AS estado, fecha_generacion AS fecha_emision, fecha_comunicacion AS fecha_envio, NULL AS sucursal_id, NULL AS observacion, '
                    .$flags('xml_path IS NOT NULL', null, null)
                )
                ->where('tenant_id', $tenantId)
        );

        // Documentos internos: cotización (COT) y nota de venta (NV).
        $q->unionAll(
            DB::table('internal_documents')
                ->selectRaw(
                    "(CASE WHEN type = 'quotation' THEN 'COT' ELSE 'NV' END) AS tipo, id, NULL AS serie, NULL AS correlativo, numero, "
                    .'client_razon_social AS cliente, client_num_doc AS cliente_doc, tipo_moneda AS moneda, '
                    .'mto_imp_venta AS total, sub_total AS subtotal, mto_igv AS igv, '
                    .'status AS estado, fecha_emision, NULL AS fecha_envio, NULL AS sucursal_id, observacion, '
                    .$flags(null, null, 'pdf_path IS NOT NULL')
                )
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
        );

        return $q;
    }

    /**
     * Lista paginada con filtros, búsqueda y orden.
     *
     * @param  array<string,mixed>  $f  filtros
     */
    public function paginate(int $tenantId, array $f): LengthAwarePaginator
    {
        $sortable = ['fecha_emision', 'total', 'tipo', 'serie', 'numero', 'estado'];
        $sort = in_array($f['sort'] ?? '', $sortable, true) ? $f['sort'] : 'fecha_emision';
        $dir = ($f['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = (int) ($f['per_page'] ?? 25);
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 25;

        $q = DB::query()->fromSub($this->baseUnion($tenantId), 'c')->select('*');

        $q->when(! empty($f['tipo']), fn ($q) => $q->where('tipo', $f['tipo']));
        $q->when(! empty($f['estado']), fn ($q) => $q->where('estado', $f['estado']));
        $q->when(! empty($f['serie']), fn ($q) => $q->where('serie', 'like', '%'.$f['serie'].'%'));
        $q->when(! empty($f['sucursal_id']), fn ($q) => $q->where('sucursal_id', $f['sucursal_id']));
        $q->when(! empty($f['fecha_desde']), fn ($q) => $q->whereDate('fecha_emision', '>=', $f['fecha_desde']));
        $q->when(! empty($f['fecha_hasta']), fn ($q) => $q->whereDate('fecha_emision', '<=', $f['fecha_hasta']));
        $q->when(! empty($f['buscar']), function ($q) use ($f) {
            $b = '%'.$f['buscar'].'%';
            $q->where(function ($w) use ($b) {
                $w->where('numero', 'like', $b)
                    ->orWhere('cliente', 'like', $b)
                    ->orWhere('cliente_doc', 'like', $b)
                    ->orWhere('serie', 'like', $b);
            });
        });

        if ($sort === 'fecha_emision') {
            $q->orderBy('fecha_emision', $dir)->orderBy('id', $dir);
        } else {
            $q->orderBy($sort, $dir);
        }

        return $q->paginate($perPage)->withQueryString();
    }

    /**
     * Totales resumen de la empresa (sin filtros): conteos por tipo, enviados,
     * aceptados y monto total emitido.
     *
     * @return array<string,mixed>
     */
    public function stats(int $tenantId): array
    {
        $rows = DB::query()->fromSub($this->baseUnion($tenantId), 'c')
            ->selectRaw(
                'tipo, COUNT(*) AS n, COALESCE(SUM(total), 0) AS monto, '
                ."SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) AS enviados, "
                ."SUM(CASE WHEN estado = 'aceptado' THEN 1 ELSE 0 END) AS aceptados"
            )
            ->groupBy('tipo')
            ->get();

        $porTipo = [];
        $total = 0;
        $enviados = 0;
        $aceptados = 0;
        $monto = 0.0;
        foreach ($rows as $r) {
            $porTipo[$r->tipo] = (int) $r->n;
            $total += (int) $r->n;
            $enviados += (int) $r->enviados;
            $aceptados += (int) $r->aceptados;
            $monto += (float) $r->monto;
        }

        return [
            'total' => $total,
            'facturas' => $porTipo['01'] ?? 0,
            'boletas' => $porTipo['03'] ?? 0,
            'notas_credito' => $porTipo['07'] ?? 0,
            'notas_debito' => $porTipo['08'] ?? 0,
            'guias' => ($porTipo['09'] ?? 0) + ($porTipo['31'] ?? 0),
            'enviados' => $enviados,
            'aceptados' => $aceptados,
            'monto_total' => round($monto, 2),
            'por_tipo' => $porTipo,
        ];
    }
}
