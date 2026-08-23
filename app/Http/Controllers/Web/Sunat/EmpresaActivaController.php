<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Tenancy\EmpresaActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmpresaActivaController extends Controller
{
    public function __construct(private readonly EmpresaActiva $empresaActiva) {}

    /** Cambia la empresa activa del panel (valida pertenencia; 403 si no corresponde). */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer'],
        ]);

        $tenant = Tenant::findOrFail($data['tenant_id']);

        $this->empresaActiva->set($tenant);

        return back()->with('success', 'Empresa activa: '.$tenant->razon_social);
    }
}
