<?php

namespace App\Actions\Documents;

use App\Events\DocumentCreated;
use App\Events\DocumentRejected;
use App\Events\DocumentSent;
use App\Jobs\NotifyWebhookJob;
use App\Jobs\SendDocumentToSunat;
use App\Models\Client;
use App\Models\Document;
use App\Models\Serie;
use App\Models\Tenant;
use App\Services\Greenter\GreenterService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Support\Facades\DB;

class CreateDocumentAction
{
    public function execute(Tenant $tenant, array $data, bool $async = false): Document
    {
        return DB::transaction(function () use ($tenant, $data, $async) {
            // 1. Obtener serie y siguiente correlativo
            $serie = Serie::where('tenant_id', $tenant->id)
                ->where('tipo_documento', $data['tipo_documento'])
                ->where('serie', $data['serie'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $correlativo = $serie->nextCorrelativo();
            $data['correlativo'] = $correlativo;

            // 2. Crear o encontrar cliente
            $client = $this->resolveClient($tenant, $data['cliente']);

            // 3. Pre-calcular items y totales
            $calculatedItems = $this->calculateItems($data['items']);
            $totals = $this->calculateTotals($calculatedItems, $data);

            // Inyectar totales calculados en $data para que el Builder los use
            $data = array_merge($data, $totals);

            // Auto-generar leyenda si no se proporcionó
            if (empty($data['leyenda'])) {
                $data['leyenda'] = $this->generateLeyenda($totals['mto_imp_venta'], $data['tipo_moneda'] ?? 'PEN');
            }

            // 4. Crear el documento en DB con totales correctos
            $document = Document::create([
                'tenant_id' => $tenant->id,
                'cod_local' => $data['cod_local'] ?? '0000',
                'client_id' => $client->id,
                'tipo_documento' => $data['tipo_documento'],
                'serie' => $data['serie'],
                'correlativo' => $correlativo,
                'fecha_emision' => $data['fecha_emision'],
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'tipo_operacion' => $data['tipo_operacion'] ?? '0101',
                'tipo_moneda' => $data['tipo_moneda'] ?? 'PEN',
                'forma_pago' => $data['forma_pago'] ?? 'Contado',
                'client_tipo_doc' => $data['cliente']['tipo_doc'],
                'client_num_doc' => $data['cliente']['num_doc'],
                'client_razon_social' => $data['cliente']['razon_social'],
                'client_direccion' => $data['cliente']['direccion'] ?? null,
                'mto_oper_gravadas' => $totals['mto_oper_gravadas'],
                'mto_oper_exoneradas' => $totals['mto_oper_exoneradas'],
                'mto_oper_inafectas' => $totals['mto_oper_inafectas'],
                'mto_oper_gratuitas' => $totals['mto_oper_gratuitas'],
                'mto_igv' => $totals['mto_igv'],
                'mto_isc' => $totals['mto_isc'],
                'mto_icbper' => $totals['mto_icbper'],
                'total_impuestos' => $totals['total_impuestos'],
                'valor_venta' => $totals['valor_venta'],
                'sub_total' => $totals['sub_total'],
                'mto_imp_venta' => $totals['mto_imp_venta'],
                'total_anticipos' => $data['total_anticipos'] ?? 0,
                'total_descuentos' => $data['total_descuentos'] ?? 0,
                'leyenda' => $data['leyenda'] ?? null,
                'observacion' => $data['observacion'] ?? null,
                'doc_afectado_tipo' => $data['doc_afectado_tipo'] ?? null,
                'doc_afectado_serie' => $data['doc_afectado_serie'] ?? null,
                'doc_afectado_correlativo' => $data['doc_afectado_correlativo'] ?? null,
                'cod_motivo' => $data['cod_motivo'] ?? null,
                'des_motivo' => $data['des_motivo'] ?? null,
                'cuotas' => $data['cuotas'] ?? null,
                'detraccion' => $data['detraccion'] ?? null,
                'percepcion' => $data['percepcion'] ?? null,
                'anticipos' => $data['anticipos'] ?? null,
                'descuentos_globales' => $data['descuentos_globales'] ?? null,
                'guias' => $data['guias'] ?? null,
                'extras' => $data['extras'] ?? null,
                'sunat_status' => 'pendiente',
            ]);

            // 5. Crear items en DB
            foreach ($calculatedItems as $item) {
                $document->items()->create($item);
            }

            event(new DocumentCreated($document));

            // 5. Enviar a SUNAT (sync o async)
            if ($async) {
                SendDocumentToSunat::dispatch($document->id);
                $document->update(['sunat_status' => 'enviado']);
            } else {
                $this->sendToSunat($tenant, $document, $data);
            }

            return $document->fresh(['items']);
        });
    }

    private function sendToSunat(Tenant $tenant, Document $document, array $data): void
    {
        $service = new GreenterService($tenant);
        $storage = new DocumentStorageService();

        $greenterDoc = match ($document->tipo_documento) {
            '01', '03' => $service->buildInvoice($data),
            '07', '08' => $service->buildNote($data),
            default => throw new \RuntimeException("Tipo no soportado: {$document->tipo_documento}"),
        };

        $result = $service->send($greenterDoc);

        if ($result['success']) {
            $document->update([
                'sunat_status' => ($result['accepted'] ?? true) ? 'aceptado' : 'rechazado',
                'sunat_code' => $result['code'] ?? null,
                'sunat_description' => $result['description'] ?? null,
                'sunat_notes' => $result['notes'] ?? null,
                'hash_cpe' => $result['hash'] ?? null,
                'sent_at' => now(),
            ]);

            // Guardar XML y CDR en disco
            if (! empty($result['xml'])) {
                $storage->storeXml($document, $tenant, $result['xml']);
            }
            if (! empty($result['cdr_zip'])) {
                $storage->storeCdr($document, $tenant, $result['cdr_zip']);
            }

            event(new DocumentSent($document, $result));

            if ($tenant->webhook_url) {
                NotifyWebhookJob::dispatch($document->id, 'document.sent');
            }
        } else {
            $updateData = [
                'sunat_status' => 'rechazado',
                'sunat_code' => $result['error_code'] ?? null,
                'sunat_description' => $result['error_message'] ?? null,
            ];
            $document->update($updateData);

            // Guardar XML aunque sea rechazado (para diagnóstico)
            if (! empty($result['xml'])) {
                $storage->storeXml($document, $tenant, $result['xml']);
            }

            event(new DocumentRejected($document, $result));
        }
    }

    private function calculateItems(array $items): array
    {
        $calculated = [];

        foreach ($items as $item) {
            $porcentajeIgv = (float) ($item['porcentaje_igv'] ?? 18);
            $tipAfeIgv = $item['tip_afe_igv'] ?? '10';
            $cantidad = (float) $item['cantidad'];
            $precioUnitario = (float) $item['precio_unitario'];

            if ($tipAfeIgv === '10') {
                // Gravado: precio_unitario incluye IGV
                $valorUnitario = round($precioUnitario / (1 + $porcentajeIgv / 100), 4);
                $valorVenta = round($valorUnitario * $cantidad, 2);
                $igv = round($valorVenta * $porcentajeIgv / 100, 2);
            } elseif ($tipAfeIgv === '20') {
                // Exonerado
                $valorUnitario = $precioUnitario;
                $valorVenta = round($valorUnitario * $cantidad, 2);
                $igv = 0;
                $porcentajeIgv = 0;
            } elseif ($tipAfeIgv === '30') {
                // Inafecto
                $valorUnitario = $precioUnitario;
                $valorVenta = round($valorUnitario * $cantidad, 2);
                $igv = 0;
                $porcentajeIgv = 0;
            } else {
                // Gratuito u otros (11, 12, 13, 14, 15, 16, 17, 21, 31, etc.)
                $valorUnitario = (float) ($item['mto_valor_unitario'] ?? $precioUnitario);
                $valorVenta = round($valorUnitario * $cantidad, 2);
                $igv = (float) ($item['igv'] ?? 0);
            }

            // Permitir override manual
            $valorUnitario = (float) ($item['mto_valor_unitario'] ?? $valorUnitario);
            $valorVenta = (float) ($item['mto_valor_venta'] ?? $valorVenta);
            $igv = (float) ($item['igv'] ?? $igv);
            $baseIgv = (float) ($item['mto_base_igv'] ?? $valorVenta);
            $isc = (float) ($item['isc'] ?? 0);
            $icbper = (float) ($item['icbper'] ?? 0);
            $totalImpuestos = (float) ($item['total_impuestos'] ?? ($igv + $isc + $icbper));

            $calculated[] = [
                'codigo' => $item['codigo'] ?? null,
                'descripcion' => $item['descripcion'],
                'unidad' => $item['unidad'] ?? 'NIU',
                'cantidad' => $cantidad,
                'mto_valor_unitario' => $valorUnitario,
                'mto_valor_venta' => $valorVenta,
                'mto_base_igv' => $baseIgv,
                'porcentaje_igv' => $porcentajeIgv,
                'igv' => $igv,
                'tip_afe_igv' => $tipAfeIgv,
                'isc' => $isc,
                'icbper' => $icbper,
                'total_impuestos' => $totalImpuestos,
                'mto_precio_unitario' => $precioUnitario,
                'descuento' => (float) ($item['descuento'] ?? 0),
            ];
        }

        return $calculated;
    }

    private function calculateTotals(array $calculatedItems, array $data): array
    {
        $gravadas = 0;
        $exoneradas = 0;
        $inafectas = 0;
        $gratuitas = 0;
        $totalIgv = 0;
        $totalIsc = 0;
        $totalIcbper = 0;

        foreach ($calculatedItems as $item) {
            $tipAfeIgv = $item['tip_afe_igv'];
            $valorVenta = $item['mto_valor_venta'];

            if ($tipAfeIgv === '10') {
                $gravadas += $valorVenta;
            } elseif ($tipAfeIgv === '20') {
                $exoneradas += $valorVenta;
            } elseif ($tipAfeIgv === '30') {
                $inafectas += $valorVenta;
            } elseif (in_array($tipAfeIgv, ['11', '12', '13', '14', '15', '16', '17', '21', '31'])) {
                $gratuitas += $valorVenta;
            }

            $totalIgv += $item['igv'];
            $totalIsc += $item['isc'];
            $totalIcbper += $item['icbper'];
        }

        $gravadas = round($gravadas, 2);
        $exoneradas = round($exoneradas, 2);
        $inafectas = round($inafectas, 2);
        $gratuitas = round($gratuitas, 2);
        $totalIgv = round($totalIgv, 2);
        $totalIsc = round($totalIsc, 2);
        $totalIcbper = round($totalIcbper, 2);
        $totalImpuestos = round($totalIgv + $totalIsc + $totalIcbper, 2);
        $valorVenta = round($gravadas + $exoneradas + $inafectas, 2);
        $subTotal = round($valorVenta + $totalImpuestos, 2);
        $totalAnticipos = (float) ($data['total_anticipos'] ?? 0);
        $mtoImpVenta = round($subTotal - $totalAnticipos, 2);

        // Permitir override manual de totales
        return [
            'mto_oper_gravadas' => (float) ($data['mto_oper_gravadas'] ?? $gravadas),
            'mto_oper_exoneradas' => (float) ($data['mto_oper_exoneradas'] ?? $exoneradas),
            'mto_oper_inafectas' => (float) ($data['mto_oper_inafectas'] ?? $inafectas),
            'mto_oper_gratuitas' => (float) ($data['mto_oper_gratuitas'] ?? $gratuitas),
            'mto_igv' => (float) ($data['mto_igv'] ?? $totalIgv),
            'mto_isc' => (float) ($data['mto_isc'] ?? $totalIsc),
            'mto_icbper' => (float) ($data['mto_icbper'] ?? $totalIcbper),
            'total_impuestos' => (float) ($data['total_impuestos'] ?? $totalImpuestos),
            'valor_venta' => (float) ($data['valor_venta'] ?? $valorVenta),
            'sub_total' => (float) ($data['sub_total'] ?? $subTotal),
            'mto_imp_venta' => (float) ($data['mto_imp_venta'] ?? $mtoImpVenta),
        ];
    }

    private function resolveClient(Tenant $tenant, array $clientData): Client
    {
        return Client::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'tipo_documento' => $clientData['tipo_doc'],
                'numero_documento' => $clientData['num_doc'],
            ],
            [
                'razon_social' => $clientData['razon_social'],
                'direccion' => $clientData['direccion'] ?? null,
                'email' => $clientData['email'] ?? null,
            ]
        );
    }

    private function generateLeyenda(float $total, string $moneda): string
    {
        $entero = (int) $total;
        $decimales = round(($total - $entero) * 100);
        $monedaTexto = $moneda === 'PEN' ? 'SOLES' : 'DOLARES AMERICANOS';

        $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
        $especiales = ['DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE'];
        $decenas = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

        $texto = $this->numberToWords($entero, $unidades, $especiales, $decenas, $centenas);

        return strtoupper($texto).' Y '.str_pad((string) $decimales, 2, '0', STR_PAD_LEFT).'/100 '.$monedaTexto;
    }

    private function numberToWords(int $number, array $unidades, array $especiales, array $decenas, array $centenas): string
    {
        if ($number === 0) {
            return 'CERO';
        }
        if ($number === 100) {
            return 'CIEN';
        }

        $resultado = '';

        if ($number >= 1000000) {
            $millones = (int) ($number / 1000000);
            $resultado .= ($millones === 1 ? 'UN MILLON' : $this->numberToWords($millones, $unidades, $especiales, $decenas, $centenas).' MILLONES');
            $number %= 1000000;
            if ($number > 0) {
                $resultado .= ' ';
            }
        }

        if ($number >= 1000) {
            $miles = (int) ($number / 1000);
            $resultado .= ($miles === 1 ? 'MIL' : $this->numberToWords($miles, $unidades, $especiales, $decenas, $centenas).' MIL');
            $number %= 1000;
            if ($number > 0) {
                $resultado .= ' ';
            }
        }

        if ($number >= 100) {
            if ($number === 100) {
                return $resultado.'CIEN';
            }
            $resultado .= $centenas[(int) ($number / 100)].' ';
            $number %= 100;
        }

        if ($number >= 10 && $number <= 15) {
            $resultado .= $especiales[$number - 10];
        } elseif ($number >= 16 && $number <= 19) {
            $resultado .= 'DIECI'.$unidades[$number - 10];
        } elseif ($number >= 21 && $number <= 29) {
            $resultado .= 'VEINTI'.$unidades[$number - 20];
        } elseif ($number >= 10) {
            $resultado .= $decenas[(int) ($number / 10)];
            $number %= 10;
            if ($number > 0) {
                $resultado .= ' Y '.$unidades[$number];
            }
        } elseif ($number > 0) {
            $resultado .= $unidades[$number];
        }

        return trim($resultado);
    }
}
