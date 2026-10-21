<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Tenant;
use DateTimeImmutable;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/** Excel del registro de ventas: detalle filtrable y totales dinámicos de filas visibles. */
class RegistroVentasExcel
{
    private const ENCABEZADOS = [
        'N°', 'F. emisión', 'F. vencimiento', 'Tipo', 'Serie-Correlativo', 'Tipo doc. cliente', 'N° doc. cliente', 'Cliente',
        'Op. gravadas', 'Op. exoneradas', 'Op. inafectas', 'Op. gratuitas', 'IGV', 'ISC', 'ICBPER', 'Importe total',
        'Moneda', 'Estado', 'Ambiente',
    ];

    private const COLUMNAS_MONTO = [
        'mto_oper_gravadas' => 'I', 'mto_oper_exoneradas' => 'J', 'mto_oper_inafectas' => 'K', 'mto_oper_gratuitas' => 'L',
        'mto_igv' => 'M', 'mto_isc' => 'N', 'mto_icbper' => 'O', 'mto_imp_venta' => 'P',
    ];

    private const FORMATO_MONTO = '#,##0.00';

    private Writer $writer;

    private int $fila = 0;

    public function generar(array $data, Tenant $tenant): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'rv_');
        $this->writer = new Writer;
        $this->writer->openToFile($ruta);

        $hoja = $this->writer->getCurrentSheet();
        $hoja->setName('Registro de ventas');
        $this->configurarColumnas($hoja);
        $hoja->setSheetView((new SheetView)->withFreezeRow(2));

        $this->emitir(Row::fromValuesWithStyle(self::ENCABEZADOS, $this->estilo('encabezado')));
        foreach ($data['documentos'] as $indice => $documento) {
            $this->emitir($this->filaDocumento($indice + 1, $documento));
        }

        $finDetalle = max(1, $this->fila);
        $hoja->setAutoFilter(new AutoFilter(0, 1, 18, $finDetalle));

        $this->emitir(Row::fromValues([]));
        $this->emitir(Row::fromValues([]));
        foreach ($data['bloques'] as $bloque) {
            $this->escribirBloque($bloque, 2, $finDetalle);
            $this->emitir(Row::fromValues([]));
        }

        $this->writer->addNewSheetAndMakeItCurrent()->setName('Filtros');
        $this->writer->addRow($this->celdas(['Reporte', $data['titulo']]));
        $this->writer->addRow($this->celdas(['Empresa', "{$tenant->razon_social} (RUC {$tenant->ruc})"]));
        $this->writer->addRow($this->celdas(['Periodo', "{$data['periodo']['desde']} al {$data['periodo']['hasta']}"]));
        foreach ($data['filtros'] as $filtro) {
            $this->writer->addRow($this->celdas([$filtro['filtro'], (string) $filtro['valor']]));
        }
        $this->writer->addRow($this->celdas(['Totales', 'Se recalculan al filtrar filas visibles en la hoja Registro de ventas.']));
        $this->writer->addRow($this->celdas(['Generado', now()->format('d/m/Y H:i:s')]));
        $this->writer->close();

        return $ruta;
    }

    private function configurarColumnas($hoja): void
    {
        $hoja->setColumnWidth(6, 1);
        $hoja->setColumnWidth(12, 2, 3);
        $hoja->setColumnWidth(18, 4, 5);
        $hoja->setColumnWidth(10, 6);
        $hoja->setColumnWidth(14, 7);
        $hoja->setColumnWidth(40, 8);
        $hoja->setColumnWidth(14, 9, 10, 11, 12, 13, 14, 15, 16);
        $hoja->setColumnWidth(10, 17);
        $hoja->setColumnWidth(26, 18);
        $hoja->setColumnWidth(12, 19);
    }

    private function filaDocumento(int $numero, array $documento): Row
    {
        $variante = match ($documento['estado_grupo']) {
            'rechazado' => 'rechazado',
            'anulado' => 'anulado',
            default => 'normal',
        };
        $texto = $this->estilo("doc_{$variante}");
        $monto = $this->estilo("doc_{$variante}_monto");
        $fecha = $this->estilo("doc_{$variante}_fecha");

        $celdas = [
            Cell::fromValue($numero, $texto),
            new DateTimeCell(new DateTimeImmutable($documento['fecha_emision']), $fecha),
            empty($documento['fecha_vencimiento'])
                ? new EmptyCell(null, $texto)
                : new DateTimeCell(new DateTimeImmutable($documento['fecha_vencimiento']), $fecha),
            new StringCell($documento['tipo_doc_nombre'], $texto),
            new StringCell($documento['numero_completo'], $texto),
            new StringCell((string) $documento['client_tipo_doc'], $texto),
            new StringCell((string) ($documento['client_num_doc'] ?? ''), $texto),
            new StringCell((string) ($documento['client_razon_social'] ?? ''), $texto),
        ];
        foreach (array_keys(self::COLUMNAS_MONTO) as $campo) {
            $celdas[] = Cell::fromValue((float) $documento[$campo], $monto);
        }
        $celdas[] = new StringCell($documento['tipo_moneda'], $texto);
        $celdas[] = new StringCell($documento['estado_etiqueta'], $texto);
        $celdas[] = new StringCell($documento['ambiente_etiqueta'], $texto);

        return new Row($celdas);
    }

    private function escribirBloque(array $bloque, int $inicio, int $fin): void
    {
        $this->emitir($this->filaTitulo('TOTALES '.mb_strtoupper($bloque['nombre'])));

        $cabecera = array_fill(0, 17, '');
        $cabecera[3] = 'Concepto';
        foreach (array_values(self::ENCABEZADOS) as $indice => $encabezado) {
            if ($indice >= 8 && $indice <= 15) {
                $cabecera[$indice] = $encabezado;
            }
        }
        $cabecera[16] = 'Cant. docs';
        $this->emitir(Row::fromValuesWithStyle($cabecera, $this->estilo('encabezado')));

        foreach ($bloque['lineas'] as $linea) {
            $this->emitir($this->filaTotal($linea, $bloque['nombre'], $inicio, $fin));
        }
    }

    private function filaTotal(array $linea, string $ambiente, int $inicio, int $fin): Row
    {
        $estilos = $this->estilosTotal($linea['estilo']);
        $celdas = array_fill(0, 19, null);
        $celdas[3] = new StringCell($linea['etiqueta'], $estilos['texto']);

        $indiceMonto = 8;
        foreach (self::COLUMNAS_MONTO + ['cantidad' => 'A'] as $campo => $columna) {
            $formula = $this->formulaVisible($campo, $columna, $ambiente, $linea['estados'], $linea['tipos'], $inicio, $fin);

            $esCantidad = $campo === 'cantidad';
            $indice = $esCantidad ? 16 : $indiceMonto++;
            $celdas[$indice] = new FormulaCell($formula, $linea['totales'][$campo], $esCantidad ? $estilos['cantidad'] : $estilos['monto']);
        }

        return new Row($this->rellenar($celdas, $estilos['texto']));
    }

    /** Suma o cuenta solo filas visibles que además cumplen ambiente, estado y tipo. */
    private function formulaVisible(string $campo, string $columna, string $ambiente, array $estados, array $tipos, int $inicio, int $fin): string
    {
        if ($fin < $inicio || $estados === [] || $tipos === []) {
            return '=0';
        }

        $rango = fn (string $letra) => "\${$letra}\${$inicio}:\${$letra}\${$fin}";
        $base = $campo === 'cantidad'
            ? "SUBTOTAL(103,OFFSET(\$A\${$inicio},ROW({$rango('A')})-ROW(\$A\${$inicio}),0,1))"
            : "SUBTOTAL(109,OFFSET(\${$columna}\${$inicio},ROW({$rango($columna)})-ROW(\${$columna}\${$inicio}),0,1))";
        $criterioEstados = $this->criterioLista($rango('R'), $estados);
        $criterioTipos = $this->criterioLista($rango('D'), $tipos);

        return "=SUMPRODUCT({$base},--({$rango('S')}=\"{$ambiente}\"),--({$criterioEstados}),--({$criterioTipos}))";
    }

    private function criterioLista(string $rango, array $valores): string
    {
        $comparaciones = array_map(fn (string $valor) => "({$rango}=\"{$valor}\")", $valores);

        return count($comparaciones) === 1 ? $comparaciones[0] : '('.implode('+', $comparaciones).'>0)';
    }

    private function filaTitulo(string $titulo): Row
    {
        $estilo = $this->estilo('titulo');
        $celdas = array_fill(0, 19, null);
        $celdas[3] = new StringCell($titulo, $estilo);

        return new Row($this->rellenar($celdas, $estilo));
    }

    private function estilosTotal(string $variante): array
    {
        $base = match ($variante) {
            'total' => $this->estilo('total'),
            'rechazado' => $this->estilo('doc_rechazado')->withFontBold(true),
            'anulado' => $this->estilo('doc_anulado')->withFontBold(true),
            default => new Style,
        };

        return [
            'texto' => $base,
            'monto' => $base->withFormat(self::FORMATO_MONTO),
            'cantidad' => $base->withFormat('#,##0'),
        ];
    }

    private function estilo(string $nombre): Style
    {
        return match ($nombre) {
            'encabezado' => new Style(fontBold: true, fontColor: Color::WHITE, backgroundColor: '374151'),
            'titulo' => new Style(fontBold: true, backgroundColor: 'E5E7EB'),
            'total' => new Style(fontBold: true, backgroundColor: 'F3F4F6'),
            'doc_rechazado' => new Style(backgroundColor: 'FEE2E2'),
            'doc_rechazado_monto' => new Style(backgroundColor: 'FEE2E2', format: self::FORMATO_MONTO),
            'doc_rechazado_fecha' => new Style(backgroundColor: 'FEE2E2', format: 'dd/mm/yyyy'),
            'doc_anulado' => new Style(fontColor: '6B7280', backgroundColor: 'F3F4F6'),
            'doc_anulado_monto' => new Style(fontColor: '6B7280', backgroundColor: 'F3F4F6', format: self::FORMATO_MONTO),
            'doc_anulado_fecha' => new Style(fontColor: '6B7280', backgroundColor: 'F3F4F6', format: 'dd/mm/yyyy'),
            'doc_normal_monto' => new Style(format: self::FORMATO_MONTO),
            'doc_normal_fecha' => new Style(format: 'dd/mm/yyyy'),
            default => new Style,
        };
    }

    private function rellenar(array $celdas, Style $estilo): array
    {
        foreach (array_keys($celdas) as $indice) {
            $celdas[$indice] ??= new EmptyCell(null, $estilo);
        }
        ksort($celdas);

        return array_values($celdas);
    }

    private function emitir(Row $fila): void
    {
        $this->writer->addRow($fila);
        $this->fila++;
    }

    private function celdas(array $valores): Row
    {
        return new Row(array_map(
            fn (mixed $valor) => is_string($valor) && $valor !== '' ? new StringCell($valor) : Cell::fromValue($valor),
            $valores
        ));
    }
}
