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
                return $content;
            }

            // Puede ser un DER binario, intentar convertir
            $pem = $this->derToPem($content);
            if ($pem) {
                return $pem;
            }

            throw new RuntimeException('El archivo no contiene un certificado PEM válido.');
        }

        if (in_array($extension, ['pfx', 'p12'])) {
            return $this->pfxToPem($content, $password ?? '');
        }

        throw new RuntimeException("Formato de certificado no soportado: .{$extension}");
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
                . chunk_split(base64_encode($derContent), 64, "\n")
                . "-----END CERTIFICATE-----\n";

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
