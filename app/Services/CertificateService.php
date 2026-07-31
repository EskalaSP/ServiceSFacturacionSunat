<?php

namespace App\Services;

use RuntimeException;

class CertificateService
{
    /**
     * Convierte un certificado (PFX o PEM) a formato PEM.
     * Si ya es PEM, lo retorna tal cual.
     * Si es PFX, lo convierte usando el password proporcionado.
     */
    public function convertToPem(string $content, string $originalExtension, ?string $password = null): string
    {
        $extension = strtolower($originalExtension);

        if (in_array($extension, ['pem', 'cer', 'crt'])) {
            // Validar que sea un PEM válido
            if (str_contains($content, '-----BEGIN')) {
                return $this->asegurarClavePrivada($content);
            }

            // Puede ser un DER binario, intentar convertir
            $pem = $this->derToPem($content);
            if ($pem) {
                return $this->asegurarClavePrivada($pem);
            }

            throw new RuntimeException('El archivo no contiene un certificado PEM válido.');
        }

        if (in_array($extension, ['pfx', 'p12'])) {
            return $this->asegurarClavePrivada($this->pfxToPem($content, $password ?? ''));
        }

        throw new RuntimeException("Formato de certificado no soportado: .{$extension}");
    }

    /**
     * Rechaza un certificado que no sirva para firmar.
     *
     * Antes bastaba con que el archivo contuviera un "-----BEGIN" para darlo por
     * bueno. Un .cer o .crt lo tiene, pero solo lleva el certificado PÚBLICO —y
     * es justo el archivo que SUNAT deja descargar de su portal, así que es el
     * error más fácil de cometer.
     *
     * El problema no era aceptarlo, era CUÁNDO se notaba: el archivo se
     * guardaba sin chistar y recién fallaba días después al firmar un
     * comprobante, con un "openssl_sign(): Supplied key param cannot be coerced
     * into a private key" que no le dice nada a nadie. Para entonces ya hay
     * boletas emitidas que nunca llegaron a SUNAT y un plazo de 7 días corriendo.
     *
     * Validar acá cuesta un milisegundo y el usuario todavía tiene el archivo
     * correcto a mano.
     */
    private function asegurarClavePrivada(string $pem): string
    {
        if (@openssl_x509_read($pem) === false) {
            throw new RuntimeException(
                'El archivo no contiene un certificado digital válido.'
            );
        }

        if (@openssl_pkey_get_private($pem) === false) {
            throw new RuntimeException(
                'El archivo tiene el certificado pero NO la clave privada, así que no sirve para firmar '
                .'comprobantes. Probablemente subiste el .cer o .crt que descargaste del portal de SUNAT: '
                .'necesitás el archivo .pfx o .p12 que te entregó tu certificadora, junto con su contraseña.'
            );
        }

        return $pem;
    }

    /**
     * Convierte PFX/P12 a PEM usando openssl.
     */
    private function pfxToPem(string $pfxContent, string $password): string
    {
        $certs = [];
        $result = openssl_pkcs12_read($pfxContent, $certs, $password);

        if (! $result || empty($certs)) {
            throw new RuntimeException('No se pudo leer el archivo PFX. Verifique el password del certificado.');
        }

        $pem = '';

        // Certificado
        if (! empty($certs['cert'])) {
            $pem .= $certs['cert'];
        }

        // Clave privada
        if (! empty($certs['pkey'])) {
            $pem .= $certs['pkey'];
        }

        if (empty($pem)) {
            throw new RuntimeException('El archivo PFX no contiene certificado ni clave privada.');
        }

        return $pem;
    }

    /**
     * Intenta convertir un certificado DER binario a PEM.
     */
    private function derToPem(string $derContent): ?string
    {
        $cert = @openssl_x509_read($derContent);

        if ($cert === false) {
            // Intentar como DER
            $pem = "-----BEGIN CERTIFICATE-----\n"
                .chunk_split(base64_encode($derContent), 64, "\n")
                ."-----END CERTIFICATE-----\n";

            $cert = @openssl_x509_read($pem);

            if ($cert === false) {
                return null;
            }

            return $pem;
        }

        openssl_x509_export($cert, $output);

        return $output;
    }
}
