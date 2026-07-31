<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CertificateService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Un certificado sin clave privada no sirve para firmar comprobantes, y el
 * archivo que SUNAT deja descargar de su portal (.cer) es exactamente eso.
 *
 * Antes se aceptaba sin chistar y el fallo aparecía días después, al firmar,
 * como "openssl_sign(): Supplied key param cannot be coerced into a private
 * key". Para entonces ya había boletas emitidas que nunca llegaron a SUNAT y
 * un plazo de 7 días corriendo.
 */
class CertificateServiceTest extends TestCase
{
    /** @return array{0: string, 1: string} [pem completo, solo certificado] */
    private function parAutofirmado(): array
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $csr = openssl_csr_new(['commonName' => 'PRUEBA SAC'], $key, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);

        openssl_x509_export($x509, $certPem);
        openssl_pkey_export($key, $keyPem);

        return [$certPem.$keyPem, $certPem];
    }

    public function test_acepta_un_pem_con_certificado_y_clave(): void
    {
        [$completo] = $this->parAutofirmado();

        $resultado = (new CertificateService)->convertToPem($completo, 'pem');

        $this->assertNotFalse(openssl_pkey_get_private($resultado));
        $this->assertNotFalse(openssl_x509_read($resultado));
    }

    public function test_rechaza_un_certificado_sin_clave_privada(): void
    {
        [, $soloCert] = $this->parAutofirmado();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/NO la clave privada/');

        (new CertificateService)->convertToPem($soloCert, 'cer');
    }

    public function test_el_mensaje_explica_qué_archivo_hace_falta(): void
    {
        [, $soloCert] = $this->parAutofirmado();

        try {
            (new CertificateService)->convertToPem($soloCert, 'crt');
            $this->fail('Debió rechazar un certificado sin clave privada.');
        } catch (RuntimeException $e) {
            // El flujo habitual es convertir el .p12 de SUNAT a mano, y la
            // conversión con -nokeys descarta la clave. El mensaje tiene que
            // dar el comando correcto, no explicar criptografía.
            $this->assertStringContainsString('.p12', $e->getMessage());
            $this->assertStringContainsString('-nodes', $e->getMessage());
        }
    }

    public function test_explica_el_caso_de_la_clave_cifrada(): void
    {
        [$completo] = $this->parAutofirmado();

        // Equivale a convertir el .p12 SIN -nodes: la clave viaja, pero cifrada
        // con su propia frase de paso, y Greenter no puede usarla.
        openssl_pkey_export(
            openssl_pkey_get_private($completo),
            $keyCifrada,
            'frase-de-paso'
        );

        openssl_x509_export(openssl_x509_read($completo), $certPem);

        try {
            (new CertificateService)->convertToPem($certPem.$keyCifrada, 'cer');
            $this->fail('Debió rechazar una clave privada cifrada.');
        } catch (RuntimeException $e) {
            // El mensaje tiene que mandar a repetir la conversión, no a buscar
            // otro archivo: la clave está ahí, el problema es cómo se exportó.
            $this->assertStringContainsString('-nodes', $e->getMessage());
        }
    }

    public function test_rechaza_un_pfx_con_contrasena_equivocada(): void
    {
        [$completo] = $this->parAutofirmado();

        openssl_pkcs12_export(
            openssl_x509_read($completo),
            $pfx,
            openssl_pkey_get_private($completo),
            'la-correcta'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/password/i');

        (new CertificateService)->convertToPem($pfx, 'pfx', 'la-equivocada');
    }
}
