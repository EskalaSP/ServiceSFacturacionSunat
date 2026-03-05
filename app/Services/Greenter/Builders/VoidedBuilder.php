<?php

namespace App\Services\Greenter\Builders;

use App\Models\Tenant;
use DateTime;
use DateTimeZone;
use Greenter\Model\Company\Company;
use Greenter\Model\Voided\Voided;
use Greenter\Model\Voided\VoidedDetail;

class VoidedBuilder
{
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function build(array $data): Voided
    {
        $voided = new Voided();

        $voided
            ->setCorrelativo($data['correlativo'])
            ->setFecGeneracion(new DateTime($data['fecha_generacion'], new DateTimeZone('America/Lima')))
            ->setFecComunicacion(new DateTime($data['fecha_comunicacion'], new DateTimeZone('America/Lima')))
            ->setCompany($this->buildCompany());

        $details = [];
        foreach ($data['detalles'] as $det) {
            $details[] = (new VoidedDetail())
                ->setTipoDoc($det['tipo_documento'])
                ->setSerie($det['serie'])
                ->setCorrelativo($det['correlativo'])
                ->setDesMotivoBaja($det['motivo']);
        }

        $voided->setDetails($details);

        return $voided;
    }

    private function buildCompany(): Company
    {
        return (new Company())
            ->setRuc($this->tenant->ruc)
            ->setRazonSocial($this->tenant->razon_social);
    }
}
