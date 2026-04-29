<?php

declare(strict_types=1);

/**
 * Catálogos SUNAT — Formato 1.3.4 (Anexo V, Anexo VIII)
 *
 * Fuente: AnexosIyII_Formato1.3.4.xlsx
 * Cada catálogo es un array code => descripción.
 * Para catálogos con metadata extra (tasas, niveles) se usa code => [...].
 */

return [

    // ─── Catálogo 01: Tipo de documento ────────────────────────────
    '01' => [
        '01' => 'Factura',
        '03' => 'Boleta de venta',
        '07' => 'Nota de crédito',
        '08' => 'Nota de débito',
        '09' => 'Guía de remisión remitente',
        '12' => 'Ticket de máquina registradora',
        '20' => 'Comprobante de retención',
        '31' => 'Guía de remisión transportista',
        '40' => 'Comprobante de Percepción',
    ],

    // ─── Catálogo 05: Tipos de tributos ────────────────────────────
    '05' => [
        '1000' => ['name' => 'IGV', 'code' => 'VAT', 'international' => 'IGV'],
        '1016' => ['name' => 'IVAP', 'code' => 'VAT', 'international' => 'IVAP'],
        '2000' => ['name' => 'ISC', 'code' => 'EXC', 'international' => 'ISC'],
        '7152' => ['name' => 'ICBPER', 'code' => 'OTH', 'international' => 'ICBPER'],
        '9995' => ['name' => 'EXP', 'code' => 'FRE', 'international' => 'EXP'],
        '9996' => ['name' => 'GRA', 'code' => 'FRE', 'international' => 'GRA'],
        '9997' => ['name' => 'EXO', 'code' => 'VAT', 'international' => 'EXO'],
        '9998' => ['name' => 'INA', 'code' => 'FRE', 'international' => 'INA'],
        '9999' => ['name' => 'OTROS', 'code' => 'OTH', 'international' => 'OTROS'],
    ],

    // ─── Catálogo 06: Tipo de documento de identidad ───────────────
    '06' => [
        '0' => 'DOC.TRIB.NO.DOM.SIN.RUC',
        '1' => 'DNI',
        '4' => 'Carnet de extranjería',
        '6' => 'RUC',
        '7' => 'Pasaporte',
        'A' => 'Cédula Diplomática de identidad',
        'B' => 'DOC.IDENT.PAIS.RESIDENCIA-NO.D',
        'C' => 'TIN - Tax Identification Number',
        'D' => 'IN - Identification Number',
        'E' => 'TAM - Tarjeta Andina de Migración',
    ],

    // ─── Catálogo 07: Tipo de afectación del IGV ───────────────────
    '07' => [
        '10' => ['desc' => 'Gravado - Operación Onerosa', 'tributo' => '1000'],
        '11' => ['desc' => 'Gravado - Retiro por premio', 'tributo' => '9996'],
        '12' => ['desc' => 'Gravado - Retiro por donación', 'tributo' => '9996'],
        '13' => ['desc' => 'Gravado - Retiro', 'tributo' => '9996'],
        '14' => ['desc' => 'Gravado - Retiro por publicidad', 'tributo' => '9996'],
        '15' => ['desc' => 'Gravado - Bonificaciones', 'tributo' => '9996'],
        '16' => ['desc' => 'Gravado - Retiro por entrega a trabajadores', 'tributo' => '9996'],
        '17' => ['desc' => 'Gravado - IVAP', 'tributo' => '1016'],
        '20' => ['desc' => 'Exonerado - Operación Onerosa', 'tributo' => '9997'],
        '21' => ['desc' => 'Exonerado - Transferencia gratuita', 'tributo' => '9996'],
        '30' => ['desc' => 'Inafecto - Operación Onerosa', 'tributo' => '9998'],
        '31' => ['desc' => 'Inafecto - Retiro por Bonificación', 'tributo' => '9996'],
        '32' => ['desc' => 'Inafecto - Retiro', 'tributo' => '9996'],
        '33' => ['desc' => 'Inafecto - Retiro por Muestras Médicas', 'tributo' => '9996'],
        '34' => ['desc' => 'Inafecto - Retiro por Convenio Colectivo', 'tributo' => '9996'],
        '35' => ['desc' => 'Inafecto - Retiro por premio', 'tributo' => '9996'],
        '36' => ['desc' => 'Inafecto - Retiro por publicidad', 'tributo' => '9996'],
        '37' => ['desc' => 'Inafecto - Transferencia gratuita', 'tributo' => '9996'],
        '40' => ['desc' => 'Exportación de Bienes o Servicios', 'tributo' => '9995'],
    ],

    // ─── Catálogo 08: Sistema de cálculo del ISC ───────────────────
    '08' => [
        '01' => 'Sistema al valor',
        '02' => 'Aplicación del Monto Fijo',
        '03' => 'Sistema de Precios de Venta al Público',
    ],

    // ─── Catálogo 09: Tipo de nota de crédito ──────────────────────
    '09' => [
        '01' => 'Anulación de la operación',
        '02' => 'Anulación por error en el RUC',
        '03' => 'Corrección por error en la descripción',
        '04' => 'Descuento global',
        '05' => 'Descuento por ítem',
        '06' => 'Devolución total/parcial',
        '07' => 'Bonificación/Descuento',
        '08' => 'Disminución en el valor',
        '09' => 'Otros',
    ],

    // ─── Catálogo 10: Tipo de nota de débito ───────────────────────
    '10' => [
        '01' => 'Intereses por mora',
        '02' => 'Aumento en el valor',
        '03' => 'Penalidades / otros conceptos',
        '04' => 'Ajustes de valor de exportación',
        '05' => 'Ajustes por corrección de la moneda',
        '06' => 'Ajustes por corrección de la cantidad',
        '07' => 'Ajustes por descuentos no aplicados',
        '08' => 'Ajustes por cargos adicionales',
        '09' => 'Otros',
        '11' => 'Ajustes de operaciones de exportación',
        '12' => 'Ajustes afectos al IVAP',
    ],

    // ─── Catálogo 11: Tipo valor de venta (Resumen Diario) ────────
    '11' => [
        '01' => 'Gravado',
        '02' => 'Exonerado',
        '03' => 'Inafecto',
        '04' => 'Exportación',
        '05' => 'Gratuitas',
    ],

    // ─── Catálogo 12: Documentos relacionados tributarios ──────────
    '12' => [
        '01' => 'Factura - emitida para corregir error en el RUC',
        '02' => 'Factura - emitida por anticipos',
        '03' => 'Boleta de Venta - emitida por anticipos',
        '04' => 'Ticket de Salida - ENAPU',
        '05' => 'Código SCOP',
        '06' => 'Factura electrónica remitente',
        '07' => 'Guía de remisión remitente',
        '08' => 'Declaración de salida del depósito franco',
        '09' => 'Declaración simplificada de importación',
        '10' => 'Liquidación de compra - emitida por anticipos',
        '99' => 'Otros',
    ],

    // ─── Catálogo 16: Tipo de precio de venta unitario ─────────────
    '16' => [
        '01' => 'Precio unitario (incluye el IGV)',
        '02' => 'Valor referencial unitario en operaciones no onerosas',
        '03' => 'Tarifas reguladas',
    ],

    // ─── Catálogo 19: Estado del ítem (Resumen Diario) ─────────────
    '19' => [
        '1' => 'Adicionar',
        '2' => 'Modificar',
        '3' => 'Anulado',
    ],

    // ─── Catálogo 22: Régimen de percepciones ──────────────────────
    '22' => [
        '01' => ['desc' => 'Percepción Venta Interna', 'tasa' => 2.00],
        '02' => ['desc' => 'Percepción adquisición de combustible', 'tasa' => 1.00],
        '03' => ['desc' => 'Percepción agente con tasa especial', 'tasa' => 0.50],
    ],

    // ─── Catálogo 23: Régimen de retenciones ───────────────────────
    '23' => [
        '01' => ['desc' => 'Tasa 3%', 'tasa' => 3.00],
        '02' => ['desc' => 'Tasa 6%', 'tasa' => 6.00],
    ],

    // ─── Catálogo 51: Tipo de operación ────────────────────────────
    '51' => [
        '0101' => 'Venta interna',
        '0112' => 'Venta Interna - Sustenta Gastos Deducibles Persona Natural',
        '0113' => 'Venta Interna - NRUS',
        '0200' => 'Exportación de Bienes',
        '0201' => 'Exportación de Servicios - prestación íntegra en el país',
        '0202' => 'Exportación de Servicios - hospedaje No Domiciliado',
        '0203' => 'Exportación de Servicios - Transporte de navieras',
        '0204' => 'Exportación de Servicios - naves y aeronaves bandera extranjera',
        '0205' => 'Exportación de Servicios - Paquete Turístico',
        '0206' => 'Exportación de Servicios - complementarios transporte carga',
        '0207' => 'Exportación de Servicios - energía eléctrica ZED',
        '0208' => 'Exportación de Servicios - prestación parcial en el extranjero',
        '0301' => 'Operaciones con Carta de porte aéreo',
        '0302' => 'Operaciones de Transporte ferroviario de pasajeros',
        '0303' => 'Operaciones de Pago de regalía petrolera',
        '0401' => 'Ventas no domiciliados que no califican como exportación',
        '1001' => 'Operación Sujeta a Detracción',
        '1002' => 'Operación Sujeta a Detracción - Recursos Hidrobiológicos',
        '1003' => 'Operación Sujeta a Detracción - Servicios de Transporte Pasajeros',
        '1004' => 'Operación Sujeta a Detracción - Servicios de Transporte Carga',
        '2001' => 'Operación Sujeta a Percepción',
    ],

    // ─── Catálogo 52: Leyendas ─────────────────────────────────────
    '52' => [
        '1000' => 'Monto en Letras',
        '1002' => 'TRANSFERENCIA GRATUITA DE UN BIEN Y/O SERVICIO PRESTADO GRATUITAMENTE',
        '2000' => 'COMPROBANTE DE PERCEPCIÓN',
        '2001' => 'BIENES TRANSFERIDOS EN LA AMAZONÍA REGIÓN SELVA PARA SER CONSUMIDOS EN LA MISMA',
        '2002' => 'SERVICIOS PRESTADOS EN LA AMAZONÍA REGIÓN SELVA PARA SER CONSUMIDOS EN LA MISMA',
        '2003' => 'CONTRATOS DE CONSTRUCCIÓN EJECUTADOS EN LA AMAZONÍA REGIÓN SELVA',
        '2004' => 'Agencia de Viaje - Paquete turístico',
        '2005' => 'Venta realizada por emisor itinerante',
        '2006' => 'Operación sujeta a detracción',
        '2007' => 'Operación sujeta al IVAP',
        '2008' => 'VENTA EXONERADA DEL IGV-ISC-IPM. PROHIBIDA LA VENTA FUERA DE LA ZONA COMERCIAL DE TACNA',
        '2009' => 'PRIMERA VENTA DE MERCANCÍA IDENTIFICABLE ENTRE USUARIOS DE LA ZONA COMERCIAL',
        '2010' => 'Restitución Simplificado de Derechos Arancelarios',
        '2011' => 'EXPORTACIÓN DE SERVICIOS - DECRETO LEGISLATIVO N° 919',
    ],

    // ─── Catálogo 53: Cargos o descuentos ──────────────────────────
    '53' => [
        '00' => ['desc' => 'Descuentos que afectan la base imponible del IGV/IVAP', 'nivel' => 'Item'],
        '01' => ['desc' => 'Descuentos que no afectan la base imponible del IGV/IVAP', 'nivel' => 'Item'],
        '02' => ['desc' => 'Descuentos globales que afectan la base imponible del IGV/IVAP', 'nivel' => 'Global'],
        '03' => ['desc' => 'Descuentos globales que no afectan la base imponible del IGV/IVAP', 'nivel' => 'Global'],
        '04' => ['desc' => 'Descuentos globales por anticipos gravados', 'nivel' => 'Global'],
        '05' => ['desc' => 'Descuentos globales por anticipos exonerados', 'nivel' => 'Global'],
        '06' => ['desc' => 'Descuentos globales por anticipos inafectos', 'nivel' => 'Global'],
        '45' => ['desc' => 'FISE', 'nivel' => 'Global'],
        '46' => ['desc' => 'Recargo al consumo y/o propinas', 'nivel' => 'Global'],
        '47' => ['desc' => 'Cargos que afectan la base imponible del IGV/IVAP', 'nivel' => 'Item'],
        '48' => ['desc' => 'Cargos que no afectan la base imponible del IGV/IVAP', 'nivel' => 'Item'],
        '49' => ['desc' => 'Cargos globales que afectan la base imponible del IGV/IVAP', 'nivel' => 'Global'],
        '50' => ['desc' => 'Cargos globales que no afectan la base imponible del IGV/IVAP', 'nivel' => 'Global'],
        '51' => ['desc' => 'Percepción venta interna', 'nivel' => 'Global'],
        '52' => ['desc' => 'Percepción adquisición de combustible', 'nivel' => 'Global'],
        '53' => ['desc' => 'Percepción agente con tasa especial', 'nivel' => 'Global'],
    ],

    // ─── Catálogo 54: Bienes y servicios sujetos a detracciones ────
    '54' => [
        '001' => ['desc' => 'Azúcar y melaza de caña', 'tasa' => 10],
        '003' => ['desc' => 'Alcohol etílico', 'tasa' => 10],
        '004' => ['desc' => 'Recursos hidrobiológicos', 'tasa' => 4],
        '005' => ['desc' => 'Maíz amarillo duro', 'tasa' => 4],
        '007' => ['desc' => 'Caña de azúcar', 'tasa' => 10],
        '008' => ['desc' => 'Madera', 'tasa' => 4],
        '009' => ['desc' => 'Arena y piedra', 'tasa' => 10],
        '010' => ['desc' => 'Residuos, subproductos, desechos', 'tasa' => 15],
        '012' => ['desc' => 'Intermediación laboral y tercerización', 'tasa' => 10],
        '014' => ['desc' => 'Carnes y despojos comestibles', 'tasa' => 4],
        '017' => ['desc' => 'Harina, polvo y pellets de pescado', 'tasa' => 4],
        '019' => ['desc' => 'Arrendamiento de bienes muebles', 'tasa' => 10],
        '020' => ['desc' => 'Mantenimiento y reparación de bienes muebles', 'tasa' => 10],
        '021' => ['desc' => 'Movimiento de carga', 'tasa' => 10],
        '022' => ['desc' => 'Otros servicios empresariales', 'tasa' => 10],
        '023' => ['desc' => 'Leche', 'tasa' => 4],
        '024' => ['desc' => 'Comisión mercantil', 'tasa' => 10],
        '025' => ['desc' => 'Fabricación de bienes por encargo', 'tasa' => 10],
        '026' => ['desc' => 'Servicio de transporte de personas', 'tasa' => 10],
        '027' => ['desc' => 'Servicio de transporte de carga', 'tasa' => 4],
        '030' => ['desc' => 'Contratos de construcción', 'tasa' => 4],
        '031' => ['desc' => 'Oro gravado con el IGV', 'tasa' => 10],
        '034' => ['desc' => 'Minerales metálicos no auríferos', 'tasa' => 10],
        '035' => ['desc' => 'Bienes exonerados del IGV', 'tasa' => 1.5],
        '036' => ['desc' => 'Oro y demás minerales metálicos exonerados del IGV', 'tasa' => 1.5],
        '037' => ['desc' => 'Demás servicios gravados con el IGV', 'tasa' => 12],
        '039' => ['desc' => 'Minerales no metálicos', 'tasa' => 10],
        '040' => ['desc' => 'Bien inmueble gravado con IGV', 'tasa' => 4],
    ],

    // ─── Catálogo 59: Medios de pago ───────────────────────────────
    '59' => [
        '001' => 'Depósito en cuenta',
        '002' => 'Giro',
        '003' => 'Transferencia de fondos',
        '004' => 'Orden de pago',
        '005' => 'Tarjeta de débito',
        '006' => 'Tarjeta de crédito emitida en el país',
        '007' => 'Cheques con cláusula NO NEGOCIABLE',
        '008' => 'Efectivo, sin obligación de medio de pago',
        '009' => 'Efectivo, en los demás casos',
        '010' => 'Medios de pago usados en comercio exterior',
        '011' => 'Documentos emitidos por EDPYMES y cooperativas',
        '012' => 'Tarjeta de crédito emitida por empresa no financiera',
        '013' => 'Tarjetas de crédito emitidas en el exterior',
        '101' => 'Transferencias - Comercio exterior',
        '102' => 'Cheques bancarios - Comercio exterior',
        '103' => 'Orden de pago simple - Comercio exterior',
        '104' => 'Orden de pago documentario - Comercio exterior',
        '105' => 'Remesa simple - Comercio exterior',
        '106' => 'Remesa documentaria - Comercio exterior',
        '107' => 'Carta de crédito simple - Comercio exterior',
        '108' => 'Carta de crédito documentario - Comercio exterior',
        '999' => 'Otros medios de pago',
    ],

    // ─── ICBPER: Factor por año ────────────────────────────────────
    'icbper' => [
        2019 => 0.10,
        2020 => 0.20,
        2021 => 0.30,
        2022 => 0.40,
        2023 => 0.50,
        2024 => 0.50,
        2025 => 0.50,
        2026 => 0.50,
    ],

];
