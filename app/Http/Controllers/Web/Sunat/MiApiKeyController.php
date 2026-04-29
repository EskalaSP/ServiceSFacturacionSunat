<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class MiApiKeyController extends Controller
{
    public function show(): \Inertia\Response|\Illuminate\Http\RedirectResponse
    {
        $tenant = auth()->user()->tenants()->first();

        if (! $tenant) {
            return redirect()->route('sunat.configuracion')
                ->with('warning', 'Configura tus credenciales SUNAT primero.');
        }

        return Inertia::render('sunat/mi-api-key', [
            'api_key'    => $tenant->api_key,
            'api_secret' => $tenant->getRawOriginal('api_secret'),
            'ruc'        => $tenant->ruc,
            'razon_social' => $tenant->razon_social ?? '',
            'environment'  => $tenant->environment ?? 'beta',
        ]);
    }
}
