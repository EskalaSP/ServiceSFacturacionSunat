<?php

namespace App\Services\Pdf;

use App\Models\DispatchGuide;
use App\Models\Tenant;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Model;

class DocumentDataMapper
{
    public function map(Model $document, Tenant $tenant): array
    {
        if ($document instanceof DispatchGuide) {
            return $this->mapDispatchGuide($document, $tenant);
        }

        return $this->mapStandardDocument($document, $tenant);
    }

    private function mapStandardDocument(Model $document, Tenant $tenant): array
    {
        $items = $document->items->map(fn ($item) => [
            'codigo' => $item->codigo ?? $item->cod_producto ?? '-',
            'descripcion' => $item->descripcion,
            'unidad' => $item->unidad ?? $item->und_medida ?? 'NIU',
            'cantidad' => (float) $item->cantidad,
            'precio_unitario' => (float) ($item->mto_precio_unitario ?? $item->precio_unitario ?? 0),
            'igv' => (float) ($item->igv ?? 0),
            'importe' => (float) ($item->mto_valor_venta ?? $item->total ?? 0),
            'total_item' => (float) ($item->mto_precio_unitario ?? $item->precio_unitario ?? 0) * (float) $item->cantidad,
        ])->toArray();

        $tipoDocumento = $document->getTipoDocumento();

        $data = [
            'tipo_documento' => $tipoDocumento,
            'titulo' => DocumentTypeConfig::titulo($tipoDocumento),
            'serie' => $document->serie,
            'correlativo' => $document->correlativo,
            'numero_completo' => $document->numero_completo,
            'fecha_emision' => $document->fecha_emision?->format('Y-m-d') ?? '',
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
            'mto_isc' => (float) ($document->mto_isc ?? 0),
            'mto_icbper' => (float) ($document->mto_icbper ?? 0),
            'total_impuestos' => (float) $document->total_impuestos,
            'valor_venta' => (float) $document->valor_venta,
            'sub_total' => (float) $document->sub_total,
            'mto_imp_venta' => (float) $document->mto_imp_venta,
            'total_anticipos' => (float) ($document->total_anticipos ?? 0),
            'total_descuentos' => (float) ($document->total_descuentos ?? 0),

            'leyenda' => $document->leyenda ?? '',
            'observacion' => $document->observacion ?? null,
            'hash_cpe' => $document->hash_cpe ?? '',
            'sunat_status' => $document->sunat_status ?? '',
            'sunat_description' => $document->sunat_description ?? '',

            // Campos especiales
            'cuotas' => $document->cuotas ?? null,
            'detraccion' => $document->detraccion ?? null,
            'percepcion' => $document->percepcion ?? null,
            'anticipos' => $document->anticipos ?? null,

            // Nota de crédito/débito
            'doc_afectado_tipo' => $document->doc_afectado_tipo ?? null,
            'doc_afectado_serie' => $document->doc_afectado_serie ?? null,
            'doc_afectado_correlativo' => $document->doc_afectado_correlativo ?? null,
            'cod_motivo' => $document->cod_motivo ?? null,
            'des_motivo' => $document->des_motivo ?? null,

            'logo_base64' => $this->getLogoBase64($tenant),
            'qr_base64' => $this->generateQrBase64($document, $tenant),
            'moneda_simbolo' => $this->getMonedaSimbolo($document->tipo_moneda ?? 'PEN'),
        ];

        return $data;
    }

    private function mapDispatchGuide(DispatchGuide $guide, Tenant $tenant): array
    {
        $items = collect($guide->items ?? [])->map(fn ($item) => [
            'codigo' => $item['codigo'] ?? '-',
            'descripcion' => $item['descripcion'] ?? $item['nombre'] ?? '',
            'unidad' => $item['unidad'] ?? 'NIU',
            'cantidad' => (float) ($item['cantidad'] ?? 0),
            'precio_unitario' => 0,
            'igv' => 0,
            'importe' => 0,
            'total_item' => 0,
        ])->toArray();

        $transportista = $guide->transportista ?? [];
        $vehiculo = $guide->vehiculo ?? [];
        $conductor = $guide->conductor ?? [];

        return [
            'tipo_documento' => '09',
            'titulo' => DocumentTypeConfig::titulo('09'),
            'serie' => $guide->serie,
            'correlativo' => $guide->correlativo,
            'numero_completo' => $guide->numero_completo,
            'fecha_emision' => $guide->fecha_emision?->format('Y-m-d') ?? '',
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
        ];
    }

    private function getLogoBase64(Tenant $tenant): ?string
    {
        if (empty($tenant->logo_path)) {
            return null;
        }

        $fullPath = storage_path('app/public/' . $tenant->logo_path);
        if (! file_exists($fullPath)) {
            return null;
        }

        $content = file_get_contents($fullPath);
        $mime = mime_content_type($fullPath);

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    private function generateQrBase64(Model $document, Tenant $tenant): string
    {
        $tipoDoc = method_exists($document, 'getTipoDocumento')
            ? $document->getTipoDocumento()
            : '09';

        $total = $document->mto_imp_venta ?? '0.00';
        $fecha = $document->fecha_emision instanceof \DateTimeInterface
            ? $document->fecha_emision->format('Y-m-d')
            : ($document->fecha_emision ?? '');

        // Formato QR SUNAT: RUC|TIPO|SERIE|CORRELATIVO|IGV|TOTAL|FECHA|TIPO_DOC_RECEPTOR|NUM_DOC_RECEPTOR
        $qrData = implode('|', [
            $tenant->ruc,
            $tipoDoc,
            $document->serie,
            $document->correlativo,
            $document->mto_igv ?? '0.00',
            $total,
            $fecha,
            $document->client_tipo_doc ?? $document->destinatario_tipo_doc ?? '',
            $document->client_num_doc ?? $document->destinatario_num_doc ?? '',
        ]);

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($qrData);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
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
