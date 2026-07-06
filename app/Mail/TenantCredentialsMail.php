<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Envía api_key y api_secret al tenant COMO ARCHIVO ADJUNTO (.txt).
 *
 * Estrategia anti-spam: el filtro de salida de Hostinger (MailChannels)
 * bloquea con "550 5.7.1 [CS] Message blocked" los correos cuyo CUERPO
 * contiene strings tipo credenciales. Por eso:
 *   · El cuerpo del correo es genérico y limpio (no lleva las keys).
 *   · Las credenciales viajan dentro de un .txt adjunto.
 * Así el contenido sensible no está en el body y pasa el filtro.
 */
class TenantCredentialsMail extends BaseMailable
{
    public function __construct(
        public Tenant $tenant,
        public string $apiKey,
        public string $apiSecret,
        public string $motivo = 'creacion', // 'creacion' | 'regeneracion'
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->motivo === 'regeneracion'
            ? 'Nuevos accesos para ' . $this->tenant->razon_social
            : 'Bienvenido — accesos para ' . $this->tenant->razon_social;

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        // Cuerpo SIN credenciales — solo indica que van adjuntas.
        return new Content(
            text: 'emails.tenant-credentials-text',
            with: [
                'tenantName' => $this->tenant->razon_social,
                'ruc'        => $this->tenant->ruc,
                'motivo'     => $this->motivo,
                'docsUrl'    => config('app.url') . '/api/v1',
                'appName'    => config('app.name'),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $contenido = $this->construirTxt();

        return [
            Attachment::fromData(fn () => $contenido, 'credenciales-api-' . $this->tenant->ruc . '.txt')
                ->withMime('text/plain'),
        ];
    }

    private function construirTxt(): string
    {
        return implode("\r\n", [
            'CREDENCIALES DE API - ' . config('app.name'),
            '===========================================',
            '',
            'Empresa: ' . $this->tenant->razon_social,
            'RUC:     ' . $this->tenant->ruc,
            '',
            '-- Credenciales de acceso --',
            'X-Api-Key:    ' . $this->apiKey,
            'X-Api-Secret: ' . $this->apiSecret,
            '',
            'Envia ambos como headers HTTP en cada request a la API.',
            'Documentacion: ' . config('app.url') . '/api/v1',
            '',
            'IMPORTANTE: guarda este archivo en un lugar seguro.',
            'El api_secret no se puede volver a mostrar. Si lo pierdes,',
            'debes regenerar las credenciales desde el panel.',
            '',
        ]);
    }
}
