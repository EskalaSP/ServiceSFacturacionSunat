<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración global del sistema de emisión (Escenario 1).
 * Dos switches: emisión ilimitada para TODAS las empresas y default
 * ilimitado para las empresas nuevas.
 */
class ConfiguracionController extends Controller
{
    public function __construct(
        private SettingsService $settings,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('admin/configuracion/index', [
            'config' => [
                'emision_ilimitada_global' => $this->settings->bool(SettingsService::EMISION_ILIMITADA_GLOBAL),
                'nuevas_empresas_ilimitadas' => $this->settings->bool(SettingsService::NUEVAS_EMPRESAS_ILIMITADAS),
            ],
            'stats' => [
                'empresas_total' => Tenant::count(),
                'empresas_ilimitadas' => Tenant::where('emission_mode', Tenant::EMISSION_UNLIMITED)->count(),
                'empresas_por_plan' => Tenant::where('emission_mode', Tenant::EMISSION_PLAN)->count(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'emision_ilimitada_global' => 'required|boolean',
            'nuevas_empresas_ilimitadas' => 'required|boolean',
        ]);

        $this->settings->setMany([
            SettingsService::EMISION_ILIMITADA_GLOBAL => (bool) $data['emision_ilimitada_global'],
            SettingsService::NUEVAS_EMPRESAS_ILIMITADAS => (bool) $data['nuevas_empresas_ilimitadas'],
        ]);

        return back()->with('success', 'Configuración global de emisión actualizada.');
    }
}
