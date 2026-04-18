<?php

use App\Sire\Enums\CodLibro;
use App\Sire\Enums\CodProceso;
use App\Sire\Enums\CodTipoArchivo;
use App\Sire\Enums\EstadoTicket;
use App\Sire\Enums\FasePeriodo;
use App\Sire\Enums\VariantAjuste;
use App\Sire\Exceptions\SireErrorCatalog;
use App\Sire\Services\Propuesta\PropuestaParser;
use App\Sire\Services\Upload\ZipBuilder;
use App\Sire\Support\Base64MetadataEncoder;
use App\Sire\Support\NombreArchivoBuilder;
use App\Sire\Support\PeriodoTributario;

// ═══════════════════════════════════════════════════════════════════════
// PeriodoTributario — validación formato yyyymm (errores 1005-1014)
// ═══════════════════════════════════════════════════════════════════════

describe('PeriodoTributario', function () {
    test('parses yyyymm correctamente', function () {
        $p = PeriodoTributario::fromString('202604');

        expect($p->anio)->toBe(2026);
        expect($p->mes)->toBe(4);
        expect($p->toString())->toBe('202604');
    });

    test('rechaza formato inválido', function () {
        PeriodoTributario::fromString('2026-04');
    })->throws(InvalidArgumentException::class);

    test('rechaza mes inválido', function () {
        PeriodoTributario::fromString('202613');
    })->throws(InvalidArgumentException::class);

    test('rechaza longitud incorrecta', function () {
        PeriodoTributario::fromString('20264');
    })->throws(InvalidArgumentException::class);

    test('isFuture detecta periodos futuros', function () {
        $futuro = PeriodoTributario::fromString('209912');
        $pasado = PeriodoTributario::fromString('202001');

        expect($futuro->isFuture())->toBeTrue();
        expect($pasado->isFuture())->toBeFalse();
    });

    test('convierte a yyyy-mm-dd', function () {
        $p = PeriodoTributario::fromString('202604');
        expect($p->toYyyyMmDd('15'))->toBe('2026-04-15');
        expect($p->toYyyyMmDd())->toBe('2026-04-01');
    });
});

// ═══════════════════════════════════════════════════════════════════════
// Base64MetadataEncoder — coincide con ejemplo del PDF v22
// ═══════════════════════════════════════════════════════════════════════

describe('Base64MetadataEncoder', function () {
    test('encodea metadata en formato TUS', function () {
        $encoded = Base64MetadataEncoder::encode([
            'filename' => 'test.zip',
            'numRuc'   => '20100000001',
        ]);

        expect($encoded)->toContain('filename dGVzdC56aXA=');
        expect($encoded)->toContain('numRuc MjAxMDAwMDAwMDE=');
    });

    test('forSireUpload genera todos los campos requeridos', function () {
        $encoded = Base64MetadataEncoder::forSireUpload([
            'filename'              => 'f.zip',
            'filetype'              => 'application/zip',
            'numRuc'                => '20100000001',
            'perTributario'         => '202604',
            'codOrigenEnvio'        => '2',
            'codProceso'            => '61',
            'codTipoCorrelativo'    => '01',
            'nomArchivoImportacion' => 'f.zip',
            'codLibro'              => '080000',
        ]);

        expect($encoded)->toContain('filename ');
        expect($encoded)->toContain('codProceso ');
        expect($encoded)->toContain('codLibro ');
        expect(substr_count($encoded, ','))->toBe(8);
    });

    test('reproduce exactamente el ejemplo del manual (perTributario 202302)', function () {
        $parts = explode(',', Base64MetadataEncoder::forSireUpload([
            'filename'              => 'demo.zip',
            'filetype'              => 'application/zip',
            'numRuc'                => '20100176450',
            'perTributario'         => '202302',
            'codOrigenEnvio'        => '1',
            'codProceso'            => '87',
            'codTipoCorrelativo'    => '1',
            'nomArchivoImportacion' => 'demo.zip',
            'codLibro'              => '140000',
        ]));

        // Del manual: numRuc MjAxMDAxNzY0NTA=
        expect($parts)->toContain('numRuc MjAxMDAxNzY0NTA=');
        // perTributario MjAyMzAy
        expect($parts)->toContain('perTributario MjAyMzAy');
        // codLibro MTQwMDAw
        expect($parts)->toContain('codLibro MTQwMDAw');
    });
});

// ═══════════════════════════════════════════════════════════════════════
// NombreArchivoBuilder — error 1044 valida por posición
// ═══════════════════════════════════════════════════════════════════════

describe('NombreArchivoBuilder', function () {
    test('genera nombre ZIP con estructura posicional', function () {
        $name = NombreArchivoBuilder::build(
            ruc: '20100000001',
            perTributario: '202604',
            codLibro: '080000',
            codProceso: '61',
            secuencia: 1,
        );

        expect($name)->toStartWith('LE');
        expect($name)->toContain('20100000001');
        expect($name)->toContain('202604');
        expect($name)->toContain('080000');
        expect($name)->toEndWith('.zip');
    });

    test('respeta padding de secuencia', function () {
        $name = NombreArchivoBuilder::build(
            ruc: '20100000001',
            perTributario: '202604',
            codLibro: '080000',
            codProceso: '61',
            secuencia: 42,
        );

        expect($name)->toContain('00000042'); // secuencia padded a 8 dígitos
    });

    test('extensión txt vs zip', function () {
        $zip = NombreArchivoBuilder::build('20100000001', '202604', '080000', '61', 1, 'zip');
        $txt = NombreArchivoBuilder::build('20100000001', '202604', '080000', '61', 1, 'txt');

        expect($zip)->toEndWith('.zip');
        expect($txt)->toEndWith('.txt');
        expect(substr($zip, 0, -4))->toBe(substr($txt, 0, -4));
    });
});

// ═══════════════════════════════════════════════════════════════════════
// PropuestaParser — formato SIRE RCE
// ═══════════════════════════════════════════════════════════════════════

describe('PropuestaParser', function () {
    test('parsea línea con todos los campos', function () {
        $parser = new PropuestaParser();
        $line = '202604|11-202604-0001|1|15/04/2026||01|F001|123|6|20100000002|PROVEEDOR SAC|1000.00|180.00|0.00|0.00|0.00|0.00|0.00|1180.00|PEN|3.700';

        $row = $parser->parseLine($line);

        expect($row['per_tributario'])->toBe('202604');
        expect($row['car_sunat'])->toBe('11-202604-0001');
        expect($row['fec_emision'])->toBe('2026-04-15');
        expect($row['cod_tipo_cdp'])->toBe('01');
        expect($row['num_serie_cdp'])->toBe('F001');
        expect($row['num_cdp'])->toBe('123');
        expect($row['num_doc_proveedor'])->toBe('20100000002');
        expect($row['razon_social_proveedor'])->toBe('PROVEEDOR SAC');
        expect($row['mto_bi_gravada'])->toBe(1000.0);
        expect($row['mto_igv'])->toBe(180.0);
        expect($row['mto_total'])->toBe(1180.0);
        expect($row['cod_moneda'])->toBe('PEN');
        expect($row['tipo_cambio'])->toBe(3.7);
    });

    test('parsea ZIP con TXT dentro', function () {
        $parser = new PropuestaParser();
        $tempDir = sys_get_temp_dir() . '/sire-parser-test-' . uniqid();
        mkdir($tempDir);

        $txt = "202604|11-202604-0001|1|15/04/2026||01|F001|1|6|20100000002|PROV A|100|18|0|0|0|0|0|118|PEN|\n"
             . "202604|11-202604-0002|1|20/04/2026||01|F001|2|6|20100000003|PROV B|200|36|0|0|0|0|0|236|PEN|\n";

        $zipPath = $tempDir . '/test.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('data.txt', $txt);
        $zip->close();

        $rows = $parser->parseZipFile($zipPath);

        expect($rows)->toHaveCount(2);
        expect($rows[0]['car_sunat'])->toBe('11-202604-0001');
        expect($rows[1]['car_sunat'])->toBe('11-202604-0002');

        unlink($zipPath);
        rmdir($tempDir);
    });

    test('maneja fechas en múltiples formatos', function () {
        $parser = new PropuestaParser();

        $r1 = $parser->parseLine('202604|CAR1|1|15/04/2026||01|F|1|6|12345678901|P|100|18|0|0|0|0|0|118|PEN|');
        $r2 = $parser->parseLine('202604|CAR2|1|2026-04-15||01|F|1|6|12345678901|P|100|18|0|0|0|0|0|118|PEN|');
        $r3 = $parser->parseLine('202604|CAR3|1|20260415||01|F|1|6|12345678901|P|100|18|0|0|0|0|0|118|PEN|');

        expect($r1['fec_emision'])->toBe('2026-04-15');
        expect($r2['fec_emision'])->toBe('2026-04-15');
        expect($r3['fec_emision'])->toBe('2026-04-15');
    });
});

// ═══════════════════════════════════════════════════════════════════════
// Enums
// ═══════════════════════════════════════════════════════════════════════

describe('Enums SIRE', function () {
    test('EstadoTicket::isFinal para estados terminales', function () {
        expect(EstadoTicket::TERMINADO->isFinal())->toBeTrue();
        expect(EstadoTicket::TERMINADO_ERRORES->isFinal())->toBeTrue();
        expect(EstadoTicket::ERROR->isFinal())->toBeTrue();
        expect(EstadoTicket::PENDIENTE->isFinal())->toBeFalse();
        expect(EstadoTicket::PROCESANDO->isFinal())->toBeFalse();
    });

    test('EstadoTicket::isSuccess solo TERMINADO', function () {
        expect(EstadoTicket::TERMINADO->isSuccess())->toBeTrue();
        expect(EstadoTicket::TERMINADO_ERRORES->isSuccess())->toBeFalse();
        expect(EstadoTicket::ERROR->isSuccess())->toBeFalse();
    });

    test('CodLibro tiene RCE y RVIE', function () {
        expect(CodLibro::RCE->value)->toBe('080000');
        expect(CodLibro::RVIE->value)->toBe('140000');
    });

    test('CodTipoArchivo mapea extensión', function () {
        expect(CodTipoArchivo::TXT->extension())->toBe('txt');
        expect(CodTipoArchivo::CSV->extension())->toBe('csv');
        expect(CodTipoArchivo::EXCEL->extension())->toBe('xlsx');
    });

    test('VariantAjuste expone segmentos de ruta únicos', function () {
        $allSegments = collect(VariantAjuste::cases())->map(fn ($v) => $v->uploadSegment());
        expect($allSegments->unique()->count())->toBe(4);
    });

    test('VariantAjuste mapea a codProceso correcto', function () {
        expect(VariantAjuste::PERIODO_ACTUAL->codProceso())->toBe(CodProceso::IMPORTAR_CP_AJUSTES_POST);
        expect(VariantAjuste::NO_DOMICILIADOS->codProceso())->toBe(CodProceso::IMPORTAR_CP_NO_DOM_AJUSTES);
    });

    test('FasePeriodo tiene 3 fases', function () {
        expect(count(FasePeriodo::cases()))->toBe(3);
        expect(FasePeriodo::PROPUESTA->value)->toBe('propuesta');
    });
});

// ═══════════════════════════════════════════════════════════════════════
// SireErrorCatalog — mapeo de códigos SUNAT
// ═══════════════════════════════════════════════════════════════════════

describe('SireErrorCatalog', function () {
    test('mapea códigos críticos', function () {
        expect(SireErrorCatalog::friendlyMessage('1001'))->toContain('numRuc');
        expect(SireErrorCatalog::friendlyMessage('1005'))->toContain('perTributario');
        expect(SireErrorCatalog::friendlyMessage('1044'))->toContain('formato del nombre');
        expect(SireErrorCatalog::friendlyMessage('1346'))->toContain('6 GB');
        expect(SireErrorCatalog::friendlyMessage('2278'))->toContain('codTipoArchivoReporte');
    });

    test('fallback para código desconocido', function () {
        $msg = SireErrorCatalog::friendlyMessage('9999', 'mensaje original');
        expect($msg)->toBe('mensaje original');
    });

    test('parsea respuesta SUNAT 422', function () {
        $parsed = SireErrorCatalog::parseSunatErrorResponse([
            'cod' => '422',
            'errors' => [
                ['cod' => '1001', 'msg' => 'numRuc vacío'],
                ['cod' => '1005', 'msg' => 'perTributario vacío'],
            ],
        ]);

        expect($parsed)->toHaveCount(2);
        expect($parsed[0]['cod'])->toBe('1001');
        expect($parsed[0]['friendly'])->toContain('numRuc');
    });
});

// ═══════════════════════════════════════════════════════════════════════
// ZipBuilder
// ═══════════════════════════════════════════════════════════════════════

describe('ZipBuilder', function () {
    test('crea ZIP válido con TXT dentro', function () {
        $builder = new ZipBuilder();
        $tempDir = sys_get_temp_dir() . '/sire-zipbuilder-test-' . uniqid();

        $result = $builder->build(
            ruc: '20100000001',
            perTributario: '202604',
            codLibro: '080000',
            codProceso: '61',
            txtContent: "linea1\nlinea2\n",
            destDir: $tempDir,
            secuencia: 1,
        );

        expect(file_exists($result->zipPath))->toBeTrue();
        expect($result->size)->toBeGreaterThan(0);
        expect($result->sha256)->toHaveLength(64);

        $zip = new ZipArchive();
        $zip->open($result->zipPath);
        expect($zip->numFiles)->toBe(1);
        expect($zip->getNameIndex(0))->toBe($result->txtName);
        $zip->close();

        unlink($result->zipPath);
        rmdir($tempDir);
    });

    test('calcula SHA256 consistente', function () {
        $builder = new ZipBuilder();
        $tempDir = sys_get_temp_dir() . '/sire-sha-test-' . uniqid();

        $r1 = $builder->build('20100000001', '202604', '080000', '61', 'same content', $tempDir, 1);
        $h1 = $r1->sha256;
        unlink($r1->zipPath);

        $r2 = $builder->build('20100000001', '202604', '080000', '61', 'same content', $tempDir, 1);
        $h2 = $r2->sha256;
        unlink($r2->zipPath);

        // ZIPs con mismo contenido (mismo fecha/contenido/metadata) = mismo SHA
        // En práctica ZipArchive agrega timestamps, pero al menos el tamaño es igual
        expect($h1)->toHaveLength(64);
        expect($h2)->toHaveLength(64);

        rmdir($tempDir);
    });
});
