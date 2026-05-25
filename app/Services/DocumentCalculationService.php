<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;

class DocumentCalculationService
{
    public function __construct(private ?TaxRateService $taxRates = null)
    {
        $this->taxRates ??= new TaxRateService();
    }

    public function calculateItems(array $items, ?Tenant $tenant = null, ?string $fechaEmision = null): array
    {
        $gratuitoGravadoCodes = ['11', '12', '13', '14', '15', '16'];
        $gratuitoInafectoCodes = ['21', '31', '32', '33', '34', '35', '36'];
        $gratuitoCodes = array_merge($gratuitoGravadoCodes, $gratuitoInafectoCodes);
        $calculated = [];

        foreach ($items as $item) {
            // Para NRUS, si el item no trae tip_afe_igv, default a '30' (Inafecto).
            // Para otros regímenes, default a '10' (Gravado) como siempre.
            $tipAfeIgv = $item['tip_afe_igv'] ?? $this->taxRates->defaultTipAfeIgv($tenant);
            // Tasa por régimen (general=18, mype_restaurantes=variable por año, nrus=0), o override por item.
            $porcentajeIgv = $this->taxRates->rateForItem($item, $tenant, $fechaEmision);
            $cantidad = (float) $item['cantidad'];
            $precioUnitario = (float) $item['precio_unitario'];
            $porcentajeIsc = (float) ($item['porcentaje_isc'] ?? 0);
            $isGratuito = in_array($tipAfeIgv, $gratuitoCodes);
            $isGratuitoGravado = in_array($tipAfeIgv, $gratuitoGravadoCodes);

            // Usar más precisión para valorUnitario (SUNAT permite hasta 10 decimales)
            if ($tipAfeIgv === '10') {
                // Si hay ISC y no se especificó mto_valor_unitario, precio_unitario incluye ISC+IGV
                if ($porcentajeIsc > 0 && ! isset($item['mto_valor_unitario'])) {
                    $valorUnitario = round($precioUnitario / ((1 + $porcentajeIsc / 100) * (1 + $porcentajeIgv / 100)), 10);
                } else {
                    $valorUnitario = round($precioUnitario / (1 + $porcentajeIgv / 100), 10);
                }
            } elseif ($tipAfeIgv === '17') {
                $valorUnitario = round($precioUnitario / (1 + $porcentajeIgv / 100), 10);
            } elseif (in_array($tipAfeIgv, ['20', '30', '40'])) {
                $valorUnitario = $precioUnitario;
            } elseif ($isGratuitoGravado) {
                $valorUnitario = 0;
            } elseif ($isGratuito) {
                $valorUnitario = 0;
            } else {
                $valorUnitario = (float) ($item['mto_valor_unitario'] ?? $precioUnitario);
            }

            // Calcular descuentos por línea sobre la base sin IGV
            $descuentoBase = 0;
            $descuentoConIgv = 0;
            $recalculatedDescuentos = null;
            if (! empty($item['descuentos'])) {
                $valorBruto = ($isGratuito ? ((float) ($item['mto_valor_unitario'] ?? $precioUnitario)) : $valorUnitario) * $cantidad;
                $totalConIgvBruto = round($precioUnitario * $cantidad, 2);
                $recalculatedDescuentos = [];
                foreach ($item['descuentos'] as $desc) {
                    $codTipo = $desc['cod_tipo'] ?? '00';
                    // cod_tipo "00" y "01" se tratan igual a nivel de ítem (reducen base)
                    // La distinción "no afecta base" solo aplica a descuentos globales (cod "03")
                    if ($codTipo === '00' || $codTipo === '01') {
                        $montoBase = round($valorBruto, 2);

                        if (! empty($desc['porcentaje'])) {
                            $factor = round((float) $desc['porcentaje'] / 100, 6);
                            $monto  = round($montoBase * $factor, 2);
                        } elseif (! empty($desc['factor'])) {
                            $factor = (float) $desc['factor'];
                            $monto  = round($montoBase * $factor, 2);
                        } else {
                            $monto  = (float) ($desc['monto'] ?? 0);
                            $factor = $montoBase > 0 ? round($monto / $montoBase, 6) : 0;
                        }

                        $descuentoBase    += $monto;
                        $descuentoConIgv  += round($totalConIgvBruto * $factor, 2);
                        $recalculatedDescuentos[] = [
                            'cod_tipo'   => $codTipo,
                            'monto_base' => $montoBase,
                            'factor'     => $factor,
                            'monto'      => $monto,
                        ];
                    } else {
                        $recalculatedDescuentos[] = $desc;
                    }
                }
            }

            if ($tipAfeIgv === '10') {
                $valorVenta = round($valorUnitario * $cantidad - $descuentoBase, 2);
                // IGV = totalConIgv - valorVenta (evita centavo de redondeo)
                $totalConIgv = round($precioUnitario * $cantidad, 2) - $descuentoConIgv;
                $igv = round($totalConIgv - $valorVenta, 2);
            } elseif ($tipAfeIgv === '20') {
                $valorVenta = round($valorUnitario * $cantidad - $descuentoBase, 2);
                $igv = 0;
                $porcentajeIgv = 0;
            } elseif ($tipAfeIgv === '30' || $tipAfeIgv === '40') {
                $valorVenta = round($valorUnitario * $cantidad - $descuentoBase, 2);
                $igv = 0;
                $porcentajeIgv = 0;
            } elseif ($tipAfeIgv === '17') {
                $valorVenta = round($valorUnitario * $cantidad - $descuentoBase, 2);
                $totalConIgv = round($precioUnitario * $cantidad, 2) - $descuentoConIgv;
                $igv = round($totalConIgv - $valorVenta, 2);
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
            $isc = (float) ($item['isc'] ?? 0);
            $icbper = (float) ($item['icbper'] ?? 0);
            $factorIcbper = (float) ($item['factor_icbper'] ?? 0);

            // Auto-calcular ISC desde porcentaje_isc
            if ($isc <= 0 && $porcentajeIsc > 0) {
                $isc = round($valorVenta * $porcentajeIsc / 100, 2);
            }

            // Auto-calcular ICBPER desde factor_icbper × cantidad
            if ($icbper <= 0 && $factorIcbper > 0) {
                $icbper = round($factorIcbper * $cantidad, 2);
            }

            // Cuando hay ISC, la base del IGV = valor_venta + ISC y el IGV debe recalcularse
            if ($isc > 0 && ! isset($item['mto_base_igv']) && ! isset($item['igv'])) {
                $baseIgv = round($valorVenta + $isc, 2);
                $igv = round($baseIgv * $porcentajeIgv / 100, 2);
            } else {
                $igv = (float) ($item['igv'] ?? $igv);
                $baseIgv = (float) ($item['mto_base_igv'] ?? $valorVenta);
            }

            // SUNAT: Gratuita inafecta/exonerada (21, 31-36) → TaxableAmount = LineExtensionAmount (ref × cantidad)
            // Evita error 3272 (base imponible != importes) y 3224 (observación gratuita)
            if (in_array($tipAfeIgv, $gratuitoInafectoCodes)) {
                $baseIgv = $valorVenta;
            }

            $totalImpuestos = (float) ($item['total_impuestos'] ?? ($igv + $isc + $icbper));

            $calculated[] = [
                'codigo' => $item['codigo'] ?? null,
                'descripcion' => $item['descripcion'],
                'unidad' => $item['unidad'],
                'cantidad' => $cantidad,
                'mto_valor_unitario' => $valorUnitario,
                'mto_valor_venta' => $valorVenta,
                'mto_base_igv' => $baseIgv,
                'porcentaje_igv' => $porcentajeIgv,
                'igv' => $igv,
                'tip_afe_igv' => $tipAfeIgv,
                'isc' => $isc,
                'icbper' => $icbper,
                'total_impuestos' => $totalImpuestos,
                'mto_precio_unitario' => $precioUnitario,
                'descuento' => (float) ($item['descuento'] ?? $descuentoBase),
                'descuentos' => $recalculatedDescuentos ?? $item['descuentos'] ?? null,
            ];
        }

        return $calculated;
    }

    public function calculateTotals(array $calculatedItems, array &$data, ?Tenant $tenant = null, ?string $fechaEmision = null): array
    {
        $gratuitoCodes = ['11', '12', '13', '14', '15', '16', '21', '31', '32', '33', '34', '35', '36'];

        $gravadas = 0;
        $exoneradas = 0;
        $inafectas = 0;
        $exportacion = 0;
        $gratuitas = 0;
        $baseIvap = 0;
        $totalIvap = 0;
        $totalIgv = 0;
        $igvGratuitas = 0;
        $totalIsc = 0;
        $totalIcbper = 0;
        $sumDescuentosNoBase = 0;

        foreach ($calculatedItems as $item) {
            $tipAfeIgv = $item['tip_afe_igv'];
            $valorVenta = $item['mto_valor_venta'];

            if ($tipAfeIgv === '10') {
                $gravadas += $valorVenta;
                $totalIgv += $item['igv'];
            } elseif ($tipAfeIgv === '17') {
                $baseIvap += $valorVenta;
                $totalIvap += $item['igv'];
            } elseif ($tipAfeIgv === '20') {
                $exoneradas += $valorVenta;
            } elseif ($tipAfeIgv === '30') {
                $inafectas += $valorVenta;
            } elseif ($tipAfeIgv === '40') {
                $exportacion += $valorVenta;
            } elseif (in_array($tipAfeIgv, $gratuitoCodes)) {
                $gratuitas += $valorVenta;
                $igvGratuitas += $item['igv'];
            }

            $totalIsc += $item['isc'];
            $totalIcbper += $item['icbper'];

            // cod_tipo "01" ya fue aplicado al mto_valor_venta en calculateItems,
            // no se agrega a sumDescuentosNoBase para evitar error SUNAT 3300
        }

        // El anticipo se declara via PrepaidPayment en el XML (setAnticipos/setTotalAnticipos).
        // NO se agrega AllowanceCharge tipo 04 porque genera validaciones en cascada de SUNAT
        // (3277 → 3291 → 3294) imposibles de satisfacer simultáneamente con los valores brutos.
        if (! empty($data['anticipos']) && ! isset($data['total_anticipos'])) {
            $data['total_anticipos'] = collect($data['anticipos'])->sum('monto');
        }

        $descuentoGlobalGravadas = 0;
        if (! empty($data['descuentos_globales'])) {
            $normalizedDescuentos = [];
            foreach ($data['descuentos_globales'] as $desc) {
                $codTipo = $desc['cod_tipo'] ?? '02';

                // Normalizar: resolver monto desde porcentaje si no viene monto
                if (! empty($desc['porcentaje']) && empty($desc['monto'])) {
                    $baseParaDesc = in_array($codTipo, ['02', '04', '05', '06']) ? $gravadas : ($exoneradas + $inafectas + $exportacion + $gravadas);
                    $monto = round($baseParaDesc * ((float) $desc['porcentaje'] / 100), 2);
                } else {
                    $monto = (float) ($desc['monto'] ?? 0);
                }

                // Calcular monto_base y factor para almacenar valores completos
                $montoBase = round(in_array($codTipo, ['02', '04', '05', '06']) ? $gravadas : ($gravadas + $exoneradas + $inafectas), 2);
                $factor = $montoBase > 0 ? round($monto / $montoBase, 6) : 0;

                $normalizedDescuentos[] = array_merge($desc, [
                    'monto'      => $monto,
                    'monto_base' => (float) ($desc['monto_base'] ?? $montoBase),
                    'factor'     => (float) ($desc['factor'] ?? $factor),
                ]);

                if ($codTipo === '02') {
                    $descuentoGlobalGravadas += $monto;
                } elseif ($codTipo === '03') {
                    $sumDescuentosNoBase += $monto;
                }
            }
            $data['descuentos_globales'] = $normalizedDescuentos;
        }

        // Aplicar descuento global a gravadas y recalcular el IGV usando la tasa efectiva
        if ($descuentoGlobalGravadas > 0) {
            // Deriva la tasa de los ítems gravados emitidos; si no hay, usa la del régimen del tenant.
            $defaultFrac = $this->taxRates->defaultIgvRate($tenant, $fechaEmision) / 100;
            $igvRate = ($gravadas > 0) ? ($totalIgv / $gravadas) : $defaultFrac;
            $gravadas -= $descuentoGlobalGravadas;
            $totalIgv = round($gravadas * $igvRate, 2);
        }

        // Asegurar que todos los totales sean no negativos (SUNAT rechaza montos negativos)
        $gravadas = max(0, round($gravadas, 2));
        $exoneradas = max(0, round($exoneradas, 2));
        $inafectas = max(0, round($inafectas, 2));
        $exportacion = max(0, round($exportacion, 2));
        $baseIvap = max(0, round($baseIvap, 2));
        $totalIvap = max(0, round($totalIvap, 2));
        $gratuitas = max(0, round($gratuitas, 2));
        $totalIgv = max(0, round($totalIgv, 2));
        $igvGratuitas = round($igvGratuitas, 2);
        $totalIsc = max(0, round($totalIsc, 2));
        $totalIcbper = max(0, round($totalIcbper, 2));
        $totalImpuestos = round($totalIgv + $totalIvap + $totalIsc + $totalIcbper, 2);
        $valorVenta = round($gravadas + $exoneradas + $inafectas + $exportacion + $baseIvap, 2);
        $sumDescuentosNoBase = round($sumDescuentosNoBase, 2);
        $subTotal = round($valorVenta + $totalImpuestos, 2);
        $totalAnticipos = (float) ($data['total_anticipos'] ?? collect($data['anticipos'] ?? [])->sum('monto'));
        $mtoImpVenta = max(0, round($subTotal - $totalAnticipos - $sumDescuentosNoBase, 2));

        // El anticipo se declara como PrepaidAmount en el XML (línea separada).
        // mto_oper_gravadas y mto_igv siempre reflejan el total bruto de los ítems;
        // mto_imp_venta ya descuenta el anticipo.
        $gravadas_netas = $gravadas;
        $igv_neto = $totalIgv;
        $totalImpuestos_neto = $totalImpuestos;

        return [
            'mto_oper_gravadas' => (float) ($data['mto_oper_gravadas'] ?? $gravadas_netas),
            'mto_oper_exoneradas' => (float) ($data['mto_oper_exoneradas'] ?? $exoneradas),
            'mto_oper_inafectas' => (float) ($data['mto_oper_inafectas'] ?? $inafectas),
            'mto_oper_exportacion' => (float) ($data['mto_oper_exportacion'] ?? $exportacion),
            'mto_oper_gratuitas' => (float) ($data['mto_oper_gratuitas'] ?? $gratuitas),
            'mto_igv' => (float) ($data['mto_igv'] ?? $igv_neto),
            'mto_base_ivap' => (float) ($data['mto_base_ivap'] ?? $baseIvap),
            'mto_ivap' => (float) ($data['mto_ivap'] ?? $totalIvap),
            'mto_igv_gratuitas' => (float) ($data['mto_igv_gratuitas'] ?? $igvGratuitas),
            'mto_isc' => (float) ($data['mto_isc'] ?? $totalIsc),
            'mto_icbper' => (float) ($data['mto_icbper'] ?? $totalIcbper),
            'total_impuestos' => (float) ($data['total_impuestos'] ?? $totalImpuestos_neto),
            'valor_venta' => (float) ($data['valor_venta'] ?? $valorVenta),   // siempre el total de ítems
            'sub_total' => (float) ($data['sub_total'] ?? $subTotal),          // siempre el total de ítems
            'mto_imp_venta' => (float) ($data['mto_imp_venta'] ?? $mtoImpVenta),
            'sum_otros_descuentos' => (float) ($data['sum_otros_descuentos'] ?? $sumDescuentosNoBase),
            'total_descuentos' => (float) ($data['total_descuentos'] ?? ($descuentoGlobalGravadas + $sumDescuentosNoBase)),
        ];
    }

    public function generateLeyenda(float $total, string $moneda): string
    {
        $entero = (int) $total;
        $decimales = round(($total - $entero) * 100);
        $monedaTexto = $moneda === 'PEN' ? 'SOLES' : 'DOLARES AMERICANOS';

        $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
        $especiales = ['DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE'];
        $decenas = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

        $texto = $this->numberToWords($entero, $unidades, $especiales, $decenas, $centenas);

        return strtoupper($texto) . ' Y ' . str_pad((string) $decimales, 2, '0', STR_PAD_LEFT) . '/100 ' . $monedaTexto;
    }

    private function numberToWords(int $number, array $unidades, array $especiales, array $decenas, array $centenas): string
    {
        if ($number === 0) {
            return 'CERO';
        }
        if ($number === 100) {
            return 'CIEN';
        }

        $resultado = '';

        if ($number >= 1000000) {
            $millones = (int) ($number / 1000000);
            $resultado .= ($millones === 1 ? 'UN MILLON' : $this->numberToWords($millones, $unidades, $especiales, $decenas, $centenas) . ' MILLONES');
            $number %= 1000000;
            if ($number > 0) {
                $resultado .= ' ';
            }
        }

        if ($number >= 1000) {
            $miles = (int) ($number / 1000);
            $resultado .= ($miles === 1 ? 'MIL' : $this->numberToWords($miles, $unidades, $especiales, $decenas, $centenas) . ' MIL');
            $number %= 1000;
            if ($number > 0) {
                $resultado .= ' ';
            }
        }

        if ($number >= 100) {
            if ($number === 100) {
                return $resultado . 'CIEN';
            }
            $resultado .= $centenas[(int) ($number / 100)] . ' ';
            $number %= 100;
        }

        if ($number >= 10 && $number <= 15) {
            $resultado .= $especiales[$number - 10];
        } elseif ($number >= 16 && $number <= 19) {
            $resultado .= 'DIECI' . $unidades[$number - 10];
        } elseif ($number >= 21 && $number <= 29) {
            $resultado .= 'VEINTI' . $unidades[$number - 20];
        } elseif ($number >= 10) {
            $resultado .= $decenas[(int) ($number / 10)];
            $number %= 10;
            if ($number > 0) {
                $resultado .= ' Y ' . $unidades[$number];
            }
        } elseif ($number > 0) {
            $resultado .= $unidades[$number];
        }

        return trim($resultado);
    }
}
