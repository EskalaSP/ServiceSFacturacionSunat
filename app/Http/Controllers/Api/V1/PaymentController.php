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
        'invoices' => Invoice::class,
        'boletas' => Boleta::class,
        'sale-notes' => SaleNote::class,
    ];

    public function store(Request $request, string $docType, int $docId, RegisterPaymentAction $action): JsonResponse
    {
        $request->validate([
            'pagos' => 'required|array|min:1',
            'pagos.*.metodo' => 'required|string|in:' . implode(',', Payment::METODOS),
            'pagos.*.monto' => 'required|numeric|min:0.01',
            'pagos.*.referencia' => 'nullable|string|max:100',
            'pagos.*.monto_recibido' => 'nullable|numeric|min:0',
            'pagos.*.notas' => 'nullable|string|max:255',
        ]);

        $document = $this->resolveDocument($docType, $docId);

        $action->execute($document, $request->input('pagos'));

        $document->refresh();

        return response()->json([
            'message' => 'Pagos registrados correctamente',
            'payment_status' => $document->payment_status,
            'monto_pagado' => (float) $document->monto_pagado,
            'payments' => $document->payments->map(fn ($p) => $this->formatPayment($p)),
        ]);
    }

    public function index(Request $request, string $docType, int $docId): JsonResponse
    {
        $document = $this->resolveDocument($docType, $docId);

        return response()->json([
            'data' => $document->payments->map(fn ($p) => $this->formatPayment($p)),
            'payment_status' => $document->payment_status,
            'monto_pagado' => (float) $document->monto_pagado,
            'total_documento' => (float) $document->mto_imp_venta,
        ]);
    }

    public function destroy(Request $request, string $docType, int $docId, int $paymentId, RegisterPaymentAction $action): JsonResponse
    {
        $document = $this->resolveDocument($docType, $docId);

        $payment = $document->payments()->where('id', $paymentId)->firstOrFail();
        $payment->delete();

        $action->recalculate($document);

        $document->refresh();

        return response()->json([
            'message' => 'Pago eliminado correctamente',
            'payment_status' => $document->payment_status,
            'monto_pagado' => (float) $document->monto_pagado,
        ]);
    }

    private function resolveDocument(string $docType, int $docId): Model
    {
        $modelClass = self::DOC_TYPE_MAP[$docType] ?? null;

        if (! $modelClass) {
            abort(404, 'Tipo de documento no válido');
        }

        $tenant = request()->attributes->get('tenant');

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
            'created_at' => $payment->created_at->toIso8601String(),
        ];
    }
}
