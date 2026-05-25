<?php

namespace App\Services\Greenter\Builders;

use App\Models\Tenant;
use App\Services\TaxRateService;
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
    private TaxRateService $taxRates;
    private ?string $fechaEmision = null;

    public function __construct(Tenant $tenant, ?TaxRateService $taxRates = null)
    {
        $this->tenant = $tenant;
        $this->taxRates = $taxRates ?? new TaxRateService();
    }

    public function build(array $data): Invoice
    {
        $this->fechaEmision = $data['fecha_emision'] ?? null;
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
            $cuotasData = $data['cuotas'] ?? [];
            // SUNAT: FormaPagoCredito = monto pendiente = suma de cuotas
            $sumCuotas = array_sum(array_column($cuotasData, 'monto'));
            $totalCredito = $sumCuotas > 0 ? $sumCuotas : ($data['mto_imp_venta'] ?? 0);
            $invoice->setFormaPago(new FormaPagoCredito($totalCredito));

            if (! empty($cuotasData)) {
                $cuotas = [];
                foreach ($cuotasData as $i => $cuota) {
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

        // Anticipos: ajustar TaxableAmount y TaxAmount ANTES de llamar a set*() en Greenter.
        // SUNAT 3277: TaxableAmount = Σ(líneas) - AllowanceCharge04_base
        // La base del anticipo = totalAnticipos / (1 + igvRate) para que 3277, 3291 y 3294 cuadren.
        $anticipoBase = 0.0;
        $totalAnticiposBuilder = (float) ($data['total_anticipos'] ?? 0);
        if ($totalAnticiposBuilder <= 0 && ! empty($data['anticipos'])) {
            $totalAnticiposBuilder = (float) (array_sum(array_column($data['anticipos'], 'monto'))
                ?: array_sum(array_column($data['anticipos'], 'total')));
        }
        if ($totalAnticiposBuilder > 0 && $mtoOperGravadas > 0) {
            $igvFracAnticipo = ($this->taxRates?->defaultIgvRate($this->tenant, $this->fechaEmision) ?? 18) / 100;
            $anticipoBase = round($totalAnticiposBuilder / (1 + $igvFracAnticipo), 2);
            $mtoOperGravadas = round(max(0, $mtoOperGravadas - $anticipoBase), 2);
            $mtoIgv = round($mtoOperGravadas * $igvFracAnticipo, 2);
        }

        // TaxTotal/TaxAmount must equal TaxSubtotal/TaxAmount (SUNAT 3294).
        // When anticipos are present, use the adjusted mtoIgv (net) instead of the gross DB value.
        $totalImpuestosHeader = $anticipoBase > 0
            ? $mtoIgv + (float) ($data['mto_isc'] ?? 0) + (float) ($data['mto_icbper'] ?? 0) + (float) ($data['mto_ivap'] ?? 0)
            : (float) ($data['total_impuestos'] ?? $mtoIgv);

        // Calcular mto_igv_gratuitas desde ítems si no está almacenado
        if (empty($data['mto_igv_gratuitas']) && ! empty($data['items'])) {
            $gratuitoGravadoCodes = ['11', '12', '13', '14', '15', '16'];
            $computedIgvGratuitas = 0;
            foreach ($data['items'] as $item) {
                if (in_array($item['tip_afe_igv'] ?? '', $gratuitoGravadoCodes)) {
                    $computedIgvGratuitas += (float) ($item['igv'] ?? 0);
                }
            }
            if ($computedIgvGratuitas > 0) {
                $data['mto_igv_gratuitas'] = round($computedIgvGratuitas, 2);
            }
        }

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
            ->setTotalImpuestos($totalImpuestosHeader)
            ->setValorVenta((float) ($data['valor_venta'] ?? 0))
            ->setSubTotal((float) ($data['sub_total'] ?? 0))
            ->setMtoImpVenta((float) ($data['mto_imp_venta'] ?? 0));

        if (! empty($data['mto_igv_gratuitas'])) {
            $invoice->setMtoIGVGratuitas((float) $data['mto_igv_gratuitas']);
        }
        if (! empty($data['mto_isc'])) {
            $invoice->setMtoISC((float) $data['mto_isc']);
            // SUNAT 3296: la base de ISC a nivel de documento debe coincidir con la suma de bases de ISC de cada línea
            $invoice->setMtoBaseIsc((float) ($data['mto_base_isc'] ?? $data['mto_oper_gravadas'] ?? 0));
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
        // Leyenda 2006 requerida por SUNAT cuando hay detracción
        if (! empty($data['detraccion'])) {
            $ya_tiene_2006 = collect($legends)->contains(fn($l) => $l->getCode() === '2006');
            if (! $ya_tiene_2006) {
                $legends[] = (new Legend())->setCode('2006')->setValue('Operación sujeta a detracción');
            }
        }
        // Leyenda 2000 requerida por SUNAT cuando hay percepción
        if (! empty($data['percepcion'])) {
            $ya_tiene_2000 = collect($legends)->contains(fn($l) => $l->getCode() === '2000');
            if (! $ya_tiene_2000) {
                $legends[] = (new Legend())->setCode('2000')->setValue('COMPROBANTE DE PERCEPCIÓN');
            }
        }
        // Leyenda 2007 requerida por SUNAT cuando hay ítems con IVAP (tip_afe_igv "17")
        $tieneIvap = collect($data['items'] ?? [])->contains(fn($it) => ($it['tip_afe_igv'] ?? '') === '17');
        if ($tieneIvap) {
            $ya_tiene_2007 = collect($legends)->contains(fn($l) => $l->getCode() === '2007');
            if (! $ya_tiene_2007) {
                $legends[] = (new Legend())->setCode('2007')->setValue('Operación sujeta al IVAP');
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
            // SUNAT espera MultiplierFactorNumeric como factor decimal (0.02 para 2%), no como porcentaje
            $porcentaje = (float) $per['porcentaje'];
            if ($porcentaje > 1) {
                $porcentaje = $porcentaje / 100;
            }
            $invoice->setPerception(
                (new SalePerception())
                    ->setCodReg($per['cod_reg'] ?? '51')
                    ->setPorcentaje($porcentaje)
                    ->setMtoBase((float) $per['mto_base'])
                    ->setMto((float) $per['mto'])
                    ->setMtoTotal((float) $per['mto_total'])
            );
        }

        // Anticipos
        $totalAnticipos = 0;
        if (! empty($data['anticipos'])) {
            $anticipos = [];
            foreach ($data['anticipos'] as $ant) {
                // Acepta nro_doc_rel directo o construye desde serie+correlativo
                $nroDocRel = $ant['nro_doc_rel']
                    ?? (isset($ant['serie'], $ant['correlativo'])
                        ? $ant['serie'] . '-' . $ant['correlativo']
                        : '');
                $anticipos[] = (new Prepayment())
                    ->setTipoDocRel($ant['tipo_doc_rel'] ?? $ant['tipo_doc'] ?? '02')
                    ->setNroDocRel($nroDocRel)
                    ->setTotal((float) ($ant['total'] ?? $ant['monto'] ?? 0));
            }
            $invoice->setAnticipos($anticipos);
            $totalAnticipos = (float) ($data['total_anticipos']
                ?? array_sum(array_column($data['anticipos'], 'total'))
                ?: array_sum(array_column($data['anticipos'], 'monto')));
            $invoice->setTotalAnticipos($totalAnticipos);
        }

        // Descuentos globales
        $descuentos = [];
        if (! empty($data['descuentos_globales'])) {
            foreach ($data['descuentos_globales'] as $desc) {
                $monto = (float) ($desc['monto'] ?? 0);

                if (! empty($desc['porcentaje'])) {
                    // porcentaje: 0-100 → convierte a factor decimal
                    $factor = round((float) $desc['porcentaje'] / 100, 6);
                    $montoBase = round($mtoOperGravadas, 2);
                    $monto = round($montoBase * $factor, 2);
                } elseif (! empty($desc['factor']) && ! empty($desc['monto_base'])) {
                    // Forma explícita: el usuario mandó los tres valores
                    $factor = (float) $desc['factor'];
                    $montoBase = (float) $desc['monto_base'];
                } else {
                    // Solo monto: calcular monto_base y factor automáticamente
                    $montoBase = round($mtoOperGravadas ?: $monto, 2);
                    $factor = $montoBase > 0 ? round($monto / $montoBase, 6) : 1;
                }

                $descuentos[] = (new Charge())
                    ->setCodTipo($desc['cod_tipo'] ?? '02')
                    ->setMontoBase($montoBase)
                    ->setFactor($factor)
                    ->setMonto($monto);
            }
        }
        // SUNAT 3287: anticipos requieren descuento global tipo 04.
        // $anticipoBase ya fue calculado arriba como totalAnticipos/(1+igvRate).
        if ($totalAnticipos > 0 && $anticipoBase > 0) {
            $hasTipo04 = false;
            foreach ($descuentos as $desc) {
                if ($desc->getCodTipo() === '04') {
                    $hasTipo04 = true;
                    break;
                }
            }
            if (! $hasTipo04) {
                $descuentos[] = (new Charge())
                    ->setCodTipo('04')
                    ->setMontoBase($anticipoBase)
                    ->setFactor(1)
                    ->setMonto($anticipoBase);
            }
        }
        if (! empty($descuentos)) {
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

        // NRUS default '30' (Inafecto); otros regímenes default '10' (Gravado).
        $tipAfeIgv = $item['tip_afe_igv'] ?? $this->taxRates->defaultTipAfeIgv($this->tenant);
        // Tasa según régimen del tenant + fecha (general=18, mype_restaurantes según schedule, nrus=0)
        $porcentajeIgv = $this->taxRates->rateForItem($item, $this->tenant, $this->fechaEmision);
        $cantidad = (float) $item['cantidad'];
        $precioUnitario = (float) $item['precio_unitario'];
        $isGratuito = in_array($tipAfeIgv, $gratuitoCodes);
        $isGratuitoGravado = in_array($tipAfeIgv, $gratuitoGravadoCodes);

        // Calcular valor unitario según tipo de afectación (mayor precisión para SUNAT)
        if ($tipAfeIgv === '10') {
            $valorUnitario = round($precioUnitario / (1 + $porcentajeIgv / 100), 10);
        } elseif ($tipAfeIgv === '17') {
            $valorUnitario = round($precioUnitario / (1 + $porcentajeIgv / 100), 10);
        } elseif (in_array($tipAfeIgv, ['20', '30', '40'])) {
            $valorUnitario = $precioUnitario;
        }

        // Calcular descuentos por línea sobre la base sin IGV (cod_tipo '00' y '01' se tratan igual)
        $descuentoBase = 0;
        $descuentoConIgv = 0;
        $recalculatedDescuentos = null;
        if (! empty($item['descuentos'])) {
            $valorBrutoForDiscount = isset($valorUnitario) ? $valorUnitario : ($isGratuito ? ((float) ($item['mto_valor_unitario'] ?? $precioUnitario)) : $precioUnitario);
            $valorBruto = $valorBrutoForDiscount * $cantidad;
            $totalConIgvBruto = round($precioUnitario * $cantidad, 2);
            $recalculatedDescuentos = [];
            foreach ($item['descuentos'] as $desc) {
                $codTipo = $desc['cod_tipo'] ?? '00';
                if ($codTipo === '00' || $codTipo === '01') {
                    $montoBase = round($valorBruto, 2);

                    if (! empty($desc['porcentaje'])) {
                        // porcentaje 0-100 → factor decimal
                        $factor = round((float) $desc['porcentaje'] / 100, 6);
                        $monto = round($montoBase * $factor, 2);
                    } elseif (! empty($desc['factor'])) {
                        // factor decimal (forma original)
                        $factor = (float) $desc['factor'];
                        $monto = round($montoBase * $factor, 2);
                    } else {
                        // Solo monto fijo: calcular factor
                        $monto = (float) ($desc['monto'] ?? 0);
                        $factor = $montoBase > 0 ? round($monto / $montoBase, 6) : 0;
                    }

                    $descuentoBase += $monto;
                    $descuentoConIgv += round($totalConIgvBruto * $factor, 2);
                    // Normalizar "01" → "00" en el XML: ambos se aplican a la base en nuestra implementación
                    // SUNAT 3271: "01" exige TaxableAmount=gross; como reducimos la base, debe ser "00"
                    $recalculatedDescuentos[] = [
                        'cod_tipo' => '00',
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
            // LineExtensionAmount = monto referencial (greenter factura-gratuita.php)
            $igvBase = round($valorGratuito * $cantidad, 2);
            $valorVenta = $igvBase;
            $igv = (float) ($item['igv'] ?? round($igvBase * $porcentajeIgv / 100, 2));
        } elseif ($isGratuito) {
            // Gratuita inafecta/exonerada (21, 31-36): NO lleva IGV
            $valorGratuito = (float) ($item['mto_valor_unitario'] ?? $precioUnitario);
            $valorUnitario = 0;
            $valorVenta = 0;
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
        if ($isGratuitoGravado) {
            // Gravada gratuita (11-16): LineExtensionAmount = monto referencial (base del IGV)
            $valorVenta = (float) ($item['mto_valor_venta'] ?? $valorVenta);
        } elseif ($isGratuito) {
            // Inafecta/exonerada gratuita (21, 31-36): LineExtensionAmount = monto referencial
            // SUNAT 3224: LineExtensionAmount debe ser el ref × cantidad para que SUNAT no observe
            $valorRefUnit = (float) ($item['mto_valor_unitario'] ?? 0);
            if ($valorRefUnit <= 0) {
                $valorRefUnit = (float) ($item['mto_valor_venta'] ?? 0) / max($cantidad, 1);
            }
            $valorVenta = round($valorRefUnit * $cantidad, 2);
        } else {
            $valorVenta = (float) ($item['mto_valor_venta'] ?? $valorVenta);
        }

        // ISC e ICBPER
        $isc = (float) ($item['isc'] ?? 0);
        $icbper = (float) ($item['icbper'] ?? 0);

        // Determinar la base IGV por defecto
        if ($isGratuitoGravado) {
            // Para gratuita gravada: base IGV = monto referencial (no valorVenta que es 0)
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

        // SUNAT: Gratuita inafecta/exonerada (21, 31-36) → TaxableAmount = LineExtensionAmount (ref × cantidad)
        if (in_array($tipAfeIgv, $gratuitoInafectoCodes)) {
            $baseIgv = $valorVenta;
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

        if ($isGratuito || $isGratuitoGravado) {
            // Gratuito: SUNAT requiere AlternativeConditionPrice con PriceTypeCode=02
            // SUNAT 2640: Price/PriceAmount DEBE ser 0 para gratuita (no el valor referencial)
            $mtoValorGratuito = (float) ($item['mto_valor_unitario'] ?? 0);
            if ($mtoValorGratuito <= 0 && $cantidad > 0) {
                $refTotal = (float) ($item['mto_valor_venta'] ?? 0);
                $mtoValorGratuito = round($refTotal / $cantidad, 2);
            }
            if ($mtoValorGratuito <= 0) {
                $mtoValorGratuito = $precioUnitario;
            }
            $detail->setMtoPrecioUnitario(0);
            $detail->setMtoValorGratuito($mtoValorGratuito);
        } else {
            // SUNAT 3270/3271: PricingReference × Qty debe cuadrar con LineExtensionAmount + IGV
            // Usar más precisión (10 decimales) para evitar diferencia de redondeo
            if ($descuentoConIgv > 0 && $cantidad > 0) {
                $effectiveTotal = round($precioUnitario * $cantidad, 2) - $descuentoConIgv;
                $detail->setMtoPrecioUnitario(round($effectiveTotal / $cantidad, 10));
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
        $company = new Company();
        $company
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
