<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Sire\Enums\EstadoTicket;
use App\Sire\Models\SireTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SireTicket>
 */
class SireTicketFactory extends Factory
{
    protected $model = SireTicket::class;

    public function definition(): array
    {
        return [
            'tenant_id'          => Tenant::factory(),
            'num_ticket'         => $this->uniqueNumTicket(),
            'per_tributario'     => '202604',
            'cod_proceso'        => '10',
            'cod_estado_proceso' => EstadoTicket::PENDIENTE->value,
            'des_estado_proceso' => 'Pendiente',
            'poll_attempts'      => 0,
        ];
    }

    private function uniqueNumTicket(): string
    {
        return sprintf('%04d%02d%08d', now()->year, 1, mt_rand(10_000_000, 99_999_999));
    }
}
