<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Sella esta copia con el fingerprint de la licencia del comprador.
 *
 * Se corre UNA vez al empaquetar la venta, antes de entregar/ofuscar. Escribe
 * el fingerprint (que da el panel del proveedor) dentro de BuildInfo.php, de
 * modo que la copia queda marcada y es rastreable si se filtra.
 */
class LicenseStamp extends Command
{
    protected $signature = 'license:stamp {fingerprint : Fingerprint de la licencia (lo da el panel)}';

    protected $description = 'Sella esta copia con el fingerprint del comprador (rastreo anti-filtración)';

    public function handle(): int
    {
        $fingerprint = trim((string) $this->argument('fingerprint'));

        if (! preg_match('/^[A-Za-z0-9_-]{6,64}$/', $fingerprint)) {
            $this->error('Fingerprint inválido. Debe tener 6-64 caracteres (letras, números, _ o -).');

            return self::FAILURE;
        }

        $path = app_path('Services/License/BuildInfo.php');

        if (! is_file($path)) {
            $this->error('No se encontró BuildInfo.php.');

            return self::FAILURE;
        }

        $contenido = (string) file_get_contents($path);

        $nuevo = preg_replace(
            "/const FINGERPRINT = '[^']*';/",
            "const FINGERPRINT = '{$fingerprint}';",
            $contenido,
            1,
            $reemplazos,
        );

        if ($reemplazos !== 1 || $nuevo === null) {
            $this->error('No se pudo escribir el fingerprint en BuildInfo.php (marcador no encontrado).');

            return self::FAILURE;
        }

        file_put_contents($path, $nuevo);

        $this->info("✓ Copia sellada con fingerprint: {$fingerprint}");
        $this->line('Recuerda ofuscar los archivos del candado antes de entregar (ver LICENSING.md).');

        return self::SUCCESS;
    }
}
