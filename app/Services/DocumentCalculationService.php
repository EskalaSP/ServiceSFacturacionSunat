<?php

namespace App\Services;

class DocumentCalculationService
{
    public function calculateItems(array $items): array
    {
        $gratuitoGravadoCodes = ['11', '12', '13', '14', '15', '16'];
        $gratuitoInafectoCodes = ['21', '31', '32', '33', '34', '35', '36'];
        $gratuitoCodes = array_merge($gratuitoGravadoCodes, $gratuitoInafectoCodes);
        $calculated = [];

        foreach ($items as $item) {
            $tipAfeIgv = $item['tip_afe_igv'] ?? '10';
            $defaultIgvRate = ($tipAfeIgv === '17') ? 4 : 18;
            $porcentajeIgv = (float) ($item['porcentaje_igv'] ?? $defaultIgvRate);
            $cantidad = (float) $item['cantidad'];
            $precioUnitario = (float) $item['precio_unitario'];
            $isGratuito = in_array($tipAfeIgv, $gratuitoCodes);
            $isGratuitoGravado = in_array($tipAfeIgv, $gratuitoGravadoCodes);

            // Usar más precisión para valorUnitario (SUNAT permite hasta 10 decimales)
            if ($tipAfeIgv === '10') {
                $valorUnitario = round($precioUnitario / (1 + $porcentajeIgv / 100), 10);
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
                    if ($codTipo === '00') {
                        $factor = (float) ($desc['factor'] ?? 0);
                        $montoBase = $valorBruto;
                        $monto = round($montoBase * $factor, 2);
                        $descuentoBase += $monto;
                        $descuentoConIgv += round($totalConIgvBruto * $factor, 2);
                        $recalculatedDescuentos[] = [
                            'cod_tipo' => $codTipo,
                            'monto_base' => $montoBase,
                            'factor' => $factor,
                            'monto' => $monto,
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

            // When ISC present, IGV base = valor_venta + ISC and IGV must be recalculated
            if ($isc > 0 && ! isset($item['mto_base_igv']) && ! isset($item['igv'])) {
                $baseIgv = round($valorVenta + $isc, 2);
                $igv = round($baseIgv * $porcentajeIgv / 100, 2);
            } else {
                $igv = (float) ($item['igv'] ?? $igv);
                $baseIgv = (float) ($item['mto_base_igv'] ?? $valorVenta);
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

    public function calculateTotals(array $calculatedItems, array $data): array
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

            if (! empty($item['descuentos'])) {
                foreach ($item['descuentos'] as $desc) {
                    if (($desc['cod_tipo'] ?? '00') === '01') {
                        $sumDescuentosNoBase += (float) $desc['monto'];
                    }
                }
            }
        }

        $descuentoGlobalGravadas = 0;
        if (! empty($data['descuentos_globales'])) {
            foreach ($data['descuentos_globales'] as $desc) {
                $codTipo = $desc['cod_tipo'] ?? '02';
                $monto = (float) ($desc['monto'] ?? 0);

                if ($codTipo === '02') {
                    // Descuento global que afecta la base imponible (gravadas)
                    $descuentoGlobalGravadas += $monto;
                } elseif ($codTipo === '03') {
                    // Cargo/descuento que no afecta base imponible
                    $sumDescuentosNoBase += $monto;
                }
                // Tipo 62 (retención) no modifica totales en XML, solo se muestra como descuento
            }
        }

        // Apply global discount to gravadas and recalculate IGV
        if ($descuentoGlobalGravadas > 0) {
            $gravadas -= $descuentoGlobalGravadas;
            $totalIgv = round($gravadas * 0.18, 2);
        }

        $gravadas = round($gravadas, 2);
        $exoneradas = round($exoneradas, 2);
        $inafectas = round($inafectas, 2);
        $exportacion = round($exportacion, 2);
        $baseIvap = round($baseIvap, 2);
        $totalIvap = round($totalIvap, 2);
        $gratuitas = round($gratuitas, 2);
        $totalIgv = round($totalIgv, 2);
        $igvGratuitas = round($igvGratuitas, 2);
        $totalIsc = round($totalIsc, 2);
        $totalIcbper = round($totalIcbper, 2);
        $totalImpuestos = round($totalIgv + $totalIvap + $totalIsc + $totalIcbper, 2);
        $valorVenta = round($gravadas + $exoneradas + $inafectas + $exportacion + $baseIvap, 2);
        $sumDescuentosNoBase = round($sumDescuentosNoBase, 2);
        $subTotal = round($valorVenta + $totalImpuestos, 2);
        $totalAnticipos = (float) ($data['total_anticipos'] ?? 0);
        $mtoImpVenta = round($subTotal - $totalAnticipos - $sumDescuentosNoBase, 2);

        return [
            'mto_oper_gravadas' => (float) ($data['mto_oper_gravadas'] ?? $gravadas),
            'mto_oper_exoneradas' => (float) ($data['mto_oper_exoneradas'] ?? $exoneradas),
            'mto_oper_inafectas' => (float) ($data['mto_oper_inafectas'] ?? $inafectas),
            'mto_oper_exportacion' => (float) ($data['mto_oper_exportacion'] ?? $exportacion),
            'mto_oper_gratuitas' => (float) ($data['mto_oper_gratuitas'] ?? $gratuitas),
            'mto_igv' => (float) ($data['mto_igv'] ?? $totalIgv),
            'mto_base_ivap' => (float) ($data['mto_base_ivap'] ?? $baseIvap),
            'mto_ivap' => (float) ($data['mto_ivap'] ?? $totalIvap),
            'mto_igv_gratuitas' => (float) ($data['mto_igv_gratuitas'] ?? $igvGratuitas),
            'mto_isc' => (float) ($data['mto_isc'] ?? $totalIsc),
            'mto_icbper' => (float) ($data['mto_icbper'] ?? $totalIcbper),
            'total_impuestos' => (float) ($data['total_impuestos'] ?? $totalImpuestos),
            'valor_venta' => (float) ($data['valor_venta'] ?? $valorVenta),
            'sub_total' => (float) ($data['sub_total'] ?? $subTotal),
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
