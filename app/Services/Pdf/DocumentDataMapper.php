<?php

namespace App\Services\Pdf;

use App\Models\DispatchGuide;
use App\Models\InternalDocument;
use App\Models\Retention;
use App\Models\Tenant;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Model;

class DocumentDataMapper
{
    /** @var array<int, string|null> */
    private static array $logoCache = [];

    /** @var array<string, string> */
    private static array $qrCache = [];

    public function map(Model $document, Tenant $tenant): array
    {
        if ($document instanceof DispatchGuide) {
            return $this->mapDispatchGuide($document, $tenant);
        }

        if ($document instanceof InternalDocument) {
            return $this->mapInternalDocument($document, $tenant);
        }

        if ($document instanceof Retention) {
            return $this->mapRetention($document, $tenant);
        }

        return $this->mapStandardDocument($document, $tenant);
    }

    private function mapStandardDocument(Model $document, Tenant $tenant): array
    {
        $items = $document->items->map(function ($item) {
            $valorUnitario  = (float) ($item->mto_valor_unitario ?? 0);
            $precioUnitario = (float) ($item->mto_precio_unitario ?? $item->precio_unitario ?? 0);
            $cantidad       = (float) $item->cantidad;
            $valorVenta     = (float) ($item->mto_valor_venta ?? 0);
            $igv            = (float) ($item->igv ?? 0);
            $descuentoBase  = (float) ($item->descuento ?? 0);

            return [
                'codigo'          => $item->codigo ?? $item->cod_producto ?? '-',
                'descripcion'     => $item->descripcion,
                'unidad'          => $item->unidad ?? $item->und_medida ?? 'NIU',
                'cantidad'        => $cantidad,
                'valor_unitario'  => $valorUnitario,   // BASE (sin IGV) — para mostrar en PDF
                'precio_unitario' => $precioUnitario,  // CON IGV
                'igv'             => $igv,
                'importe'         => $valorVenta,
                'descuento'       => $descuentoBase,
                'total_item'      => $valorVenta,
                'icbper'          => (float) ($item->mto_icbper ?? 0),
            ];
        })->toArray();

        // Tasa de IGV a mostrar en los totales: se toma de la línea gravada
        // (columna porcentaje_igv, ej. 18.00) — NO se recalcula desde los montos
        // redondeados, porque 15.25/84.75 daría 17.99% en vez de 18%. Respeta
        // igv_rate_override (10.5% MYPE, 8%, etc.) porque ese valor ya quedó
        // guardado en porcentaje_igv al emitir el documento.
        $igvPct = null;
        foreach ($document->items as $item) {
            $pct     = (float) ($item->porcentaje_igv ?? 0);
            $igvLine = (float) ($item->igv ?? 0);
            if ($pct > 0 && $igvLine > 0) {
                $igvPct = $pct;
                break;
            }
        }

        $tipoDocumento = $document->getTipoDocumento();

        // Detectar comprobante de contingencia: serie totalmente numérica
        // (ej. "0001" en vez de "F001"/"B001")
        $esContingencia = ctype_digit((string) $document->serie);
        $tituloBase = DocumentTypeConfig::titulo($tipoDocumento);
        if ($esContingencia && in_array($tipoDocumento, ['01', '03'], true)) {
            $tituloBase = str_replace('ELECTRÓNICA', 'DE CONTINGENCIA', $tituloBase);
        }

        $data = [
            'tipo_documento' => $tipoDocumento,
            'titulo' => $tituloBase,
            'es_contingencia' => $esContingencia,
            'serie' => $document->serie,
            'correlativo' => $document->correlativo,
            'numero_completo' => $document->numero_completo,
            'fecha_emision' => $document->fecha_emision?->format('d/m/Y h:i A') ?? '',
            'fecha_vencimiento' => $document->fecha_vencimiento?->format('Y-m-d') ?? null,
            'tipo_moneda' => $document->tipo_moneda ?? 'PEN',
            'forma_pago' => $document->forma_pago ?? null,
            'tipo_operacion' => $document->tipo_operacion ?? null,

            // Emisor
            'emisor' => [
                'ruc' => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
                'nombre_comercial' => $tenant->nombre_comercial ?? $tenant->razon_social,
                'direccion' => $tenant->direccion ?? '',
                'ubigeo' => $tenant->ubigeo ?? '',
                'cod_local' => $document->cod_local ?? '0000',
            ],

            // Receptor
            'receptor' => [
                'tipo_doc' => $document->client_tipo_doc,
                'num_doc' => $document->client_num_doc,
                'razon_social' => $document->client_razon_social,
                'direccion' => $document->client_direccion ?? '',
            ],

            'items' => $items,

            // Totales
            'mto_oper_gravadas' => (float) $document->mto_oper_gravadas,
            'mto_oper_exoneradas' => (float) $document->mto_oper_exoneradas,
            'mto_oper_inafectas' => (float) $document->mto_oper_inafectas,
            'mto_oper_gratuitas' => (float) $document->mto_oper_gratuitas,
            'mto_igv' => (float) $document->mto_igv,
            'igv_pct' => $igvPct, // tasa real del documento (18, 10.5, ...) o null
            'mto_base_ivap' => (float) ($document->mto_base_ivap ?? 0),
            'mto_ivap' => (float) ($document->mto_ivap ?? 0),
            'mto_isc' => (float) ($document->mto_isc ?? 0),
            'mto_icbper' => (float) ($document->mto_icbper ?? 0),
            'total_impuestos' => (float) $document->total_impuestos,
            'valor_venta' => (float) $document->valor_venta,
            'sub_total' => (float) $document->sub_total,
            'mto_imp_venta' => (float) $document->mto_imp_venta,
            'total_anticipos' => (float) ($document->total_anticipos ?? 0),
            'total_descuentos' => (float) ($document->total_descuentos ?? 0),

            'leyenda' => $document->leyenda ?? '',
            'leyendas_sunat' => $this->buildSunatLegends($document, $items),
            'observacion' => $document->observacion ?? null,
            'hash_cpe' => $document->hash_cpe ?? '',
            'sunat_status' => $document->sunat_status ?? '',
            'sunat_description' => $document->sunat_description ?? '',

            // Pagos/adelanto
            'pagos' => $document->relationLoaded('payments') && $document->payments->count() > 0
                ? $document->payments->map(fn ($p) => [
                    'metodo' => $p->metodo_pago ?? '',
                    'monto' => (float) $p->monto,
                    'fecha' => $p->fecha?->format('Y-m-d') ?? $p->created_at?->format('Y-m-d') ?? '',
                ])->toArray()
                : null,

            // Campos especiales
            'cuotas' => $document->cuotas ?? null,
            'detraccion' => $this->normalizeDetraccion($document->detraccion ?? null),
            'percepcion' => $this->normalizePercepcion($document->percepcion ?? null),
            'anticipos' => $document->anticipos ?? null,
            'guias' => $document->guias ?? null,

            // Nota de crédito/débito
            'doc_afectado_tipo' => $document->doc_afectado_tipo ?? null,
            'doc_afectado_serie' => $document->doc_afectado_serie ?? null,
            'doc_afectado_correlativo' => $document->doc_afectado_correlativo ?? null,
            'cod_motivo' => $document->cod_motivo ?? null,
            'des_motivo' => $document->des_motivo ?? null,

            'logo_base64' => $this->getLogoBase64($tenant),
            'qr_base64' => $this->generateQrBase64($document, $tenant),
            'moneda_simbolo' => $this->getMonedaSimbolo($document->tipo_moneda ?? 'PEN'),
            ...$this->getBusinessConfig($tenant),
        ];

        return $data;
    }

    private function mapRetention(Retention $retention, Tenant $tenant): array
    {
        $regimenes = ['01' => 'Tasa 3% (Régimen General)', '02' => 'Tasa 6% (Régimen Especial)'];

        $documentos = $retention->items->map(fn ($item) => [
            'tipo_doc'        => $item->tipo_doc,
            'num_doc'         => $item->num_doc,
            'fecha_emision'   => $item->fecha_emision_doc?->format('d/m/Y') ?? '',
            'moneda'          => $item->moneda ?? 'PEN',
            'imp_total'       => (float) $item->imp_total,
            'fecha_retencion' => $item->fecha_retencion?->format('d/m/Y') ?? '',
            'imp_retenido'    => (float) $item->imp_retenido,
            'imp_pagar'       => (float) $item->imp_pagar,
        ])->toArray();

        return [
            'tipo_documento'  => '20',
            'titulo'          => DocumentTypeConfig::titulo('20'),
            'serie'           => $retention->serie,
            'correlativo'     => $retention->correlativo,
            'numero_completo' => $retention->numero_completo,
            'fecha_emision'   => $retention->fecha_emision?->format('d/m/Y h:i A') ?? '',
            'tipo_moneda'     => 'PEN',

            'emisor' => [
                'ruc'             => $tenant->ruc,
                'razon_social'    => $tenant->razon_social,
                'nombre_comercial'=> $tenant->nombre_comercial ?? $tenant->razon_social,
                'direccion'       => $tenant->direccion ?? '',
                'ubigeo'          => $tenant->ubigeo ?? '',
                'cod_local'       => '0000',
            ],

            // Para retenciones, el "receptor" es el proveedor al que se retiene
            'receptor' => [
                'tipo_doc'    => $retention->proveedor_tipo_doc,
                'num_doc'     => $retention->proveedor_num_doc,
                'razon_social'=> $retention->proveedor_razon_social,
                'direccion'   => $retention->proveedor_direccion ?? '',
            ],

            'regimen'       => $retention->regimen,
            'tasa'          => (float) $retention->tasa,
            'imp_retenido'  => (float) $retention->imp_retenido,
            'imp_pagado'    => (float) $retention->imp_pagado,
            'regimen_label' => $regimenes[$retention->regimen] ?? "Tasa {$retention->tasa}%",
            'documentos_retenidos' => $documentos,

            'hash_cpe'          => $retention->hash_cpe ?? '',
            'sunat_status'      => $retention->sunat_status ?? '',
            'sunat_description' => $retention->sunat_description ?? '',
            'observacion'       => $retention->observacion ?? null,
            'leyenda'           => '',
            'fecha_vencimiento' => null,
            'tipo_operacion'    => null,

            'logo_base64'    => $this->getLogoBase64($tenant),
            'qr_base64'      => $this->generateQrBase64($retention, $tenant),
            'moneda_simbolo' => 'S/',
            ...$this->getBusinessConfig($tenant),
        ];
    }

    private function mapDispatchGuide(DispatchGuide $guide, Tenant $tenant): array
    {
        $tipoDocumento = $guide->tipo_documento ?? '09';

        $items = collect($guide->items ?? [])->map(fn ($item) => [
            'codigo' => $item['codigo'] ?? '-',
            'descripcion' => $item['descripcion'] ?? $item['nombre'] ?? '',
            'unidad' => $item['unidad'] ?? 'NIU',
            'cantidad' => (float) ($item['cantidad'] ?? 0),
            'valor_unitario' => 0,
            'precio_unitario' => 0,
            'igv' => 0,
            'importe' => 0,
            'total_item' => 0,
        ])->toArray();

        $transportista = $guide->transportista ?? [];
        $vehiculo = $guide->vehiculo ?? [];
        $conductor = $guide->conductor ?? [];

        return [
            'tipo_documento' => $tipoDocumento,
            'titulo' => DocumentTypeConfig::titulo($tipoDocumento),
            'serie' => $guide->serie,
            'correlativo' => $guide->correlativo,
            'numero_completo' => $guide->numero_completo,
            'fecha_emision' => $guide->fecha_emision?->format('d/m/Y h:i A') ?? '',
            'tipo_moneda' => 'PEN',

            'emisor' => [
                'ruc' => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
                'nombre_comercial' => $tenant->nombre_comercial ?? $tenant->razon_social,
                'direccion' => $tenant->direccion ?? '',
                'ubigeo' => $tenant->ubigeo ?? '',
                'cod_local' => $guide->cod_local ?? '0000',
            ],

            'receptor' => [
                'tipo_doc' => $guide->destinatario_tipo_doc,
                'num_doc' => $guide->destinatario_num_doc,
                'razon_social' => $guide->destinatario_razon_social,
                'direccion' => '',
            ],

            'items' => $items,

            // Datos de despacho
            'dispatch' => [
                'cod_traslado' => $guide->cod_traslado,
                'mod_traslado' => $guide->mod_traslado,
                'fecha_traslado' => $guide->fecha_traslado?->format('Y-m-d') ?? '',
                'peso_total' => (float) $guide->peso_total,
                'und_peso_total' => $guide->und_peso_total ?? 'KGM',
                'num_bultos' => $guide->num_bultos,
                'partida_ubigeo' => $guide->partida_ubigeo,
                'partida_direccion' => $guide->partida_direccion,
                'llegada_ubigeo' => $guide->llegada_ubigeo,
                'llegada_direccion' => $guide->llegada_direccion,
                'transportista' => [
                    'tipo_doc' => $transportista['tipo_doc'] ?? '',
                    'num_doc' => $transportista['num_doc'] ?? '',
                    'razon_social' => $transportista['razon_social'] ?? $transportista['denominacion'] ?? '',
                ],
                'vehiculo' => [
                    'placa' => $vehiculo['placa'] ?? $vehiculo['nroPlaca'] ?? '',
                ],
                'conductor' => is_array($conductor) && isset($conductor[0])
                    ? collect($conductor)->map(fn ($c) => [
                        'tipo_doc' => $c['tipo_doc'] ?? $c['tipoDoc'] ?? '',
                        'num_doc' => $c['num_doc'] ?? $c['nroDoc'] ?? '',
                        'nombres' => $c['nombres'] ?? '',
                        'apellidos' => $c['apellidos'] ?? '',
                        'licencia' => $c['licencia'] ?? '',
                    ])->toArray()
                    : [[
                        'tipo_doc' => $conductor['tipo_doc'] ?? $conductor['tipoDoc'] ?? '',
                        'num_doc' => $conductor['num_doc'] ?? $conductor['nroDoc'] ?? '',
                        'nombres' => $conductor['nombres'] ?? '',
                        'apellidos' => $conductor['apellidos'] ?? '',
                        'licencia' => $conductor['licencia'] ?? '',
                    ]],
            ],

            // Totales (cero para guías)
            'mto_oper_gravadas' => 0,
            'mto_oper_exoneradas' => 0,
            'mto_oper_inafectas' => 0,
            'mto_oper_gratuitas' => 0,
            'mto_igv' => 0,
            'mto_isc' => 0,
            'mto_icbper' => 0,
            'mto_imp_venta' => 0,
            'total_anticipos' => 0,
            'total_descuentos' => 0,
            'fecha_vencimiento' => null,

            'hash_cpe' => $guide->hash_cpe ?? '',
            'sunat_status' => $guide->sunat_status ?? '',
            'sunat_description' => $guide->sunat_description ?? '',
            'observacion' => null,
            'leyenda' => '',

            'logo_base64' => $this->getLogoBase64($tenant),
            'qr_base64' => $this->generateQrBase64($guide, $tenant),
            'moneda_simbolo' => 'S/',
            ...$this->getBusinessConfig($tenant),
        ];
    }

    private function mapInternalDocument(InternalDocument $document, Tenant $tenant): array
    {
        $items = $document->items->map(fn ($item) => [
            'codigo' => $item->codigo ?? '-',
            'descripcion' => $item->descripcion,
            'unidad' => $item->unidad ?? 'NIU',
            'cantidad' => (float) $item->cantidad,
            'precio_unitario' => (float) ($item->mto_precio_unitario ?? 0),
            'igv' => (float) ($item->igv ?? 0),
            'importe' => (float) ($item->mto_valor_venta ?? 0),
            'total_item' => (float) ($item->mto_precio_unitario ?? 0) * (float) $item->cantidad,
        ])->toArray();

        $tipoDocumento = $document->getTipoDocumento();

        return [
            'tipo_documento' => $tipoDocumento,
            'titulo' => DocumentTypeConfig::titulo($tipoDocumento),
            'serie' => '',
            'correlativo' => '',
            'numero_completo' => $document->numero,
            'fecha_emision' => $document->fecha_emision?->format('d/m/Y h:i A') ?? '',
            'fecha_vencimiento' => $document->fecha_vencimiento?->format('Y-m-d') ?? null,
            'tipo_moneda' => $document->tipo_moneda ?? 'PEN',
            'forma_pago' => $document->forma_pago ?? null,
            'tipo_operacion' => null,

            'emisor' => [
                'ruc' => $tenant->ruc,
                'razon_social' => $tenant->razon_social,
                'nombre_comercial' => $tenant->nombre_comercial ?? $tenant->razon_social,
                'direccion' => $tenant->direccion ?? '',
                'ubigeo' => $tenant->ubigeo ?? '',
                'cod_local' => '0000',
            ],

            'receptor' => [
                'tipo_doc' => $document->client_tipo_doc,
                'num_doc' => $document->client_num_doc,
                'razon_social' => $document->client_razon_social,
                'direccion' => $document->client_direccion ?? '',
            ],

            'items' => $items,

            'mto_oper_gravadas' => (float) $document->mto_oper_gravadas,
            'mto_oper_exoneradas' => (float) $document->mto_oper_exoneradas,
            'mto_oper_inafectas' => (float) $document->mto_oper_inafectas,
            'mto_oper_gratuitas' => (float) $document->mto_oper_gratuitas,
            'mto_igv' => (float) $document->mto_igv,
            'mto_base_ivap' => (float) ($document->mto_base_ivap ?? 0),
            'mto_ivap' => (float) ($document->mto_ivap ?? 0),
            'mto_isc' => (float) ($document->mto_isc ?? 0),
            'mto_icbper' => (float) ($document->mto_icbper ?? 0),
            'total_impuestos' => (float) $document->total_impuestos,
            'valor_venta' => (float) $document->valor_venta,
            'sub_total' => (float) $document->sub_total,
            'mto_imp_venta' => (float) $document->mto_imp_venta,
            'total_anticipos' => 0,
            'total_descuentos' => (float) ($document->total_descuentos ?? 0),

            'leyenda' => '',
            'observacion' => $document->observacion ?? null,
            'hash_cpe' => '',
            'sunat_status' => '',
            'sunat_description' => '',

            'cuotas' => null,
            'detraccion' => null,
            'percepcion' => null,
            'anticipos' => null,
            'doc_afectado_tipo' => null,
            'doc_afectado_serie' => null,
            'doc_afectado_correlativo' => null,
            'cod_motivo' => null,
            'des_motivo' => null,

            'logo_base64' => $this->getLogoBase64($tenant),
            'qr_base64' => '', // Los documentos internos no tienen código QR
            'moneda_simbolo' => $this->getMonedaSimbolo($document->tipo_moneda ?? 'PEN'),
            ...$this->getBusinessConfig($tenant),
        ];
    }

    private function getBusinessConfig(Tenant $tenant): array
    {
        return [
            'telefonos' => $tenant->telefonos ?? [],
            'emails' => $tenant->emails ?? [],
            'cuentas_bancarias' => $tenant->cuentas_bancarias ?? [],
            'billeteras_digitales' => $tenant->billeteras_digitales ?? [],
            'mensaje_agradecimiento' => $tenant->mensaje_agradecimiento,
            'mensaje_promocional' => $tenant->mensaje_promocional,
        ];
    }

    private function getLogoBase64(Tenant $tenant): ?string
    {
        if (empty($tenant->logo_path)) {
            return null;
        }

        if (isset(self::$logoCache[$tenant->id])) {
            return self::$logoCache[$tenant->id];
        }

        $fullPath = storage_path('app/public/' . $tenant->logo_path);
        if (! file_exists($fullPath)) {
            return self::$logoCache[$tenant->id] = null;
        }

        $content = file_get_contents($fullPath);
        $mime = mime_content_type($fullPath);

        return self::$logoCache[$tenant->id] = 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    private function generateQrBase64(Model $document, Tenant $tenant): string
    {
        $fecha = $document->fecha_emision instanceof \DateTimeInterface
            ? $document->fecha_emision->format('Y-m-d')
            : ($document->fecha_emision ?? '');

        // Retención (tipo 20) y Percepción (tipo 40) tienen campos distintos
        if ($document instanceof Retention) {
            $qrData = implode('|', [
                $tenant->ruc, '20',
                $document->serie, $document->correlativo,
                number_format((float) $document->imp_retenido, 2, '.', ''),
                number_format((float) $document->imp_pagado,   2, '.', ''),
                $fecha,
                $document->proveedor_tipo_doc ?? '',
                $document->proveedor_num_doc  ?? '',
            ]);
        } else {
            $tipoDoc = method_exists($document, 'getTipoDocumento')
                ? $document->getTipoDocumento()
                : '09';

            // Formato QR SUNAT: RUC|TIPO|SERIE|CORRELATIVO|IGV|TOTAL|FECHA|TIPO_DOC_RECEPTOR|NUM_DOC_RECEPTOR
            $qrData = implode('|', [
                $tenant->ruc,
                $tipoDoc,
                $document->serie,
                $document->correlativo,
                $document->mto_igv ?? '0.00',
                $document->mto_imp_venta ?? '0.00',
                $fecha,
                $document->client_tipo_doc ?? $document->destinatario_tipo_doc ?? '',
                $document->client_num_doc  ?? $document->destinatario_num_doc  ?? '',
            ]);
        }

        // Caché por cadena de datos QR (determinista)
        if (isset(self::$qrCache[$qrData])) {
            return self::$qrCache[$qrData];
        }

        $renderer = new ImageRenderer(
            new RendererStyle(150),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($qrData);

        return self::$qrCache[$qrData] = 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function buildSunatLegends($document, array $items): array
    {
        $legends = [];
        if (! empty($document->detraccion)) {
            $legends[] = 'Operación sujeta a detracción';
        }
        if (! empty($document->percepcion)) {
            $legends[] = 'COMPROBANTE DE PERCEPCIÓN';
        }
        $tieneIvap = $document->relationLoaded('items')
            ? $document->items->contains(fn($it) => ($it->tip_afe_igv ?? '') === '17')
            : false;
        if ($tieneIvap) {
            $legends[] = 'Operación sujeta al IVAP';
        }
        return $legends;
    }

    private function normalizeDetraccion(?array $detraccion): ?array
    {
        if (empty($detraccion)) {
            return null;
        }

        return [
            'codigo' => $detraccion['cod_bien'] ?? $detraccion['codigo'] ?? '',
            'medio_pago' => $detraccion['cod_medio_pago'] ?? $detraccion['medio_pago'] ?? '001',
            'cuenta' => $detraccion['cta_banco'] ?? $detraccion['cuenta'] ?? '',
            'porcentaje' => (float) ($detraccion['porcentaje'] ?? 0),
            'monto' => (float) ($detraccion['monto'] ?? 0),
        ];
    }

    private function normalizePercepcion(?array $percepcion): ?array
    {
        if (empty($percepcion)) {
            return null;
        }

        $porcentaje = (float) ($percepcion['porcentaje'] ?? 0);
        $monto      = (float) ($percepcion['mto']      ?? $percepcion['monto']  ?? 0);
        $mto_base   = (float) ($percepcion['mto_base'] ?? $percepcion['base']   ?? 0);
        $mto_total  = (float) ($percepcion['mto_total'] ?? 0);

        if ($mto_total === 0.0) {
            $mto_total = round($mto_base + $monto, 2);
        }

        return [
            'codigo'    => $percepcion['cod_regimen'] ?? $percepcion['cod_reg'] ?? $percepcion['codigo'] ?? '',
            'porcentaje' => $porcentaje,
            'monto'     => $monto,
            'mto_base'  => $mto_base,
            'mto_total' => $mto_total,
        ];
    }

    private function getMonedaSimbolo(string $tipoMoneda): string
    {
        return match ($tipoMoneda) {
            'PEN' => 'S/',
            'USD' => '$',
            'EUR' => '€',
            default => $tipoMoneda,
        };
    }
}
