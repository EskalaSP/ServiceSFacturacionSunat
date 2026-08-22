<?php

namespace App\Services\License;

/**
 * Identificador estable de ESTA instalación del sistema.
 *
 * Se genera una vez y se persiste en storage (fuera del control de versiones),
 * de modo que la misma copia -corra en localhost, un VPS o un hosting- se
 * identifica siempre igual, sin depender de un dominio. Una copia nueva del
 * código (sin este archivo) genera un id distinto y necesita su propia
 * activación, que controla el proveedor.
 */
class MachineId
{
    private const RUTA = 'license/machine-id';

    public function get(): string
    {
        $path = storage_path('app/'.self::RUTA);

        if (is_file($path)) {
            $id = trim((string) @file_get_contents($path));

            if ($id !== '') {
                return $id;
            }
        }

        $id = bin2hex(random_bytes(20)); // 40 caracteres hex

        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }

        @file_put_contents($path, $id);

        return $id;
    }
}
