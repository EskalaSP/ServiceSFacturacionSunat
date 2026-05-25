<?php

declare(strict_types=1);

namespace App\Services\Greenter\Builders;

use App\Models\Tenant;
use DateTime;
use DateTimeZone;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Retention\Exchange;
use Greenter\Model\Retention\Payment;
use Greenter\Model\Retention\Retention;
use Greenter\Model\Retention\RetentionDetail;

class RetentionBuilder
{
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function build(array $data): Retention
    {
        $retention = new Retention();

        $retention
            ->setSerie($data['serie'])
            ->setCorrelativo((string) $data['correlativo'])
            ->setFechaEmision(new DateTime($data['fecha_emision'], new DateTimeZone('America/Lima')))
            ->setCompany($this->buildCompany($data['cod_local'] ?? '0000'))
            ->setProveedor($this->buildProveedor($data['proveedor']))
            ->setRegimen($data['regimen'])
            ->setTasa((float) $data['tasa'])
            ->setImpRetenido((float) ($data['imp_retenido'] ?? 0))
            ->setImpPagado((float) ($data['imp_pagado'] ?? 0));

        if (! empty($data['observacion'])) {
            $retention->setObservacion($data['observacion']);
        }

        // Documentos relacionados
        $details = [];
        foreach ($data['documentos'] as $doc) {
            $details[] = $this->buildDetail($doc, $data['tasa']);
        }
        $retention->setDetails($details);

        // Calcular totales si no se proporcionaron
        if (empty($data['imp_retenido']) || empty($data['imp_pagado'])) {
            $totalRetenido = 0;
            $totalPagado = 0;
            foreach ($details as $detail) {
                $totalRetenido += $detail->getImpRetenido();
                $totalPagado += $detail->getImpPagar();
            }
            $retention->setImpRetenido(round($totalRetenido, 2));
            $retention->setImpPagado(round($totalPagado, 2));
        }

        return $retention;
    }

    private function buildDetail(array $doc, float $tasa): RetentionDetail
    {
        $detail = new RetentionDetail();

        $moneda = $doc['moneda'] ?? 'PEN';
        $impTotal = (float) $doc['imp_total'];

        // Calcular la retención si no se proporcionó
        $impRetenido = (float) ($doc['imp_retenido'] ?? round($impTotal * $tasa / 100, 2));
        $impPagar = (float) ($doc['imp_pagar'] ?? round($impTotal - $impRetenido, 2));

        $detail
            ->setTipoDoc($doc['tipo_doc'])
            ->setNumDoc($doc['num_doc'])
            ->setFechaEmision(new DateTime($doc['fecha_emision'], new DateTimeZone('America/Lima')))
            ->setImpTotal($impTotal)
            ->setMoneda($moneda)
            ->setFechaRetencion(new DateTime($doc['fecha_retencion'], new DateTimeZone('America/Lima')))
            ->setImpRetenido($impRetenido)
            ->setImpPagar($impPagar);

        // Pagos
        $pagos = [];
        foreach ($doc['pagos'] as $pago) {
            $pagos[] = (new Payment())
                ->setMoneda($pago['moneda'] ?? $moneda)
                ->setImporte((float) $pago['importe'])
                ->setFecha(new DateTime($pago['fecha'], new DateTimeZone('America/Lima')));
        }
        $detail->setPagos($pagos);

        // Tipo de cambio (requerido si moneda != PEN)
        if (! empty($doc['tipo_cambio'])) {
            $tc = $doc['tipo_cambio'];
            $detail->setTipoCambio(
                (new Exchange())
                    ->setMonedaRef($tc['moneda_ref'])
                    ->setMonedaObj($tc['moneda_obj'])
                    ->setFactor((float) $tc['factor'])
                    ->setFecha(new DateTime($tc['fecha'], new DateTimeZone('America/Lima')))
            );
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

        $company->setAddress($address);

        return $company;
    }

    private function buildProveedor(array $data): Client
    {
        $client = (new Client())
            ->setTipoDoc($data['tipo_doc'])
            ->setNumDoc($data['num_doc'])
            ->setRznSocial($data['razon_social']);

        if (! empty($data['direccion'])) {
            $client->setAddress(
                (new Address())->setDireccion($data['direccion'])
            );
        }

        return $client;
    }
}
