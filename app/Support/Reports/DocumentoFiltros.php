<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Models\Boleta;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Filtros multi-selección (tipos, estados, ambientes) compartidos por Reporte de ventas, Exportar ZIP e Historial.
 */
final class DocumentoFiltros
{
    public const CONTEXTO_VENTAS = 'ventas';

    public const CONTEXTO_ZIP = 'zip';

    /** Tipos de comprobante de venta: clave pública => modelo, código SUNAT y nombre. */
    public const TIPOS = [
        'factura' => ['modelo' => Invoice::class, 'codigo' => '01', 'nombre' => 'FACTURA'],
        'boleta' => ['modelo' => Boleta::class, 'codigo' => '03', 'nombre' => 'BOLETA'],
        'nota-credito' => ['modelo' => CreditNote::class, 'codigo' => '07', 'nombre' => 'NOTA DE CRÉDITO'],
        'nota-debito' => ['modelo' => DebitNote::class, 'codigo' => '08', 'nombre' => 'NOTA DE DÉBITO'],
    ];

    /** Nombres del formato antiguo del ZIP (`documentos=facturas,boletas,...`). */
    private const ALIAS_TIPOS = [
        'facturas' => 'factura',
        'boletas' => 'boleta',
        'notas-credito' => 'nota-credito',
        'notas-debito' => 'nota-debito',
    ];

    public const ESTADO_ACEPTADO = 'aceptado';

    public const ESTADO_ACEPTADO_OBS = 'aceptado_obs';

    public const ESTADOS = [
        'aceptado' => 'Aceptado',
        'aceptado_obs' => 'Aceptado con observaciones',
        'pendiente' => 'Pendiente',
        'enviado' => 'Enviado',
        'rechazado' => 'Rechazado',
        'anulado' => 'Anulado',
        'anulacion_en_proceso' => 'Anulación en proceso',
    ];

    /** Estados que muestra el reporte de ventas (pendiente, enviado y anulacion_en_proceso no se incluyen). */
    public const ESTADOS_VENTAS = ['aceptado', 'aceptado_obs', 'rechazado', 'anulado'];

    public const AMBIENTES = ['produccion' => 'Producción', 'prueba' => 'Prueba'];

    public const DEFAULT_TIPOS = ['factura', 'boleta'];

    public const DEFAULT_ESTADOS = ['aceptado', 'aceptado_obs'];

    public const DEFAULT_AMBIENTES = ['produccion'];

    /** Reglas de validación para los tres filtros (aceptan array o lista separada por comas). */
    public static function reglas(): array
    {
        return [
            'tipos' => ['nullable', 'array'],
            'tipos.*' => ['string', Rule::in(array_keys(self::TIPOS))],
            'estados' => ['nullable', 'array'],
            'estados.*' => ['string', Rule::in(array_keys(self::ESTADOS))],
            'ambientes' => ['nullable', 'array'],
            'ambientes.*' => ['string', Rule::in(array_keys(self::AMBIENTES))],
        ];
    }

    /** Convierte `tipos`, `estados` y `ambientes` recibidos como CSV (query string) en arrays. */
    public static function prepararEntrada(array $input): array
    {
        $out = [];
        foreach (['tipos', 'estados', 'ambientes'] as $campo) {
            if (array_key_exists($campo, $input) && is_string($input[$campo])) {
                $out[$campo] = self::lista($input[$campo]);
            }
        }

        return $out;
    }

    /**
     * Resuelve los filtros efectivos aplicando defaults y compatibilidad con parámetros antiguos
     * (`estado`, `estado_sunat`, `documentos`).
     *
     * @return array{tipos: string[], estados: string[], ambientes: string[]}
     */
    public static function normalizar(array $input, string $contexto): array
    {
        $tipos = array_map(fn (string $t) => self::ALIAS_TIPOS[$t] ?? $t, self::lista($input['tipos'] ?? $input['documentos'] ?? null));
        $tipos = array_values(array_intersect($tipos, array_keys(self::TIPOS)));

        $estados = self::lista($input['estados'] ?? null);
        if ($estados === []) {
            $legacy = self::texto($input['estado'] ?? $input['estado_sunat'] ?? '');
            if ($legacy === 'aceptado') {
                $estados = self::DEFAULT_ESTADOS;
            } elseif ($legacy === 'todos') {
                $estados = array_keys(self::ESTADOS);
            } elseif ($legacy !== '') {
                $estados = [$legacy];
            }
        }
        $estados = array_values(array_intersect($estados, array_keys(self::ESTADOS)));

        $ambientes = array_values(array_intersect(self::lista($input['ambientes'] ?? null), array_keys(self::AMBIENTES)));

        // El reporte de ventas muestra todo por defecto; sus totales se separan por ambiente y estado.
        $ventas = $contexto === self::CONTEXTO_VENTAS;

        $estados = $estados ?: ($ventas ? self::ESTADOS_VENTAS : self::DEFAULT_ESTADOS);
        if ($ventas) {
            $estados = array_values(array_intersect($estados, self::ESTADOS_VENTAS));
        }

        return [
            'tipos' => $tipos ?: self::DEFAULT_TIPOS,
            'estados' => $estados,
            'ambientes' => $ambientes ?: ($ventas ? array_keys(self::AMBIENTES) : self::DEFAULT_AMBIENTES),
        ];
    }

    /** Texto en minúsculas y sin tildes, para agrupar estados/ambientes sin depender de cómo se guardaron. */
    public static function texto(mixed $valor): string
    {
        return strtolower(trim(Str::ascii((string) $valor)));
    }

    /** Aplica estados y ambientes a una consulta de comprobantes. */
    public static function aplicar(Builder $query, array $filtros, bool $tieneNotasSunat = true, bool $tieneAmbiente = true): Builder
    {
        self::aplicarEstados($query, $filtros['estados'], $tieneNotasSunat);

        if ($tieneAmbiente) {
            self::whereNormalizadoIn($query, 'ambiente', $filtros['ambientes']);
        }

        return $query;
    }

    public static function aplicarEstados(Builder $query, array $estados, bool $tieneNotasSunat = true): Builder
    {
        if ($estados === []) {
            return $query->whereRaw('1 = 0');
        }

        $directos = array_values(array_diff($estados, [self::ESTADO_ACEPTADO, self::ESTADO_ACEPTADO_OBS]));
        $limpio = in_array(self::ESTADO_ACEPTADO, $estados, true);
        $conObs = in_array(self::ESTADO_ACEPTADO_OBS, $estados, true);
        $sinNotas = self::sqlSinNotas($query);
        $estadoSql = self::sqlNormalizado('sunat_status');

        // "Aceptado" = sin código SUNAT distinto de 0 y sin notas; lo demás aceptado es "con observaciones".
        $codigoLimpio = fn (Builder $q) => $q->whereNull('sunat_code')->orWhereIn('sunat_code', ['', '0']);

        return $query->where(function (Builder $w) use ($directos, $limpio, $conObs, $sinNotas, $codigoLimpio, $tieneNotasSunat, $estadoSql) {
            if ($directos !== []) {
                self::whereNormalizadoIn($w, 'sunat_status', $directos);
            }

            if ($limpio && $conObs) {
                $w->orWhereRaw("{$estadoSql} = ?", [self::ESTADO_ACEPTADO]);
            } elseif ($limpio) {
                $w->orWhere(function (Builder $x) use ($sinNotas, $codigoLimpio, $tieneNotasSunat, $estadoSql) {
                    $x->whereRaw("{$estadoSql} = ?", [self::ESTADO_ACEPTADO])->where($codigoLimpio);
                    if ($tieneNotasSunat) {
                        $x->whereRaw($sinNotas);
                    }
                });
            } elseif ($conObs) {
                $w->orWhere(function (Builder $x) use ($sinNotas, $tieneNotasSunat, $estadoSql) {
                    $x->whereRaw("{$estadoSql} = ?", [self::ESTADO_ACEPTADO])->where(function (Builder $y) use ($sinNotas, $tieneNotasSunat) {
                        $y->whereNotNull('sunat_code')->whereNotIn('sunat_code', ['', '0']);
                        if ($tieneNotasSunat) {
                            $y->orWhereRaw("NOT {$sinNotas}");
                        }
                    });
                });
            }
        });
    }

    /** Etiquetas legibles para encabezados de reportes (PDF/Excel/pantalla). */
    public static function etiquetas(array $filtros): array
    {
        return [
            ['filtro' => 'Tipos', 'valor' => implode(', ', array_map(fn ($t) => ucfirst(str_replace('-', ' ', $t)), $filtros['tipos']))],
            ['filtro' => 'Estados', 'valor' => implode(', ', array_map(fn ($e) => self::ESTADOS[$e], $filtros['estados'])) ?: 'Ninguno'],
            ['filtro' => 'Ambiente', 'valor' => implode(', ', array_map(fn ($a) => self::AMBIENTES[$a], $filtros['ambientes']))],
        ];
    }

    /** @return string[] */
    public static function lista(mixed $valor): array
    {
        if (is_string($valor)) {
            $valor = explode(',', $valor);
        }
        if (! is_array($valor)) {
            return [];
        }

        $items = array_map(fn ($v) => is_scalar($v) ? self::texto($v) : '', $valor);

        return array_values(array_unique(array_filter($items, fn ($v) => $v !== '')));
    }

    /** SQL portable (MySQL/MariaDB/Postgres/SQLite) para "sunat_notes vacío". */
    private static function sqlSinNotas(Builder $query): string
    {
        $tipo = $query->getConnection()->getDriverName() === 'pgsql' ? 'TEXT' : 'CHAR';

        return "(sunat_notes IS NULL OR CAST(sunat_notes AS {$tipo}) IN ('[]', 'null', ''))";
    }

    /** Expresión SQL portable para comparar texto sin mayúsculas ni tildes. */
    private static function sqlNormalizado(string $columna): string
    {
        $sql = "LOWER(TRIM({$columna}))";
        foreach (['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u'] as $origen => $destino) {
            $sql = "REPLACE({$sql}, '{$origen}', '{$destino}')";
        }

        return $sql;
    }

    private static function whereNormalizadoIn(Builder $query, string $columna, array $valores): Builder
    {
        $marcas = implode(', ', array_fill(0, count($valores), '?'));

        return $query->whereRaw(self::sqlNormalizado($columna)." IN ({$marcas})", $valores);
    }
}
