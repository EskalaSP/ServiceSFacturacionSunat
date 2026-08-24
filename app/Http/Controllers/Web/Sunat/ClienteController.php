<?php

namespace App\Http\Controllers\Web\Sunat;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class ClienteController extends Controller
{
    public function index(): \Inertia\Response|\Illuminate\Http\RedirectResponse
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actual();

        if (! $tenant) {
            return redirect()->route('sunat.configuracion');
        }

        $clientes = Client::where('tenant_id', $tenant->id)
            ->orderBy('razon_social')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'tipo_documento' => $c->tipo_documento,
                'numero_documento' => $c->numero_documento,
                'razon_social' => $c->razon_social,
                'nombre_comercial' => $c->nombre_comercial,
                'direccion' => $c->direccion,
                'email' => $c->email,
                'telefono' => $c->telefono,
            ])
            ->all();

        return Inertia::render('sunat/clientes/index', [
            'clientes' => $clientes,
            'tenant' => ['environment' => $tenant->environment ?? 'beta'],
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actualOFallar();
        \Illuminate\Support\Facades\Gate::authorize('gestionar-clientes', $tenant);

        $data = $request->validate([
            'tipo_documento' => 'required|string|max:1',
            'numero_documento' => 'required|string|max:15',
            'razon_social' => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:500',
            'email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',
        ]);

        Client::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'tipo_documento' => $data['tipo_documento'],
                'numero_documento' => $data['numero_documento'],
            ],
            array_merge($data, ['tenant_id' => $tenant->id])
        );

        return back()->with('success', 'Cliente guardado correctamente.');
    }

    public function update(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actualOFallar();
        \Illuminate\Support\Facades\Gate::authorize('gestionar-clientes', $tenant);
        $cliente = Client::where('tenant_id', $tenant->id)->findOrFail($id);

        $data = $request->validate([
            'razon_social' => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:500',
            'email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',
        ]);

        $cliente->update($data);

        return back()->with('success', 'Cliente actualizado.');
    }

    public function destroy(int $id): \Illuminate\Http\RedirectResponse
    {
        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actualOFallar();
        \Illuminate\Support\Facades\Gate::authorize('gestionar-clientes', $tenant);
        Client::where('tenant_id', $tenant->id)->findOrFail($id)->delete();

        return back()->with('success', 'Cliente eliminado.');
    }

    public function lookupRuc(Request $request): \Illuminate\Http\JsonResponse
    {
        $numero = trim($request->input('numero', ''));

        if (strlen($numero) !== 8 && strlen($numero) !== 11) {
            return response()->json(['error' => 'Número inválido (8 dígitos para DNI, 11 para RUC).'], 422);
        }

        $tenant = app(\App\Services\Tenancy\EmpresaActiva::class)->actual();
        if (! $tenant) {
            return response()->json(['error' => 'No hay empresa activa.'], 403);
        }

        // 1) Buscar primero en la base de datos local (clientes ya registrados de esta empresa).
        $local = Client::where('tenant_id', $tenant->id)
            ->where('numero_documento', $numero)
            ->first();

        if ($local) {
            return response()->json([
                'razon_social' => $local->razon_social,
                'direccion' => $local->direccion ?? '',
                'email' => $local->email ?? '',
                'tipo_documento' => $local->tipo_documento,
                'origen' => 'local',
            ]);
        }

        // 2) No está en la BD → consultar api.json.pe con el token propio de la empresa.
        $token = $tenant->consulta_token;
        if (! $token) {
            return response()->json([
                'error' => 'No has configurado tu token de consulta. Agrégalo en Configuración → Consulta RUC/DNI.',
            ], 503);
        }

        $esRuc = strlen($numero) === 11;

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(8)
                ->post(
                    $esRuc ? 'https://api.json.pe/api/ruc' : 'https://api.json.pe/api/dni',
                    $esRuc ? ['ruc' => $numero] : ['dni' => $numero],
                );

            if (in_array($response->status(), [401, 403], true)) {
                return response()->json(['error' => 'Token de consulta inválido o vencido. Revísalo en Configuración.'], 502);
            }

            $json = $response->json();

            if (! $response->successful() || ! ($json['success'] ?? false) || empty($json['data'])) {
                return response()->json(['error' => $esRuc ? 'RUC no encontrado.' : 'DNI no encontrado.'], 404);
            }

            $data = $json['data'];

            if ($esRuc) {
                $nombre = $data['nombre_o_razon_social'] ?? '';
            } else {
                $nombre = $data['nombre_completo'] ?? trim(implode(' ', array_filter([
                    $data['nombres'] ?? '',
                    $data['apellido_paterno'] ?? '',
                    $data['apellido_materno'] ?? '',
                ])));
            }

            return response()->json([
                'razon_social' => $nombre,
                'direccion' => $data['direccion_completa'] ?? $data['direccion'] ?? '',
                'tipo_documento' => $esRuc ? '6' : '1',
                'estado' => $data['estado'] ?? '',
                'condicion' => $data['condicion'] ?? '',
                'origen' => 'api',
            ]);
        } catch (\Throwable) {
            return response()->json(['error' => 'No se pudo conectar con el servicio de consulta.'], 500);
        }
    }
}
