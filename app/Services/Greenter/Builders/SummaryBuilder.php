<?php

namespace App\Services\Greenter\Builders;

use App\Models\Tenant;
use DateTime;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\Document;
use Greenter\Model\Summary\Summary;
use Greenter\Model\Summary\SummaryDetail;

class SummaryBuilder
{
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function build(array $data): Summary
    {
        $summary = new Summary();

        $summary
            ->setFecGeneracion(new DateTime($data['fecha_generacion']))
            ->setFecResumen(new DateTime($data['fecha_resumen']))
            ->setCorrelativo($data['correlativo'])
            ->setCompany($this->buildCompany());

        $details = [];
        foreach ($data['detalles'] as $det) {
            $detail = new SummaryDetail();
            $detail
                ->setTipoDoc($det['tipo_documento'])
                ->setSerieNro($det['serie_nro'])
                ->setEstado($det['estado'])
                ->setClienteTipo($det['cliente_tipo'] ?? '0')
                ->setClienteNro($det['cliente_nro'] ?? '00000000')
                ->setTotal((float) ($det['total'] ?? 0))
                ->setMtoOperGravadas((float) ($det['mto_oper_gravadas'] ?? 0))
                ->setMtoOperInafectas((float) ($det['mto_oper_inafectas'] ?? 0))
                ->setMtoOperExoneradas((float) ($det['mto_oper_exoneradas'] ?? 0))
                ->setMtoIGV((float) ($det['mto_igv'] ?? 0));

            if (! empty($det['mto_oper_exportacion'])) {
                $detail->setMtoOperExportacion((float) $det['mto_oper_exportacion']);
            }

            if (! empty($det['mto_otros_cargos'])) {
                $detail->setMtoOtrosCargos((float) $det['mto_otros_cargos']);
            }

            if (! empty($det['doc_referencia'])) {
                $detail->setDocReferencia(
                    (new Document())
                        ->setTipoDoc($det['doc_referencia']['tipo_doc'])
                        ->setNroDoc($det['doc_referencia']['nro_doc'])
                );
            }

            $details[] = $detail;
        }

        $summary->setDetails($details);

        return $summary;
    }

    private function buildCompany(): Company
    {
        return (new Company())
            ->setRuc($this->tenant->ruc)
            ->setRazonSocial($this->tenant->razon_social);
    }
}
