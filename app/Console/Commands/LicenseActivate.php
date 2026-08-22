<?php

namespace App\Console\Commands;

use App\Services\License\LicenseClient;
use App\Services\License\MachineId;
use Illuminate\Console\Command;

/**
 * Activa esta instalación en el servidor de licencias del proveedor.
 * Se corre una vez tras instalar, con LICENSE_KEY / LICENSE_SECRET ya puestos.
 */
class LicenseActivate extends Command
{
    protected $signature = 'license:activate';

    protected $description = 'Activa la licencia de esta instalación en el servidor del proveedor';

    public function handle(LicenseClient $license, MachineId $machineId): int
    {
        $this->line('Instalación : '.$machineId->get());
        $this->line('Dominio     : '.config('license.domain'));
        $this->line('Servidor    : '.config('license.server_url'));

        if (! config('license.key') || ! config('license.secret')) {
            $this->error('Faltan LICENSE_KEY y/o LICENSE_SECRET en el .env.');

            return self::FAILURE;
        }

        $resultado = $license->activate();

        if ($resultado['ok']) {
            $this->info('✓ Licencia activada: '.$resultado['message']);

            return self::SUCCESS;
        }

        $this->error('✗ No se pudo activar ('.$resultado['code'].'): '.$resultado['message']);

        return self::FAILURE;
    }
}
