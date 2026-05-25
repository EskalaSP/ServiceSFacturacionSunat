<?php

namespace App\Services\Greenter\Builders;

use App\Models\Tenant;
use DateTime;
use DateTimeZone;
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
            // Greenter mapea: fecGeneracion → ReferenceDate (fecha de los documentos), fecResumen → IssueDate (fecha de envío)
            ->setFecGeneracion(new DateTime($data['fecha_referencia'], new DateTimeZone('America/Lima')))
            ->setFecResumen(new DateTime($data['fecha_envio'], new DateTimeZone('America/Lima')))
            ->setCorrelativo($data['correlativo'])
            ->setCompany($this->buildCompany());

        $details = [];
        foreach ($data['detalles'] as $det) {
            $detail = new SummaryDetail();
            $detail
                ->setTipoDoc($det['tipo_documento'])
                ->setSerieNro($det['serie_nro'])
                ->setEstado($det['estado'])
                ->setClienteTipo($det['cliente_tipo_doc'] ?? $det['cliente_tipo'] ?? '0')
                ->setClienteNro($det['cliente_num_doc'] ?? $det['cliente_nro'] ?? '00000000')
                ->setTotal((float) ($det['total'] ?? 0))
                ->setMtoOperGravadas((float) ($det['mto_oper_gravadas'] ?? 0))
                ->setMtoOperInafectas((float) ($det['mto_oper_inafectas'] ?? 0))
                ->setMtoOperExoneradas((float) ($det['mto_oper_exoneradas'] ?? 0))
                ->setMtoOperGratuitas((float) ($det['mto_oper_gratuitas'] ?? 0))
                ->setMtoIGV((float) ($det['mto_igv'] ?? 0))
                ->setPorcentajeIgv((float) ($det['porcentaje_igv'] ?? 18));

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
