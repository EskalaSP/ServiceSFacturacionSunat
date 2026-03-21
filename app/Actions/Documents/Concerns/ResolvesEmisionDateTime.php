<?php

namespace App\Actions\Documents\Concerns;

use Carbon\Carbon;

trait ResolvesEmisionDateTime
{
    /**
     * If fecha_emision is date-only (Y-m-d), append current Lima time.
     * If it already includes time, use as-is.
     */
    protected function resolveEmisionDateTime(string $fecha): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return $fecha . ' ' . Carbon::now('America/Lima')->format('H:i:s');
        }

        return $fecha;
    }
}
