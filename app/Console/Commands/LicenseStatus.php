<?php

namespace App\Console\Commands;

use App\Services\License\LicenseClient;
use App\Services\License\MachineId;
use Illuminate\Console\Command;

/**
 * Muestra el estado actual de la licencia de esta instalación.
 */
class LicenseStatus extends Command
{
    protected $signature = 'license:status';

    protected $description = 'Muestra si la licencia de esta instalación es válida';

    public function handle(LicenseClient $license, MachineId $machineId): int
    {
        $this->line('Habilitada  : '.(config('license.enabled') ? 'sí' : 'no'));
        $this->line('Instalación : '.$machineId->get());
        $this->line('Dominio     : '.config('license.domain'));
        $this->line('Servidor    : '.config('license.server_url'));
        $this->line('Clave       : '.(config('license.key') ? substr((string) config('license.key'), 0, 14).'...' : '(vacía)'));
        $this->newLine();

        $check = $license->check();

        if ($check->allowed) {
            $this->info('✓ '.$check->message.' ['.$check->code.']');

            if ($check->offlineGrace) {
                $this->warn('⚠ Operando en periodo de gracia (servidor no disponible).');
            }

            return self::SUCCESS;
        }

        $this->error('✗ Licencia no válida ('.$check->code.'): '.$check->message);

        return self::FAILURE;
    }
}
