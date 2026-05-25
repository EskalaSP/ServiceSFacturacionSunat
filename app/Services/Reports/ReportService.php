<?php

namespace App\Services\Reports;

use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\InternalDocument;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Sucursal;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function registroVentas(Tenant $tenant, array $filters): array
    {
        $desde = $filters['fecha_desde'];
        $hasta = $filters['fecha_hasta'];

        $invoices = $this->queryDocuments(Invoice::query(), $tenant, $filters, 'sunat')
            ->get()->map(fn ($d) => $this->mapDocumentRow($d, '01', 'FACTURA'));

        $boletas = $this->queryDocuments(Boleta::query(), $tenant, $filters, 'sunat')
            ->get()->map(fn ($d) => $this->mapDocumentRow($d, '03', 'BOLETA'));

        $creditNotes = $this->queryDocuments(CreditNote::query(), $tenant, $filters, 'sunat')
            ->get()->map(fn ($d) => $this->mapDocumentRow($d, '07', 'NOTA DE CRÉDITO'));

        $debitNotes = $this->queryDocuments(DebitNote::query(), $tenant, $filters, 'sunat')
            ->get()->map(fn ($d) => $this->mapDocumentRow($d, '08', 'NOTA DE DÉBITO'));

        $all = $invoices->concat($boletas)->concat($creditNotes)->concat($debitNotes)
            ->sortBy('fecha_emision')->values();

        $totalesPorTipo = [
            '01' => $this->sumarTotales($invoices),
            '03' => $this->sumarTotales($boletas),
            '07' => $this->sumarTotales($creditNotes),
            '08' => $this->sumarTotales($debitNotes),
        ];

        $granTotal = $this->sumarTotales($all);
        $ventaNeta = round(
            ($totalesPorTipo['01']['mto_imp_venta'] + $totalesPorTipo['03']['mto_imp_venta'] + $totalesPorTipo['08']['mto_imp_venta'])
            - $totalesPorTipo['07']['mto_imp_venta'],
            2
        );

        return [
            'titulo' => 'REGISTRO DE VENTAS',
            'periodo' => ['desde' => $desde, 'hasta' => $hasta],
            'filtros' => $this->filtrosAplicados($filters),
            'documentos' => $all,
            'totales_por_tipo' => $totalesPorTipo,
            'gran_total' => $granTotal,
            'venta_neta' => $ventaNeta,
            'total_documentos' => $all->count(),
        ];
    }

    public function ventasConsolidado(Tenant $tenant, array $filters): array
    {
        $desde = $filters['fecha_desde'];
        $hasta = $filters['fecha_hasta'];
        $agrupacion = $filters['agrupacion'] ?? 'mes';

        $invoices = $this->queryDocuments(Invoice::query(), $tenant, $filters, 'sunat')->get();
        $boletas = $this->queryDocuments(Boleta::query(), $tenant, $filters, 'sunat')->get();
        $creditNotes = $this->queryDocuments(CreditNote::query(), $tenant, $filters, 'sunat')->get();
        $debitNotes = $this->queryDocuments(DebitNote::query(), $tenant, $filters, 'sunat')->get();

        $totalFacturado = $invoices->sum('mto_imp_venta') + $boletas->sum('mto_imp_venta') + $debitNotes->sum('mto_imp_venta');
        $totalNC = $creditNotes->sum('mto_imp_venta');
        $totalND = $debitNotes->sum('mto_imp_venta');
        $ventaNeta = $totalFacturado - $totalNC;
        $totalIgv = $invoices->sum('mto_igv') + $boletas->sum('mto_igv') + $debitNotes->sum('mto_igv') - $creditNotes->sum('mto_igv');
        $docsEmitidos = $invoices->count() + $boletas->count() + $creditNotes->count() + $debitNotes->count();
        $ticketPromedio = $docsEmitidos > 0 ? round($totalFacturado / ($invoices->count() + $boletas->count() ?: 1), 2) : 0;

        $totalCobrado = $invoices->sum('monto_pagado') + $boletas->sum('monto_pagado');
        $porcentajeCobrado = $totalFacturado > 0 ? round(($totalCobrado / $totalFacturado) * 100, 1) : 0;

        // Desglose temporal
        $allDocs = collect()
            ->concat($invoices->map(fn ($d) => ['tipo' => '01', 'fecha' => $d->fecha_emision, 'total' => (float) $d->mto_imp_venta, 'igv' => (float) $d->mto_igv]))
            ->concat($boletas->map(fn ($d) => ['tipo' => '03', 'fecha' => $d->fecha_emision, 'total' => (float) $d->mto_imp_venta, 'igv' => (float) $d->mto_igv]))
            ->concat($creditNotes->map(fn ($d) => ['tipo' => '07', 'fecha' => $d->fecha_emision, 'total' => (float) $d->mto_imp_venta, 'igv' => (float) $d->mto_igv]))
            ->concat($debitNotes->map(fn ($d) => ['tipo' => '08', 'fecha' => $d->fecha_emision, 'total' => (float) $d->mto_imp_venta, 'igv' => (float) $d->mto_igv]));

        $desgloseTemporal = $this->agruparPorPeriodo($allDocs, $agrupacion);

        // Desglose por tipo
        $desglosePorTipo = [
            ['tipo' => '01', 'nombre' => 'Facturas', 'cantidad' => $invoices->count(), 'monto' => round($invoices->sum('mto_imp_venta'), 2)],
            ['tipo' => '03', 'nombre' => 'Boletas', 'cantidad' => $boletas->count(), 'monto' => round($boletas->sum('mto_imp_venta'), 2)],
            ['tipo' => '07', 'nombre' => 'Notas de Crédito', 'cantidad' => $creditNotes->count(), 'monto' => round($creditNotes->sum('mto_imp_venta'), 2)],
            ['tipo' => '08', 'nombre' => 'Notas de Débito', 'cantidad' => $debitNotes->count(), 'monto' => round($debitNotes->sum('mto_imp_venta'), 2)],
        ];

        // Desglose por sucursal
        $desglosePorSucursal = $this->desglosePorSucursal($tenant, $invoices, $boletas, $creditNotes, $debitNotes);

        // Top 10 clientes
        $allVentas = $invoices->concat($boletas);
        $topClientes = $allVentas->groupBy('client_num_doc')->map(function ($docs, $numDoc) {
            $nc = 0; // simplificado - la reducción de NC por cliente requeriría vincular doc_afectado
            return [
                'num_doc' => $numDoc,
                'razon_social' => $docs->first()->client_razon_social,
                'cantidad_docs' => $docs->count(),
                'monto_bruto' => round($docs->sum('mto_imp_venta'), 2),
            ];
        })->sortByDesc('monto_bruto')->take(10)->values();

        // Top 10 productos
        $topProductos = $this->topProductos($tenant, $filters);

        // Desglose por moneda
        $desglosePorMoneda = $allVentas->groupBy('tipo_moneda')->map(function ($docs, $moneda) {
            return [
                'moneda' => $moneda,
                'cantidad' => $docs->count(),
                'monto' => round($docs->sum('mto_imp_venta'), 2),
            ];
        })->values();

        return [
            'titulo' => 'VENTAS CONSOLIDADO',
            'periodo' => ['desde' => $desde, 'hasta' => $hasta],
            'filtros' => $this->filtrosAplicados($filters),
            'kpis' => [
                'total_facturado' => round($totalFacturado, 2),
                'total_igv' => round($totalIgv, 2),
                'docs_emitidos' => $docsEmitidos,
                'ticket_promedio' => $ticketPromedio,
                'nc_emitidas' => $creditNotes->count(),
                'nd_emitidas' => $debitNotes->count(),
                'venta_neta' => round($ventaNeta, 2),
                'total_cobrado' => round($totalCobrado, 2),
                'porcentaje_cobrado' => $porcentajeCobrado,
            ],
            'desglose_temporal' => $desgloseTemporal,
            'desglose_por_tipo' => $desglosePorTipo,
            'desglose_por_sucursal' => $desglosePorSucursal,
            'top_clientes' => $topClientes,
            'top_productos' => $topProductos,
            'desglose_por_moneda' => $desglosePorMoneda,
        ];
    }

    public function notas(Tenant $tenant, array $filters): array
    {
        $desde = $filters['fecha_desde'];
        $hasta = $filters['fecha_hasta'];

        $creditNotes = $this->queryDocuments(CreditNote::query(), $tenant, $filters, 'sunat')
            ->get()->map(function ($nc) {
                return [
                    'id' => $nc->id,
                    'serie' => $nc->serie,
                    'correlativo' => $nc->correlativo,
                    'numero_completo' => $nc->numero_completo,
                    'fecha_emision' => $nc->fecha_emision->format('Y-m-d'),
                    'tipo_doc_afectado' => $nc->doc_afectado_tipo,
                    'serie_afectado' => $nc->doc_afectado_serie,
                    'correlativo_afectado' => $nc->doc_afectado_correlativo,
                    'doc_afectado' => $nc->doc_afectado_serie . '-' . $nc->doc_afectado_correlativo,
                    'cod_motivo' => $nc->cod_motivo,
                    'des_motivo' => $nc->des_motivo,
                    'client_num_doc' => $nc->client_num_doc,
                    'client_razon_social' => $nc->client_razon_social,
                    'mto_oper_gravadas' => (float) $nc->mto_oper_gravadas,
                    'mto_igv' => (float) $nc->mto_igv,
                    'mto_imp_venta' => (float) $nc->mto_imp_venta,
                    'tipo_moneda' => $nc->tipo_moneda,
                    'estado_sunat' => $nc->sunat_status,
                ];
            });

        $debitNotes = $this->queryDocuments(DebitNote::query(), $tenant, $filters, 'sunat')
            ->get()->map(function ($nd) {
                return [
                    'id' => $nd->id,
                    'serie' => $nd->serie,
                    'correlativo' => $nd->correlativo,
                    'numero_completo' => $nd->numero_completo,
                    'fecha_emision' => $nd->fecha_emision->format('Y-m-d'),
                    'tipo_doc_afectado' => $nd->doc_afectado_tipo,
                    'serie_afectado' => $nd->doc_afectado_serie,
                    'correlativo_afectado' => $nd->doc_afectado_correlativo,
                    'doc_afectado' => $nd->doc_afectado_serie . '-' . $nd->doc_afectado_correlativo,
                    'cod_motivo' => $nd->cod_motivo,
                    'des_motivo' => $nd->des_motivo,
                    'client_num_doc' => $nd->client_num_doc,
                    'client_razon_social' => $nd->client_razon_social,
                    'mto_oper_gravadas' => (float) $nd->mto_oper_gravadas,
                    'mto_igv' => (float) $nd->mto_igv,
                    'mto_imp_venta' => (float) $nd->mto_imp_venta,
                    'tipo_moneda' => $nd->tipo_moneda,
                    'estado_sunat' => $nd->sunat_status,
                ];
            });

        // Resumen por motivo NC
        $resumenPorMotivo = $creditNotes->groupBy('cod_motivo')->map(function ($notas, $cod) {
            return [
                'cod_motivo' => $cod,
                'des_motivo' => $notas->first()['des_motivo'],
                'cantidad' => $notas->count(),
                'monto_total' => round($notas->sum('mto_imp_venta'), 2),
                'igv_revertido' => round($notas->sum('mto_igv'), 2),
                'base_gravada_reducida' => round($notas->sum('mto_oper_gravadas'), 2),
            ];
        })->sortByDesc('monto_total')->values();

        $totalNC = round($creditNotes->sum('mto_imp_venta'), 2);
        $totalND = round($debitNotes->sum('mto_imp_venta'), 2);

        return [
            'titulo' => 'NOTAS DE CRÉDITO Y DÉBITO',
            'periodo' => ['desde' => $desde, 'hasta' => $hasta],
            'filtros' => $this->filtrosAplicados($filters),
            'kpis' => [
                'total_nc' => $totalNC,
                'cantidad_nc' => $creditNotes->count(),
                'total_nd' => $totalND,
                'cantidad_nd' => $debitNotes->count(),
                'nc_netas' => round($totalNC - $totalND, 2),
                'igv_revertido' => round($creditNotes->sum('mto_igv'), 2),
                'base_gravada_reducida' => round($creditNotes->sum('mto_oper_gravadas'), 2),
            ],
            'notas_credito' => $creditNotes->values(),
            'notas_debito' => $debitNotes->values(),
            'resumen_por_motivo' => $resumenPorMotivo,
        ];
    }

    public function cobranzas(Tenant $tenant, array $filters): array
    {
        $desde = $filters['fecha_desde'];
        $hasta = $filters['fecha_hasta'];
        $soloVencidos = filter_var($filters['vencido'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Documentos con pagos (Invoice + Boleta)
        $invoicesQuery = $this->queryDocuments(Invoice::query(), $tenant, $filters, 'payment');
        $boletasQuery = $this->queryDocuments(Boleta::query(), $tenant, $filters, 'payment');

        if ($soloVencidos) {
            $invoicesQuery->where('fecha_vencimiento', '<', now()->toDateString())
                ->where('payment_status', '!=', 'pagado');
            $boletasQuery->where('fecha_vencimiento', '<', now()->toDateString())
                ->where('payment_status', '!=', 'pagado');
        }

        $invoices = $invoicesQuery->get();
        $boletas = $boletasQuery->get();
        $allDocs = $invoices->concat($boletas);

        $totalFacturado = round($allDocs->sum('mto_imp_venta'), 2);
        $totalCobrado = round($allDocs->sum('monto_pagado'), 2);
        $totalPendiente = round($totalFacturado - $totalCobrado, 2);
        $porcentajeCobranza = $totalFacturado > 0 ? round(($totalCobrado / $totalFacturado) * 100, 1) : 0;

        // Aging
        $aging = $this->calcularAging($allDocs);

        // Detalle por documento
        $detalle = $allDocs->map(function ($doc) {
            $pendiente = (float) $doc->mto_imp_venta - (float) $doc->monto_pagado;
            $diasAtraso = 0;
            if ($doc->fecha_vencimiento && $doc->fecha_vencimiento->lt(now()) && $pendiente > 0) {
                $diasAtraso = $doc->fecha_vencimiento->diffInDays(now());
            }

            return [
                'id' => $doc->id,
                'tipo' => $doc instanceof Invoice ? '01' : '03',
                'numero_completo' => $doc->numero_completo,
                'client_num_doc' => $doc->client_num_doc,
                'client_razon_social' => $doc->client_razon_social,
                'fecha_emision' => $doc->fecha_emision->format('Y-m-d'),
                'fecha_vencimiento' => $doc->fecha_vencimiento?->format('Y-m-d'),
                'tipo_moneda' => $doc->tipo_moneda,
                'total' => (float) $doc->mto_imp_venta,
                'cobrado' => (float) $doc->monto_pagado,
                'pendiente' => round($pendiente, 2),
                'dias_atraso' => $diasAtraso,
                'estado_pago' => $doc->payment_status,
            ];
        })->sortByDesc('pendiente')->values();

        // Resumen por método de pago
        $payments = Payment::where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$desde, Carbon::parse($hasta)->endOfDay()])
            ->get();

        $resumenPorMetodo = $payments->groupBy('metodo')->map(function ($pagos, $metodo) {
            return [
                'metodo' => $metodo,
                'cantidad' => $pagos->count(),
                'monto' => round($pagos->sum('monto'), 2),
            ];
        })->sortByDesc('monto')->values();

        // Resumen por cliente
        $resumenPorCliente = $allDocs->groupBy('client_num_doc')->map(function ($docs, $numDoc) {
            return [
                'num_doc' => $numDoc,
                'razon_social' => $docs->first()->client_razon_social,
                'total_facturado' => round($docs->sum('mto_imp_venta'), 2),
                'total_cobrado' => round($docs->sum('monto_pagado'), 2),
                'total_pendiente' => round($docs->sum('mto_imp_venta') - $docs->sum('monto_pagado'), 2),
            ];
        })->sortByDesc('total_pendiente')->values();

        return [
            'titulo' => 'COBRANZAS Y CUENTAS POR COBRAR',
            'periodo' => ['desde' => $desde, 'hasta' => $hasta],
            'filtros' => $this->filtrosAplicados($filters),
            'kpis' => [
                'total_facturado' => $totalFacturado,
                'total_cobrado' => $totalCobrado,
                'total_pendiente' => $totalPendiente,
                'porcentaje_cobranza' => $porcentajeCobranza,
            ],
            'aging' => $aging,
            'detalle' => $detalle,
            'resumen_por_metodo' => $resumenPorMetodo,
            'resumen_por_cliente' => $resumenPorCliente,
        ];
    }

    public function documentosInternos(Tenant $tenant, array $filters): array
    {
        $desde = $filters['fecha_desde'];
        $hasta = $filters['fecha_hasta'];

        $cotizaciones = InternalDocument::where('tenant_id', $tenant->id)
            ->where('type', 'quotation')
            ->whereBetween('fecha_emision', [$desde, $hasta])
            ->when($filters['client_num_doc'] ?? null, fn ($q, $v) => $q->where('client_num_doc', $v))
            ->when($filters['tipo_moneda'] ?? null, fn ($q, $v) => $q->where('tipo_moneda', $v))
            ->orderByDesc('created_at')
            ->get();

        $notasVenta = InternalDocument::where('tenant_id', $tenant->id)
            ->where('type', 'sale_note')
            ->whereBetween('fecha_emision', [$desde, $hasta])
            ->when($filters['client_num_doc'] ?? null, fn ($q, $v) => $q->where('client_num_doc', $v))
            ->when($filters['tipo_moneda'] ?? null, fn ($q, $v) => $q->where('tipo_moneda', $v))
            ->orderByDesc('created_at')
            ->get();

        // Cotizaciones KPIs
        $cotPorStatus = $cotizaciones->groupBy('status');
        $cotTotal = $cotizaciones->count();
        $cotAceptadas = $cotPorStatus->get('aceptada', collect())->count();
        $tasaConversion = $cotTotal > 0 ? round(($cotAceptadas / $cotTotal) * 100, 1) : 0;

        $cotResumen = [
            'total' => $cotTotal,
            'por_status' => [
                'vigente' => $cotPorStatus->get('vigente', collect())->count(),
                'aceptada' => $cotAceptadas,
                'rechazada' => $cotPorStatus->get('rechazada', collect())->count(),
                'vencida' => $cotPorStatus->get('vencida', collect())->count(),
            ],
            'monto_total' => round($cotizaciones->sum('mto_imp_venta'), 2),
            'monto_por_status' => [
                'vigente' => round($cotPorStatus->get('vigente', collect())->sum('mto_imp_venta'), 2),
                'aceptada' => round($cotPorStatus->get('aceptada', collect())->sum('mto_imp_venta'), 2),
                'rechazada' => round($cotPorStatus->get('rechazada', collect())->sum('mto_imp_venta'), 2),
                'vencida' => round($cotPorStatus->get('vencida', collect())->sum('mto_imp_venta'), 2),
            ],
            'ticket_promedio' => $cotTotal > 0 ? round($cotizaciones->sum('mto_imp_venta') / $cotTotal, 2) : 0,
            'tasa_conversion' => $tasaConversion,
        ];

        // Notas de Venta KPIs
        $nvPorStatus = $notasVenta->groupBy('status');
        $nvResumen = [
            'total' => $notasVenta->count(),
            'por_status' => [
                'emitida' => $nvPorStatus->get('emitida', collect())->count(),
                'anulada' => $nvPorStatus->get('anulada', collect())->count(),
            ],
            'monto_total' => round($notasVenta->sum('mto_imp_venta'), 2),
            'cobrado' => round($notasVenta->sum('monto_pagado'), 2),
            'pendiente' => round($notasVenta->sum('mto_imp_venta') - $notasVenta->sum('monto_pagado'), 2),
        ];

        // Detalle
        $detalleCot = $cotizaciones->map(fn ($d) => [
            'id' => $d->id,
            'numero' => $d->numero,
            'fecha_emision' => $d->fecha_emision->format('Y-m-d'),
            'fecha_vencimiento' => $d->fecha_vencimiento?->format('Y-m-d'),
            'client_razon_social' => $d->client_razon_social,
            'client_num_doc' => $d->client_num_doc,
            'mto_imp_venta' => (float) $d->mto_imp_venta,
            'tipo_moneda' => $d->tipo_moneda,
            'estado' => $d->status,
        ])->values();

        $detalleNV = $notasVenta->map(fn ($d) => [
            'id' => $d->id,
            'numero' => $d->numero,
            'fecha_emision' => $d->fecha_emision->format('Y-m-d'),
            'client_razon_social' => $d->client_razon_social,
            'client_num_doc' => $d->client_num_doc,
            'mto_imp_venta' => (float) $d->mto_imp_venta,
            'monto_pagado' => (float) $d->monto_pagado,
            'tipo_moneda' => $d->tipo_moneda,
            'estado' => $d->status,
            'estado_pago' => $d->payment_status,
        ])->values();

        // Top clientes
        $topClientesCot = $cotizaciones->groupBy('client_num_doc')->map(function ($docs, $numDoc) {
            return [
                'num_doc' => $numDoc,
                'razon_social' => $docs->first()->client_razon_social,
                'cantidad' => $docs->count(),
                'monto' => round($docs->sum('mto_imp_venta'), 2),
            ];
        })->sortByDesc('monto')->take(10)->values();

        return [
            'titulo' => 'DOCUMENTOS INTERNOS',
            'periodo' => ['desde' => $desde, 'hasta' => $hasta],
            'filtros' => $this->filtrosAplicados($filters),
            'cotizaciones' => $cotResumen,
            'notas_venta' => $nvResumen,
            'detalle_cotizaciones' => $detalleCot,
            'detalle_notas_venta' => $detalleNV,
            'top_clientes' => $topClientesCot,
        ];
    }

    public function porCliente(Tenant $tenant, array $filters): array
    {
        $desde = $filters['fecha_desde'];
        $hasta = $filters['fecha_hasta'];
        $numDoc = $filters['client_num_doc'];

        // Datos del cliente
        $client = $tenant->clients()->where('numero_documento', $numDoc)->first();
        $clientData = $client ? [
            'tipo_documento' => $client->tipo_documento,
            'numero_documento' => $client->numero_documento,
            'razon_social' => $client->razon_social,
            'nombre_comercial' => $client->nombre_comercial,
            'direccion' => $client->direccion,
            'email' => $client->email,
            'telefono' => $client->telefono,
        ] : [
            'numero_documento' => $numDoc,
            'razon_social' => 'No registrado',
        ];

        // Documentos del cliente
        $invoices = Invoice::forTenant($tenant->id)->where('client_num_doc', $numDoc)
            ->fechas($desde, $hasta)->orderBy('fecha_emision')->get();
        $boletas = Boleta::forTenant($tenant->id)->where('client_num_doc', $numDoc)
            ->fechas($desde, $hasta)->orderBy('fecha_emision')->get();
        $creditNotes = CreditNote::forTenant($tenant->id)->where('client_num_doc', $numDoc)
            ->fechas($desde, $hasta)->orderBy('fecha_emision')->get();
        $debitNotes = DebitNote::forTenant($tenant->id)->where('client_num_doc', $numDoc)
            ->fechas($desde, $hasta)->orderBy('fecha_emision')->get();
        $cotizaciones = InternalDocument::where('tenant_id', $tenant->id)->where('type', 'quotation')
            ->where('client_num_doc', $numDoc)->whereBetween('fecha_emision', [$desde, $hasta])
            ->orderBy('fecha_emision')->get();
        $notasVenta = InternalDocument::where('tenant_id', $tenant->id)->where('type', 'sale_note')
            ->where('client_num_doc', $numDoc)->whereBetween('fecha_emision', [$desde, $hasta])
            ->orderBy('fecha_emision')->get();

        $totalFacturado = round($invoices->sum('mto_imp_venta') + $boletas->sum('mto_imp_venta'), 2);
        $totalNC = round($creditNotes->sum('mto_imp_venta'), 2);
        $totalND = round($debitNotes->sum('mto_imp_venta'), 2);
        $neto = round($totalFacturado - $totalNC + $totalND, 2);
        $cobrado = round($invoices->sum('monto_pagado') + $boletas->sum('monto_pagado'), 2);
        $pendiente = round($neto - $cobrado, 2);

        // Mapear documentos
        $mapDoc = function ($doc, $tipo, $nombre) {
            return [
                'tipo' => $tipo,
                'tipo_nombre' => $nombre,
                'numero_completo' => $doc->numero_completo ?? $doc->numero,
                'fecha_emision' => $doc->fecha_emision->format('Y-m-d'),
                'fecha_vencimiento' => $doc->fecha_vencimiento?->format('Y-m-d'),
                'mto_imp_venta' => (float) $doc->mto_imp_venta,
                'tipo_moneda' => $doc->tipo_moneda,
                'estado_sunat' => $doc->sunat_status ?? null,
                'estado_pago' => $doc->payment_status ?? null,
                'monto_pagado' => (float) ($doc->monto_pagado ?? 0),
            ];
        };

        $detalle = collect()
            ->concat($invoices->map(fn ($d) => $mapDoc($d, '01', 'Factura')))
            ->concat($boletas->map(fn ($d) => $mapDoc($d, '03', 'Boleta')))
            ->concat($creditNotes->map(fn ($d) => $mapDoc($d, '07', 'Nota de Crédito')))
            ->concat($debitNotes->map(fn ($d) => $mapDoc($d, '08', 'Nota de Débito')))
            ->concat($cotizaciones->map(fn ($d) => $mapDoc($d, 'COT', 'Cotización')))
            ->concat($notasVenta->map(fn ($d) => $mapDoc($d, 'NV', 'Nota de Venta')))
            ->sortBy('fecha_emision')->values();

        // Historial de pagos
        $payableDocs = $invoices->concat($boletas)->concat($notasVenta);
        $payableIds = [];
        foreach ($payableDocs as $doc) {
            $type = get_class($doc);
            $payableIds[] = ['type' => $type, 'id' => $doc->id];
        }

        $pagos = Payment::where('tenant_id', $tenant->id)
            ->where(function ($q) use ($payableIds) {
                foreach ($payableIds as $p) {
                    $q->orWhere(function ($sq) use ($p) {
                        $sq->where('payable_type', $p['type'])->where('payable_id', $p['id']);
                    });
                }
            })
            ->orderBy('created_at')
            ->get()
            ->map(fn ($p) => [
                'fecha' => $p->created_at->format('Y-m-d H:i'),
                'metodo' => $p->metodo,
                'monto' => (float) $p->monto,
                'referencia' => $p->referencia,
                'notas' => $p->notas,
            ]);

        // Aging del cliente
        $docsConPago = $invoices->concat($boletas);
        $aging = $this->calcularAging($docsConPago);

        return [
            'titulo' => 'ESTADO DE CUENTA POR CLIENTE',
            'periodo' => ['desde' => $desde, 'hasta' => $hasta],
            'cliente' => $clientData,
            'resumen' => [
                'total_facturado' => $totalFacturado,
                'nc_emitidas' => $totalNC,
                'nd_emitidas' => $totalND,
                'neto' => $neto,
                'cobrado' => $cobrado,
                'pendiente' => $pendiente,
            ],
            'detalle_documentos' => $detalle,
            'historial_pagos' => $pagos,
            'aging' => $aging,
        ];
    }

    public function porSucursal(Tenant $tenant, array $filters): array
    {
        $desde = $filters['fecha_desde'];
        $hasta = $filters['fecha_hasta'];
        $sucursalId = $filters['sucursal_id'] ?? null;

        $sucursales = Sucursal::where('tenant_id', $tenant->id)->where('is_active', true)->get();

        if ($sucursalId) {
            $sucursales = $sucursales->where('id', $sucursalId);
        }

        $comparativo = $sucursales->map(function ($sucursal) use ($tenant, $desde, $hasta) {
            $codLocal = $sucursal->cod_local;

            $invoices = Invoice::forTenant($tenant->id)->where('cod_local', $codLocal)
                ->fechas($desde, $hasta)->get();
            $boletas = Boleta::forTenant($tenant->id)->where('cod_local', $codLocal)
                ->fechas($desde, $hasta)->get();
            $creditNotes = CreditNote::forTenant($tenant->id)->where('cod_local', $codLocal)
                ->fechas($desde, $hasta)->get();
            $debitNotes = DebitNote::forTenant($tenant->id)->where('cod_local', $codLocal)
                ->fechas($desde, $hasta)->get();

            $totalBruto = round($invoices->sum('mto_imp_venta') + $boletas->sum('mto_imp_venta') + $debitNotes->sum('mto_imp_venta'), 2);
            $totalNC = round($creditNotes->sum('mto_imp_venta'), 2);
            $neto = round($totalBruto - $totalNC, 2);
            $igv = round($invoices->sum('mto_igv') + $boletas->sum('mto_igv') + $debitNotes->sum('mto_igv') - $creditNotes->sum('mto_igv'), 2);
            $cobrado = round($invoices->sum('monto_pagado') + $boletas->sum('monto_pagado'), 2);
            $pendiente = round($neto - $cobrado, 2);

            $totalDocs = $invoices->count() + $boletas->count() + $creditNotes->count() + $debitNotes->count();
            $dias = max(1, Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) + 1);

            return [
                'sucursal_id' => $sucursal->id,
                'nombre' => $sucursal->nombre,
                'cod_local' => $codLocal,
                'facturas' => ['cantidad' => $invoices->count(), 'monto' => round($invoices->sum('mto_imp_venta'), 2)],
                'boletas' => ['cantidad' => $boletas->count(), 'monto' => round($boletas->sum('mto_imp_venta'), 2)],
                'notas_credito' => ['cantidad' => $creditNotes->count(), 'monto' => $totalNC],
                'notas_debito' => ['cantidad' => $debitNotes->count(), 'monto' => round($debitNotes->sum('mto_imp_venta'), 2)],
                'total_bruto' => $totalBruto,
                'neto' => $neto,
                'igv' => $igv,
                'cobrado' => $cobrado,
                'pendiente' => $pendiente,
                'ticket_promedio' => ($invoices->count() + $boletas->count()) > 0
                    ? round($totalBruto / ($invoices->count() + $boletas->count()), 2) : 0,
                'docs_dia_promedio' => round($totalDocs / $dias, 1),
            ];
        })->values();

        // Calcular % del total
        $granTotal = $comparativo->sum('neto');
        $comparativo = $comparativo->map(function ($s) use ($granTotal) {
            $s['porcentaje_total'] = $granTotal > 0 ? round(($s['neto'] / $granTotal) * 100, 1) : 0;

            return $s;
        });

        return [
            'titulo' => 'COMPARATIVO POR SUCURSAL',
            'periodo' => ['desde' => $desde, 'hasta' => $hasta],
            'filtros' => $this->filtrosAplicados($filters),
            'comparativo' => $comparativo,
            'gran_total' => round($granTotal, 2),
        ];
    }

    // ===== HELPERS =====

    private function queryDocuments(Builder $query, Tenant $tenant, array $filters, string $mode = 'sunat'): Builder
    {
        $query->where('tenant_id', $tenant->id)
            ->whereBetween('fecha_emision', [$filters['fecha_desde'], $filters['fecha_hasta']])
            ->orderBy('fecha_emision')
            ->orderBy('serie')
            ->orderBy('correlativo');

        if (! empty($filters['sucursal_id'])) {
            $sucursal = Sucursal::find($filters['sucursal_id']);
            if ($sucursal) {
                $query->where('cod_local', $sucursal->cod_local);
            }
        }

        if (! empty($filters['serie'])) {
            $query->where('serie', $filters['serie']);
        }

        if (! empty($filters['client_num_doc'])) {
            $query->where('client_num_doc', $filters['client_num_doc']);
        }

        if (! empty($filters['tipo_moneda'])) {
            $query->where('tipo_moneda', $filters['tipo_moneda']);
        }

        if ($mode === 'sunat' && ! empty($filters['estado_sunat'])) {
            $query->where('sunat_status', $filters['estado_sunat']);
        }

        if ($mode === 'payment' && ! empty($filters['estado_pago'])) {
            $query->where('payment_status', $filters['estado_pago']);
        }

        return $query;
    }

    private function mapDocumentRow($doc, string $tipoDoc, string $tipoNombre): array
    {
        return [
            'id' => $doc->id,
            'fecha_emision' => $doc->fecha_emision->format('Y-m-d'),
            'fecha_vencimiento' => $doc->fecha_vencimiento?->format('Y-m-d'),
            'tipo_doc' => $tipoDoc,
            'tipo_doc_nombre' => $tipoNombre,
            'serie' => $doc->serie,
            'correlativo' => $doc->correlativo,
            'numero_completo' => $doc->numero_completo,
            'client_tipo_doc' => $doc->client_tipo_doc,
            'client_num_doc' => $doc->client_num_doc,
            'client_razon_social' => $doc->client_razon_social,
            'mto_oper_gravadas' => (float) $doc->mto_oper_gravadas,
            'mto_oper_exoneradas' => (float) $doc->mto_oper_exoneradas,
            'mto_oper_inafectas' => (float) $doc->mto_oper_inafectas,
            'mto_oper_gratuitas' => (float) $doc->mto_oper_gratuitas,
            'mto_igv' => (float) $doc->mto_igv,
            'mto_isc' => (float) ($doc->mto_isc ?? 0),
            'mto_icbper' => (float) ($doc->mto_icbper ?? 0),
            'otros_tributos' => 0,
            'mto_imp_venta' => (float) $doc->mto_imp_venta,
            'tipo_moneda' => $doc->tipo_moneda,
            'estado_sunat' => $doc->sunat_status,
        ];
    }

    private function sumarTotales(Collection $docs): array
    {
        return [
            'cantidad' => $docs->count(),
            'mto_oper_gravadas' => round($docs->sum('mto_oper_gravadas'), 2),
            'mto_oper_exoneradas' => round($docs->sum('mto_oper_exoneradas'), 2),
            'mto_oper_inafectas' => round($docs->sum('mto_oper_inafectas'), 2),
            'mto_oper_gratuitas' => round($docs->sum('mto_oper_gratuitas'), 2),
            'mto_igv' => round($docs->sum('mto_igv'), 2),
            'mto_isc' => round($docs->sum('mto_isc'), 2),
            'mto_icbper' => round($docs->sum('mto_icbper'), 2),
            'mto_imp_venta' => round($docs->sum('mto_imp_venta'), 2),
        ];
    }

    private function calcularAging(Collection $docs): array
    {
        $ranges = [
            '0-15' => 0, '16-30' => 0, '31-60' => 0,
            '61-90' => 0, '91-120' => 0, '120+' => 0,
        ];

        foreach ($docs as $doc) {
            $pendiente = (float) $doc->mto_imp_venta - (float) $doc->monto_pagado;
            if ($pendiente <= 0) {
                continue;
            }

            $fechaRef = $doc->fecha_vencimiento ?? $doc->fecha_emision;
            if (! $fechaRef) {
                continue;
            }

            $dias = $fechaRef->diffInDays(now(), false);
            if ($dias < 0) {
                $dias = 0;
            }

            if ($dias <= 15) {
                $ranges['0-15'] += $pendiente;
            } elseif ($dias <= 30) {
                $ranges['16-30'] += $pendiente;
            } elseif ($dias <= 60) {
                $ranges['31-60'] += $pendiente;
            } elseif ($dias <= 90) {
                $ranges['61-90'] += $pendiente;
            } elseif ($dias <= 120) {
                $ranges['91-120'] += $pendiente;
            } else {
                $ranges['120+'] += $pendiente;
            }
        }

        return array_map(fn ($v) => round($v, 2), $ranges);
    }

    private function agruparPorPeriodo(Collection $docs, string $agrupacion): array
    {
        $grouped = $docs->groupBy(function ($doc) use ($agrupacion) {
            $fecha = Carbon::parse($doc['fecha']);

            return match ($agrupacion) {
                'dia' => $fecha->format('Y-m-d'),
                'semana' => $fecha->startOfWeek()->format('Y-m-d'),
                'mes' => $fecha->format('Y-m'),
                default => $fecha->format('Y-m'),
            };
        });

        return $grouped->map(function ($items, $periodo) {
            $byType = $items->groupBy('tipo');

            return [
                'periodo' => $periodo,
                'facturas' => $byType->get('01', collect())->count(),
                'facturas_monto' => round($byType->get('01', collect())->sum('total'), 2),
                'boletas' => $byType->get('03', collect())->count(),
                'boletas_monto' => round($byType->get('03', collect())->sum('total'), 2),
                'nc' => $byType->get('07', collect())->count(),
                'nc_monto' => round($byType->get('07', collect())->sum('total'), 2),
                'nd' => $byType->get('08', collect())->count(),
                'nd_monto' => round($byType->get('08', collect())->sum('total'), 2),
                'neto' => round(
                    $byType->get('01', collect())->sum('total')
                    + $byType->get('03', collect())->sum('total')
                    + $byType->get('08', collect())->sum('total')
                    - $byType->get('07', collect())->sum('total'),
                    2
                ),
                'igv' => round($items->sum('igv'), 2),
            ];
        })->sortKeys()->values()->all();
    }

    private function desglosePorSucursal(Tenant $tenant, Collection $invoices, Collection $boletas, Collection $creditNotes, Collection $debitNotes): array
    {
        $sucursales = Sucursal::where('tenant_id', $tenant->id)->where('is_active', true)->get();

        return $sucursales->map(function ($sucursal) use ($invoices, $boletas, $creditNotes, $debitNotes) {
            $cod = $sucursal->cod_local;
            $fi = $invoices->where('cod_local', $cod);
            $fb = $boletas->where('cod_local', $cod);
            $fnc = $creditNotes->where('cod_local', $cod);
            $fnd = $debitNotes->where('cod_local', $cod);

            return [
                'sucursal' => $sucursal->nombre,
                'cod_local' => $cod,
                'facturas' => $fi->count(),
                'boletas' => $fb->count(),
                'nc' => $fnc->count(),
                'nd' => $fnd->count(),
                'monto' => round($fi->sum('mto_imp_venta') + $fb->sum('mto_imp_venta') + $fnd->sum('mto_imp_venta') - $fnc->sum('mto_imp_venta'), 2),
            ];
        })->values()->all();
    }

    private function topProductos(Tenant $tenant, array $filters): array
    {
        $desde = $filters['fecha_desde'];
        $hasta = $filters['fecha_hasta'];

        $query = "
            SELECT descripcion, SUM(cantidad) as total_cantidad, SUM(cantidad * mto_precio_unitario) as total_monto
            FROM (
                SELECT ii.descripcion, ii.cantidad, ii.mto_precio_unitario
                FROM invoice_items ii
                JOIN invoices i ON i.id = ii.invoice_id
                WHERE i.tenant_id = ? AND i.fecha_emision BETWEEN ? AND ? AND i.deleted_at IS NULL
                UNION ALL
                SELECT bi.descripcion, bi.cantidad, bi.mto_precio_unitario
                FROM boleta_items bi
                JOIN boletas b ON b.id = bi.boleta_id
                WHERE b.tenant_id = ? AND b.fecha_emision BETWEEN ? AND ? AND b.deleted_at IS NULL
            ) combined
            GROUP BY descripcion
            ORDER BY total_monto DESC
            LIMIT 10
        ";

        $results = DB::select($query, [$tenant->id, $desde, $hasta, $tenant->id, $desde, $hasta]);

        return array_map(fn ($r) => [
            'descripcion' => $r->descripcion,
            'cantidad' => (float) $r->total_cantidad,
            'monto' => round((float) $r->total_monto, 2),
        ], $results);
    }

    private function filtrosAplicados(array $filters): array
    {
        $aplicados = [];
        $labels = [
            'sucursal_id' => 'Sucursal',
            'serie' => 'Serie',
            'client_num_doc' => 'Cliente',
            'estado_sunat' => 'Estado SUNAT',
            'estado_pago' => 'Estado Pago',
            'tipo_moneda' => 'Moneda',
            'agrupacion' => 'Agrupación',
            'vencido' => 'Solo vencidos',
        ];

        foreach ($labels as $key => $label) {
            if (! empty($filters[$key])) {
                $aplicados[] = ['filtro' => $label, 'valor' => $filters[$key]];
            }
        }

        return $aplicados;
    }
}
