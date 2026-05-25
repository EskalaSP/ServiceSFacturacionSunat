<?php

declare(strict_types=1);

namespace App\Services\Greenter\Builders;

use App\Models\Tenant;
use App\Services\TaxRateService;
use DateTime;
use DateTimeZone;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\Charge;
use Greenter\Model\Sale\Document;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;

class NoteBuilder
{
    private Tenant $tenant;
    private TaxRateService $taxRates;
    private ?string $fechaEmision = null;

    public function __construct(Tenant $tenant, ?TaxRateService $taxRates = null)
    {
        $this->tenant = $tenant;
        $this->taxRates = $taxRates ?? new TaxRateService();
    }

    public function build(array $data): Note
    {
        $this->fechaEmision = $data['fecha_emision'] ?? null;
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

        // Montos - solo setear > 0 para evitar nodos de tributo vacíos en el XML
        $mtoOperGravadas = (float) ($data['mto_oper_gravadas'] ?? 0);
        $mtoOperExoneradas = (float) ($data['mto_oper_exoneradas'] ?? 0);
        $mtoOperInafectas = (float) ($data['mto_oper_inafectas'] ?? 0);
        $mtoOperGratuitas = (float) ($data['mto_oper_gratuitas'] ?? 0);
        $mtoIgv = (float) ($data['mto_igv'] ?? 0);

        if ($mtoOperGravadas > 0) {
            $note->setMtoOperGravadas($mtoOperGravadas);
        }
        if ($mtoOperExoneradas > 0) {
            $note->setMtoOperExoneradas($mtoOperExoneradas);
        }
        if ($mtoOperInafectas > 0) {
            $note->setMtoOperInafectas($mtoOperInafectas);
        }
        if ($mtoOperGratuitas > 0) {
            $note->setMtoOperGratuitas($mtoOperGratuitas);
        }
        if ($mtoIgv > 0) {
            $note->setMtoIGV($mtoIgv);
        }

        $note
            ->setTotalImpuestos((float) ($data['total_impuestos'] ?? $mtoIgv))
            ->setMtoImpVenta((float) ($data['mto_imp_venta'] ?? 0));

        if (! empty($data['mto_igv_gratuitas'])) {
            $note->setMtoIGVGratuitas((float) $data['mto_igv_gratuitas']);
        }
        if (! empty($data['mto_isc'])) {
            $note->setMtoISC((float) $data['mto_isc']);
            $note->setMtoBaseIsc((float) ($data['mto_base_isc'] ?? $data['mto_oper_gravadas'] ?? 0));
        }
        if (! empty($data['mto_icbper'])) {
            $note->setIcbper((float) $data['mto_icbper']);
        }
        if (! empty($data['mto_base_ivap'])) {
            $note->setMtoBaseIvap((float) $data['mto_base_ivap']);
        }
        if (! empty($data['mto_ivap'])) {
            $note->setMtoIvap((float) $data['mto_ivap']);
        }
        if (! empty($data['sum_otros_descuentos'])) {
            $note->setSumOtrosDescuentos((float) $data['sum_otros_descuentos']);
        }

        // Items
        $items = [];
        foreach ($data['items'] as $item) {
            $items[] = $this->buildItem($item);
        }
        $note->setDetails($items);

        // Leyendas (soporta campo simple y array)
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
            $note->setLegends($legends);
        }

        // Guías relacionadas
        if (! empty($data['guias'])) {
            $guias = [];
            foreach ($data['guias'] as $guia) {
                $guias[] = (new Document())->setTipoDoc($guia['tipo_doc'])->setNroDoc($guia['nro_doc']);
            }
            $note->setGuias($guias);
        }

        // Descuentos globales (relevante para NC tipo 04 descuento global)
        if (! empty($data['descuentos_globales'])) {
            $descuentos = [];
            foreach ($data['descuentos_globales'] as $desc) {
                $descuentos[] = (new Charge())
                    ->setCodTipo($desc['cod_tipo'] ?? '02')
                    ->setMontoBase((float) ($desc['monto_base'] ?? 0))
                    ->setFactor((float) ($desc['factor'] ?? $desc['porcentaje'] ?? 1))
                    ->setMonto((float) $desc['monto']);
            }
            $note->setDescuentos($descuentos);
        }

        return $note;
    }

    private function buildItem(array $item): SaleDetail
    {
        $gratuitoGravadoCodes = ['11', '12', '13', '14', '15', '16'];
        $gratuitoInafectoCodes = ['21', '31', '32', '33', '34', '35', '36'];
        $gratuitoCodes = array_merge($gratuitoGravadoCodes, $gratuitoInafectoCodes);

        $detail = new SaleDetail();

        // NRUS default '30' (Inafecto); otros regímenes default '10' (Gravado).
        $tipAfeIgv = $item['tip_afe_igv'] ?? $this->taxRates->defaultTipAfeIgv($this->tenant);
        // Tasa según régimen del tenant + fecha (general=18, mype_restaurantes según schedule, nrus=0)
        $porcentajeIgv = $this->taxRates->rateForItem($item, $this->tenant, $this->fechaEmision);
        $cantidad = (float) $item['cantidad'];
        $precioUnitario = (float) $item['precio_unitario'];
        $isGratuito = in_array($tipAfeIgv, $gratuitoCodes);
        $isGratuitoGravado = in_array($tipAfeIgv, $gratuitoGravadoCodes);

        // Calcular valor unitario según tipo de afectación (10 decimales para SUNAT)
        if ($tipAfeIgv === '10') {
            $valorUnitario = round($precioUnitario / (1 + $porcentajeIgv / 100), 10);
        } elseif ($tipAfeIgv === '17') {
            $valorUnitario = round($precioUnitario / (1 + $porcentajeIgv / 100), 10);
        } elseif (in_array($tipAfeIgv, ['20', '30', '40'])) {
            $valorUnitario = $precioUnitario;
        }

        // Calcular descuentos por línea sobre la base sin IGV (cod_tipo '00')
        $descuentoBase = 0;
        $descuentoConIgv = 0;
        $recalculatedDescuentos = null;
        if (! empty($item['descuentos'])) {
            $valorBrutoForDiscount = isset($valorUnitario) ? $valorUnitario : ($isGratuito ? ((float) ($item['mto_valor_unitario'] ?? $precioUnitario)) : $precioUnitario);
            $valorBruto = $valorBrutoForDiscount * $cantidad;
            $totalConIgvBruto = round($precioUnitario * $cantidad, 2);
            $recalculatedDescuentos = [];
            foreach ($item['descuentos'] as $desc) {
                if (($desc['cod_tipo'] ?? '00') === '00') {
                    $montoBase = round($valorBruto, 2);

                    if (! empty($desc['porcentaje'])) {
                        $factor = round((float) $desc['porcentaje'] / 100, 6);
                        $monto = round($montoBase * $factor, 2);
                    } elseif (! empty($desc['factor'])) {
                        $factor = (float) $desc['factor'];
                        $monto = round($montoBase * $factor, 2);
                    } else {
                        $monto = (float) ($desc['monto'] ?? 0);
                        $factor = $montoBase > 0 ? round($monto / $montoBase, 6) : 0;
                    }

                    $descuentoBase += $monto;
                    $descuentoConIgv += round($totalConIgvBruto * $factor, 2);
                    $recalculatedDescuentos[] = [
                        'cod_tipo' => $desc['cod_tipo'] ?? '00',
                        'monto_base' => $montoBase,
                        'factor' => $factor,
                        'monto' => $monto,
                    ];
                } else {
                    $recalculatedDescuentos[] = $desc;
                }
            }
        }

        // Calcular valorVenta e IGV según tipo de afectación
        if ($tipAfeIgv === '10') {
            $valorVenta = round($valorUnitario * $cantidad - $descuentoBase, 2);
            $totalConIgv = round($precioUnitario * $cantidad, 2) - $descuentoConIgv;
            $igv = round($totalConIgv - $valorVenta, 2);
        } elseif ($tipAfeIgv === '17') {
            $valorVenta = round($valorUnitario * $cantidad - $descuentoBase, 2);
            $totalConIgv = round($precioUnitario * $cantidad, 2) - $descuentoConIgv;
            $igv = round($totalConIgv - $valorVenta, 2);
        } elseif (in_array($tipAfeIgv, ['20', '30', '40'])) {
            $valorVenta = round($valorUnitario * $cantidad - $descuentoBase, 2);
            $igv = 0;
            $porcentajeIgv = 0;
        } elseif ($isGratuitoGravado) {
            // Gratuita gravada (11-17): lleva IGV
            $valorGratuito = (float) ($item['mto_valor_unitario'] ?? $precioUnitario);
            $valorUnitario = 0;
            // LineExtensionAmount = 0 para ítems gratuitos (SUNAT 3271)
            $valorVenta = 0;
            $igvBase = round($valorGratuito * $cantidad, 2);
            $igv = (float) ($item['igv'] ?? round($igvBase * $porcentajeIgv / 100, 2));
        } elseif ($isGratuito) {
            // Gratuita inafecta/exonerada (21, 31-36): NO lleva IGV
            $valorGratuito = (float) ($item['mto_valor_unitario'] ?? $precioUnitario);
            $valorUnitario = 0;
            $valorVenta = 0;
            $igv = 0;
            $porcentajeIgv = 0;
        } else {
            $valorUnitario = (float) ($item['mto_valor_unitario'] ?? $precioUnitario);
            $valorVenta = round($valorUnitario * $cantidad, 2);
            $igv = (float) ($item['igv'] ?? 0);
        }

        // Permitir override manual de valores calculados
        if (! $isGratuito) {
            $valorUnitario = (float) ($item['mto_valor_unitario'] ?? $valorUnitario);
        }
        // Para gratuitas, LineExtensionAmount debe ser 0 independientemente del valor en BD
        if ($isGratuito || $isGratuitoGravado) {
            $valorVenta = 0;
        } else {
            $valorVenta = (float) ($item['mto_valor_venta'] ?? $valorVenta);
        }

        // ISC e ICBPER
        $isc = (float) ($item['isc'] ?? 0);
        $icbper = (float) ($item['icbper'] ?? 0);

        // Determinar la base IGV por defecto
        if ($isGratuitoGravado) {
            $defaultBaseIgv = isset($igvBase) ? $igvBase : round(((float) ($item['mto_valor_unitario'] ?? $precioUnitario)) * $cantidad, 2);
        } else {
            $defaultBaseIgv = $valorVenta;
        }

        // Cuando hay ISC, la base del IGV = valor_venta + ISC (regla SUNAT)
        if ($isc > 0 && ! isset($item['mto_base_igv']) && ! isset($item['igv'])) {
            $baseIgv = round($defaultBaseIgv + $isc, 2);
            $igv = round($baseIgv * $porcentajeIgv / 100, 2);
        } else {
            $igv = (float) ($item['igv'] ?? $igv);
            $baseIgv = (float) ($item['mto_base_igv'] ?? $defaultBaseIgv);
        }

        // totalImpuestos = IGV + ISC + ICBPER (SUNAT 3292 requiere sumar todos los impuestos)
        $totalImpuestos = (float) ($item['total_impuestos'] ?? ($igv + $isc + $icbper));

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
            ->setTotalImpuestos($totalImpuestos);

        if (! empty($item['cod_producto_sunat'])) {
            $detail->setCodProductoSunat($item['cod_producto_sunat']);
        }

        if ($isGratuito || $isGratuitoGravado) {
            $mtoValorGratuito = (float) ($item['mto_valor_unitario'] ?? $precioUnitario);
            $detail->setMtoPrecioUnitario(0);
            $detail->setMtoValorGratuito($mtoValorGratuito);
        } else {
            // SUNAT 3270: PriceAmount debe reflejar el precio efectivo después del descuento por línea
            if ($descuentoConIgv > 0 && $cantidad > 0) {
                $effectiveTotal = round($precioUnitario * $cantidad, 2) - $descuentoConIgv;
                $detail->setMtoPrecioUnitario(round($effectiveTotal / $cantidad, 2));
            } else {
                $detail->setMtoPrecioUnitario($precioUnitario);
            }
        }

        // Descuentos por línea (usar los recalculados con base sin IGV)
        $descuentosToUse = $recalculatedDescuentos ?? ($item['descuentos'] ?? null);
        if (! empty($descuentosToUse)) {
            $descuentos = [];
            foreach ($descuentosToUse as $desc) {
                $descuentos[] = (new Charge())
                    ->setCodTipo($desc['cod_tipo'] ?? '00')
                    ->setMontoBase(round((float) ($desc['monto_base'] ?? 0), 2))
                    ->setFactor((float) ($desc['factor'] ?? 1))
                    ->setMonto(round((float) $desc['monto'], 2));
            }
            $detail->setDescuentos($descuentos);
        }

        // ISC
        if ($isc > 0) {
            $mtoBaseIsc = (float) ($item['mto_base_isc'] ?? $valorVenta);
            $pctIsc = (float) ($item['porcentaje_isc'] ?? 0);
            if ($pctIsc <= 0 && $mtoBaseIsc > 0) {
                $pctIsc = round($isc / $mtoBaseIsc * 100, 2);
            }
            $detail->setIsc($isc);
            $detail->setMtoBaseIsc($mtoBaseIsc);
            $detail->setPorcentajeIsc($pctIsc);
            $detail->setTipSisIsc($item['tip_sis_isc'] ?? '01');
        }

        // ICBPER (bolsas plásticas)
        if ($icbper > 0) {
            $detail->setIcbper($icbper);
            $factorIcbper = (float) ($item['factor_icbper'] ?? 0);
            if ($factorIcbper <= 0 && $cantidad > 0) {
                $factorIcbper = round($icbper / $cantidad, 2);
            }
            $detail->setFactorIcbper($factorIcbper);
        }

        return $detail;
    }

    private function buildCompany(string $codLocal = '0000'): Company
    {
        $company = (new Company())
            ->setRuc($this->tenant->ruc)
            ->setRazonSocial($this->tenant->razon_social);

        // SUNAT 4092: Greenter siempre emite <cac:PartyName/cbc:Name>, si está vacío
        // SUNAT observa. Fallback a razon_social cuando no hay nombre_comercial.
        $company->setNombreComercial($this->tenant->nombre_comercial ?: $this->tenant->razon_social);

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
        $client = (new Client())
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
