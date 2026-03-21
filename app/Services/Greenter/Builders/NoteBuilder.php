<?php

namespace App\Services\Greenter\Builders;

use App\Models\Tenant;
use DateTime;
use DateTimeZone;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\Document;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;

class NoteBuilder
{
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function build(array $data): Note
    {
        $note = new Note();

        $note
            ->setUblVersion('2.1')
            ->setTipoDoc($data['tipo_documento'])
            ->setSerie($data['serie'])
            ->setCorrelativo((string) $data['correlativo'])
            ->setFechaEmision(new DateTime($data['fecha_emision'], new DateTimeZone('America/Lima')))
            ->setTipoMoneda($data['tipo_moneda'] ?? 'PEN')
            ->setCompany($this->buildCompany($data['cod_local'] ?? '0000'))
            ->setClient($this->buildClient($data['cliente']));

        // Documento afectado
        $note->setTipDocAfectado($data['doc_afectado_tipo']);
        $note->setNumDocfectado($data['doc_afectado_serie'] . '-' . $data['doc_afectado_correlativo']);

        // Motivo
        $note->setCodMotivo($data['cod_motivo']);
        $note->setDesMotivo($data['des_motivo']);

        // Montos
        $note
            ->setMtoOperGravadas((float) ($data['mto_oper_gravadas'] ?? 0))
            ->setMtoOperExoneradas((float) ($data['mto_oper_exoneradas'] ?? 0))
            ->setMtoOperInafectas((float) ($data['mto_oper_inafectas'] ?? 0))
            ->setMtoOperGratuitas((float) ($data['mto_oper_gratuitas'] ?? 0))
            ->setMtoIGV((float) ($data['mto_igv'] ?? 0))
            ->setTotalImpuestos((float) ($data['total_impuestos'] ?? $data['mto_igv'] ?? 0))
            ->setMtoImpVenta((float) ($data['mto_imp_venta'] ?? 0));

        if (! empty($data['mto_igv_gratuitas'])) {
            $note->setMtoIGVGratuitas((float) $data['mto_igv_gratuitas']);
        }

        // Items
        $items = [];
        foreach ($data['items'] as $item) {
            $items[] = $this->buildItem($item);
        }
        $note->setDetails($items);

        // Leyendas
        if (! empty($data['leyenda'])) {
            $note->setLegends([
                (new Legend())->setCode('1000')->setValue($data['leyenda']),
            ]);
        }

        // Guías relacionadas
        if (! empty($data['guias'])) {
            $guias = [];
            foreach ($data['guias'] as $guia) {
                $guias[] = (new Document())->setTipoDoc($guia['tipo_doc'])->setNroDoc($guia['nro_doc']);
            }
            $note->setGuias($guias);
        }

        return $note;
    }

    private function buildItem(array $item): SaleDetail
    {
        $gratuitoGravadoCodes = ['11', '12', '13', '14', '15', '16'];
        $gratuitoInafectoCodes = ['21', '31', '32', '33', '34', '35', '36'];
        $gratuitoCodes = array_merge($gratuitoGravadoCodes, $gratuitoInafectoCodes);

        $detail = new SaleDetail();

        $tipAfeIgv = $item['tip_afe_igv'] ?? '10';
        $defaultIgvRate = ($tipAfeIgv === '17') ? 4 : 18;
        $porcentajeIgv = (float) ($item['porcentaje_igv'] ?? $defaultIgvRate);
        $cantidad = (float) $item['cantidad'];
        $precioUnitario = (float) $item['precio_unitario'];
        $isGratuito = in_array($tipAfeIgv, $gratuitoCodes);
        $isGratuitoGravado = in_array($tipAfeIgv, $gratuitoGravadoCodes);

        if ($tipAfeIgv === '10') {
            $valorUnitario = round($precioUnitario / (1 + $porcentajeIgv / 100), 4);
            $valorVenta = round($valorUnitario * $cantidad, 2);
            $igv = round($valorVenta * $porcentajeIgv / 100, 2);
        } elseif (in_array($tipAfeIgv, ['20', '30'])) {
            $valorUnitario = $precioUnitario;
            $valorVenta = round($valorUnitario * $cantidad, 2);
            $igv = 0;
            $porcentajeIgv = 0;
        } elseif ($isGratuitoGravado) {
            $valorGratuito = (float) ($item['mto_valor_unitario'] ?? $precioUnitario);
            $valorUnitario = 0;
            $valorVenta = round($valorGratuito * $cantidad, 2);
            $igv = (float) ($item['igv'] ?? round($valorVenta * $porcentajeIgv / 100, 2));
        } elseif ($isGratuito) {
            $valorGratuito = (float) ($item['mto_valor_unitario'] ?? $precioUnitario);
            $valorUnitario = 0;
            $valorVenta = round($valorGratuito * $cantidad, 2);
            $igv = 0;
            $porcentajeIgv = 0;
        } else {
            $valorUnitario = (float) ($item['mto_valor_unitario'] ?? $precioUnitario);
            $valorVenta = round($valorUnitario * $cantidad, 2);
            $igv = (float) ($item['igv'] ?? 0);
        }

        if (! $isGratuito) {
            $valorUnitario = (float) ($item['mto_valor_unitario'] ?? $valorUnitario);
        }
        $valorVenta = (float) ($item['mto_valor_venta'] ?? $valorVenta);
        $igv = (float) ($item['igv'] ?? $igv);

        $detail
            ->setCodProducto($item['codigo'] ?? '')
            ->setUnidad($item['unidad'])
            ->setDescripcion($item['descripcion'])
            ->setCantidad($cantidad)
            ->setMtoValorUnitario($valorUnitario)
            ->setMtoValorVenta($valorVenta)
            ->setMtoBaseIgv((float) ($item['mto_base_igv'] ?? $valorVenta))
            ->setPorcentajeIgv($porcentajeIgv)
            ->setIgv($igv)
            ->setTipAfeIgv($tipAfeIgv)
            ->setTotalImpuestos((float) ($item['total_impuestos'] ?? $igv));

        if ($isGratuito || $isGratuitoGravado) {
            $mtoValorGratuito = (float) ($item['mto_valor_unitario'] ?? $precioUnitario);
            $detail->setMtoPrecioUnitario(0);
            $detail->setMtoValorGratuito($mtoValorGratuito);
        } else {
            $detail->setMtoPrecioUnitario($precioUnitario);
        }

        return $detail;
    }

    private function buildCompany(string $codLocal = '0000'): Company
    {
        $company = (new Company())
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
        return (new Client())
            ->setTipoDoc($clientData['tipo_doc'])
            ->setNumDoc($clientData['num_doc'])
            ->setRznSocial($clientData['razon_social']);
    }
}
