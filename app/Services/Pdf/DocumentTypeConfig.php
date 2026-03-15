<?php

namespace App\Services\Pdf;

class DocumentTypeConfig
{
    private static array $configs = [
        '01' => [
            'titulo' => 'FACTURA ELECTRÓNICA',
            'sections' => [
                'header', 'document-badge', 'emitter', 'receiver',
                'items', 'totals', 'leyenda', 'payment-info',
                'qr-code', 'footer',
            ],
        ],
        '03' => [
            'titulo' => 'BOLETA DE VENTA ELECTRÓNICA',
            'sections' => [
                'header', 'document-badge', 'emitter', 'receiver',
                'items', 'totals', 'leyenda', 'payment-info',
                'qr-code', 'footer',
            ],
        ],
        '07' => [
            'titulo' => 'NOTA DE CRÉDITO ELECTRÓNICA',
            'sections' => [
                'header', 'document-badge', 'emitter', 'receiver',
                'note-reference', 'items', 'totals', 'leyenda',
                'qr-code', 'footer',
            ],
        ],
        '08' => [
            'titulo' => 'NOTA DE DÉBITO ELECTRÓNICA',
            'sections' => [
                'header', 'document-badge', 'emitter', 'receiver',
                'note-reference', 'items', 'totals', 'leyenda',
                'qr-code', 'footer',
            ],
        ],
        '09' => [
            'titulo' => 'GUÍA DE REMISIÓN ELECTRÓNICA',
            'sections' => [
                'header', 'document-badge', 'emitter', 'receiver',
                'dispatch-info', 'items',
                'qr-code', 'footer',
            ],
        ],
        'COT' => [
            'titulo' => 'COTIZACIÓN',
            'sections' => [
                'header', 'document-badge', 'emitter', 'receiver',
                'items', 'totals', 'footer',
            ],
        ],
        'NV' => [
            'titulo' => 'NOTA DE VENTA',
            'sections' => [
                'header', 'document-badge', 'emitter', 'receiver',
                'items', 'totals', 'payment-info', 'footer',
            ],
        ],
    ];

    public static function get(string $tipoDocumento): array
    {
        return self::$configs[$tipoDocumento] ?? self::$configs['01'];
    }

    public static function titulo(string $tipoDocumento): string
    {
        return self::get($tipoDocumento)['titulo'];
    }

    public static function sections(string $tipoDocumento): array
    {
        return self::get($tipoDocumento)['sections'];
    }
}
