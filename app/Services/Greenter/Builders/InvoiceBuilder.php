<?php

namespace App\Services\Greenter\Builders;

use App\Models\Tenant;
use DateTime;
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
            ->setFechaEmision(new DateTime($data['fecha_emision']))
            ->setTipoMoneda($data['tipo_moneda'] ?? 'PEN')
            ->setCompany($this->buildCompany())
            ->setClient($this->buildClient($data['cliente']));

        if (! empty($data['fecha_vencimiento'])) {
            $invoice->setFecVencimiento(new DateTime($data['fecha_vencimiento']));
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
                        ->setFechaPago(new DateTime($cuota['fecha_pago']));
                }
                $invoice->setCuotas($cuotas);
            }
        } else {
            $invoice->setFormaPago(new FormaPagoContado());
        }

        // Montos
        $invoice
            ->setMtoOperGravadas((float) ($data['mto_oper_gravadas'] ?? 0))
            ->setMtoOperExoneradas((float) ($data['mto_oper_exoneradas'] ?? 0))
            ->setMtoOperInafectas((float) ($data['mto_oper_inafectas'] ?? 0))
            ->setMtoOperGratuitas((float) ($data['mto_oper_gratuitas'] ?? 0))
            ->setMtoIGV((float) ($data['mto_igv'] ?? 0))
            ->setTotalImpuestos((float) ($data['total_impuestos'] ?? $data['mto_igv'] ?? 0))
            ->setValorVenta((float) ($data['valor_venta'] ?? 0))
            ->setSubTotal((float) ($data['sub_total'] ?? 0))
            ->setMtoImpVenta((float) ($data['mto_imp_venta'] ?? 0));

        if (! empty($data['mto_isc'])) {
            $invoice->setMtoISC((float) $data['mto_isc']);
        }
        if (! empty($data['mto_icbper'])) {
            $invoice->setIcbper((float) $data['mto_icbper']);
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
        $detail = new SaleDetail();

        $porcentajeIgv = (float) ($item['porcentaje_igv'] ?? 18);
        $tipAfeIgv = $item['tip_afe_igv'] ?? '10';
        $cantidad = (float) $item['cantidad'];
        $precioUnitario = (float) $item['precio_unitario'];

        // Calcular valores según tipo de afectación
        if ($tipAfeIgv === '10') {
            // Gravado: precio_unitario incluye IGV
            $valorUnitario = round($precioUnitario / (1 + $porcentajeIgv / 100), 4);
            $valorVenta = round($valorUnitario * $cantidad, 2);
            $igv = round($valorVenta * $porcentajeIgv / 100, 2);
        } elseif (in_array($tipAfeIgv, ['20', '30'])) {
            // Exonerado/Inafecto: precio = valor (sin IGV)
            $valorUnitario = $precioUnitario;
            $valorVenta = round($valorUnitario * $cantidad, 2);
            $igv = 0;
            $porcentajeIgv = 0;
        } else {
            // Gratuito u otros
            $valorUnitario = (float) ($item['mto_valor_unitario'] ?? $precioUnitario);
            $valorVenta = round($valorUnitario * $cantidad, 2);
            $igv = (float) ($item['igv'] ?? 0);
        }

        // Permitir override manual de valores calculados
        $valorUnitario = (float) ($item['mto_valor_unitario'] ?? $valorUnitario);
        $valorVenta = (float) ($item['mto_valor_venta'] ?? $valorVenta);
        $igv = (float) ($item['igv'] ?? $igv);
        $baseIgv = (float) ($item['mto_base_igv'] ?? $valorVenta);
        $totalImpuestos = (float) ($item['total_impuestos'] ?? $igv);

        $detail
            ->setCodProducto($item['codigo'] ?? '')
            ->setUnidad($item['unidad'] ?? 'NIU')
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

    private function buildCompany(): Company
    {
        $company = new Company();
        $company
            ->setRuc($this->tenant->ruc)
            ->setRazonSocial($this->tenant->razon_social);

        if ($this->tenant->nombre_comercial) {
            $company->setNombreComercial($this->tenant->nombre_comercial);
        }

        $address = (new Address())
            ->setCodLocal('0000')
            ->setDireccion($this->tenant->direccion ?? '-');

        if ($this->tenant->ubigeo) {
            $address->setUbigueo($this->tenant->ubigeo);
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
