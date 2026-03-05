<?php

namespace App\Services\Greenter\Builders;

use App\Models\Tenant;
use DateTime;
use DateTimeZone;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\Charge;
use Greenter\Model\Sale\Cuota;
use Greenter\Model\Sale\Detraction;
use Greenter\Model\Sale\Document;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\FormaPagos\FormaPagoCredito;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Prepayment;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Model\Sale\SalePerception;

class InvoiceBuilder
{
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function build(array $data): Invoice
    {
        $invoice = new Invoice();

        $invoice
            ->setUblVersion('2.1')
            ->setTipoOperacion($data['tipo_operacion'] ?? '0101')
            ->setTipoDoc($data['tipo_documento'])
            ->setSerie($data['serie'])
            ->setCorrelativo((string) $data['correlativo'])
            ->setFechaEmision(new DateTime($data['fecha_emision'], new DateTimeZone('America/Lima')))
            ->setTipoMoneda($data['tipo_moneda'] ?? 'PEN')
            ->setCompany($this->buildCompany($data['cod_local'] ?? '0000'))
            ->setClient($this->buildClient($data['cliente']));

        if (! empty($data['fecha_vencimiento'])) {
            $invoice->setFecVencimiento(new DateTime($data['fecha_vencimiento'], new DateTimeZone('America/Lima')));
        }

        // Forma de pago
        if (($data['forma_pago'] ?? 'Contado') === 'Credito') {
            $totalCredito = $data['mto_imp_venta'] ?? 0;
            $invoice->setFormaPago(new FormaPagoCredito($totalCredito));

            if (! empty($data['cuotas'])) {
                $cuotas = [];
                foreach ($data['cuotas'] as $i => $cuota) {
                    $cuotas[] = (new Cuota())
                        ->setMonto($cuota['monto'])
                        ->setFechaPago(new DateTime($cuota['fecha_pago'], new DateTimeZone('America/Lima')));
                }
                $invoice->setCuotas($cuotas);
            }
        } else {
            $invoice->setFormaPago(new FormaPagoContado());
        }

        // Montos - solo setear > 0 para evitar nodos de tributo vacíos en el XML
        $mtoOperGravadas = (float) ($data['mto_oper_gravadas'] ?? 0);
        $mtoOperExoneradas = (float) ($data['mto_oper_exoneradas'] ?? 0);
        $mtoOperInafectas = (float) ($data['mto_oper_inafectas'] ?? 0);
        $mtoOperGratuitas = (float) ($data['mto_oper_gratuitas'] ?? 0);
        $mtoOperExportacion = (float) ($data['mto_oper_exportacion'] ?? 0);
        $mtoIgv = (float) ($data['mto_igv'] ?? 0);

        if ($mtoOperGravadas > 0) {
            $invoice->setMtoOperGravadas($mtoOperGravadas);
        }
        if ($mtoOperExoneradas > 0) {
            $invoice->setMtoOperExoneradas($mtoOperExoneradas);
        }
        if ($mtoOperInafectas > 0) {
            $invoice->setMtoOperInafectas($mtoOperInafectas);
        }
        if ($mtoOperGratuitas > 0) {
            $invoice->setMtoOperGratuitas($mtoOperGratuitas);
        }
        if ($mtoOperExportacion > 0) {
            $invoice->setMtoOperExportacion($mtoOperExportacion);
        }
        if ($mtoIgv > 0) {
            $invoice->setMtoIGV($mtoIgv);
        }

        $invoice
            ->setTotalImpuestos((float) ($data['total_impuestos'] ?? $mtoIgv))
            ->setValorVenta((float) ($data['valor_venta'] ?? 0))
            ->setSubTotal((float) ($data['sub_total'] ?? 0))
            ->setMtoImpVenta((float) ($data['mto_imp_venta'] ?? 0));

        if (! empty($data['mto_igv_gratuitas'])) {
            $invoice->setMtoIGVGratuitas((float) $data['mto_igv_gratuitas']);
        }
        if (! empty($data['mto_isc'])) {
            $invoice->setMtoISC((float) $data['mto_isc']);
        }
        if (! empty($data['mto_icbper'])) {
            $invoice->setIcbper((float) $data['mto_icbper']);
        }
        if (! empty($data['mto_base_ivap'])) {
            $invoice->setMtoBaseIvap((float) $data['mto_base_ivap']);
        }
        if (! empty($data['mto_ivap'])) {
            $invoice->setMtoIvap((float) $data['mto_ivap']);
        }
        if (! empty($data['sum_otros_descuentos'])) {
            $invoice->setSumOtrosDescuentos((float) $data['sum_otros_descuentos']);
        }

        // Items
        $items = [];
        foreach ($data['items'] as $item) {
            $items[] = $this->buildItem($item);
        }
        $invoice->setDetails($items);

        // Leyendas
        $legends = [];
        if (! empty($data['leyenda'])) {
            $legends[] = (new Legend())->setCode('1000')->setValue($data['leyenda']);
        }
        if (! empty($data['leyendas'])) {
            foreach ($data['leyendas'] as $legend) {
                $legends[] = (new Legend())->setCode($legend['code'])->setValue($legend['value']);
            }
        }
        if (! empty($legends)) {
            $invoice->setLegends($legends);
        }

        // Guías relacionadas
        if (! empty($data['guias'])) {
            $guias = [];
            foreach ($data['guias'] as $guia) {
                $guias[] = (new Document())->setTipoDoc($guia['tipo_doc'])->setNroDoc($guia['nro_doc']);
            }
            $invoice->setGuias($guias);
        }

        // Detracción
        if (! empty($data['detraccion'])) {
            $det = $data['detraccion'];
            $invoice->setTipoOperacion($det['tipo_operacion'] ?? '1001');
            $invoice->setDetraccion(
                (new Detraction())
                    ->setCodBienDetraccion($det['cod_bien'])
                    ->setCodMedioPago($det['cod_medio_pago'] ?? '001')
                    ->setCtaBanco($det['cta_banco'])
                    ->setPercent((float) $det['porcentaje'])
                    ->setMount((float) $det['monto'])
            );
        }

        // Percepción
        if (! empty($data['percepcion'])) {
            $per = $data['percepcion'];
            $invoice->setTipoOperacion($per['tipo_operacion'] ?? '2001');
            $invoice->setPerception(
                (new SalePerception())
                    ->setCodReg($per['cod_reg'] ?? '51')
                    ->setPorcentaje((float) $per['porcentaje'])
                    ->setMtoBase((float) $per['mto_base'])
                    ->setMto((float) $per['mto'])
                    ->setMtoTotal((float) $per['mto_total'])
            );
        }

        // Anticipos
        if (! empty($data['anticipos'])) {
            $anticipos = [];
            foreach ($data['anticipos'] as $ant) {
                $anticipos[] = (new Prepayment())
                    ->setTipoDocRel($ant['tipo_doc_rel'] ?? '02')
                    ->setNroDocRel($ant['nro_doc_rel'])
                    ->setTotal((float) $ant['total']);
            }
            $invoice->setAnticipos($anticipos);
            $invoice->setTotalAnticipos((float) ($data['total_anticipos'] ?? 0));
        }

        // Descuentos globales
        if (! empty($data['descuentos_globales'])) {
            $descuentos = [];
            foreach ($data['descuentos_globales'] as $desc) {
                $descuentos[] = (new Charge())
                    ->setCodTipo($desc['cod_tipo'] ?? '02')
                    ->setMontoBase((float) ($desc['monto_base'] ?? 0))
                    ->setFactor((float) ($desc['factor'] ?? 1))
                    ->setMonto((float) $desc['monto']);
            }
            $invoice->setDescuentos($descuentos);
        }

        return $invoice;
    }

    private function buildItem(array $item): SaleDetail
    {
        $gratuitoGravadoCodes = ['11', '12', '13', '14', '15', '16'];
        $gratuitoInafectoCodes = ['21', '31', '32', '33', '34', '35', '36'];
        $gratuitoCodes = array_merge($gratuitoGravadoCodes, $gratuitoInafectoCodes);

        $detail = new SaleDetail();

        $porcentajeIgv = (float) ($item['porcentaje_igv'] ?? 18);
        $tipAfeIgv = $item['tip_afe_igv'] ?? '10';
        $cantidad = (float) $item['cantidad'];
        $precioUnitario = (float) $item['precio_unitario'];
        $isGratuito = in_array($tipAfeIgv, $gratuitoCodes);
        $isGratuitoGravado = in_array($tipAfeIgv, $gratuitoGravadoCodes);

        // Calcular descuentos por línea que afectan la base (cod_tipo '00')
        $descuentoBase = 0;
        if (! empty($item['descuentos'])) {
            foreach ($item['descuentos'] as $desc) {
                if (($desc['cod_tipo'] ?? '00') === '00') {
                    $descuentoBase += (float) $desc['monto'];
                }
            }
        }

        // Calcular valores según tipo de afectación
        if ($tipAfeIgv === '10') {
            // Gravado: precio_unitario incluye IGV
            $valorUnitario = round($precioUnitario / (1 + $porcentajeIgv / 100), 4);
            $valorVenta = round($valorUnitario * $cantidad - $descuentoBase, 2);
            $igv = round($valorVenta * $porcentajeIgv / 100, 2);
        } elseif ($tipAfeIgv === '17') {
            // IVAP: como gravado pero con tasa especial (4%)
            $valorUnitario = round($precioUnitario / (1 + $porcentajeIgv / 100), 4);
            $valorVenta = round($valorUnitario * $cantidad - $descuentoBase, 2);
            $igv = round($valorVenta * $porcentajeIgv / 100, 2);
        } elseif (in_array($tipAfeIgv, ['20', '30', '40'])) {
            // Exonerado/Inafecto/Exportación: precio = valor (sin IGV)
            $valorUnitario = $precioUnitario;
            $valorVenta = round($valorUnitario * $cantidad - $descuentoBase, 2);
            $igv = 0;
            $porcentajeIgv = 0;
        } elseif ($isGratuitoGravado) {
            // Gratuita gravada (11-17): lleva IGV
            $valorGratuito = (float) ($item['mto_valor_unitario'] ?? $precioUnitario);
            $valorUnitario = 0;
            $valorVenta = round($valorGratuito * $cantidad, 2);
            $igv = (float) ($item['igv'] ?? round($valorVenta * $porcentajeIgv / 100, 2));
        } elseif ($isGratuito) {
            // Gratuita inafecta/exonerada (21, 31-36): NO lleva IGV
            $valorGratuito = (float) ($item['mto_valor_unitario'] ?? $precioUnitario);
            $valorUnitario = 0;
            $valorVenta = round($valorGratuito * $cantidad, 2);
            $igv = 0;
            $porcentajeIgv = 0;
        } else {
            // Otros
            $valorUnitario = (float) ($item['mto_valor_unitario'] ?? $precioUnitario);
            $valorVenta = round($valorUnitario * $cantidad, 2);
            $igv = (float) ($item['igv'] ?? 0);
        }

        // Permitir override manual de valores calculados
        if (! $isGratuito) {
            $valorUnitario = (float) ($item['mto_valor_unitario'] ?? $valorUnitario);
        }
        $valorVenta = (float) ($item['mto_valor_venta'] ?? $valorVenta);
        $igv = (float) ($item['igv'] ?? $igv);
        $baseIgv = (float) ($item['mto_base_igv'] ?? $valorVenta);
        $totalImpuestos = (float) ($item['total_impuestos'] ?? $igv);

        $detail
            ->setCodProducto($item['codigo'] ?? '')
            ->setUnidad($item['unidad'])
            ->setDescripcion($item['descripcion'])
            ->setCantidad($cantidad)
            ->setMtoValorUnitario($valorUnitario)
            ->setMtoValorVenta($valorVenta)
            ->setMtoBaseIgv($baseIgv)
            ->setPorcentajeIgv($porcentajeIgv)
            ->setIgv($igv)
            ->setTipAfeIgv($tipAfeIgv)
            ->setTotalImpuestos($totalImpuestos)
            ->setMtoPrecioUnitario($precioUnitario);

        // Gratuito: setMtoValorGratuito con el valor referencial por unidad
        if ($isGratuito) {
            $mtoValorGratuito = (float) ($item['mto_valor_unitario'] ?? $precioUnitario);
            $detail->setMtoValorGratuito($mtoValorGratuito);
        }

        // Descuentos por línea
        if (! empty($item['descuentos'])) {
            $descuentos = [];
            foreach ($item['descuentos'] as $desc) {
                $descuentos[] = (new Charge())
                    ->setCodTipo($desc['cod_tipo'] ?? '00')
                    ->setMontoBase((float) ($desc['monto_base'] ?? 0))
                    ->setFactor((float) ($desc['factor'] ?? 1))
                    ->setMonto((float) $desc['monto']);
            }
            $detail->setDescuentos($descuentos);
        }

        if (! empty($item['isc'])) {
            $detail->setIsc((float) $item['isc']);
        }

        if (! empty($item['icbper'])) {
            $detail->setIcbper((float) $item['icbper']);
            if (! empty($item['factor_icbper'])) {
                $detail->setFactorIcbper((float) $item['factor_icbper']);
            }
        }

        return $detail;
    }

    private function buildCompany(string $codLocal = '0000'): Company
    {
        $company = new Company();
        $company
            ->setRuc($this->tenant->ruc)
            ->setRazonSocial($this->tenant->razon_social);

        if ($this->tenant->nombre_comercial) {
            $company->setNombreComercial($this->tenant->nombre_comercial);
        }

        $address = (new Address())
            ->setCodLocal($codLocal)
            ->setDireccion($this->tenant->direccion ?? '-');

        if ($this->tenant->ubigeo) {
            $address->setUbigueo($this->tenant->ubigeo);
        }
        if ($this->tenant->departamento) {
            $address->setDepartamento($this->tenant->departamento);
        }
        if ($this->tenant->provincia) {
            $address->setProvincia($this->tenant->provincia);
        }
        if ($this->tenant->distrito) {
            $address->setDistrito($this->tenant->distrito);
        }

        $company->setAddress($address);

        return $company;
    }

    private function buildClient(array $clientData): Client
    {
        $client = new Client();
        $client
            ->setTipoDoc($clientData['tipo_doc'])
            ->setNumDoc($clientData['num_doc'])
            ->setRznSocial($clientData['razon_social']);

        if (! empty($clientData['direccion'])) {
            $client->setAddress(
                (new Address())->setDireccion($clientData['direccion'])
            );
        }

        return $client;
    }
}
