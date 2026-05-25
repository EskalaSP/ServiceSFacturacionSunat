<?php

declare(strict_types=1);

namespace App\Services\Greenter\Builders;

use App\Models\Tenant;
use DateTime;
use DateTimeZone;
use Greenter\Model\Company\Company;
use Greenter\Model\Voided\Reversion;
use Greenter\Model\Voided\VoidedDetail;

class ReversionBuilder
{
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Construye un documento de Reversión para anular Retenciones (20) o Percepciones (40).
     * Usa el formato de identificador RR-YYYYMMDD-NNN.
     */
    public function build(array $data): Reversion
    {
        $reversion = new Reversion();

        $reversion
            ->setCorrelativo($data['correlativo'])
            ->setFecGeneracion(new DateTime($data['fecha_generacion'], new DateTimeZone('America/Lima')))
            ->setFecComunicacion(new DateTime($data['fecha_comunicacion'], new DateTimeZone('America/Lima')))
            ->setCompany($this->buildCompany());

        $details = [];
        foreach ($data['detalles'] as $det) {
            $details[] = (new VoidedDetail())
                ->setTipoDoc($det['tipo_documento'])   // 20=Retención, 40=Percepción
                ->setSerie($det['serie'])
                ->setCorrelativo($det['correlativo'])
                ->setDesMotivoBaja($det['motivo']);
        }

        $reversion->setDetails($details);

        return $reversion;
    }

    private function buildCompany(): Company
    {
        return (new Company())
            ->setRuc($this->tenant->ruc)
            ->setRazonSocial($this->tenant->razon_social);
    }
}
