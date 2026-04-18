<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $ruc = '20' . $this->faker->unique()->numerify('#########');

        return [
            'user_id'        => User::factory(),
            'ruc'            => $ruc,
            'razon_social'   => $this->faker->company(),
            'direccion'      => $this->faker->address(),
            'ubigeo'         => '150101',
            'sol_user'       => 'MODDATOS',
            'sol_pass'       => 'MODDATOS',
            'client_id'      => $this->faker->uuid(),
            'client_secret'  => bin2hex(random_bytes(16)),
            'environment'    => 'beta',
            'plan'           => 'free',
            'api_key'        => 'key_' . $this->faker->unique()->uuid(),
            'api_secret'     => 'sec_' . bin2hex(random_bytes(16)),
            'is_active'      => true,
            'sire_enabled'   => false,
        ];
    }
}
