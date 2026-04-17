<?php

namespace App\Http\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros avanzados para listados de comprobantes (facturas, boletas, notas).
 * Uso: $query = $this->applyDocumentFilters($query, $request);
 */
trait AppliesDocumentFilters
{
    protected function applyDocumentFilters(Builder $query, Request $request): Builder
    {
        // Fecha de emisión
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_emision', '>=', $request->input('fecha_desde'));
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_emision', '<=', $request->input('fecha_hasta'));
        }

        // Fecha de vencimiento
        if ($request->filled('vencimiento_desde')) {
            $query->whereDate('fecha_vencimiento', '>=', $request->input('vencimiento_desde'));
        }
        if ($request->filled('vencimiento_hasta')) {
            $query->whereDate('fecha_vencimiento', '<=', $request->input('vencimiento_hasta'));
        }

        // Estado SUNAT (permite múltiples separados por coma: ?sunat_status=aceptado,rechazado)
        if ($request->filled('sunat_status')) {
            $estados = explode(',', (string) $request->input('sunat_status'));
            $query->whereIn('sunat_status', array_map('trim', $estados));
        }

        // Estado de pago
        if ($request->filled('estado_pago')) {
            $estados = explode(',', (string) $request->input('estado_pago'));
            $query->whereIn('payment_status', array_map('trim', $estados));
        }

        // Serie (exacta o LIKE con comodín)
        if ($request->filled('serie')) {
            $serie = (string) $request->input('serie');
            if (str_contains($serie, '%')) {
                $query->where('serie', 'like', $serie);
            } else {
                $query->where('serie', $serie);
            }
        }

        // Correlativo (rango)
        if ($request->filled('correlativo_desde')) {
            $query->where('correlativo', '>=', (int) $request->input('correlativo_desde'));
        }
        if ($request->filled('correlativo_hasta')) {
            $query->where('correlativo', '<=', (int) $request->input('correlativo_hasta'));
        }
        if ($request->filled('correlativo')) {
            $query->where('correlativo', (int) $request->input('correlativo'));
        }

        // Cliente — por número de documento (exacto)
        if ($request->filled('client_num_doc')) {
            $query->where('client_num_doc', $request->input('client_num_doc'));
        }

        // Cliente — tipo de documento (6=RUC, 1=DNI, etc.)
        if ($request->filled('client_tipo_doc')) {
            $query->where('client_tipo_doc', $request->input('client_tipo_doc'));
        }

        // Cliente — búsqueda por razón social (LIKE)
        if ($request->filled('cliente')) {
            $query->where('client_razon_social', 'like', '%' . $request->input('cliente') . '%');
        }

        // Moneda
        if ($request->filled('tipo_moneda')) {
            $query->where('tipo_moneda', strtoupper((string) $request->input('tipo_moneda')));
        }

        // Tipo de operación SUNAT (0101, 0200, etc.)
        if ($request->filled('tipo_operacion')) {
            $query->where('tipo_operacion', $request->input('tipo_operacion'));
        }

        // Forma de pago
        if ($request->filled('forma_pago')) {
            $query->where('forma_pago', $request->input('forma_pago'));
        }

        // Sucursal
        if ($request->filled('sucursal_id')) {
            $query->where('sucursal_id', (int) $request->input('sucursal_id'));
        }

        // Rango de montos
        if ($request->filled('monto_min')) {
            $query->where('mto_imp_venta', '>=', (float) $request->input('monto_min'));
        }
        if ($request->filled('monto_max')) {
            $query->where('mto_imp_venta', '<=', (float) $request->input('monto_max'));
        }

        // Búsqueda general (search/q): razón social, num doc cliente, correlativo
        $search = $request->input('search') ?? $request->input('q');
        if ($search) {
            $term = '%' . $search . '%';
            $query->where(function ($w) use ($term, $search) {
                $w->where('client_razon_social', 'like', $term)
                  ->orWhere('client_num_doc', 'like', $term)
                  ->orWhere('serie', 'like', $term);
                if (is_numeric($search)) {
                    $w->orWhere('correlativo', (int) $search);
                }
            });
        }

        // Solo con CDR (aceptados ya cerrados)
        if ($request->boolean('con_cdr')) {
            $query->whereNotNull('cdr_path');
        }

        // Ordenamiento
        $ordenarPor = $request->input('ordenar_por', 'created_at');
        $orden = strtolower((string) $request->input('orden', 'desc'));
        $orden = in_array($orden, ['asc', 'desc'], true) ? $orden : 'desc';

        $columnasValidas = [
            'fecha_emision', 'fecha_vencimiento', 'created_at', 'updated_at',
            'correlativo', 'mto_imp_venta', 'sunat_status', 'payment_status',
            'client_razon_social',
        ];
        if (! in_array($ordenarPor, $columnasValidas, true)) {
            $ordenarPor = 'created_at';
        }

        return $query->orderBy($ordenarPor, $orden);
    }
}
