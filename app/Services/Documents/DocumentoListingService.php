<?php

namespace App\Services\Documents;

use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\DispatchGuide;
use App\Models\Invoice;
use App\Models\Perception;
use App\Models\Retention;
use App\Models\Tenant;

/**
 * Punto único para listar comprobantes en formato uniforme.
 * Lo usan el Historial (todos los tipos) y los listados por tipo de "Emitir".
 */
class DocumentoListingService
{
    /**
     * @param  array{estado?:?string,desde?:?string,hasta?:?string,cliente?:?string}  $filtros
     * @return array<int,array<string,mixed>>
     */
    public function listar(Tenant $tenant, string $tipo = 'todos', array $filtros = []): array
    {
        $fuentes = $this->fuentes($tenant, $filtros);
        $seleccion = array_key_exists($tipo, $fuentes) ? [$tipo] : array_keys($fuentes);

        $merged = collect();
        foreach ($seleccion as $key) {
            $merged = $merged->concat($fuentes[$key]());
        }

        return $merged->sortByDesc('fecha')->take(300)->values()->all();
    }

    /** @return array<string,\Closure> */
    private function fuentes(Tenant $tenant, array $filtros): array
    {
        $estado = $filtros['estado'] ?? null;
        $desde = $filtros['desde'] ?? null;
        $hasta = $filtros['hasta'] ?? null;
        $cliente = $filtros['cliente'] ?? null;

        $comunes = function ($query, ?string $campoCliente) use ($estado, $desde, $hasta, $cliente) {
            if ($estado) {
                $query->where('sunat_status', $estado);
            }
            if ($desde) {
                $query->whereDate('fecha_emision', '>=', $desde);
            }
            if ($hasta) {
                $query->whereDate('fecha_emision', '<=', $hasta);
            }
            if ($cliente && $campoCliente) {
                $query->where($campoCliente, 'like', "%{$cliente}%");
            }

            return $query->orderByDesc('fecha_emision');
        };

        return [
            'facturas' => fn () => $comunes(Invoice::forTenant($tenant->id), 'client_razon_social')->limit(300)->get()->map(fn ($d) => $this->mapDoc($d, '01')),
            'boletas' => fn () => $comunes(Boleta::forTenant($tenant->id), 'client_razon_social')->limit(300)->get()->map(fn ($d) => $this->mapDoc($d, '03')),
            'notas-credito' => fn () => $comunes(CreditNote::forTenant($tenant->id), 'client_razon_social')->limit(200)->get()->map(fn ($d) => $this->mapDoc($d, '07')),
            'notas-debito' => fn () => $comunes(DebitNote::forTenant($tenant->id), 'client_razon_social')->limit(200)->get()->map(fn ($d) => $this->mapDoc($d, '08')),
            'guias' => fn () => $comunes(DispatchGuide::forTenant($tenant->id), 'destinatario_razon_social')->limit(200)->get()->map(fn ($d) => $this->mapGuia($d)),
            'retenciones' => fn () => $comunes(Retention::forTenant($tenant->id), 'proveedor_razon_social')->limit(150)->get()->map(fn ($d) => $this->mapRetPerc($d, '20')),
            'percepciones' => fn () => $comunes(Perception::forTenant($tenant->id), 'cliente_razon_social')->limit(150)->get()->map(fn ($d) => $this->mapRetPerc($d, '40')),
        ];
    }

    private function mapDoc(object $doc, string $tipoDoc): array
    {
        return [
            'id' => $doc->id,
            'tipo_doc' => $tipoDoc,
            'serie' => $doc->serie,
            'correlativo' => $doc->correlativo,
            'numero' => $doc->serie.'-'.str_pad((string) $doc->correlativo, 8, '0', STR_PAD_LEFT),
            'cliente' => $doc->client_razon_social,
            'fecha' => $doc->fecha_emision,
            'total' => (float) $doc->mto_imp_venta,
            'moneda' => $doc->tipo_moneda ?? 'PEN',
            'estado' => $doc->sunat_status ?? 'pendiente',
            'ambiente' => $doc->ambiente ?? 'produccion',
            'sunat_code' => $doc->sunat_code ?? null,
            'tiene_pdf' => ! empty($doc->pdf_path),
            'tiene_xml' => ! empty($doc->xml_path),
            'tiene_cdr' => ! empty($doc->cdr_path),
        ];
    }

    private function mapGuia(object $doc): array
    {
        return [
            'id' => $doc->id,
            'tipo_doc' => (string) ($doc->tipo_documento ?? '09'),
            'serie' => $doc->serie,
            'correlativo' => $doc->correlativo,
            'numero' => $doc->serie.'-'.str_pad((string) $doc->correlativo, 8, '0', STR_PAD_LEFT),
            'cliente' => $doc->destinatario_razon_social ?? '—',
            'fecha' => $doc->fecha_emision,
            'total' => 0.0,
            'moneda' => 'PEN',
            'estado' => $doc->sunat_status ?? 'pendiente',
            'ambiente' => 'produccion',
            'sunat_code' => $doc->sunat_code ?? null,
            'tiene_pdf' => ! empty($doc->pdf_path),
            'tiene_xml' => ! empty($doc->xml_path),
            'tiene_cdr' => ! empty($doc->cdr_path),
        ];
    }

    private function mapRetPerc(object $doc, string $tipoDoc): array
    {
        return [
            'id' => $doc->id,
            'tipo_doc' => $tipoDoc,
            'serie' => $doc->serie,
            'correlativo' => $doc->correlativo,
            'numero' => $doc->serie.'-'.str_pad((string) $doc->correlativo, 8, '0', STR_PAD_LEFT),
            'cliente' => $tipoDoc === '20' ? ($doc->proveedor_razon_social ?? '—') : ($doc->cliente_razon_social ?? '—'),
            'fecha' => $doc->fecha_emision,
            'total' => (float) ($tipoDoc === '20' ? $doc->imp_retenido : $doc->imp_percibido),
            'moneda' => 'PEN',
            'estado' => $doc->sunat_status ?? 'pendiente',
            'ambiente' => 'produccion',
            'tiene_pdf' => ! empty($doc->pdf_path),
            'tiene_xml' => ! empty($doc->xml_path),
            'tiene_cdr' => ! empty($doc->cdr_path),
        ];
    }
}
