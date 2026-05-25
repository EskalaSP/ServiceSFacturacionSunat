<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payments\RegisterPaymentAction;
use App\Http\Controllers\Controller;
use App\Models\Boleta;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SaleNote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    private const DOC_TYPE_MAP = [
        'facturas' => Invoice::class,
        'boletas' => Boleta::class,
        'notas-venta' => SaleNote::class,
    ];

    public function store(Request $request, string $id, RegisterPaymentAction $action): JsonResponse
    {
        // Acepta un pago único {metodo, monto, ...} o un array pagos:[{...}]
        if ($request->has('metodo') && !$request->has('pagos')) {
            $request->merge(['pagos' => [
                [
                    'metodo'          => $request->input('metodo'),
                    'monto'           => $request->input('monto'),
                    'referencia'      => $request->input('referencia'),
                    'monto_recibido'  => $request->input('monto_recibido'),
                    'notas'           => $request->input('notas'),
                ],
            ]]);
        }

        $request->validate([
            'pagos' => 'required|array|min:1',
            'pagos.*.metodo' => 'required|string|in:' . implode(',', Payment::METODOS),
            'pagos.*.monto' => 'required|numeric|min:0.01',
            'pagos.*.referencia' => 'nullable|string|max:100',
            'pagos.*.monto_recibido' => 'nullable|numeric|min:0',
            'pagos.*.notas' => 'nullable|string|max:255',
        ]);

        $document = $this->resolveDocument($this->detectDocType($request), (int) $id);

        $action->execute($document, $request->input('pagos'));

        $document->refresh();

        return response()->json([
            'mensaje' => 'Pagos registrados correctamente',
            'estado_pago' => $document->payment_status,
            'monto_pagado' => (float) $document->monto_pagado,
            'pagos' => $document->payments->map(fn ($p) => $this->formatPayment($p)),
        ]);
    }

    public function index(Request $request, string $id): JsonResponse
    {
        $document = $this->resolveDocument($this->detectDocType($request), (int) $id);

        return response()->json([
            'datos' => $document->payments->map(fn ($p) => $this->formatPayment($p)),
            'estado_pago' => $document->payment_status,
            'monto_pagado' => (float) $document->monto_pagado,
            'total_documento' => (float) $document->mto_imp_venta,
        ]);
    }

    public function destroy(Request $request, string $id, string $paymentId, RegisterPaymentAction $action): JsonResponse
    {
        $document = $this->resolveDocument($this->detectDocType($request), (int) $id);

        $payment = $document->payments()->where('id', (int) $paymentId)->firstOrFail();
        $payment->delete();

        $action->recalculate($document);

        $document->refresh();

        return response()->json([
            'mensaje' => 'Pago eliminado correctamente',
            'estado_pago' => $document->payment_status,
            'monto_pagado' => (float) $document->monto_pagado,
        ]);
    }

    private function detectDocType(Request $request): string
    {
        // URL: /api/v1/{docType}/{id}/pagos  → segmento en el índice 2
        return $request->segment(3) ?? '';
    }

    private function resolveDocument(string $docType, int|string $docId): Model
    {
        $modelClass = self::DOC_TYPE_MAP[$docType] ?? null;

        if (! $modelClass) {
            abort(404, 'Tipo de documento no válido');
        }

        $tenant = request()->get('tenant');

        return $modelClass::where('tenant_id', $tenant->id)
            ->with('payments')
            ->findOrFail($docId);
    }

    private function formatPayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'metodo' => $payment->metodo,
            'monto' => (float) $payment->monto,
            'referencia' => $payment->referencia,
            'monto_recibido' => $payment->monto_recibido ? (float) $payment->monto_recibido : null,
            'vuelto' => $payment->metodo === 'efectivo' && $payment->monto_recibido
                ? round((float) $payment->monto_recibido - (float) $payment->monto, 2)
                : null,
            'notas' => $payment->notas,
            'creado_en' => $payment->created_at->toIso8601String(),
        ];
    }
}
