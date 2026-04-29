<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function index(): RedirectResponse
    {
        $tenant = auth()->user()->tenants()->first();

        if (! $tenant || empty($tenant->sol_user)) {
            return redirect()->route('sunat.configuracion')
                ->with('info', 'Configura tus credenciales SUNAT para comenzar.');
        }

        return redirect()->route('sunat.facturas.create');
    }
}
