<?php

namespace App\Support\Rbac;

/**
 * Catálogo de permisos del panel multiusuario.
 *
 * Cada permiso es una cadena. Los de comprobante siguen el patrón "{tipo}.{acción}"
 * (ej. "factura.emitir"); los transversales son claves fijas (ej. "cliente.gestionar").
 *
 * El dueño (owner) tiene todos implícitamente; el cajero solo los que el dueño le marque.
 */
class Ability
{
    // ── Acciones sobre un comprobante ────────────────────────────────
    public const EMITIR = 'emitir';

    public const EDITAR = 'editar';

    public const ANULAR = 'anular';

    public const REENVIAR = 'reenviar';

    public const DESCARGAR = 'descargar';

    /** @var list<string> */
    public const ACCIONES_DOCUMENTO = [
        self::EMITIR,
        self::EDITAR,
        self::ANULAR,
        self::REENVIAR,
        self::DESCARGAR,
    ];

    /** Acciones con su etiqueta legible. @var array<string,string> */
    public const ACCIONES_LABELS = [
        self::EMITIR => 'Emitir',
        self::EDITAR => 'Editar',
        self::ANULAR => 'Anular',
        self::REENVIAR => 'Reenviar',
        self::DESCARGAR => 'Descargar',
    ];

    // ── Tipos de comprobante (clave base) ────────────────────────────
    public const TIPO_FACTURA = 'factura';

    public const TIPO_BOLETA = 'boleta';

    public const TIPO_NOTA_CREDITO = 'nota_credito';

    public const TIPO_NOTA_DEBITO = 'nota_debito';

    public const TIPO_GUIA_REMITENTE = 'guia_remitente';

    public const TIPO_GUIA_TRANSPORTISTA = 'guia_transportista';

    public const TIPO_RESUMEN = 'resumen';

    public const TIPO_ANULACION = 'anulacion';

    public const TIPO_RETENCION = 'retencion';

    public const TIPO_PERCEPCION = 'percepcion';

    public const TIPO_REVERSION = 'reversion';

    public const TIPO_COTIZACION = 'cotizacion';

    public const TIPO_NOTA_VENTA = 'nota_venta';

    /** Tipos con su etiqueta legible. @var array<string,string> */
    public const TIPOS = [
        self::TIPO_FACTURA => 'Factura',
        self::TIPO_BOLETA => 'Boleta',
        self::TIPO_NOTA_CREDITO => 'Nota de crédito',
        self::TIPO_NOTA_DEBITO => 'Nota de débito',
        self::TIPO_GUIA_REMITENTE => 'Guía de remisión remitente',
        self::TIPO_GUIA_TRANSPORTISTA => 'Guía de remisión transportista',
        self::TIPO_RESUMEN => 'Resumen diario',
        self::TIPO_ANULACION => 'Comunicación de baja / anulación',
        self::TIPO_RETENCION => 'Retención',
        self::TIPO_PERCEPCION => 'Percepción',
        self::TIPO_REVERSION => 'Reversión',
        self::TIPO_COTIZACION => 'Cotización',
        self::TIPO_NOTA_VENTA => 'Nota de venta',
    ];

    // ── Módulos transversales (por empresa) ──────────────────────────
    public const CLIENTE_GESTIONAR = 'cliente.gestionar';

    public const SERIE_GESTIONAR = 'serie.gestionar';

    public const SUCURSAL_GESTIONAR = 'sucursal.gestionar';

    public const REPORTE_VER = 'reporte.ver';

    public const EXPORTAR = 'exportar';

    public const CONSULTA_CPE = 'consulta.cpe';

    public const CONFIG_EDITAR = 'config.editar';

    public const APIKEY_VER = 'apikey.ver';

    public const EQUIPO_GESTIONAR = 'equipo.gestionar';

    public const SIRE_GESTIONAR = 'sire.gestionar';

    /** Módulos con su etiqueta legible. @var array<string,string> */
    public const MODULOS = [
        self::CLIENTE_GESTIONAR => 'Gestionar clientes',
        self::SERIE_GESTIONAR => 'Series y correlativos',
        self::SUCURSAL_GESTIONAR => 'Sucursales',
        self::REPORTE_VER => 'Reportes',
        self::EXPORTAR => 'Exportar (ZIP)',
        self::CONSULTA_CPE => 'Consultar CPE/CDR',
        self::CONFIG_EDITAR => 'Editar datos de la empresa',
        self::APIKEY_VER => 'Ver/regenerar API key',
        self::EQUIPO_GESTIONAR => 'Gestionar usuarios del equipo',
        self::SIRE_GESTIONAR => 'Módulo SIRE',
    ];

    /** @return list<string> Claves base de todos los tipos de comprobante. */
    public static function tipos(): array
    {
        return array_keys(self::TIPOS);
    }

    /** @return list<string> Todas las abilities posibles (para validar y para la UI). */
    public static function todas(): array
    {
        $abilities = [];

        foreach (self::tipos() as $tipo) {
            foreach (self::ACCIONES_DOCUMENTO as $accion) {
                $abilities[] = "{$tipo}.{$accion}";
            }
        }

        return array_merge($abilities, array_keys(self::MODULOS));
    }

    /**
     * Permisos por defecto para un cajero nuevo: emitir/descargar/reenviar de los
     * comprobantes de uso común + gestionar clientes. Sin anular ni configuración.
     *
     * @return list<string>
     */
    public static function presetCajero(): array
    {
        $abilities = [];

        $comunes = [
            self::TIPO_FACTURA,
            self::TIPO_BOLETA,
            self::TIPO_NOTA_CREDITO,
            self::TIPO_NOTA_DEBITO,
            self::TIPO_COTIZACION,
            self::TIPO_NOTA_VENTA,
        ];

        foreach ($comunes as $tipo) {
            $abilities[] = "{$tipo}.".self::EMITIR;
            $abilities[] = "{$tipo}.".self::DESCARGAR;
            $abilities[] = "{$tipo}.".self::REENVIAR;
        }

        $abilities[] = self::CLIENTE_GESTIONAR;

        return $abilities;
    }

    /**
     * Permisos que el dueño NO puede delegar a un cajero (quedan reservados para el dueño).
     *
     * @var list<string>
     */
    public const NO_ASIGNABLES_A_CAJERO = [
        self::EQUIPO_GESTIONAR,
        self::CONFIG_EDITAR,
        self::APIKEY_VER,
    ];

    /**
     * Permisos que un dueño SÍ puede marcarle a un cajero.
     *
     * @return list<string>
     */
    public static function asignablesACajero(): array
    {
        return array_values(array_diff(self::todas(), self::NO_ASIGNABLES_A_CAJERO));
    }

    /**
     * Permisos base para el rol "simple" (cliente final restringido).
     * Solo emite/descarga/reenvía comprobantes: no ve Trámites (anulación,
     * resumen, retención, percepción, reversión) ni puede anular nada.
     *
     * @return list<string>
     */
    public static function presetSimple(): array
    {
        return [
            self::CONFIG_EDITAR,
        ];
    }
}
