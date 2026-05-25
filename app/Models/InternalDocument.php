<?php

namespace App\Models;

use App\Contracts\Documentable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Traits\HasPayments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InternalDocument extends Model implements Documentable
{
    use BelongsToTenant, HasPayments, SoftDeletes;

    protected $table = 'internal_documents';

    // STI: mapeo tipo → clase de modelo
    protected static array $typeMap = [
        'quotation' => Quotation::class,
        'sale_note' => SaleNote::class,
    ];

    // Códigos de tipo de documento para PDF
    protected static array $tipoDocMap = [
        'quotation' => 'COT',
        'sale_note' => 'NV',
    ];

    protected $fillable = [
        'tenant_id', 'client_id', 'type', 'numero',
        'fecha_emision', 'fecha_vencimiento', 'tipo_moneda', 'forma_pago',
        'client_tipo_doc', 'client_num_doc', 'client_razon_social', 'client_direccion',
        'mto_oper_gravadas', 'mto_oper_exoneradas', 'mto_oper_inafectas',
        'mto_oper_gratuitas', 'mto_igv', 'mto_isc', 'mto_icbper',
        'total_impuestos', 'valor_venta', 'sub_total', 'mto_imp_venta',
        'total_descuentos', 'observacion', 'pdf_path', 'status',
        'payment_status', 'monto_pagado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime',
            'fecha_vencimiento' => 'date',
            'mto_oper_gravadas' => 'decimal:2',
            'mto_oper_exoneradas' => 'decimal:2',
            'mto_oper_inafectas' => 'decimal:2',
            'mto_oper_gratuitas' => 'decimal:2',
            'mto_igv' => 'decimal:2',
            'mto_isc' => 'decimal:2',
            'mto_icbper' => 'decimal:2',
            'total_impuestos' => 'decimal:2',
            'valor_venta' => 'decimal:2',
            'sub_total' => 'decimal:2',
            'mto_imp_venta' => 'decimal:2',
            'total_descuentos' => 'decimal:2',
        ];
    }

    // STI: resolver automáticamente la clase de modelo desde la columna 'type'
    public function newFromBuilder($attributes = [], $connection = null): static
    {
        $attributes = (array) $attributes;
        $type = $attributes['type'] ?? null;

        if ($type && isset(static::$typeMap[$type]) && static::class === self::class) {
            $class = static::$typeMap[$type];
            $model = (new $class)->newInstance([], true);
            $model->setRawAttributes($attributes, true);
            $model->setConnection($connection ?: $this->getConnectionName());

            return $model;
        }

        return parent::newFromBuilder($attributes, $connection);
    }

    public function getTipoDocumento(): string
    {
        return static::$tipoDocMap[$this->type] ?? 'COT';
    }

    public function getNumeroCompletoAttribute(): string
    {
        return $this->numero;
    }

    public function items(): HasMany
    {
        return $this->hasMany(InternalDocumentItem::class, 'internal_document_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeFechas(Builder $query, string $desde, string $hasta): Builder
    {
        return $query->whereBetween('fecha_emision', [
            $desde . ' 00:00:00',
            $hasta . ' 23:59:59',
        ]);
    }

    /**
     * Genera el siguiente número secuencial para un tenant+tipo dado.
     * Formato: PREFIJO-YYYYMM-NNNNNN
     */
    public static function generateNumero(int $tenantId, string $type): string
    {
        $prefix = match ($type) {
            'quotation' => 'COT',
            'sale_note' => 'NV',
            default => 'DOC',
        };

        $yearMonth = now()->format('Ym');
        $pattern = "{$prefix}-{$yearMonth}-";

        $last = static::where('tenant_id', $tenantId)
            ->where('type', $type)
            ->where('numero', 'like', "{$pattern}%")
            ->orderByRaw('CAST(SUBSTRING(numero, ' . (strlen($pattern) + 1) . ') AS UNSIGNED) DESC')
            ->lockForUpdate()
            ->value('numero');

        if ($last) {
            $lastNum = (int) substr($last, strlen($pattern));
            $next = $lastNum + 1;
        } else {
            $next = 1;
        }

        return $prefix . '-' . $yearMonth . '-' . str_pad($next, 6, '0', STR_PAD_LEFT);
    }
}
