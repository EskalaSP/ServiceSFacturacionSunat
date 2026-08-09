<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EnvioLogController extends Controller
{
    public function index(Request $request)
    {
        $estado = $request->string('estado')->toString();
        $buscar = trim($request->string('buscar')->toString());

        $union = DB::table('invoices')
            ->selectRaw("'Factura' AS tipo, invoices.id, invoices.tenant_id, invoices.serie, invoices.correlativo, invoices.sunat_status AS estado, invoices.sunat_code AS codigo, invoices.sunat_description AS descripcion, invoices.fecha_emision, invoices.sent_at, invoices.mto_imp_venta AS total, tenants.ruc, tenants.razon_social")
            ->join('tenants', 'tenants.id', '=', 'invoices.tenant_id')
            ->whereNull('invoices.deleted_at')
            ->unionAll(
                DB::table('boletas')
                    ->selectRaw("'Boleta' AS tipo, boletas.id, boletas.tenant_id, boletas.serie, boletas.correlativo, boletas.sunat_status AS estado, boletas.sunat_code AS codigo, boletas.sunat_description AS descripcion, boletas.fecha_emision, boletas.sent_at, boletas.mto_imp_venta AS total, tenants.ruc, tenants.razon_social")
                    ->join('tenants', 'tenants.id', '=', 'boletas.tenant_id')
                    ->whereNull('boletas.deleted_at')
            )
            ->unionAll(
                DB::table('credit_notes')
                    ->selectRaw("'Nota de crédito' AS tipo, credit_notes.id, credit_notes.tenant_id, credit_notes.serie, credit_notes.correlativo, credit_notes.sunat_status AS estado, credit_notes.sunat_code AS codigo, credit_notes.sunat_description AS descripcion, credit_notes.fecha_emision, credit_notes.sent_at, credit_notes.mto_imp_venta AS total, tenants.ruc, tenants.razon_social")
                    ->join('tenants', 'tenants.id', '=', 'credit_notes.tenant_id')
                    ->whereNull('credit_notes.deleted_at')
            )
            ->unionAll(
                DB::table('debit_notes')
                    ->selectRaw("'Nota de débito' AS tipo, debit_notes.id, debit_notes.tenant_id, debit_notes.serie, debit_notes.correlativo, debit_notes.sunat_status AS estado, debit_notes.sunat_code AS codigo, debit_notes.sunat_description AS descripcion, debit_notes.fecha_emision, debit_notes.sent_at, debit_notes.mto_imp_venta AS total, tenants.ruc, tenants.razon_social")
                    ->join('tenants', 'tenants.id', '=', 'debit_notes.tenant_id')
                    ->whereNull('debit_notes.deleted_at')
            );

        $query = DB::query()->fromSub($union, 'logs')->select('*');
        $query->when($estado !== '', fn ($q) => $q->where('estado', $estado));
        $query->when($buscar !== '', function ($q) use ($buscar) {
            $term = "%{$buscar}%";
            $q->where(function ($w) use ($term) {
                $w->where('ruc', 'like', $term)
                    ->orWhere('razon_social', 'like', $term)
                    ->orWhere('serie', 'like', $term)
                    ->orWhere('correlativo', 'like', $term)
                    ->orWhere('descripcion', 'like', $term);
            });
        });

        $logs = $query->orderByRaw('COALESCE(sent_at, fecha_emision) DESC')->paginate(50)->withQueryString();

        return Inertia::render('admin/logs-envios', [
            'logs' => $logs,
            'filtros' => ['estado' => $estado, 'buscar' => $buscar],
        ]);
    }
}
