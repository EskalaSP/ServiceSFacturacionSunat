<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Controller;
use App\Services\Tenancy\EmpresaActiva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Descarga masiva (ZIP) de comprobantes de la empresa activa. Reutiliza ExportController de la API. */
class ExportarController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $tenant = app(EmpresaActiva::class)->actual();
        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }

        return Inertia::render('sunat/exportar/index', []);
    }

    public function download(Request $request, ExportController $export): StreamedResponse|JsonResponse
    {
        $tenant = app(EmpresaActiva::class)->actualOFallar();
        Gate::authorize('exportar', $tenant);

        // ExportController@zip lee el tenant desde la request (como en la API).
        $request->merge(['tenant' => $tenant]);

        return $export->zip($request);
    }
}
