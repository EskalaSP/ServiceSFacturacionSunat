<?php

declare(strict_types=1);

namespace App\Services\Greenter\Builders;

use App\Models\Tenant;
use DateTime;
use DateTimeZone;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Perception\Perception;
use Greenter\Model\Perception\PerceptionDetail;
use Greenter\Model\Retention\Exchange;
use Greenter\Model\Retention\Payment;

class PerceptionBuilder
{
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function build(array $data): Perception
    {
        $perception = new Perception();

        $perception
            ->setSerie($data['serie'])
            ->setCorrelativo((string) $data['correlativo'])
            ->setFechaEmision(new DateTime($data['fecha_emision'], new DateTimeZone('America/Lima')))
            ->setCompany($this->buildCompany($data['cod_local'] ?? '0000'))
            ->setProveedor($this->buildCliente($data['cliente']))
            ->setRegimen($data['regimen'])
            ->setTasa((float) $data['tasa'])
            ->setImpPercibido((float) ($data['imp_percibido'] ?? 0))
            ->setImpCobrado((float) ($data['imp_cobrado'] ?? 0));

        if (! empty($data['observacion'])) {
            $perception->setObservacion($data['observacion']);
        }

        $details = [];
        foreach ($data['documentos'] as $doc) {
            $details[] = $this->buildDetail($doc, $data['tasa']);
        }
        $perception->setDetails($details);

        // Auto-calcular totales si no se proporcionaron
        if (empty($data['imp_percibido']) || empty($data['imp_cobrado'])) {
            $totalPercibido = 0;
            $totalCobrado = 0;
            foreach ($details as $detail) {
                $totalPercibido += $detail->getImpPercibido();
                $totalCobrado += $detail->getImpCobrar();
            }
            $perception->setImpPercibido(round($totalPercibido, 2));
            $perception->setImpCobrado(round($totalCobrado, 2));
        }

        return $perception;
    }

    private function buildDetail(array $doc, float $tasa): PerceptionDetail
    {
        $detail = new PerceptionDetail();

        $moneda = $doc['moneda'] ?? 'PEN';
        $impTotal = (float) $doc['imp_total'];
        $impPercibido = (float) ($doc['imp_percibido'] ?? round($impTotal * $tasa / 100, 2));
        $impCobrar = (float) ($doc['imp_cobrar'] ?? round($impTotal + $impPercibido, 2));

        $detail
            ->setTipoDoc($doc['tipo_doc'])
            ->setNumDoc($doc['num_doc'])
            ->setFechaEmision(new DateTime($doc['fecha_emision'], new DateTimeZone('America/Lima')))
            ->setImpTotal($impTotal)
            ->setMoneda($moneda)
            ->setFechaPercepcion(new DateTime($doc['fecha_percepcion'], new DateTimeZone('America/Lima')))
            ->setImpPercibido($impPercibido)
            ->setImpCobrar($impCobrar);

        // Cobros
        $cobros = [];
        foreach ($doc['cobros'] as $cobro) {
            $cobros[] = (new Payment())
                ->setMoneda($cobro['moneda'] ?? $moneda)
                ->setImporte((float) $cobro['importe'])
                ->setFecha(new DateTime($cobro['fecha'], new DateTimeZone('America/Lima')));
        }
        $detail->setCobros($cobros);

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

    private function buildCliente(array $data): Client
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
