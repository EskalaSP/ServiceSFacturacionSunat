<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RegisterPaymentAction
{
    public function execute(Model $document, array $pagos): void
    {
        DB::transaction(function () use ($document, $pagos) {
            foreach ($pagos as $pago) {
                $document->payments()->create([
                    'tenant_id' => $document->tenant_id,
                    'metodo' => $pago['metodo'],
                    'monto' => $pago['monto'],
                    'referencia' => $pago['referencia'] ?? null,
                    'monto_recibido' => $pago['monto_recibido'] ?? null,
                    'notas' => $pago['notas'] ?? null,
                ]);
            }

            $this->recalculate($document);
        });
    }

    public function recalculate(Model $document): void
    {
        $montoPagado = $document->payments()->sum('monto');
        $total = (float) $document->mto_imp_venta;

        $status = 'pendiente';
        if ($montoPagado > 0 && $montoPagado < $total) {
            $status = 'parcial';
        } elseif ($montoPagado >= $total) {
            $status = 'pagado';
        }

        $document->update([
            'monto_pagado' => $montoPagado,
            'payment_status' => $status,
        ]);
    }
}
